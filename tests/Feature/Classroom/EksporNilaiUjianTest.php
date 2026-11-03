<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Jawaban;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Soal;
use App\Services\Classroom\UjianService;
use App\Support\Classroom\XlsxExport;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class EksporNilaiUjianTest extends TestCase
{
    private Akun $guru;

    private Post $pa;

    private Post $pb;

    /** @var array<string, Akun> */
    private array $siswa = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Satu ujian terbit ke dua kelas. Kelas A: dua soal PG @10 (total 20); kelas B: satu soal PG @50 (total 50).
        $this->guru = $this->akun('GURU', 'guru1');
        $ra = $this->rombel('X TKJ 1');
        $rb = $this->rombel('X TKJ 2');
        $a = $this->kelas($this->guru, $ra);
        $b = $this->kelas($this->guru, $rb);
        $this->actingAs($this->guru)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'UJIAN', 'judul' => 'Kuis Jaringan', 'durasiMenit' => 30, 'kelasTujuan' => [$b->id],
        ])->assertRedirect();
        $this->pa = Post::where('kelasId', $a->id)->firstOrFail();
        $this->pb = Post::where('kelasId', $b->id)->firstOrFail();

        foreach ([$this->pa, $this->pb] as $post) {
            $post->soal()->delete();
        }
        foreach ([10, 10] as $i => $poin) {
            $this->soal($this->pa, $i + 1, $poin);
        }
        $this->soal($this->pb, 1, 50);

        $this->siswa['a1'] = $this->siswa($ra, 'siswa-a1');
        $this->siswa['a1']->siswa->update(['nis' => '00123', 'nama' => 'Adi Aditya']);
        $this->siswa['a2'] = $this->siswa($ra, 'siswa-a2');
        $this->siswa['a2']->siswa->update(['nama' => 'Budi Belum']);
        $this->siswa['b1'] = $this->siswa($rb, 'siswa-b1');
        $this->siswa['b1']->siswa->update(['nama' => 'Cici Penuh']);
    }

    private function soal(Post $post, int $urut, float $poin, string $tipe = 'PG'): Soal
    {
        return Soal::create([
            'postId' => $post->id, 'urut' => $urut, 'tipe' => $tipe, 'teks' => "Soal {$urut}", 'poin' => $poin,
            'opsi' => $tipe === 'ESSAY' ? null : ['a', 'b', 'c'], 'kunci' => $tipe === 'ESSAY' ? null : '1',
        ]);
    }

    /** Siswa mengerjakan dan menyelesaikan: `$jawaban` = jawaban per urutan soal ('1' = benar untuk PG di atas). */
    private function kerjakan(Akun $akun, Post $post, array $jawaban): Percobaan
    {
        $percobaan = Percobaan::create([
            'postId' => $post->id, 'siswaId' => $akun->siswa->id,
            'mulaiAt' => now()->subMinutes(12), 'batasAt' => now()->addMinutes(18), 'status' => 'BERJALAN',
        ]);
        foreach ($post->soal()->get() as $i => $soal) {
            Jawaban::create(['percobaanId' => $percobaan->id, 'soalId' => $soal->id, 'jawaban' => $jawaban[$i] ?? null]);
        }

        return app(UjianService::class)->selesai($percobaan);
    }

    private function url(Post $post, string $query = ''): string
    {
        return "/classroom/post/{$post->id}/nilai/export".($query === '' ? '' : "?{$query}");
    }

    private function buka($respons): Spreadsheet
    {
        $berkas = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($berkas, $respons->streamedContent());
        $buku = IOFactory::createReader('Xlsx')->load($berkas);
        unlink($berkas);

        return $buku;
    }

    /** @return list<string> */
    private function baris(Spreadsheet $buku, string $sheet, int $baris, int $kolom): array
    {
        $hasil = [];
        for ($k = 1; $k <= $kolom; $k++) {
            $hasil[] = (string) $buku->getSheetByName($sheet)->getCell([$k, $baris])->getValue();
        }

        return $hasil;
    }

    public function test_ekspor_satu_kelas_memuat_rekap_nilai_nis_bernol_dan_skor_per_soal(): void
    {
        $this->kerjakan($this->siswa['a1'], $this->pa, ['1', '0']);

        $respons = $this->actingAs($this->guru)->get($this->url($this->pa))->assertOk();
        $this->assertStringContainsString('nilai-ujian-kuis-jaringan-x-tkj-1.xlsx', (string) $respons->headers->get('content-disposition'));
        $buku = $this->buka($respons);

        $this->assertSame(['Rekap', 'X TKJ 1'], $buku->getSheetNames());

        // Rekap: 2 siswa, 1 sudah mengerjakan (skor 10 dari 20 = nilai 50), 1 belum.
        $this->assertSame(['No', 'Kelas', 'Mata Pelajaran', 'Jumlah Siswa', 'Sudah Mengerjakan', 'Perlu Koreksi', 'Sedang Mengerjakan', 'Belum Mengerjakan', 'Rata-rata', 'Tertinggi', 'Terendah'], $this->baris($buku, 'Rekap', 5, 11));
        $this->assertSame(['1', 'X TKJ 1', 'Jaringan Dasar', '2', '1', '0', '0', '1', '50', '50', '50'], $this->baris($buku, 'Rekap', 6, 11));

        $sheet = $buku->getSheetByName('X TKJ 1');
        $this->assertSame(['No', 'NIS', 'Nama', 'Status', 'Skor (/20)', 'Nilai (0-100)', '1 (/10)', '2 (/10)'], $this->baris($buku, 'X TKJ 1', 5, 8));
        $this->assertSame(['1', '00123', 'Adi Aditya', 'Dinilai', '10', '50', '10', '0'], $this->baris($buku, 'X TKJ 1', 6, 8));
        // NIS tetap teks (nol di depan tidak hilang).
        $this->assertSame('s', $sheet->getCell('B6')->getDataType());
        $this->assertSame('00123', $sheet->getCell('B6')->getValue());
        // Siswa yang belum mengerjakan ikut tercatat tanpa skor dan nilai.
        $belum = $this->baris($buku, 'X TKJ 1', 7, 8);
        $this->assertSame(['2', 'Budi Belum', 'Belum mengerjakan'], [$belum[0], $belum[2], $belum[3]]);
        $this->assertSame(['', '', '', ''], array_slice($belum, 4));
    }

    public function test_ekspor_beberapa_kelas_memakai_total_poin_tiap_kelas_dan_baris_semua_kelas(): void
    {
        $this->kerjakan($this->siswa['a1'], $this->pa, ['1', '0']);
        $this->kerjakan($this->siswa['b1'], $this->pb, ['1']);

        $buku = $this->buka($this->actingAs($this->guru)->get($this->url($this->pa, "kelas[]={$this->pa->id}&kelas[]={$this->pb->id}"))->assertOk());

        $this->assertSame(['Rekap', 'X TKJ 1', 'X TKJ 2'], $buku->getSheetNames());

        // Kelas B memakai total 50: skor 50 = nilai 100.
        $this->assertSame(['No', 'NIS', 'Nama', 'Status', 'Skor (/50)', 'Nilai (0-100)'], array_slice($this->baris($buku, 'X TKJ 2', 5, 8), 0, 6));
        $this->assertSame(['1', $this->siswa['b1']->siswa->nis, 'Cici Penuh', 'Dinilai', '50', '100'], array_slice($this->baris($buku, 'X TKJ 2', 6, 8), 0, 6));

        $this->assertSame(['', 'Semua kelas', '', '3', '2', '0', '0', '1', '75', '100', '50'], $this->baris($buku, 'Rekap', 8, 11));
        $this->assertSame(['2', 'X TKJ 2', 'Jaringan Dasar', '1', '1', '0', '0', '0', '100', '100', '100'], $this->baris($buku, 'Rekap', 7, 11));
    }

    public function test_tanpa_skor_per_soal_dan_tanpa_siswa_yang_belum_mengerjakan(): void
    {
        $this->kerjakan($this->siswa['a1'], $this->pa, ['1', '1']);

        $buku = $this->buka($this->actingAs($this->guru)->get($this->url($this->pa, 'soal=0&belum=0'))->assertOk());

        $this->assertSame(['No', 'NIS', 'Nama', 'Status', 'Skor (/20)', 'Nilai (0-100)', 'Mulai', 'Selesai', 'Lama (menit)'], $this->baris($buku, 'X TKJ 1', 5, 9));
        // Hanya satu baris siswa (yang belum mengerjakan tidak ikut), tetapi rekap tetap menghitung semua siswa kelas.
        $this->assertSame('Adi Aditya', $this->baris($buku, 'X TKJ 1', 6, 3)[2]);
        $this->assertSame('', $this->baris($buku, 'X TKJ 1', 7, 3)[2]);
        $this->assertSame('2', $this->baris($buku, 'Rekap', 6, 4)[3]);
    }

    public function test_esai_yang_belum_dinilai_ditandai_perlu_koreksi_dan_skornya_dikosongkan(): void
    {
        $this->soal($this->pa, 3, 20, 'ESSAY');
        $this->kerjakan($this->siswa['a1'], $this->pa, ['1', '1', 'Jawaban esai saya']);

        $buku = $this->buka($this->actingAs($this->guru)->get($this->url($this->pa))->assertOk());

        $this->assertSame(['1', '00123', 'Adi Aditya', 'Perlu koreksi', '20', '50', '10', '10', ''], $this->baris($buku, 'X TKJ 1', 6, 9));
        $this->assertSame('1', $this->baris($buku, 'Rekap', 6, 6)[5]);
    }

    public function test_otorisasi_ekspor_nilai(): void
    {
        $url = $this->url($this->pa);

        $this->actingAs($this->siswa['a1'])->get($url)->assertForbidden();
        $this->actingAs($this->akun('GURU', 'guru-lain'))->get($url)->assertForbidden();
        $this->actingAs($this->akun('KEPSEK', 'kepsek1'))->get($url)->assertOk();
        $this->actingAs($this->akun('ADMIN', 'admin1'))->get($url)->assertOk();

        // Kelas di luar grup ujian ini (milik guru lain) ditolak, bukan diabaikan diam-diam.
        $lain = $this->akun('GURU', 'guru2');
        $luar = $this->buatPost($this->kelas($lain, $this->rombel('XI RPL 1', 'XI', 'RPL')), $lain, 'UJIAN', ['durasiMenit' => 30]);
        $this->actingAs($this->guru)->get($this->url($this->pa, "kelas[]={$luar->id}"))->assertForbidden();

        // Bukan ujian: 404.
        $tugas = $this->buatPost($this->pa->kelas, $this->guru, 'TUGAS');
        $this->actingAs($this->guru)->get($this->url($tugas))->assertNotFound();
    }

    public function test_guru_kelas_lain_dalam_grup_hanya_bisa_mengekspor_kelasnya_sendiri(): void
    {
        // Kelas B dipegang guru lain tetapi satu grup: guru A tidak boleh memilihnya, kepsek boleh.
        $guruB = $this->akun('GURU', 'guru-b');
        Kelas::whereKey($this->pb->kelasId)->update(['guruId' => $guruB->id]);

        $this->actingAs($this->guru)->get($this->url($this->pa, "kelas[]={$this->pb->id}"))->assertForbidden();
        $this->actingAs($this->akun('KEPSEK', 'kepsek1'))->get($this->url($this->pa, "kelas[]={$this->pb->id}"))->assertOk();
    }

    public function test_detail_ujian_guru_memuat_ringkasan_kelas_untuk_dialog_ekspor(): void
    {
        $this->kerjakan($this->siswa['a1'], $this->pa, ['1', '1']);

        $this->actingAs($this->guru)->get("/classroom/post/{$this->pa->id}")->assertInertia(fn (Assert $p) => $p
            ->has('kelasUjian', 2)
            ->where('kelasUjian.0.postId', $this->pa->id)
            ->where('kelasUjian.0.utama', true)
            ->where('kelasUjian.0.rombel', 'X TKJ 1')
            ->where('kelasUjian.0.jumlahSiswa', 2)
            ->where('kelasUjian.0.selesai', 1)
            ->where('kelasUjian.0.rata', 100)
            ->where('kelasUjian.1.postId', $this->pb->id)
            ->where('kelasUjian.1.utama', false)
            ->where('kelasUjian.1.selesai', 0)
            ->where('kelasUjian.1.rata', null));
    }

    public function test_nama_sheet_dirapikan_dan_dibuat_unik(): void
    {
        $dipakai = [];

        $this->assertSame('X TKJ 1', XlsxExport::namaSheet('X TKJ 1', $dipakai));
        // Bentrok tanpa membedakan huruf besar kecil (aturan Excel); huruf yang dikirim dipertahankan.
        $this->assertSame('x tkj 1 (2)', XlsxExport::namaSheet('x tkj 1', $dipakai));
        $this->assertSame('X TKJ 1 (3)', XlsxExport::namaSheet('X TKJ 1', $dipakai));
        $this->assertSame('A B C D E F', XlsxExport::namaSheet('A/B:C?D*E[F]', $dipakai));
        $this->assertSame('Sheet', XlsxExport::namaSheet('///', $dipakai));

        $panjang = XlsxExport::namaSheet(str_repeat('K', 40), $dipakai);
        $this->assertSame(31, mb_strlen($panjang));
        $kembar = XlsxExport::namaSheet(str_repeat('K', 40), $dipakai);
        $this->assertSame(31, mb_strlen($kembar));
        $this->assertNotSame($panjang, $kembar);
    }
}
