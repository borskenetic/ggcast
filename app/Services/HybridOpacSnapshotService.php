<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Ebook;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HybridOpacSnapshotService
{
    /**
     * Build a compact OPAC cache of title groups + sample copy metadata.
     *
     * @return array<string, mixed>
     */
    public function build(?Carbon $since = null, int $limit = 5000): array
    {
        $serverTime = now()->toIso8601String();

        $base = Book::query()
            ->whereNull('archived_at')
            ->whereNull('deleted_at');

        if ($since) {
            $base->where(function ($q) use ($since) {
                $q->where('updated_at', '>=', $since)
                    ->orWhere('created_at', '>=', $since);
            });
        }

        $groupQuery = (clone $base)
            ->select(
                'title_statement',
                'main_author',
                'pub_year',
                DB::raw('COUNT(*) AS copies'),
                DB::raw('MIN(id) AS sample_id'),
                DB::raw("MAX(CASE WHEN availability = 'Available' THEN 1 ELSE 0 END) AS is_available"),
                DB::raw('MAX(updated_at) AS last_updated')
            )
            ->groupBy('title_statement', 'main_author', 'pub_year')
            ->orderByDesc(DB::raw('MAX(updated_at)'))
            ->limit($limit);

        $groupRows = $groupQuery->get();
        $sampleIds = $groupRows->pluck('sample_id')->filter()->all();

        $booksById = $sampleIds === []
            ? collect()
            : Book::query()->whereIn('id', $sampleIds)->get()->keyBy('id');

        $titles = $groupRows->map(function ($row) use ($booksById) {
            $book = $booksById->get($row->sample_id);
            if (! $book) {
                return null;
            }

            return [
                'id' => (int) $book->id,
                'title_statement' => $book->title_statement,
                'main_author' => $book->main_author,
                'pub_year' => $book->pub_year,
                'isbn' => $book->isbn,
                'call_number' => $book->call_number,
                'subject_topic' => $book->subject_topic,
                'genre' => $book->genre,
                'content_type' => $book->content_type,
                'section' => $book->section,
                'course' => $book->course,
                'cover_image' => $book->cover_image,
                'publisher' => $book->publisher,
                'general_note' => $book->general_note,
                'copies' => (int) $row->copies,
                'is_available' => (int) $row->is_available === 1,
                'updated_at' => $book->updated_at?->toIso8601String(),
            ];
        })->filter()->values();

        // Include individual copy rows for holdings (limited)
        $copiesQuery = Book::query()
            ->whereNull('archived_at')
            ->whereNull('deleted_at')
            ->select([
                'id',
                'title_statement',
                'main_author',
                'pub_year',
                'accession_no',
                'barcode',
                'rfid',
                'call_number',
                'availability',
                'section',
                'updated_at',
            ])
            ->orderBy('id');

        if ($since) {
            $copiesQuery->where(function ($q) use ($since) {
                $q->where('updated_at', '>=', $since)
                    ->orWhere('created_at', '>=', $since);
            });
        }

        $copies = $copiesQuery->limit(min($limit * 3, 15000))->get()->map(fn (Book $b) => [
            'id' => $b->id,
            'title_statement' => $b->title_statement,
            'main_author' => $b->main_author,
            'pub_year' => $b->pub_year,
            'accession_no' => $b->accession_no,
            'barcode' => $b->barcode,
            'rfid' => $b->rfid,
            'call_number' => $b->call_number,
            'availability' => $b->availability,
            'section' => $b->section,
            'updated_at' => $b->updated_at?->toIso8601String(),
        ])->values();

        $ebooks = collect();
        if ($since === null || Ebook::query()->where('updated_at', '>=', $since)->exists()) {
            $eq = Ebook::query()->orderBy('title');
            if ($since) {
                $eq->where(function ($q) use ($since) {
                    $q->where('updated_at', '>=', $since)
                        ->orWhere('created_at', '>=', $since);
                });
            }
            $ebooks = $eq->limit(2000)->get()->map(fn (Ebook $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'author' => $e->author,
                'publisher' => $e->publisher,
                'publication_year' => $e->publication_year,
                'source' => $e->source,
                'url' => $e->link ?? null,
            ])->values();
        }

        $filters = [
            'courses' => Book::query()->whereNull('archived_at')->whereNotNull('course')->distinct()->orderBy('course')->pluck('course')->filter()->values(),
            'subjects' => Book::query()->whereNull('archived_at')->whereNotNull('subject_topic')->distinct()->orderBy('subject_topic')->pluck('subject_topic')->filter()->values(),
            'genres' => Book::query()->whereNull('archived_at')->whereNotNull('genre')->distinct()->orderBy('genre')->pluck('genre')->filter()->values(),
            'content_types' => Book::query()->whereNull('archived_at')->whereNotNull('content_type')->distinct()->orderBy('content_type')->pluck('content_type')->filter()->values(),
            'sections' => Book::query()->whereNull('archived_at')->whereNotNull('section')->distinct()->orderBy('section')->pluck('section')->filter()->values(),
        ];

        return [
            'server_time' => $serverTime,
            'since' => $since?->toIso8601String(),
            'full_snapshot' => $since === null,
            'titles' => $titles,
            'copies' => $copies,
            'ebooks' => $ebooks,
            'filters' => $filters,
        ];
    }
}
