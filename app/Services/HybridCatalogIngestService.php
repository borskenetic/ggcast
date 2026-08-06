<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookMarcField;
use App\Models\HybridCatalogPush;
use App\Models\HybridDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HybridCatalogIngestService
{
    public function __construct(
        protected BookMarcDisplay $marcDisplay
    ) {}

    /**
     * Accept one offline-queued catalog create payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array{book_id: int, copies_created: int, book_ids: array<int, int>}
     */
    public function ingest(
        string $clientUuid,
        array $payload,
        ?HybridDevice $device = null,
        ?int $userId = null
    ): array {
        if ($existing = HybridCatalogPush::where('client_uuid', $clientUuid)->first()) {
            return [
                'book_id' => (int) $existing->book_id,
                'copies_created' => (int) $existing->copies_created,
                'book_ids' => array_map('intval', $existing->accepted_book_ids ?? []),
            ];
        }

        $marc = is_array($payload['marc'] ?? null) ? $payload['marc'] : [];
        $multipleCopies = ! empty($payload['multiple_copies']);
        $copies = is_array($payload['copies'] ?? null) ? $payload['copies'] : [];
        $programIds = array_values(array_unique(array_filter(array_map(
            static fn ($v) => (int) $v > 0 ? (int) $v : null,
            is_array($payload['program_ids'] ?? null) ? $payload['program_ids'] : []
        ))));

        $shared = array_filter([
            'availability' => 'Available',
            'year' => $payload['year'] ?? null,
            'course' => $payload['course'] ?? null,
            'curriculum' => $payload['curriculum'] ?? null,
            'cover_image' => is_string($payload['cover_image'] ?? null) ? $payload['cover_image'] : null,
        ], static fn ($v) => $v !== null && $v !== '');

        // Flatten single-copy book columns if sent without full MARC framework.
        $directFields = [
            'title_statement', 'main_author', 'isbn', 'publisher', 'pub_place', 'pub_year',
            'edition', 'pages', 'call_number', 'accession_no', 'barcode', 'rfid',
            'subject_topic', 'genre', 'content_type', 'section', 'general_note',
            'series_title', 'library_name',
        ];
        foreach ($directFields as $field) {
            if (array_key_exists($field, $payload) && $payload[$field] !== null && $payload[$field] !== '') {
                $shared[$field] = $payload[$field];
            }
        }

        $framework = $this->marcDisplay->booksFramework();

        return DB::transaction(function () use (
            $clientUuid,
            $device,
            $shared,
            $marc,
            $multipleCopies,
            $copies,
            $programIds,
            $framework
        ) {
            $createdIds = [];

            if (! $multipleCopies) {
                $book = Book::create($shared);
                $this->saveMarc($book, $framework, $marc);
                $this->assertIdentifiersUnique($book);
                if ($programIds !== []) {
                    $book->programs()->attach($programIds);
                }
                $createdIds[] = $book->id;
            } else {
                foreach ($copies as $copy) {
                    if (! is_array($copy)) {
                        continue;
                    }
                    $acc = trim((string) ($copy['accession_no'] ?? ''));
                    $rfid = trim((string) ($copy['rfid'] ?? ''));
                    $barcode = trim((string) ($copy['barcode'] ?? ''));
                    if ($acc === '' && $rfid === '' && $barcode === '') {
                        continue;
                    }

                    $book = Book::create($shared);
                    $copyMarc = $marc;
                    if ($acc !== '') {
                        $copyMarc = $this->injectMarcValue($copyMarc, '852', 'p', $acc);
                        $book->accession_no = $acc;
                    }
                    if ($rfid !== '') {
                        $book->rfid = $rfid;
                    }
                    if ($barcode !== '') {
                        $book->barcode = $barcode;
                    }
                    $this->saveMarc($book, $framework, $copyMarc);
                    $this->assertIdentifiersUnique($book);
                    if ($programIds !== []) {
                        $book->programs()->attach($programIds);
                    }
                    $createdIds[] = $book->id;
                }

                if ($createdIds === []) {
                    throw ValidationException::withMessages([
                        'copies' => ['Add at least one copy with an accession number, barcode, and/or RFID.'],
                    ]);
                }
            }

            $firstId = $createdIds[0];
            HybridCatalogPush::create([
                'client_uuid' => $clientUuid,
                'hybrid_device_id' => $device?->id,
                'book_id' => $firstId,
                'copies_created' => count($createdIds),
                'accepted_book_ids' => $createdIds,
            ]);

            return [
                'book_id' => $firstId,
                'copies_created' => count($createdIds),
                'book_ids' => $createdIds,
            ];
        });
    }

    protected function injectMarcValue(array $marc, string $tag, string $subfield, string $value): array
    {
        $marc[$tag] = $marc[$tag] ?? [];
        $marc[$tag][$subfield] = [$value];

        return $marc;
    }

    protected function saveMarc(Book $book, $framework, array $marc): void
    {
        if (! $framework) {
            // Apply direct attributes already set; nothing else.
            $book->save();

            return;
        }

        foreach ($framework->fields as $ff) {
            $mf = $ff->marcField;
            if (! $mf) {
                continue;
            }

            $subKey = $mf->subfield ?? '_';
            $vals = $marc[$mf->tag][$subKey] ?? [];
            if (! is_array($vals)) {
                $vals = [$vals];
            }
            $vals = array_values(array_filter(array_map(static function ($v) {
                $v = is_string($v) ? trim($v) : $v;

                return $v === '' ? null : $v;
            }, $vals)));

            if ($ff->required && count($vals) === 0) {
                // Soft-require offline: keep going if title present; hard-fail on empty title later
                continue;
            }

            BookMarcField::where('book_id', $book->id)
                ->where('tag', $mf->tag)
                ->where(function ($q) use ($mf) {
                    if ($mf->subfield === null) {
                        $q->whereNull('subfield');
                    } else {
                        $q->where('subfield', $mf->subfield);
                    }
                })
                ->delete();

            foreach ($vals as $i => $val) {
                BookMarcField::create([
                    'book_id' => $book->id,
                    'tag' => $mf->tag,
                    'subfield' => $mf->subfield,
                    'occurrence' => $i,
                    'value' => $val,
                ]);
            }

            if ($ff->book_column) {
                $book->{$ff->book_column} = $vals[0] ?? $book->{$ff->book_column};
            }
        }

        if (empty($book->title_statement)) {
            throw ValidationException::withMessages([
                'title_statement' => ['Title is required.'],
            ]);
        }

        $book->save();
    }

    protected function assertIdentifiersUnique(Book $book): void
    {
        foreach (['accession_no', 'barcode', 'rfid'] as $column) {
            $value = trim((string) ($book->{$column} ?? ''));
            if ($value === '') {
                continue;
            }

            $clash = Book::query()
                ->where($column, $value)
                ->where('id', '!=', $book->id)
                ->whereNull('archived_at')
                ->exists();

            if ($clash) {
                throw ValidationException::withMessages([
                    $column => ["{$column} '{$value}' is already in use."],
                ]);
            }
        }
    }
}
