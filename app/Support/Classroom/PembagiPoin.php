<?php

namespace App\Support\Classroom;

/**
 * Pembagian poin soal ujian supaya totalnya tepat 100. Dihitung dalam sen (bilangan bulat) agar tidak ada selisih
 * pembulatan: 20 soal = 5 poin, 3 soal = 33,33 / 33,33 / 33,34 (sisa sen jatuh pada soal-soal terakhir).
 */
final class PembagiPoin
{
    public const TOTAL = 100;

    /**
     * @return list<float> satu poin per soal menurut urutan; kosong bila tak ada soal
     */
    public static function bagiRata(int $jumlah): array
    {
        if ($jumlah <= 0) {
            return [];
        }

        $sen = self::TOTAL * 100;
        $dasar = intdiv($sen, $jumlah);
        $sisa = $sen % $jumlah;

        $hasil = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $hasil[] = ($dasar + ($i >= $jumlah - $sisa ? 1 : 0)) / 100.0;
        }

        return $hasil;
    }

    /**
     * Apakah poin yang ada persis pembagian rata untuk jumlah soal sebanyak itu (urutan soal sama dengan urutan poin).
     *
     * @param  iterable<int|float|string>  $poin
     */
    public static function sama(iterable $poin): bool
    {
        $ada = [];
        foreach ($poin as $p) {
            $ada[] = (int) round((float) $p * 100);
        }

        return $ada === array_map(fn (float $p) => (int) round($p * 100), self::bagiRata(count($ada)));
    }
}
