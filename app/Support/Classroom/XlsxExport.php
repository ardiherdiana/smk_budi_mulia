<?php

namespace App\Support\Classroom;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
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

    private const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * Tabel dengan kop sekolah, header oranye, dan baris bergaris tipis (sama dengan ekspor absensi).
     *
     * @param  string[]  $headers
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public static function download(
        string $filename,
        string $schoolName,
        string $title,
        string $subtitle,
        array $headers,
        array $rows,
    ): StreamedResponse {
        $spreadsheet = new Spreadsheet;
        self::tulisSheet($spreadsheet->getActiveSheet(), $schoolName, $title, $subtitle, $headers, $rows);

        return self::kirim($spreadsheet, $filename);
    }

    /**
     * Satu berkas dengan beberapa sheet, masing-masing bergaya sama dengan `download`.
     *
     * Tiap sheet: `nama` (judul tab, dirapikan dan dibuat unik), `judul`, `subjudul`, `headers`, `rows`, dan opsional
     * `format` (nomor kolom mulai 1 => kode format angka, mis. `0.00`), `teks` (nomor kolom yang ditulis sebagai teks
     * supaya nol di depan seperti NIS "00123" tidak hilang), dan `tebal` (indeks baris data yang dicetak tebal).
     *
     * @param  list<array{nama: string, judul: string, subjudul: string, headers: list<string>, rows: list<list<string|int|float|null>>, format?: array<int, string>, teks?: list<int>, tebal?: list<int>}>  $sheets
     */
    public static function downloadSheets(string $filename, string $schoolName, array $sheets): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $dipakai = [];

        foreach (array_values($sheets) as $i => $s) {
            $sheet = $i === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle(self::namaSheet($s['nama'], $dipakai));
            self::tulisSheet($sheet, $schoolName, $s['judul'], $s['subjudul'], $s['headers'], $s['rows'], $s['format'] ?? [], $s['teks'] ?? [], $s['tebal'] ?? []);
        }
        $spreadsheet->setActiveSheetIndex(0);

        return self::kirim($spreadsheet, $filename);
    }

    /**
     * Nama tab Excel: maksimal 31 karakter, tanpa `[ ] : * ? / \`, dan unik (tanpa membedakan huruf besar kecil).
     *
     * @param  list<string>  $dipakai
     */
    public static function namaSheet(string $nama, array &$dipakai): string
    {
        $dasar = trim((string) preg_replace('/[\[\]:*?\/\\\\]+/u', ' ', $nama));
        $dasar = mb_substr($dasar === '' ? 'Sheet' : $dasar, 0, 31);

        $kandidat = $dasar;
        for ($n = 2; in_array(mb_strtolower($kandidat), $dipakai, true); $n++) {
            $akhiran = " ({$n})";
            $kandidat = mb_substr($dasar, 0, 31 - mb_strlen($akhiran)).$akhiran;
        }
        $dipakai[] = mb_strtolower($kandidat);

        return $kandidat;
    }

    /**
     * @param  string[]  $headers
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @param  array<int, string>  $format
     * @param  list<int>  $teks
     * @param  list<int>  $tebal
     */
    private static function tulisSheet(
        Worksheet $sheet,
        string $schoolName,
        string $title,
        string $subtitle,
        array $headers,
        array $rows,
        array $format = [],
        array $teks = [],
        array $tebal = [],
    ): void {
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        self::writeLetterhead($sheet, $schoolName, $title, $subtitle, $lastColumn);

        $headerRow = 5;
        $sheet->fromArray($headers, null, "A{$headerRow}");
        self::styleHeaderRow($sheet, $headerRow, $lastColumn);

        $firstDataRow = $headerRow + 1;
        $lastDataRow = $headerRow;
        if ($rows !== []) {
            // Perbandingan ketat: tanpa ini angka 0 dianggap kosong (0 == null) dan sel skor 0 hilang dari berkas.
            $sheet->fromArray($rows, null, "A{$firstDataRow}", true);
            $lastDataRow = $firstDataRow + count($rows) - 1;
            $sheet->getStyle("A{$firstDataRow}:{$lastColumn}{$lastDataRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
            ]);

            foreach ($teks as $kolom) {
                foreach ($rows as $i => $baris) {
                    $nilai = $baris[$kolom - 1] ?? null;
                    if ($nilai !== null && $nilai !== '') {
                        $sheet->setCellValueExplicit([$kolom, $firstDataRow + $i], (string) $nilai, DataType::TYPE_STRING);
                    }
                }
            }
            foreach ($format as $kolom => $kode) {
                $huruf = Coordinate::stringFromColumnIndex($kolom);
                $sheet->getStyle("{$huruf}{$firstDataRow}:{$huruf}{$lastDataRow}")->getNumberFormat()->setFormatCode($kode);
            }
            foreach ($tebal as $i) {
                $baris = $firstDataRow + $i;
                $sheet->getStyle("A{$baris}:{$lastColumn}{$baris}")->getFont()->setBold(true);
            }
        }

        foreach (range(1, count($headers)) as $index) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        $sheet->freezePane("A{$firstDataRow}");

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
    }

    private static function kirim(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, ['Content-Type' => self::MIME]);
    }

    private static function writeLetterhead(Worksheet $sheet, string $schoolName, string $title, string $subtitle, string $lastColumn): void
    {
        $sheet->setCellValue('A1', $schoolName);
        $sheet->setCellValue('A2', $title);
        $sheet->setCellValue('A3', $subtitle);

        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->mergeCells("A3:{$lastColumn}3");

        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 14]]);
        $sheet->getStyle('A2')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);
        $sheet->getStyle('A3')->applyFromArray(['font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '6B7280']]]);
    }

    private static function styleHeaderRow(Worksheet $sheet, int $headerRow, string $lastColumn): void
    {
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::HEADER_FILL]]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(20);
    }
}
