<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Ebook;
use App\Models\Program;
use Illuminate\Support\Collection;

class LibraryHoldingsReportBuilder
{
    /**
     * @return array{
     *     detail: list<array{
     *         collection_type: string,
     *         curriculum_label: string,
     *         course: string,
     *         title: string,
     *         author: string,
     *         pub_year: int|string|null,
     *         copy_count: int
     *     }>,
     *     summary: list<array{
     *         curriculum_label: string,
     *         course: string,
     *         title_count: int,
     *         recent_title_count: int,
     *         copy_count: int
     *     }>
     * }
     */
    public function buildForProgram(Program $program, ?int $recentYears = null): array
    {
        $cutoffYear = $this->publicationYearCutoff($recentYears);

        $books = $this->booksForProgram($program, requireCourse: true);
        $recentBooks = $books
            ->filter(fn (Book $book) => $this->isRecentPublication($book->pub_year, $cutoffYear))
            ->values();

        $courseOrder = $this->prospectusCourseOrder($program);

        $detail = $this->buildDetailLines($recentBooks, $courseOrder);
        $summary = $this->buildSummaryLines($books, $courseOrder, $cutoffYear);

        return [
            'detail' => $detail,
            'summary' => $summary,
        ];
    }

    /**
     * Report 2 — program holdings list with classification summary.
     *
     * @return array{
     *     detail: list<array{
     *         collection_type: string,
     *         classification: string,
     *         course: string,
     *         title: string,
     *         author: string,
     *         pub_year: int|string|null,
     *         volume_count: int
     *     }>,
     *     summary: list<array{
     *         classification: string,
     *         printed_titles: int,
     *         electronic_titles: int,
     *         total_titles: int,
     *         printed_volumes: int,
     *         electronic_volumes: int,
     *         total_volumes: int
     *     }>,
     *     totals: array{
     *         printed_titles: int,
     *         electronic_titles: int,
     *         total_titles: int,
     *         printed_volumes: int,
     *         electronic_volumes: int,
     *         total_volumes: int
     *     }
     * }
     */
    public function buildReport2(Program $program): array
    {
        $books = $this->booksForProgram($program, requireCourse: false);

        $detail = $this->buildReport2DetailLines($books);
        $summary = $this->buildReport2SummaryLines($books);

        if ($summary === [] && $detail !== []) {
            $summary = $this->buildReport2SummaryFromDetail($detail);
        }

        $totals = [
            'printed_titles' => array_sum(array_column($summary, 'printed_titles')),
            'electronic_titles' => array_sum(array_column($summary, 'electronic_titles')),
            'total_titles' => array_sum(array_column($summary, 'total_titles')),
            'printed_volumes' => array_sum(array_column($summary, 'printed_volumes')),
            'electronic_volumes' => array_sum(array_column($summary, 'electronic_volumes')),
            'total_volumes' => array_sum(array_column($summary, 'total_volumes')),
        ];

        return [
            'detail' => $detail,
            'summary' => $summary,
            'totals' => $totals,
        ];
    }

    /**
     * Report 3 — professional journal subscriptions from E-Resources.
     *
     * @return array{
     *     detail: list<array{
     *         type: string,
     *         journal_name: string,
     *         publisher: string,
     *         publication: string,
     *         frequency: string,
     *         subscription: string,
     *         supporting_link: string
     *     }>
     * }
     */
    public function buildReport3(Program $program): array
    {
        $ebooks = $this->ebooksForProgram($program);

        $detail = $ebooks->map(function (Ebook $ebook) {
            return [
                'type' => trim((string) ($ebook->resource_type ?: 'Electronic')),
                'journal_name' => trim((string) $ebook->title),
                'publisher' => trim((string) ($ebook->publisher ?? '')),
                'publication' => trim((string) ($ebook->publication_scope ?? '')),
                'frequency' => trim((string) ($ebook->frequency ?? '')),
                'subscription' => trim((string) ($ebook->subscription_type ?? '')),
                'supporting_link' => trim((string) ($ebook->link ?? '')),
            ];
        })->values()->all();

        return ['detail' => $detail];
    }

    /**
     * E-Resources linked to the selected program (or available to all programs).
     */
    protected function ebooksForProgram(Program $program): Collection
    {
        $programIds = $this->programIdsForHoldings($program);

        return Ebook::query()
            ->where(function ($query) use ($programIds) {
                $query->whereIn('program_id', $programIds)
                    ->orWhereNull('program_id');
            })
            ->orderBy('title')
            ->get();
    }

