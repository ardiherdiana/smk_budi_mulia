<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class XlsxExport
{
    private const HEADER_FILL = 'F97316';

    /** Soft background + dark text per status, echoing the badge colors
     * used on the web pages (success/warning/destructive/outline). */
    private const STATUS_COLORS = [
        'HADIR' => ['bg' => 'D1FAE5', 'text' => '065F46'],
        'TELAT' => ['bg' => 'FEF3C7', 'text' => '92400E'],
        'ALPA' => ['bg' => 'FEE2E2', 'text' => '991B1B'],
        'IZIN' => ['bg' => 'E5E7EB', 'text' => '374151'],
        'SAKIT' => ['bg' => 'E5E7EB', 'text' => '374151'],
    ];

    private const STATUS_LABELS = [
        'HADIR' => 'Hadir',
        'TELAT' => 'Telat',
        'IZIN' => 'Izin',
        'SAKIT' => 'Sakit',
        'ALPA' => 'Alpa',
    ];

    /** @param  string[]  $headers
     * @param  array<int, array<int, string>>  $rows  status defaults to the last column unless $statusColumnIndex says otherwise
     */
    public static function download(
        string $filename,
        string $schoolName,
        string $title,
        string $period,
        array $headers,
        array $rows,
        ?int $statusColumnIndex = null,
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $columnCount = count($headers);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $statusColumnIndex ??= $columnCount - 1;
        $statusColumn = Coordinate::stringFromColumnIndex($statusColumnIndex + 1);

        self::writeLetterhead($sheet, $schoolName, $title, $period, $lastColumn);

        $headerRow = 5;
        $sheet->fromArray($headers, null, "A{$headerRow}");
        self::styleHeaderRow($sheet, $headerRow, $lastColumn);

        $firstDataRow = $headerRow + 1;
        $sheet->fromArray($rows, null, "A{$firstDataRow}");
        $lastDataRow = $firstDataRow + count($rows) - 1;

        if ($rows !== []) {
            self::styleDataRows($sheet, $firstDataRow, $lastDataRow, $lastColumn, $statusColumn);
            self::writeSummary($sheet, $lastDataRow, $rows, $statusColumnIndex);
        }

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->freezePane("A{$firstDataRow}");
        $sheet->setAutoFilter("A{$headerRow}:{$lastColumn}{$lastDataRow}");

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private static function writeLetterhead(Worksheet $sheet, string $schoolName, string $title, string $period, string $lastColumn): void
    {
        $sheet->setCellValue('A1', $schoolName);
        $sheet->setCellValue('A2', $title);
        $sheet->setCellValue('A3', "Periode: {$period}");

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->mergeCells("A3:{$lastColumn}3");

        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14]]);
        $sheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
        $sheet->getStyle('A3')->applyFromArray(['font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']]]);
    }

    private static function styleHeaderRow(Worksheet $sheet, int $headerRow, string $lastColumn): void
    {
        $range = "A{$headerRow}:{$lastColumn}{$headerRow}";
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::HEADER_FILL]]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(20);
    }

    private static function styleDataRows(Worksheet $sheet, int $firstDataRow, int $lastDataRow, string $lastColumn, string $statusColumn): void
    {
        $sheet->getStyle("A{$firstDataRow}:{$lastColumn}{$lastDataRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);

        for ($row = $firstDataRow; $row <= $lastDataRow; $row++) {
            $status = strtoupper((string) $sheet->getCell("{$statusColumn}{$row}")->getValue());
            $colors = self::STATUS_COLORS[$status] ?? null;
            if ($colors === null) {
                continue;
            }

            $sheet->getStyle("{$statusColumn}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => $colors['text']]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors['bg']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }
    }

    /** @param  array<int, array<int, string>>  $rows */
    private static function writeSummary(Worksheet $sheet, int $lastDataRow, array $rows, int $statusColumnIndex): void
    {
        $counts = array_count_values(array_map(
            fn ($row) => strtoupper((string) ($row[$statusColumnIndex] ?? '')),
            $rows
        ));

        $row = $lastDataRow + 2;
        $sheet->setCellValue("A{$row}", 'Ringkasan');
        $sheet->getStyle("A{$row}")->applyFromArray(['font' => ['bold' => true]]);
        $row++;

        foreach (self::STATUS_LABELS as $key => $label) {
            if (empty($counts[$key])) {
                continue;
            }
            $sheet->setCellValue("A{$row}", "{$label}:");
            $sheet->setCellValue("B{$row}", $counts[$key]);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Total:');
        $sheet->setCellValue("B{$row}", count($rows));
        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray(['font' => ['bold' => true]]);
    }
}
