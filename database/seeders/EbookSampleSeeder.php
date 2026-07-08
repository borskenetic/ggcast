<?php

namespace Database\Seeders;

use App\Models\Ebook;
use App\Models\Program;
use App\Models\ProgramCourse;
use Illuminate\Database\Seeder;

/**
 * Sample E-Resources for testing View E-Resources and Library Holdings Report 3.
 *
 * Run: php artisan db:seed --class=EbookSampleSeeder
 */
class EbookSampleSeeder extends Seeder
{
    public function run(): void
    {
        $bsed = Program::where('program_code', 'BSED')->first();
        $bscs = Program::where('program_code', 'BSCS')->first();

        if (! $bsed) {
            $this->command?->warn('BSED program not found. Run ProspectusSeeder first.');

            return;
        }

        $resources = [
            [
                'title' => 'Intercultural Pragmatics',
                'author' => 'De Gruyter Mouton',
                'publication_year' => '2024',
                'publisher' => 'De Gruyter',
                'source' => 'Institutional subscription',
                'link' => 'https://drive.google.com/file/d/1y4BdfqMrmYnYN2w-example/view',
                'resource_type' => 'Electronic',
                'publication_scope' => 'Foreign',
                'frequency' => 'Annual',
                'subscription_type' => 'Renewable',
                'program_id' => $bsed->id,
                'course_name' => 'Introduction to Linguistics',
            ],
            [
                'title' => 'TESOL Quarterly',
                'author' => 'TESOL International Association',
                'publication_year' => '2024',
                'publisher' => 'Wiley',
                'source' => 'TESOL Press',
                'link' => 'https://example.org/subscriptions/tesol-quarterly',
                'resource_type' => 'Electronic',
                'publication_scope' => 'Foreign',
                'frequency' => 'Quarterly',
                'subscription_type' => 'Renewable',
                'program_id' => $bsed->id,
                'course_name' => 'Teaching and Assessment of Macro Skills',
            ],
            [
                'title' => 'English Language Teaching Journal',
                'author' => 'Oxford University Press',
                'publication_year' => '2024',
                'publisher' => 'Oxford University Press',
                'source' => 'Oxford Academic',
                'link' => 'https://example.org/subscriptions/elt-journal',
                'resource_type' => 'Electronic',
                'publication_scope' => 'Foreign',
                'frequency' => 'Quarterly',
                'subscription_type' => 'Renewable',
                'program_id' => $bsed->id,
                'course_name' => 'Teaching and Assessment of Grammar',
            ],
            [
                'title' => 'Language Teaching Research',
                'author' => 'Sage Publications',
                'publication_year' => '2023',
                'publisher' => 'Sage',
                'source' => 'Sage Journals',
                'link' => 'https://example.org/subscriptions/language-teaching-research',
                'resource_type' => 'Electronic',
                'publication_scope' => 'Foreign',
                'frequency' => 'Quarterly',
                'subscription_type' => 'Renewable',
                'program_id' => $bsed->id,
                'course_name' => 'Language Learning Materials Development',
            ],
            [
                'title' => 'Journal of English for Academic Purposes',
                'author' => 'Elsevier',
                'publication_year' => '2024',
                'publisher' => 'Elsevier',
                'source' => 'ScienceDirect',
                'link' => 'https://example.org/subscriptions/jeap',
                'resource_type' => 'Electronic',
                'publication_scope' => 'Foreign',
                'frequency' => 'Quarterly',
                'subscription_type' => 'Renewable',
                'program_id' => $bsed->id,
                'course_name' => 'Structure of English',
            ],
            [
                'title' => 'Philippine Journal of Linguistics',
                'author' => 'Linguistic Society of the Philippines',
                'publication_year' => '2023',
                'publisher' => 'LSP',
                'source' => 'Local publisher',
                'link' => 'https://example.org/subscriptions/pjl',
                'resource_type' => 'Electronic',
                'publication_scope' => 'Local',
                'frequency' => 'Annual',
                'subscription_type' => 'Renewable',
                'program_id' => $bsed->id,
                'course_name' => 'Language, Culture and Society',
            ],
            [
                'title' => 'Children\'s Literature in Education',
                'author' => 'Springer Nature',
                'publication_year' => '2024',
                'publisher' => 'Springer',
                'source' => 'SpringerLink',
                'link' => 'https://example.org/subscriptions/childrens-literature-education',
                'resource_type' => 'Electronic',
                'publication_scope' => 'Foreign',
                'frequency' => 'Quarterly',
                'subscription_type' => 'Renewable',
                'program_id' => $bsed->id,
                'course_name' => 'Children and Adolescent Literature',
            ],
            [
                'title' => 'Asian Journal of English Language Studies',
                'author' => 'University of the Philippines',
                'publication_year' => '2022',
                'publisher' => 'UP Department of English and Comparative Literature',
                'source' => 'Open repository',
                'link' => 'https://example.org/open/ajels',
                'resource_type' => 'Electronic',
                'publication_scope' => 'Local',
                'frequency' => 'Annual',
                'subscription_type' => 'Open Access',
                'program_id' => null,
                'course_name' => null,
            ],
            [
                'title' => 'IEEE Transactions on Education',
                'author' => 'IEEE',
                'publication_year' => '2024',
                'publisher' => 'IEEE',
                'source' => 'IEEE Xplore',
                'link' => 'https://example.org/subscriptions/ieee-education',
                'resource_type' => 'Electronic',
                'publication_scope' => 'Foreign',
                'frequency' => 'Quarterly',
                'subscription_type' => 'Renewable',
                'program_id' => $bscs?->id,
                'course_name' => 'Computer Networks',
            ],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($resources as $row) {
            $courseName = $row['course_name'] ?? null;
            unset($row['course_name']);

            $programId = $row['program_id'] ?? null;
            $row['course_id'] = null;

            if ($courseName && $programId) {
                $row['course_id'] = $this->courseIdForProgram($programId, $courseName);
            }

            if ($programId === null) {
                unset($row['program_id']);
                $row['program_id'] = null;
            }

            $existing = Ebook::where('title', $row['title'])->first();
            if ($existing) {
                $skipped++;

                continue;
            }

            Ebook::create($row);
            $created++;
        }

        $bsedCount = Ebook::query()
            ->where(function ($q) use ($bsed) {
                $q->where('program_id', $bsed->id)->orWhereNull('program_id');
            })
            ->count();

        $this->command?->info("E-Resources sample: {$created} created, {$skipped} skipped (already present).");
        $this->command?->info("{$bsedCount} e-resources available for BSED Report 3 (includes all-program entries).");
        $this->command?->info('Test: Catalog → View E-Resources, or Library Holdings Report → BSED → MAJOR IN ENGLISH.');
    }

    protected function courseIdForProgram(int $programId, string $courseName): ?int
    {
        return ProgramCourse::query()
            ->whereHas('year', fn ($q) => $q->where('program_id', $programId))
            ->where('course_name', $courseName)
            ->value('id');
    }
}
