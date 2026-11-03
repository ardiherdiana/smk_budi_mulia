<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DocxBuilder;
use Tests\TestCase;
use ZipArchive;

class ImporSoalDocxTest extends TestCase
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

    private function impor(Akun $pengguna, Post $post, string $isi, string $nama = 'soal.docx')
    {
        return $this->actingAs($pengguna)
            ->postJson("/classroom/post/{$post->id}/soal/impor", ['berkas' => UploadedFile::fake()->createWithContent($nama, $isi)]);
    }

    public function test_template_tka_docx_dengan_gambar_di_pertanyaan_dan_pilihan(): void
    {
        [$guru, , $post] = $this->siapkan();

        $d = DocxBuilder::baru()->gambar('topologi.png', DocxBuilder::png(60, 40))->gambar('kabel.png', DocxBuilder::png(50, 30, 200, 60, 60));
        $huruf1 = $d->daftar();
        $huruf2 = $d->daftar();
        $bullet = $d->daftar('bullet');

        $docx = $d
            ->meta(1, 'Mengenal topologi', 'Topologi dasar', 'Pilihan Ganda (PG)', 'C')
            ->p('Perhatikan gambar topologi berikut.')->p('', ['topologi.png'])->p('Topologi pada gambar tersebut adalah ...')
            ->butir($huruf1, 'Bus')->butir($huruf1, 'Star')->butir($huruf1, 'Ring')->butir($huruf1, 'Mesh')->butir($huruf1, 'Tree')
            ->meta(2, 'Keselamatan kerja', 'APD', 'PGK MCMA', 'A, C')
            ->p('Kondisi lapangan:')->butir($bullet, 'Lantai licin')->butir($bullet, 'Ruangan sempit')
            ->p('Tindakan yang tepat adalah ... ( Jawaban benar lebih dari satu )')
            ->butir($huruf2, 'Pastikan lantai kering', ['kabel.png'])->butir($huruf2, 'Abaikan kondisi ruangan')->butir($huruf2, 'Periksa ventilasi')
            ->isi();

        $json = $this->impor($guru, $post, $docx)->assertOk()->json();

        $this->assertCount(2, $json['soal']);
        [$satu, $dua] = $json['soal'];

        $this->assertSame('PG', $satu['tipe']);
        $this->assertSame('Mengenal topologi', $satu['kompetensi']);
        $this->assertSame('Topologi dasar', $satu['subKompetensi']);
        $this->assertSame(['Bus', 'Star', 'Ring', 'Mesh', 'Tree'], $satu['opsi']);
        $this->assertSame('2', $satu['kunci']);
        $this->assertSame("Perhatikan gambar topologi berikut.\nTopologi pada gambar tersebut adalah ...", $satu['teks']);
        $this->assertCount(1, $satu['gambar']);
        Storage::disk('media')->assertExists($satu['gambar'][0]['path']);
        $this->assertStringStartsWith('soal-tmp/', $satu['gambar'][0]['path']);

        $this->assertSame('PGK_MCMA', $dua['tipe']);
        $this->assertSame('0,2', $dua['kunci']);
        $this->assertSame(['Pastikan lantai kering', 'Abaikan kondisi ruangan', 'Periksa ventilasi'], $dua['opsi']);
        $this->assertStringContainsString("Kondisi lapangan:\n• Lantai licin\n• Ruangan sempit\nTindakan yang tepat", $dua['teks']);
        // Gambar di dalam butir pilihan menempel ke pilihan itu (dengan catatan untuk diperiksa guru).
        $this->assertNotNull($dua['opsiGambar'][0]);
        $this->assertNull($dua['opsiGambar'][1]);
        $this->assertTrue(collect($json['peringatan'])->contains(fn ($p) => str_contains($p, 'Soal 2') && str_contains($p, 'gambar')));
        $this->assertSame(0, $post->soal()->count());
    }

    public function test_pilihan_hanya_gambar_dan_gambar_yang_sama_dipakai_bersama(): void
    {
        [$guru, , $post] = $this->siapkan();

        $d = DocxBuilder::baru()->gambar('a.png', DocxBuilder::png(40, 30))->gambar('b.png', DocxBuilder::png(44, 30, 10, 200, 10));
        $huruf = $d->daftar();

        $docx = $d->meta(1, 'K', 'S', 'Pilihan Ganda (PG)', 'B')->p('Pilih gambar yang benar.')
            ->butir($huruf, '', ['a.png'])->butir($huruf, '', ['b.png'])->butir($huruf, '', ['a.png'])
            ->isi();

        $soal = $this->impor($guru, $post, $docx)->assertOk()->json('soal.0');

        $this->assertSame(['', '', ''], $soal['opsi']);
        $this->assertSame('1', $soal['kunci']);
        $this->assertSame($soal['opsiGambar'][0]['path'], $soal['opsiGambar'][2]['path']);
        $this->assertNotSame($soal['opsiGambar'][0]['path'], $soal['opsiGambar'][1]['path']);
    }

    public function test_bentuk_kategori_dengan_kunci_beberapa_paragraf_dalam_satu_sel(): void
    {
        [$guru, , $post] = $this->siapkan();

        $docx = DocxBuilder::baru()
            ->meta(1, 'K3LH', 'Instalasi', 'Kategori PGK', ['Pernyataan', 'A. Benar', 'B. Salah', 'C. Benar'])
            ->p('Tentukan Benar atau Salah.')
            ->tabel([
                ['#', 'Pernyataan', 'Benar', 'Salah'],
                ['A.', 'Kabel ditata rapi', '', ''],
                ['B.', 'Kabel dibiarkan menjuntai', '', ''],
                ['C.', ['Area kerja rapi', 'dan bersih'], '', ''],
            ])
            ->isi();

        $soal = $this->impor($guru, $post, $docx)->assertOk()->json('soal.0');

        $this->assertSame('PGK_KATEGORI', $soal['tipe']);
        $this->assertSame('1,0,1', $soal['kunci']);
        $this->assertSame(['Kabel ditata rapi', 'Kabel dibiarkan menjuntai', 'Area kerja rapi dan bersih'], $soal['opsi']);
    }

    public function test_huruf_daftar_mengikuti_word_daftar_baru_mulai_lagi_dan_nomor_desimal_lanjut(): void
    {
        [$guru, , $post] = $this->siapkan();

        // Dua soal template: tiap soal punya daftar huruf baru (numId baru), jadi sama-sama mulai dari A.
        $d = DocxBuilder::baru();
        $a = $d->daftar();
        $b = $d->daftar();
        $docx = $d
            ->meta(1, 'K', 'S', 'Pilihan Ganda (PG)', 'A')->p("Baris satu\nBaris dua adalah")->butir($a, 'x1')->butir($a, 'x2')
            ->meta(2, 'K', 'S', 'Pilihan Ganda (PG)', 'B')->p('Soal kedua adalah')->butir($b, 'y1')->butir($b, 'y2')
            ->isi();

        $json = $this->impor($guru, $post, $docx)->assertOk()->json();
        $this->assertSame(['x1', 'x2'], $json['soal'][0]['opsi']);
        $this->assertSame("Baris satu\nBaris dua adalah", $json['soal'][0]['teks']);
        $this->assertSame(['y1', 'y2'], $json['soal'][1]['opsi']);
        $this->assertSame(['0', '1'], [$json['soal'][0]['kunci'], $json['soal'][1]['kunci']]);

        // Soal bernomor otomatis (daftar desimal) yang diselingi baris "Kunci" tetap lanjut 1, 2.
        $d = DocxBuilder::baru();
        $q = $d->daftar('decimal');
        $a1 = $d->daftar();
        $a2 = $d->daftar();
        $bernomor = $d
            ->butir($q, 'Protokol manakah yang bekerja di lapisan transport?')->butir($a1, 'TCP')->butir($a1, 'HTTP')->p('Kunci: A')
            ->butir($q, 'Berapa nomor port bawaan HTTP?')->butir($a2, '80')->butir($a2, '443')->p('Kunci: A')
            ->isi();

        $json = $this->impor($guru, $post, $bernomor)->assertOk()->json();
        $this->assertSame([1, 2], array_column($json['soal'], 'nomor'));
        $this->assertSame(['TCP', 'HTTP'], $json['soal'][0]['opsi']);
        $this->assertSame(['80', '443'], $json['soal'][1]['opsi']);
        $this->assertSame(['0', '0'], array_column($json['soal'], 'kunci'));
    }

    public function test_penomoran_dari_gaya_paragraf_dan_gaya_turunan_terbaca(): void
    {
        [$guru, , $post] = $this->siapkan();

        $d = DocxBuilder::baru();
        $huruf = $d->daftar();
        $docx = $d->gayaDaftar('DaftarAlfabet', $huruf)->gayaDaftar('Turunan', '', 'DaftarAlfabet')
            ->meta(1, 'K', 'S', 'Pilihan Ganda (PG)', 'B')->p('Pilih jawaban yang benar adalah')
            ->p('Bus', [], 'DaftarAlfabet')->p('Star', [], 'Turunan')->p('Ring', [], 'Turunan')
            ->isi();

        $soal = $this->impor($guru, $post, $docx)->assertOk()->json('soal.0');

        $this->assertSame(['Bus', 'Star', 'Ring'], $soal['opsi']);
        $this->assertSame('1', $soal['kunci']);
    }

    public function test_isi_alternatif_tidak_menggandakan_teks_dan_teks_terhapus_diabaikan(): void
    {
        [$guru, , $post] = $this->siapkan();

        $docx = DocxBuilder::baru()->meta(1, 'K', 'S', 'Essay')
            ->mentah('<w:p><w:r><mc:AlternateContent><mc:Choice Requires="wps"><w:t>Pertanyaan asli</w:t></mc:Choice>'
                .'<mc:Fallback><w:t>Salinan lama</w:t></mc:Fallback></mc:AlternateContent></w:r>'
                .'<w:del w:id="1"><w:r><w:delText>teks yang dihapus</w:delText></w:r></w:del>'
                .'<w:ins w:id="2"><w:r><w:t xml:space="preserve"> yang disisipkan</w:t></w:r></w:ins></w:p>')
            ->isi();

        $soal = $this->impor($guru, $post, $docx)->assertOk()->json('soal.0');

        $this->assertSame('ESSAY', $soal['tipe']);
        $this->assertSame('Pertanyaan asli yang disisipkan', $soal['teks']);
    }

    public function test_rumus_dan_kotak_teks_memberi_peringatan_tanpa_menggagalkan_impor(): void
    {
        [$guru, , $post] = $this->siapkan();

        $docx = DocxBuilder::baru()->meta(1, 'K', 'S', 'Essay')
            ->mentah('<w:p><w:r><w:t>Hitung nilai </w:t></w:r><m:oMath><m:r><m:t>x+1</m:t></m:r></m:oMath></w:p>')
            ->mentah('<w:p><w:r><w:drawing><wp:inline><wp:extent cx="1905000" cy="1270000"/><a:graphic><a:graphicData>'
                .'<w:txbxContent><w:p><w:r><w:t>Isi kotak</w:t></w:r></w:p></w:txbxContent></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>')
            ->isi();

        $json = $this->impor($guru, $post, $docx)->assertOk()->json();

        $this->assertSame('Hitung nilai x+1', $json['soal'][0]['teks']);
        $peringatan = implode(' | ', $json['peringatan']);
        $this->assertStringContainsString('Rumus', $peringatan);
        $this->assertStringContainsString('Kotak teks', $peringatan);
    }

    public function test_template_dari_sistem_dibaca_menjadi_tepat_enam_soal_contoh(): void
    {
        [$guru, , $post] = $this->siapkan();

        // Menjaga template yang dibagikan ke guru dan parser tetap sinkron.
        $json = $this->impor($guru, $post, (string) file_get_contents(resource_path('templates/template-soal.docx')))->assertOk()->json();

        $this->assertSame(['PG', 'PG', 'PG', 'PGK_MCMA', 'PGK_KATEGORI', 'ESSAY'], array_column($json['soal'], 'tipe'));
        $this->assertSame(['2', '1', '1', '0,2,4', '1,0,1', ''], array_column($json['soal'], 'kunci'));
        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($json['soal'], 'nomor'));
        $this->assertSame([0, 1, 0, 0, 0, 0], array_map(fn ($s) => count($s['gambar']), $json['soal']));
        // Contoh 3: keempat pilihan hanya berupa gambar.
        $this->assertSame(['', '', '', ''], $json['soal'][2]['opsi']);
        $this->assertCount(4, array_filter($json['soal'][2]['opsiGambar']));
        $this->assertSame([], $json['peringatan']);
    }

    public function test_dokumen_yang_bukan_word_atau_tidak_aman_ditolak_dengan_pesan_jelas(): void
    {
        [$guru, , $post] = $this->siapkan();

        $pesan = fn (string $isi, string $nama = 'x.docx') => $this->impor($guru, $post, $isi, $nama)
            ->assertStatus(422)->assertJsonValidationErrors('berkas')->json('errors.berkas.0');

        $this->assertStringContainsString('.doc lama', $pesan("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 64)));
        $this->assertStringContainsString('tidak dikenali', $pesan('ini hanya teks biasa'));

        $zipBiasa = $this->zip(['catatan.txt' => 'halo']);
        $this->assertStringContainsString('bukan dokumen Word', $pesan($zipBiasa));

        $xxe = $this->zip(['word/document.xml' => '<?xml version="1.0"?><!DOCTYPE d [<!ENTITY x SYSTEM "file:///etc/passwd">]><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>&x;</w:t></w:r></w:p></w:body></w:document>']);
        $this->assertStringContainsString('tidak aman', $pesan($xxe));

        // Teks dokumen yang membengkak setelah dibuka (bom zip) ditolak sebelum dibaca.
        $bom = $this->zip(['word/document.xml' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.str_repeat('a', 21 * 1024 * 1024).'</w:body></w:document>']);
        $this->assertStringContainsString('terlalu besar', $pesan($bom));

        // Format .doc lama ditolak langsung oleh aturan ekstensi.
        $this->impor($guru, $post, 'x', 'lama.doc')->assertStatus(422)->assertJsonValidationErrors('berkas');
    }

    public function test_hanya_guru_yang_boleh_mengunduh_template(): void
    {
        [$guru, $siswa] = $this->siapkan();

        $respons = $this->actingAs($guru)->get('/classroom/template-soal')->assertOk();
        $this->assertStringContainsString('Template Soal Ujian.docx', (string) $respons->headers->get('content-disposition'));
        $this->assertSame((string) file_get_contents(resource_path('templates/template-soal.docx')), $respons->streamedContent());

        $this->actingAs($siswa)->get('/classroom/template-soal')->assertForbidden();
    }

    /** @param array<string, string> $isi */
    private function zip(array $isi): string
    {
        $berkas = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($berkas, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($isi as $nama => $konten) {
            $zip->addFromString($nama, $konten);
        }
        $zip->close();
        $bytes = (string) file_get_contents($berkas);
        unlink($berkas);

        return $bytes;
    }
}
