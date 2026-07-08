<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LibraryHoldingsReport3ExcelWriter
{
    public function __construct(
        protected string $heiName,
        protected string $programName,
        protected ?string $dateAccomplished,
        protected array $detail,
    ) {}

    public function addSheetTo(Spreadsheet $spreadsheet): Worksheet
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Report 3');

        $this->applyColumnWidths($sheet);

        $sheet->setCellValue('A1', 'HEI Name: '.$this->heiName);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(10);

        $dateLine = $this->dateAccomplished
            ? 'Date Accomplished: '.$this->dateAccomplished
            : 'Date Accomplished:________________________________________________';
        $sheet->setCellValue('A2', $dateLine);
        $sheet->getStyle('A2')->getFont()->setSize(10);

        $sheet->setCellValue('A3', 'Program Name: '.mb_strtoupper($this->programName));
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(10);

        $sheet->setCellValue('A5', 'Library Collection');
        $sheet->getStyle('A5')->getFont()->setBold(true)->setSize(12);

        $sheet->setCellValue('A6', 'List of subscription to relevant professional journals');
        $sheet->getStyle('A6')->getFont()->setSize(10);

        $headers = [
            'A8' => 'No.',
            'B8' => 'Type',
            'C8' => 'Journal Name',
            'D8' => 'Publisher',
            'E8' => 'Publication',
            'F8' => 'Frequency',
            'G8' => 'Regular Subscription',
            'H8' => 'Supporting Documents Link',
        ];
        foreach ($headers as $cell => $label) {
            $sheet->setCellValue($cell, $label);
        }

        $sheet->getStyle('A8:H8')->applyFromArray([
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

        $startRow = 9;
        $detailCount = count($this->detail);

        for ($i = 0; $i < $detailCount; $i++) {
            $row = $startRow + $i;
            $line = $this->detail[$i];

            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $line['type']);
            $sheet->setCellValue("C{$row}", $line['journal_name']);
            $sheet->setCellValue("D{$row}", $line['publisher']);
            $sheet->setCellValue("E{$row}", $line['publication']);
            $sheet->setCellValue("F{$row}", $line['frequency']);
            $sheet->setCellValue("G{$row}", $line['subscription']);
            $sheet->setCellValue("H{$row}", $line['supporting_link']);

            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray($this->dataStyle());
        }

        if ($detailCount > 0) {
            $lastDataRow = $startRow + $detailCount - 1;
            $sheet->getStyle("A{$startRow}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$startRow}:G{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$startRow}:C{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D{$startRow}:D{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("H{$startRow}:H{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("A{$startRow}:H{$lastDataRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        }

        $footerStartRow = $startRow + max($detailCount, 1) + 1;
        $sheet->setCellValue("B{$footerStartRow}", '*add rows when necessary*');
        $sheet->getStyle("B{$footerStartRow}")->getFont()->setItalic(true)->setSize(10);

        $sigRow = $footerStartRow + 4;
        $nameRow = $sigRow + 1;
        $labelRow = $sigRow + 2;
        $titleRow = $sigRow + 3;

        $preparedName = config('reports.signatories.prepared_by_name', '');
        $approvedName = config('reports.signatories.approved_by_name', '');
        $preparedTitle = config('reports.signatories.prepared_by_title', 'College Librarian');
        $approvedTitle = config('reports.signatories.approved_by_title', 'HEI Head');

        $sheet->setCellValue("A{$sigRow}", 'Prepared by:');
        $sheet->setCellValue("E{$sigRow}", 'Approved by:');

        $sheet->setCellValue("A{$nameRow}", $preparedName);
        $sheet->setCellValue("E{$nameRow}", $approvedName);

        $sheet->setCellValue("A{$labelRow}", 'Name');
        $sheet->setCellValue("E{$labelRow}", 'Name');

        $sheet->setCellValue("A{$titleRow}", $preparedTitle);
        $sheet->setCellValue("E{$titleRow}", $approvedTitle);

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
            'B' => 12.0,
            'C' => 32.71,
            'D' => 24.57,
            'E' => 14.0,
            'F' => 14.0,
            'G' => 18.0,
            'H' => 42.0,
        ];

        foreach ($widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }
}
