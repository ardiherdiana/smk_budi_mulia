<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Services\Classroom\Pdf\PdfGambarDecoder;
use App\Services\Classroom\Pdf\PdfSoalReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PdfBuilder;
use Tests\TestCase;

class ImporSoalPdfTest extends TestCase
{
    /** @return array{0: Akun, 1: Akun, 2: Post} */
    private function siapkan(): array
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel('X TKJ 1');
        $siswa = $this->siswa($rombel, 'siswa01');
        $post = $this->buatPost($this->kelas($guru, $rombel), $guru, 'UJIAN', [
            'durasiMenit' => 30, 'mulaiAt' => now()->subDay(), 'selesaiAt' => now()->addDay(),
        ]);

        return [$guru, $siswa, $post];
    }

    private function impor(Akun $pengguna, Post $post, string $isiPdf, string $nama = 'soal.pdf')
    {
        return $this->actingAs($pengguna)
            ->postJson("/classroom/post/{$post->id}/soal/impor", ['berkas' => UploadedFile::fake()->createWithContent($nama, $isiPdf)]);
    }

    public function test_template_tka_dipecah_menjadi_draf_dengan_gambar_di_pertanyaan_dan_pilihan(): void
    {
        [$guru, , $post] = $this->siapkan();

        $pdf = PdfBuilder::baru()->jpeg('topologi')->flate('kabel')->flate('serat', 40, 30)
            ->baris('Tidak Soal 1', true)
            ->baris('Kompetensi Mengenal topologi jaringan')
            ->baris('Sub Kompetensi Topologi dasar')
            ->baris('Bentuk Soal Pilihan Ganda (PG)')
            ->baris('Kunci C')
            ->baris('Perhatikan gambar topologi berikut.', true)
            ->pasang('topologi')
            ->baris('Topologi yang digunakan pada gambar tersebut adalah', true)
            ->baris('A. Bus', true)
            ->baris('B. Star')
            ->baris('C. Ring')
            ->baris('D. Mesh')
            ->baris('E. Tree')
            ->baris('Tidak Soal 2', true)
            ->baris('Kompetensi Media transmisi')
            ->baris('Sub Kompetensi Kabel')
            ->baris('Bentuk Soal PGK MCMA')
            ->baris('Kunci Pernyataan 1, Pernyataan 3')
            ->baris('Pilih semua gambar yang benar. ( Jawaban benar lebih dari satu )', true)
            ->baris('A.', true)->pasang('kabel')
            ->baris('B.', true)->pasang('serat')
            ->baris('C.', true)->pasang('kabel')
            ->isi();

        $json = $this->impor($guru, $post, $pdf)->assertOk()->json();

        $this->assertCount(2, $json['soal']);
        [$satu, $dua] = $json['soal'];

        $this->assertSame('PG', $satu['tipe']);
        $this->assertSame('Mengenal topologi jaringan', $satu['kompetensi']);
        $this->assertSame('Topologi dasar', $satu['subKompetensi']);
        $this->assertSame(['Bus', 'Star', 'Ring', 'Mesh', 'Tree'], $satu['opsi']);
        $this->assertSame('2', $satu['kunci']);
        $this->assertStringContainsString('Perhatikan gambar topologi berikut.', $satu['teks']);
        $this->assertStringContainsString('adalah', $satu['teks']);
        $this->assertCount(1, $satu['gambar']);

        $this->assertSame('PGK_MCMA', $dua['tipe']);
        $this->assertSame('0,2', $dua['kunci']);
        $this->assertSame(['', '', ''], $dua['opsi']);
        $this->assertSame([], $dua['gambar']);
        $this->assertCount(3, $dua['opsiGambar']);
        // Gambar yang sama dipakai di pilihan A dan C: satu berkas, dua rujukan.
        $this->assertSame($dua['opsiGambar'][0]['path'], $dua['opsiGambar'][2]['path']);
        $this->assertNotSame($dua['opsiGambar'][0]['path'], $dua['opsiGambar'][1]['path']);

        foreach ([$satu['gambar'][0], ...$dua['opsiGambar']] as $gambar) {
            $this->assertStringStartsWith('soal-tmp/', $gambar['path']);
            $this->assertStringStartsWith('/media/soal-tmp/', $gambar['url']);
            Storage::disk('media')->assertExists($gambar['path']);
        }
        // Tidak ada soal yang tersimpan: impor hanya menghasilkan draf.
        $this->assertSame(0, $post->soal()->count());
    }

    public function test_format_bernomor_dengan_wacana_bersama_dan_gambar_di_stem(): void
    {
        [$guru, , $post] = $this->siapkan();

        $pdf = PdfBuilder::baru()->jpeg('wacana')->flate('logo')
            ->baris('1. Manakah protokol yang bekerja di lapisan transport?', true)
            ->baris('A. TCP')->baris('B. HTTP')->baris('C. ARP')
            ->baris('Kunci: A')
            ->baris('Gunakan gambar berikut untuk menjawab soal nomor 2 sampai 3.', true)
            ->pasang('wacana')
            ->baris('2. Berapa jumlah perangkat pada diagram?', true)
            ->baris('A. 2')->baris('B. 3')->baris('Kunci: B')
            ->baris('3. Jelaskan fungsi perangkat pusat pada diagram tersebut.', true)
            ->baris('4. Perhatikan logo berikut.', true)
            ->pasang('logo')
            ->baris('A. Cisco', true)->baris('B. Mikrotik')->baris('Kunci: B')
            ->isi();

        $json = $this->impor($guru, $post, $pdf)->assertOk()->json();

        $this->assertCount(4, $json['soal']);
        [$a, $b, $c, $d] = $json['soal'];

        $this->assertSame('0', $a['kunci']);
        $this->assertSame([], $a['gambar']);

        // Wacana dibagi ke soal 2 dan 3 (path sama), teks wacana ikut ke dua soal itu.
        $this->assertSame('PG', $b['tipe']);
        $this->assertSame('ESSAY', $c['tipe']);
        $this->assertCount(1, $b['gambar']);
        $this->assertSame($b['gambar'][0]['path'], $c['gambar'][0]['path']);
        $this->assertStringContainsString('soal nomor 2 sampai 3', $c['teks']);
        $this->assertStringContainsString('Jelaskan fungsi perangkat pusat', $c['teks']);
        $this->assertSame('1', $b['kunci']);

        $this->assertCount(1, $d['gambar']);
        $this->assertSame(['Cisco', 'Mikrotik'], $d['opsi']);
        $this->assertSame('1', $d['kunci']);
    }

    public function test_bentuk_kategori_dengan_kunci_beberapa_baris_dan_kunci_pg_kosong_memberi_peringatan(): void
    {
        [$guru, , $post] = $this->siapkan();

        // Susunan seperti template TKA asli: sel Kunci Kategori berisi beberapa baris, tabel pernyataan memakai huruf.
        $pdf = PdfBuilder::baru()
            ->baris('Tidak Soal 1', true)
            ->baris('Kompetensi Penerapan K3LH')
            ->baris('Bentuk Soal Kategori PGK')
            ->baris('Kunci')
            ->baris('Pernyataan')
            ->baris('A. Benar')->baris('B. Salah')->baris('C. Benar')
            ->baris('Dalam instalasi jaringan, kabel berserakan di lantai. Tentukan pernyataan berikut Benar atau Salah.', true)
            ->baris('Pernyataan Benar Salah', true)
            ->baris('A. Menata kabel agar tidak berserakan', true)
            ->baris('B. Melepas kabel dan memasang yang baru')
            ->baris('C. Menjaga area kerja tetap rapi')
            ->baris('Tidak Soal 2', true)
            ->baris('Kompetensi Instalasi OS')
            ->baris('Bentuk Soal Pilihan Ganda (PG)')
            ->baris('Kunci')
            ->baris('Langkah yang paling tepat adalah', true)
            ->baris('A. Ulangi instalasi', true)->baris('B. Ganti kabel')->baris('C. Matikan perangkat')
            ->isi();

        $json = $this->impor($guru, $post, $pdf)->assertOk()->json();

        $this->assertCount(2, $json['soal']);
        $kategori = $json['soal'][0];
        $this->assertSame('PGK_KATEGORI', $kategori['tipe']);
        $this->assertSame('1,0,1', $kategori['kunci']);
        $this->assertSame(['Menata kabel agar tidak berserakan', 'Melepas kabel dan memasang yang baru', 'Menjaga area kerja tetap rapi'], $kategori['opsi']);
        $this->assertStringContainsString('Tentukan pernyataan berikut', $kategori['teks']);

        // Kunci PG yang kosong di PDF tidak ditebak: dikosongkan dan guru diberi tahu soal mana.
        $pg = $json['soal'][1];
        $this->assertSame('PG', $pg['tipe']);
        $this->assertSame('', $pg['kunci']);
        $this->assertSame(['Ulangi instalasi', 'Ganti kabel', 'Matikan perangkat'], $pg['opsi']);
        $this->assertTrue(collect($json['peringatan'])->contains(fn ($p) => str_contains($p, 'Soal 2') && str_contains($p, 'kunci')));
    }

    public function test_angka_desimal_di_awal_baris_bukan_nomor_soal_dan_nomor_yang_melompat_tetap_terbaca(): void
    {
        [$guru, , $post] = $this->siapkan();

        $pdf = PdfBuilder::baru()
            ->baris('1. Berapa panjang maksimal kabel UTP antar perangkat?', true)
            ->baris('1.5 meter bukan jawabannya karena terlalu pendek untuk standar.')
            ->baris('A. 100 meter', true)->baris('B. 1.5 meter')->baris('Kunci: A')
            ->baris('4. Sebutkan dua jenis media transmisi.', true)
            ->isi();

        $json = $this->impor($guru, $post, $pdf)->assertOk()->json();

        // Soal 2 dan 3 dihapus guru dari dokumen: nomor 4 tetap dikenali sebagai soal berikutnya.
        $this->assertCount(2, $json['soal']);
        $this->assertSame([1, 4], array_column($json['soal'], 'nomor'));
        $this->assertStringContainsString('1.5 meter bukan jawabannya', $json['soal'][0]['teks']);
        $this->assertSame(['100 meter', '1.5 meter'], $json['soal'][0]['opsi']);
        $this->assertSame('ESSAY', $json['soal'][1]['tipe']);
    }

    public function test_gambar_flate_berprediktor_bertopeng_dan_berpalet_didekode_dengan_piksel_benar(): void
    {
        $pdf = PdfBuilder::baru()
            ->flate('polos')->flate('prediktor', predictor: true)
            ->flate('topengLurus', alfa: true)->flate('topengTerbalik', alfa: true, alfaTerbalik: true)->flate('topengPrediktor', predictor: true, alfa: true)
            ->berpalet('palet')->jpeg('jpg')
            ->baris('Contoh gambar')
            ->pasang('polos')->pasang('prediktor')->pasang('topengLurus')->pasang('topengTerbalik')->pasang('topengPrediktor')->pasang('palet')->pasang('jpg')
            ->isi();

        $berkas = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($berkas, $pdf);
        $bacaan = app(PdfSoalReader::class)->baca($berkas);
        unlink($berkas);

        $gambar = array_values(array_filter($bacaan->blok, fn ($b) => $b['jenis'] === 'gambar'));
        $this->assertCount(7, $gambar);

        $dekoder = new PdfGambarDecoder;
        $warna = function (array $hasil, int $x, int $y): array {
            $this->assertArrayNotHasKey('galat', $hasil, $hasil['galat'] ?? '');
            $this->assertSame('image/png', $hasil['mime']);
            $gd = imagecreatefromstring($hasil['bytes']);
            $c = imagecolorsforindex($gd, imagecolorat($gd, $x, $y));

            return [$c['red'], $c['green'], $c['blue']];
        };

        foreach ([0, 1] as $i) {
            $hasil = $dekoder->dekode($gambar[$i]['objek']);
            $this->assertSame(PdfBuilder::pikselFlate(10, 7), $warna($hasil, 10, 7), "gambar {$i}");
            $this->assertSame(PdfBuilder::pikselFlate(47, 35), $warna($hasil, 47, 35), "gambar {$i}");
        }

        // Topeng: kiri buram (warna asli), kanan transparan (jadi putih), baik topeng lurus, terbalik, maupun berprediktor.
        foreach ([2, 3, 4] as $i) {
            $hasil = $dekoder->dekode($gambar[$i]['objek']);
            $this->assertSame(PdfBuilder::pikselFlate(5, 9), $warna($hasil, 5, 9), "topeng {$i} kiri");
            $this->assertSame([255, 255, 255], $warna($hasil, 40, 9), "topeng {$i} kanan");
        }

        $palet = $dekoder->dekode($gambar[5]['objek']);
        $this->assertSame([255, 0, 0], $warna($palet, 2, 2));
        $this->assertSame([0, 0, 255], $warna($palet, 28, 2));

        $jpeg = $dekoder->dekode($gambar[6]['objek']);
        $this->assertSame('image/jpeg', $jpeg['mime']);
        $this->assertStringStartsWith("\xFF\xD8", $jpeg['bytes']);
    }

    public function test_logo_yang_berulang_di_tiap_halaman_dan_nomor_halaman_dibuang(): void
    {
        [$guru, , $post] = $this->siapkan();

        $pdf = PdfBuilder::baru()->jpeg('logo')->flate('soal');
        foreach ([1, 2, 3] as $h) {
            $pdf->pasang('logo', 60, 40)->baris('SMK Budi Mulia Karawang');
            if ($h === 1) {
                $pdf->baris('1. Perhatikan gambar.', true)->pasang('soal')->baris('A. Satu', true)->baris('B. Dua');
            }
            $pdf->baris((string) $h, true);
            if ($h < 3) {
                $pdf->halamanBaru();
            }
        }

        $json = $this->impor($guru, $post, $pdf->isi())->assertOk()->json();

        $this->assertCount(1, $json['soal']);
        $this->assertSame('Perhatikan gambar.', $json['soal'][0]['teks']);
        $this->assertCount(1, $json['soal'][0]['gambar']);
        $this->assertSame(['Satu', 'Dua'], $json['soal'][0]['opsi']);
    }

    public function test_pdf_tanpa_teks_hasil_scan_ditolak_dengan_pesan_jelas(): void
    {
        [$guru, , $post] = $this->siapkan();

        $pdf = PdfBuilder::baru()->jpeg('halaman')->pasang('halaman', 400, 600)->isi();

        $this->impor($guru, $post, $pdf)->assertStatus(422)->assertJsonValidationErrors('berkas');
        $this->assertStringContainsString('scan', $this->impor($guru, $post, $pdf)->json('errors.berkas.0'));
    }

    public function test_berkas_bukan_pdf_atau_terlalu_panjang_ditolak(): void
    {
        [$guru, , $post] = $this->siapkan();

        $this->impor($guru, $post, 'ini bukan pdf')->assertStatus(422)->assertJsonValidationErrors('berkas');

        config(['classroom.max_pdf_halaman' => 2]);
        $panjang = PdfBuilder::baru()->baris('1. a')->halamanBaru()->baris('b')->halamanBaru()->baris('c')->isi();
        $this->impor($guru, $post, $panjang)->assertStatus(422)->assertJsonValidationErrors('berkas');
    }

    public function test_pdf_tanpa_soal_yang_dikenali_memberi_peringatan_bukan_galat(): void
    {
        [$guru, , $post] = $this->siapkan();

        $json = $this->impor($guru, $post, PdfBuilder::baru()->baris('Halo dunia')->baris('Ini hanya catatan biasa')->isi())->assertOk()->json();

        $this->assertSame([], $json['soal']);
        $this->assertNotEmpty($json['peringatan']);
    }

    public function test_siswa_tidak_boleh_impor_dan_ujian_yang_sudah_dikerjakan_terkunci(): void
    {
        [$guru, $siswa, $post] = $this->siapkan();
        $pdf = PdfBuilder::baru()->baris('1. Soal?')->baris('A. a')->baris('B. b')->isi();

        $this->impor($siswa, $post, $pdf)->assertForbidden();

        Percobaan::create([
            'postId' => $post->id, 'siswaId' => $siswa->siswa->id,
            'mulaiAt' => now(), 'batasAt' => now()->addMinutes(30), 'status' => 'BERJALAN',
        ]);
        $this->impor($guru, $post, $pdf)->assertStatus(409);
    }
}
