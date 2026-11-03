<?php

namespace App\Services\Classroom\Impor;

/** Merapikan teks hasil baca dokumen (PDF maupun Word) sebelum diurai menjadi soal. */
final class PembersihTeks
{
    public static function bersihkan(string $teks): string
    {
        $teks = mb_scrub($teks);
        $teks = str_replace(["\u{00AD}", "\u{200B}", "\u{FEFF}"], '', $teks);
        $teks = preg_replace('/[\x{00A0}\x{2007}\x{202F}]/u', ' ', $teks) ?? $teks;
        $teks = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $teks) ?? $teks;
        // Butir daftar Word memakai huruf font Symbol (area pribadi U+F000); jadikan butir biasa.
        $teks = preg_replace('/^[\x{F000}-\x{F0FF}]+\s*/u', '• ', $teks) ?? $teks;
        $teks = preg_replace('/ {2,}/', ' ', $teks) ?? $teks;
        $teks = preg_replace('/\s*\t\s*/', "\t", $teks) ?? $teks;

        return trim($teks);
    }
}
