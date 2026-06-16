<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Library holdings report (CHED-style)
    |--------------------------------------------------------------------------
    */

    'hei_name' => env('REPORT_HEI_NAME', config('app.name', 'Higher Education Institution')),

    'recent_publication_years' => (int) env('REPORT_RECENT_YEARS', 5),

    'curriculum_labels' => [
        'prof ed' => 'Professional Education',
        'gen ed' => 'General Education',
        'filipiniana' => 'Filipiniana',
        'general reference' => 'General Reference',
    ],

    'curriculum_sort' => [
        'prof ed' => 1,
        'gen ed' => 2,
        'filipiniana' => 3,
        'general reference' => 4,
    ],

    /*
    | Report 2 uses shorter classification labels and a different detail sort order
    | (General Reference → Filipiniana → Professional → General Education).
    */
    'report2_classification_labels' => [
        'prof ed' => 'Professional',
        'gen ed' => 'General Education',
        'filipiniana' => 'Filipiniana',
        'general reference' => 'General Reference',
    ],

    'report2_detail_sort' => [
        'general reference' => 1,
        'filipiniana' => 2,
        'prof ed' => 3,
        'gen ed' => 4,
    ],

    'report2_summary_order' => [
        'General Reference',
        'General Education',
        'Filipiniana',
        'Professional',
    ],

    'signatories' => [
        'prepared_by_name' => env('REPORT_PREPARED_BY_NAME', ''),
        'prepared_by_title' => env('REPORT_PREPARED_BY_TITLE', 'College Librarian'),
        'approved_by_name' => env('REPORT_APPROVED_BY_NAME', ''),
        'approved_by_title' => env('REPORT_APPROVED_BY_TITLE', 'HEI Head'),
    ],

];
