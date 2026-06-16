<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LibraryHoldingsReport2ExcelWriter
{
    public function __construct(
        protected string $heiName,
        protected ?string $dateAccomplished,
        protected array $detail,
        protected array $summary,
        protected array $totals,
    ) {}

    public function addSheetTo(Spreadsheet $spreadsheet): Worksheet
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Report 2');

        $this->applyColumnWidths($sheet);

        $sheet->setCellValue('A1', 'HEI Name: '.$this->heiName);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(10);

        $dateLine = $this->dateAccomplished
            ? 'Date Accomplished: '.$this->dateAccomplished
            : 'Date Accomplished:________________________________________________';
        $sheet->setCellValue('A2', $dateLine);
        $sheet->getStyle('A2')->getFont()->setSize(10);

        $sheet->setCellValue('A4', 'List of Book Holdings');
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(12);
        $sheet->setCellValue('J4', 'SUMMARY OF REPORT');
        $sheet->getStyle('J4')->getFont()->setBold(true)->setSize(12);

        $sheet->setCellValue('A5', 'List of book collections');
        $sheet->getStyle('A5')->getFont()->setSize(10);

        $summaryHeaders = [
            'J6' => 'Classification',
            'K6' => "No. of Titles\n(Printed)",
            'L6' => "No. of Titles\n(Electronic)",
            'M6' => 'Total Titles',
            'N6' => "No. of Volumes\n(Printed)",
            'O6' => "No. of Volumes\n(Electronic)",
            'P6' => 'Total Titles',
        ];
        foreach ($summaryHeaders as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }

        $detailHeaders = [
            'A7' => 'No.',
            'B7' => 'Collection Type',
            'C7' => 'Classification',
            'D7' => 'Course Name',
            'E7' => 'Book Title',
            'F7' => 'Author',
            'G7' => 'Publication Year',
            'H7' => 'Volumes',
        ];
        foreach ($detailHeaders as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }

        foreach (['J6:P7', 'A7:H7'] as $range) {
            $sheet->getStyle($range)->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'CFE2F3'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'wrapText' => true,
                ],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN],
                ],
            ]);
        }

        foreach (['J6:J7', 'K6:K7', 'L6:L7', 'M6:M7', 'N6:N7', 'O6:O7', 'P6:P7'] as $merge) {
            $sheet->mergeCells($merge);
        }

        $summaryStartRow = 8;
        foreach ($this->summary as $index => $line) {
            $row = $summaryStartRow + $index;
            $sheet->setCellValue("J{$row}", $line['classification']);
            $sheet->setCellValue("K{$row}", $line['printed_titles']);
            $sheet->setCellValue("L{$row}", $line['electronic_titles']);
            $sheet->setCellValue("M{$row}", $line['total_titles']);
            $sheet->setCellValue("N{$row}", $line['printed_volumes']);
            $sheet->setCellValue("O{$row}", $line['electronic_volumes']);
            $sheet->setCellValue("P{$row}", $line['total_volumes']);

            $sheet->getStyle("J{$row}:P{$row}")->applyFromArray($this->dataStyle());
        }

        $totalRow = $summaryStartRow + count($this->summary);
        $sheet->setCellValue("J{$totalRow}", 'TOTAL');
        $sheet->setCellValue("K{$totalRow}", $this->totals['printed_titles']);
        $sheet->setCellValue("L{$totalRow}", $this->totals['electronic_titles']);
        $sheet->setCellValue("M{$totalRow}", $this->totals['total_titles']);
        $sheet->setCellValue("N{$totalRow}", $this->totals['printed_volumes']);
        $sheet->setCellValue("O{$totalRow}", $this->totals['electronic_volumes']);
        $sheet->setCellValue("P{$totalRow}", $this->totals['total_volumes']);

        $sheet->getStyle("J{$summaryStartRow}:P{$totalRow}")->applyFromArray($this->dataStyle());
        $sheet->getStyle("J{$summaryStartRow}:J{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("K{$summaryStartRow}:P{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $detailStartRow = 8;
        $detailCount = count($this->detail);

        for ($i = 0; $i < $detailCount; $i++) {
            $row = $detailStartRow + $i;
            $line = $this->detail[$i];

            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $line['collection_type']);
            $sheet->setCellValue("C{$row}", $line['classification']);
            $sheet->setCellValue("D{$row}", $line['course']);
            $sheet->setCellValue("E{$row}", $line['title']);
            $sheet->setCellValue("F{$row}", $line['author']);
            $sheet->setCellValue("G{$row}", $line['pub_year'] ?? '');
            $sheet->setCellValue("H{$row}", $line['volume_count']);

            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray($this->dataStyle());
        }

        if ($detailCount > 0) {
            $lastDetailRow = $detailStartRow + $detailCount - 1;
            $sheet->getStyle("A{$detailStartRow}:H{$lastDetailRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A{$detailStartRow}:A{$lastDetailRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$detailStartRow}:H{$lastDetailRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $footerStartRow = max($detailStartRow + $detailCount, $totalRow) + 2;
        $sheet->setCellValue("B{$footerStartRow}", '*add rows when necessary*');
        $sheet->getStyle("B{$footerStartRow}")->getFont()->setItalic(true)->setSize(10);

        $noteRow = $footerStartRow + 2;
        $sheet->setCellValue(
            "A{$noteRow}",
            '*Note: Start by presenting the library holdings classified as General References followed by Filipiniana, Professional, and General Education*'
        );
        $sheet->getStyle("A{$noteRow}")->getFont()->setItalic(true)->setSize(10);

        $sigRow = $noteRow + 3;
        $nameRow = $sigRow + 1;
        $labelRow = $sigRow + 2;
        $titleRow = $sigRow + 3;

        $preparedName = config('reports.signatories.prepared_by_name', '');
        $approvedName = config('reports.signatories.approved_by_name', '');
        $preparedTitle = config('reports.signatories.prepared_by_title', 'College Librarian');
        $approvedTitle = config('reports.signatories.approved_by_title', 'HEI Head');

        $sheet->setCellValue("A{$sigRow}", 'Prepared by:');
        $sheet->setCellValue("E{$sigRow}", 'Approved by:');
        $sheet->setCellValue("J{$sigRow}", 'Prepared by:');
        $sheet->setCellValue("N{$sigRow}", 'Approved by:');

        $sheet->setCellValue("A{$nameRow}", $preparedName);
        $sheet->setCellValue("E{$nameRow}", $approvedName);
        $sheet->setCellValue("J{$nameRow}", $preparedName);
        $sheet->setCellValue("N{$nameRow}", $approvedName);

        $sheet->setCellValue("A{$labelRow}", 'Name');
        $sheet->setCellValue("E{$labelRow}", 'Name');
        $sheet->setCellValue("J{$labelRow}", 'Name');
        $sheet->setCellValue("N{$labelRow}", 'Name');

        $sheet->setCellValue("A{$titleRow}", $preparedTitle);
        $sheet->setCellValue("E{$titleRow}", $approvedTitle);
        $sheet->setCellValue("J{$titleRow}", $preparedTitle);
        $sheet->setCellValue("N{$titleRow}", $approvedTitle);

        return $sheet;
    }

    protected function dataStyle(): array
    {
        return [
            'font' => ['size' => 10],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN],
            ],
            'alignment' => [
                'wrapText' => true,
            ],
        ];
    }

    protected function applyColumnWidths(Worksheet $sheet): void
    {
        $widths = [
            'A' => 5.71,
            'B' => 19.29,
            'C' => 23.43,
            'D' => 18.86,
            'E' => 32.71,
            'F' => 24.57,
            'G' => 14.0,
            'H' => 7.86,
            'I' => 13.0,
            'J' => 23.43,
            'K' => 10.43,
            'L' => 13.0,
            'M' => 9.57,
            'N' => 13.0,
            'O' => 13.0,
            'P' => 9.57,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }
}