    /**
     * Books linked to the selected program (and code-based sibling variants, e.g. BSED-ENG → BSED).
     */
    protected function booksForProgram(Program $program, bool $requireCourse = false): Collection
    {
        $programIds = $this->programIdsForHoldings($program);

        $query = Book::query()
            ->whereNull('archived_at')
            ->whereHas('programs', fn ($q) => $q->whereIn('programs.id', $programIds));

        if ($requireCourse) {
            $query->whereNotNull('course')->where('course', '!=', '');
        }

        return $query->get();
    }

    /**
     * Program ids whose catalog links count toward a holdings report.
     *
     * @return list<int>
     */
    protected function programIdsForHoldings(Program $program): array
    {
        $ids = collect([$program->id]);
        $code = trim((string) $program->program_code);

        if ($code === '') {
            return $ids->unique()->values()->all();
        }

        $ids = $ids->merge(
            Program::where('program_code', $code)->pluck('id')
        );

        $baseCode = preg_replace('/[\-_\s].*$/', '', $code) ?: $code;
        if (strlen($baseCode) >= 2) {
            $ids = $ids->merge(
                Program::query()
                    ->where('program_code', $baseCode)
                    ->orWhere('program_code', 'like', $baseCode.'-%')
                    ->orWhere('program_code', 'like', $baseCode.'\_%')
                    ->pluck('id')
            );
        }

        return $ids->unique()->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildReport2DetailLines(Collection $books): array
    {
        $groups = $books->groupBy(function (Book $book) {
            $classification = $this->report2ClassificationLabel($book->curriculum);
            $course = mb_strtolower(trim((string) $book->course));
            $title = mb_strtolower(trim((string) $book->title_statement));

            return mb_strtolower($classification).'|'.$course.'|'.$title;
        });

        $lines = $groups->map(function (Collection $copies) {
            /** @var Book $sample */
            $sample = $copies->first();

            return [
                'collection_type' => $this->collectionTypeFor($sample),
                'classification' => $this->report2ClassificationLabel($sample->curriculum),
                'classification_key' => $this->report2DetailSortOrder($sample->curriculum),
                'course' => trim((string) $sample->course),
                'title' => trim((string) $sample->title_statement),
                'author' => trim((string) ($sample->main_author ?? '')),
                'pub_year' => $this->normalizePubYear($sample->pub_year),
                'volume_count' => $copies->count(),
            ];
        })->values();

        return $lines->sort(function (array $a, array $b) {
            $classificationCompare = ($a['classification_key'] ?? 99) <=> ($b['classification_key'] ?? 99);
            if ($classificationCompare !== 0) {
                return $classificationCompare;
            }

            $courseCompare = strnatcasecmp($a['course'], $b['course']);
            if ($courseCompare !== 0) {
                return $courseCompare;
            }

            return strnatcasecmp($a['title'], $b['title']);
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildReport2SummaryLines(Collection $books): array
    {
        $lines = [];

        foreach ($this->report2SummaryOrder() as $classification) {
            $classified = $books->filter(
                fn (Book $book) => $this->report2ClassificationLabel($book->curriculum) === $classification
            );

            $titleGroups = $classified->groupBy(
                fn (Book $book) => mb_strtolower(trim((string) $book->title_statement))
            );

            $printedTitles = 0;
            $electronicTitles = 0;

            foreach ($titleGroups as $copies) {
                $hasPrinted = $copies->contains(fn (Book $book) => $this->collectionTypeFor($book) === 'Printed');
                $hasElectronic = $copies->contains(fn (Book $book) => $this->collectionTypeFor($book) === 'Electronic');

                if ($hasPrinted) {
                    $printedTitles++;
                }
                if ($hasElectronic) {
                    $electronicTitles++;
                }
            }

            $printedVolumes = $classified
                ->filter(fn (Book $book) => $this->collectionTypeFor($book) === 'Printed')
                ->count();
            $electronicVolumes = $classified
                ->filter(fn (Book $book) => $this->collectionTypeFor($book) === 'Electronic')
                ->count();

            $lines[] = [
                'classification' => $classification,
                'printed_titles' => $printedTitles,
                'electronic_titles' => $electronicTitles,
                'total_titles' => $printedTitles + $electronicTitles,
                'printed_volumes' => $printedVolumes,
                'electronic_volumes' => $electronicVolumes,
                'total_volumes' => $printedVolumes + $electronicVolumes,
            ];
        }

        return $lines;
    }

    /**
     * Build Report 2 summary from detail lines (same title groups as the list).
     *
     * @param  list<array<string, mixed>>  $detail
     * @return list<array<string, mixed>>
     */
    protected function buildReport2SummaryFromDetail(array $detail): array
    {
        $byClassification = collect($detail)->groupBy('classification');
        $lines = [];

        foreach ($this->report2SummaryOrder() as $classification) {
            $items = $byClassification->get($classification, collect());

            $printedTitles = 0;
            $electronicTitles = 0;
            $printedVolumes = 0;
            $electronicVolumes = 0;

            foreach ($items as $line) {
                $volumes = (int) ($line['volume_count'] ?? 0);
                if (($line['collection_type'] ?? '') === 'Electronic') {
                    $electronicTitles++;
                    $electronicVolumes += $volumes;
                } else {
                    $printedTitles++;
                    $printedVolumes += $volumes;
                }
            }

            $lines[] = [
                'classification' => $classification,
                'printed_titles' => $printedTitles,
                'electronic_titles' => $electronicTitles,
                'total_titles' => $printedTitles + $electronicTitles,
                'printed_volumes' => $printedVolumes,
                'electronic_volumes' => $electronicVolumes,
                'total_volumes' => $printedVolumes + $electronicVolumes,
            ];
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    protected function report2SummaryOrder(): array
    {
        $order = config('reports.report2_summary_order');

        if (is_array($order) && $order !== []) {
            return $order;
        }

        return [
            'General Reference',
            'General Education',
            'Filipiniana',
            'Professional',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function report2ClassificationLabels(): array
    {
        $labels = config('reports.report2_classification_labels');

        if (is_array($labels) && $labels !== []) {
            return $labels;
        }

        return [
            'prof ed' => 'Professional',
            'gen ed' => 'General Education',
            'filipiniana' => 'Filipiniana',
            'general reference' => 'General Reference',
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function report2DetailSortMap(): array
    {
        $sort = config('reports.report2_detail_sort');

        if (is_array($sort) && $sort !== []) {
            return $sort;
        }

        return [
            'general reference' => 1,
            'filipiniana' => 2,
            'prof ed' => 3,
            'gen ed' => 4,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function curriculumLabels(): array
    {
        $labels = config('reports.curriculum_labels');

        if (is_array($labels) && $labels !== []) {
            return $labels;
        }

        return [
            'prof ed' => 'Professional Education',
            'gen ed' => 'General Education',
            'filipiniana' => 'Filipiniana',
            'general reference' => 'General Reference',
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function curriculumSortMap(): array
    {
        $sort = config('reports.curriculum_sort');

        if (is_array($sort) && $sort !== []) {
            return $sort;
        }

        return [
            'prof ed' => 1,
            'gen ed' => 2,
            'filipiniana' => 3,
            'general reference' => 4,
        ];
    }

    protected function normalizeCurriculumKey(?string $curriculum): string
    {
        $normalized = mb_strtolower(trim((string) $curriculum));
        $normalized = str_replace(['.', '_', '-'], ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        $aliases = [
            'general education' => 'gen ed',
            'ge' => 'gen ed',
            'professional education' => 'prof ed',
            'professional' => 'prof ed',
            'pe' => 'prof ed',
            'general reference' => 'general reference',
            'ref' => 'general reference',
        ];

        return $aliases[$normalized] ?? $normalized;
    }

    protected function report2ClassificationLabel(?string $curriculum): string
    {
        $normalized = $this->normalizeCurriculumKey($curriculum);
        $labels = $this->report2ClassificationLabels();

        return $labels[$normalized] ?? 'General Education';
    }

    protected function report2DetailSortOrder(?string $curriculum): int
    {
        $normalized = $this->normalizeCurriculumKey($curriculum);
        $sort = $this->report2DetailSortMap();

        return $sort[$normalized] ?? 99;
    }

    /**
     * @return list<string>
     */
    protected function prospectusCourseOrder(Program $program): array
    {
        $program->loadMissing(['years.courses']);

        $ordered = [];
        foreach ($program->years->sortBy('year_level') as $year) {
            foreach ($year->courses->sortBy('course_code') as $course) {
                $name = trim((string) $course->course_name);
                if ($name !== '') {
                    $ordered[] = $name;
                }
            }
        }

        return array_values(array_unique($ordered));
    }

    /**
     * @param  list<string>  $courseOrder
     * @return list<array<string, mixed>>
     */
    protected function buildDetailLines(Collection $books, array $courseOrder): array
    {
        $groups = $books->groupBy(function (Book $book) {
            return mb_strtolower(trim((string) $book->course)).'|'.mb_strtolower(trim((string) $book->title_statement));
        });

        $lines = $groups->map(function (Collection $copies) {
            /** @var Book $sample */
            $sample = $copies->first();
            $years = $copies
                ->map(fn (Book $book) => $this->normalizePubYear($book->pub_year))
                ->filter(fn (?int $year) => $year !== null);

            return [
                'collection_type' => $this->collectionTypeFor($sample),
                'curriculum_label' => $this->curriculumLabelFor($sample->curriculum),
                'curriculum_key' => $this->curriculumSortOrder($sample->curriculum),
                'course' => trim((string) $sample->course),
                'title' => trim((string) $sample->title_statement),
                'author' => trim((string) ($sample->main_author ?? '')),
                'pub_year' => $years->isEmpty() ? null : $years->max(),
                'copy_count' => $copies->count(),
            ];
        })->values();

        return $this->sortLines($lines, $courseOrder)->values()->all();
    }

    /**
     * @param  list<string>  $courseOrder
     * @return list<array<string, mixed>>
     */
    protected function buildSummaryLines(Collection $books, array $courseOrder, int $cutoffYear): array
    {
        $byCourse = $books->groupBy(fn (Book $book) => mb_strtolower(trim((string) $book->course)));

        $lines = $byCourse->map(function (Collection $courseBooks, string $courseKey) use ($cutoffYear) {
            /** @var Book $sample */
            $sample = $courseBooks->first();
            $course = trim((string) $sample->course);

            $titleGroups = $courseBooks->groupBy(
                fn (Book $book) => mb_strtolower(trim((string) $book->title_statement))
            );

            $recentTitleGroups = $titleGroups->filter(function (Collection $copies) use ($cutoffYear) {
                return $copies->contains(
                    fn (Book $book) => $this->isRecentPublication($book->pub_year, $cutoffYear)
                );
            });

            return [
                'curriculum_label' => $this->curriculumLabelFor($sample->curriculum),
                'curriculum_key' => $this->curriculumSortOrder($sample->curriculum),
                'course' => $course,
                'title_count' => $titleGroups->count(),
                'recent_title_count' => $recentTitleGroups->count(),
                'copy_count' => $courseBooks->count(),
            ];
        })->values();

        return $this->sortLines($lines, $courseOrder)->values()->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lines
     * @param  list<string>  $courseOrder
     * @return Collection<int, array<string, mixed>>
     */
    protected function sortLines(Collection $lines, array $courseOrder): Collection
    {
        $courseRank = [];
        foreach ($courseOrder as $index => $courseName) {
            $courseRank[mb_strtolower($courseName)] = $index;
        }

        return $lines->sort(function (array $a, array $b) use ($courseRank) {
            $curriculumCompare = ($a['curriculum_key'] ?? 99) <=> ($b['curriculum_key'] ?? 99);
            if ($curriculumCompare !== 0) {
                return $curriculumCompare;
            }

            $aCourse = mb_strtolower($a['course']);
            $bCourse = mb_strtolower($b['course']);
            $aRank = $courseRank[$aCourse] ?? PHP_INT_MAX;
            $bRank = $courseRank[$bCourse] ?? PHP_INT_MAX;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            $courseCompare = strnatcasecmp($a['course'], $b['course']);
            if ($courseCompare !== 0) {
                return $courseCompare;
            }

            return strnatcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
        });
    }

    protected function collectionTypeFor(Book $book): string
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) $book->content_type,
            (string) $book->media_type,
            (string) $book->carrier_type,
        ])));

        if (str_contains($haystack, 'electronic')
            || str_contains($haystack, 'digital')
            || str_contains($haystack, 'online')
            || str_contains($haystack, 'ebook')
            || str_contains($haystack, 'computer disc')) {
            return 'Electronic';
        }

        return 'Printed';
    }

    protected function curriculumLabelFor(?string $curriculum): string
    {
        $normalized = $this->normalizeCurriculumKey($curriculum);
        $labels = $this->curriculumLabels();

        return $labels[$normalized] ?? 'General Education';
    }

    protected function curriculumSortOrder(?string $curriculum): int
    {
        $normalized = $this->normalizeCurriculumKey($curriculum);
        $sort = $this->curriculumSortMap();

        return $sort[$normalized] ?? 99;
    }

    protected function publicationYearCutoff(?int $recentYears = null): int
    {
        $recentYears = $recentYears ?? (int) config('reports.recent_publication_years', 5);
        $recentYears = max(1, $recentYears);

        return (int) now()->year - $recentYears + 1;
    }

    protected function isRecentPublication(mixed $pubYear, int $cutoffYear): bool
    {
        $year = $this->normalizePubYear($pubYear);

        return $year !== null && $year >= $cutoffYear;
    }

    protected function normalizePubYear(mixed $pubYear): ?int
    {
        if ($pubYear === null || $pubYear === '') {
            return null;
        }

        if (is_numeric($pubYear)) {
            $year = (int) $pubYear;

            return $year >= 1000 ? $year : null;
        }

        if (preg_match_all('/(?:19|20)\d{2}/', (string) $pubYear, $matches)) {
            return max(array_map('intval', $matches[0]));
        }

        return null;
    }
}
