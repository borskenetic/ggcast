<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Services\LibraryHoldingsReport1ExcelWriter;
use App\Services\LibraryHoldingsReport2ExcelWriter;
use App\Services\LibraryHoldingsReportBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LibraryHoldingsReportController extends Controller
{
    public function create()
    {
        $programs = Program::orderBy('program_name')->get();

        return view('reports.library_holdings', compact('programs'));
    }

    public function download(Request $request, LibraryHoldingsReportBuilder $builder)
    {
        $validated = $request->validate([
            'program_id' => 'required|integer|exists:programs,id',
            'program_suffix' => 'nullable|string|max:255',
            'date_accomplished' => 'nullable|string|max:255',
        ]);

        $program = Program::findOrFail($validated['program_id']);
        $programName = $program->program_name;
        if (! empty($validated['program_suffix'])) {
            $suffix = trim($validated['program_suffix']);
            $programName .= str_starts_with($suffix, '(') ? ' '.$suffix : ' ('.$suffix.')';
        }

        $report = $builder->buildForProgram($program);
        $report2 = $builder->buildReport2($program);

        if ($report['detail'] === [] && $report['summary'] === [] && $report2['detail'] === []) {
            return back()
                ->withInput()
                ->with('error', 'No cataloged books were found for this program. On each book copy, link it to this program in the cataloging form (Programs tab). For a major/specialization, either link copies to that program or select the base program (e.g. BSED) and use the suffix field for "MAJOR IN ENGLISH". Report 1 also requires a course on each copy.');
        }

        $slug = Str::slug($program->program_code ?: $program->program_name);
        $fileName = 'library_holdings_report_'.$slug.'_'.now()->format('Y-m-d').'.xlsx';
        $filePath = storage_path('app/'.$fileName);

        $spreadsheet = (new LibraryHoldingsReport1ExcelWriter(
            heiName: config('reports.hei_name'),
            programName: $programName,
            dateAccomplished: $validated['date_accomplished'] ?? null,
            detail: $report['detail'],
            summary: $report['summary'],
        ))->build();

        (new LibraryHoldingsReport2ExcelWriter(
            heiName: config('reports.hei_name'),
            dateAccomplished: $validated['date_accomplished'] ?? null,
            detail: $report2['detail'],
            summary: $report2['summary'],
            totals: $report2['totals'],
        ))->addSheetTo($spreadsheet);

        (new Xlsx($spreadsheet))->save($filePath);

        return response()->download($filePath, $fileName)->deleteFileAfterSend(true);
    }
}
