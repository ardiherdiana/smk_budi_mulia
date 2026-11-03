<?php

namespace Tests\Unit;

use App\Models\Classroom\Soal;
use App\Support\Classroom\PembagiPoin;
use PHPUnit\Framework\TestCase;

class PembagiPoinTest extends TestCase
{
    public function test_dua_puluh_soal_masing_masing_lima_poin(): void
    {
        $this->assertSame(array_fill(0, 20, 5.0), PembagiPoin::bagiRata(20));
        $this->assertSame([100.0], PembagiPoin::bagiRata(1));
        $this->assertSame([], PembagiPoin::bagiRata(0));
    }

    public function test_tiga_soal_menaruh_sisa_sen_di_soal_terakhir(): void
    {
        $this->assertSame([33.33, 33.33, 33.34], PembagiPoin::bagiRata(3));
        $this->assertSame([16.66, 16.66, 16.67, 16.67, 16.67, 16.67], PembagiPoin::bagiRata(6));
    }

    public function test_untuk_semua_jumlah_soal_total_tepat_seratus_dan_tiap_soal_minimal_setengah(): void
    {
        for ($n = 1; $n <= 200; $n++) {
            $poin = PembagiPoin::bagiRata($n);
            $sen = array_sum(array_map(fn (float $p) => (int) round($p * 100), $poin));

            $this->assertCount($n, $poin);
            $this->assertSame(10000, $sen, "Total {$n} soal bukan tepat 100");
            $this->assertGreaterThanOrEqual(0.5, min($poin), "Ada soal di bawah 0,5 pada {$n} soal");
        }
    }

    public function test_sama_mengenali_pembagian_rata_dan_menolak_poin_manual(): void
    {
        $this->assertTrue(PembagiPoin::sama([]));
        $this->assertTrue(PembagiPoin::sama([5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5, 5]));
        $this->assertTrue(PembagiPoin::sama(['33.33', 33.33, '33.34']));
        $this->assertFalse(PembagiPoin::sama([33.34, 33.33, 33.33]));
        $this->assertFalse(PembagiPoin::sama([10, 10, 10]));
        $this->assertFalse(PembagiPoin::sama([60, 40]));
    }

    public function test_skor_parsial_pilihan_ganda_kompleks_dan_benar_salah_mengikuti_poin_soal(): void
    {
        $mcma = new Soal(['tipe' => 'PGK_MCMA', 'poin' => 5, 'opsi' => ['a', 'b', 'c', 'd', 'e'], 'kunci' => '0,1,4']);
        // Tiap jawaban benar = 5/3; satu salah mengurangi sebesar itu; tidak pernah di bawah 0.
        $this->assertSame(5.0, $mcma->skorUntuk('0,1,4'));
        $this->assertSame(3.33, $mcma->skorUntuk('0,1'));
        $this->assertSame(1.67, $mcma->skorUntuk('0,1,2'));
        $this->assertSame(0.0, $mcma->skorUntuk('2,3'));

        $kategori = new Soal(['tipe' => 'PGK_KATEGORI', 'poin' => 5, 'opsi' => ['p1', 'p2', 'p3'], 'kunci' => '1,0,1']);
        // Tiap pernyataan yang tepat = 5/3.
        $this->assertSame(5.0, $kategori->skorUntuk('1,0,1'));
        $this->assertSame(3.33, $kategori->skorUntuk('1,0,0'));
        $this->assertSame(1.67, $kategori->skorUntuk('0,0,0'));
    }
}
