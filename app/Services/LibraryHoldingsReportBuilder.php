<?php

namespace App\Services;

use App\Models\Book;
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
        $recentYears = $recentYears ?? (int) config('reports.recent_publication_years', 5);
        $cutoffYear = (int) now()->year - $recentYears + 1;

        $books = $this->booksForProgram($program, requireCourse: true);

        $courseOrder = $this->prospectusCourseOrder($program);

        $detail = $this->buildDetailLines($books, $courseOrder);
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

        foreach (config('reports.report2_summary_order', []) as $classification) {
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

    protected function report2ClassificationLabel(?string $curriculum): string
    {
        $normalized = mb_strtolower(trim((string) $curriculum));

        return config("reports.report2_classification_labels.{$normalized}", 'General Education');
    }

    protected function report2DetailSortOrder(?string $curriculum): int
    {
        $normalized = mb_strtolower(trim((string) $curriculum));

        return config("reports.report2_detail_sort.{$normalized}", 99);
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

            return [
                'collection_type' => $this->collectionTypeFor($sample),
                'curriculum_label' => $this->curriculumLabelFor($sample->curriculum),
                'curriculum_key' => $this->curriculumSortOrder($sample->curriculum),
                'course' => trim((string) $sample->course),
                'title' => trim((string) $sample->title_statement),
                'author' => trim((string) ($sample->main_author ?? '')),
                'pub_year' => $this->normalizePubYear($sample->pub_year),
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
                $year = $this->normalizePubYear($copies->first()->pub_year);

                return $year !== null && $year >= $cutoffYear;
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
        $normalized = mb_strtolower(trim((string) $curriculum));

        return config("reports.curriculum_labels.{$normalized}", 'General Education');
    }

    protected function curriculumSortOrder(?string $curriculum): int
    {
        $normalized = mb_strtolower(trim((string) $curriculum));

        return config("reports.curriculum_sort.{$normalized}", 99);
    }

    protected function normalizePubYear(mixed $pubYear): ?int
    {
        if ($pubYear === null || $pubYear === '') {
            return null;
        }

        if (is_numeric($pubYear)) {
            return (int) $pubYear;
        }

        if (preg_match('/\b(19|20)\d{2}\b/', (string) $pubYear, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }
}
