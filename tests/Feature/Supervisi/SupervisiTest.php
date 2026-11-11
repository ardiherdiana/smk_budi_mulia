<?php

namespace Tests\Feature\Supervisi;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Mapel;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\TahunAjaran;
use App\Models\Supervisi\Supervisi;
use App\Models\User;
use App\Notifications\Supervisi\SupervisiNotifikasi;
use App\Services\Supervisi\JadwalSupervisi;
use App\Services\Supervisi\PenilaianSupervisi;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Modul Supervisi Guru dengan format "Instrumen Observasi Pembelajaran Mendalam": jadwal, lembar 14 butir, Jumlah dan
 * Total Score, notifikasi, PDF, dan rekap.
 */
class SupervisiTest extends TestCase
{
    /** Skor per kategori untuk lembar contoh: 2x4 + 4x3 + 6x3 + 2x4 = 46, Total = 46 / 56 x 100 = 82,14. */
    private const SKOR_CONTOH = ['Keselarasan' => 4, 'Kerangka Pembelajaran' => 3, 'Langkah Pembelajaran' => 3, 'Asesmen' => 4];

    private TahunAjaran $tahun;

    private Akun $kepsek;

    private Akun $guru;

    private Akun $guruLain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tahun = TahunAjaran::create(['nama' => '2026/2027', 'semester' => 'GANJIL', 'aktif' => true]);
        $this->kepsek = $this->akun('KEPSEK', 'kepsek', 'Ibu Kepala');
        $this->guru = $this->akun('GURU', 'guru1', 'Budi Guru');
        $this->guruLain = $this->akun('GURU', 'guru2', 'Sari Senior');
    }

    // ---- Instrumen ---------------------------------------------------------------------------------------------

    public function test_instrumen_berisi_14_butir_dokumen_dalam_4_kategori(): void
    {
        $butir = JadwalSupervisi::butir();

        $this->assertCount(14, $butir);
        $this->assertSame(
            ['Keselarasan' => 2, 'Kerangka Pembelajaran' => 4, 'Langkah Pembelajaran' => 6, 'Asesmen' => 2],
            collect($butir)->countBy('kategori')->all(),
        );
        $this->assertSame('Langkah perencanaan memfasilitasi tindakan saling MEMULIAKAN.', $butir[9]['uraian']);
        $this->assertSame([1 => 'Hampir tidak ada', 2 => 'Sedikit', 3 => 'Cukup', 4 => 'Memadai'], config('supervisi.skor_label'));
    }

    public function test_total_score_sama_dengan_jumlah_dibagi_56_kali_100(): void
    {
        $this->assertSame(100.0, PenilaianSupervisi::total(56, 14));
        $this->assertSame(25.0, PenilaianSupervisi::total(14, 14));
        $this->assertSame(82.14, PenilaianSupervisi::total(46, 14));
    }

    // ---- Jadwal ------------------------------------------------------------------------------------------------

    public function test_kepsek_membuat_jadwal_dan_lembar_disiapkan_dari_butir_dokumen(): void
    {
        $this->actingAs($this->kepsek)->post('/supervisi/jadwal', $this->dataJadwal())
            ->assertRedirect()->assertSessionHasNoErrors();

        $supervisi = Supervisi::sole();
        $this->assertSame(Supervisi::DIJADWALKAN, $supervisi->status);
        $this->assertSame($this->kepsek->id, $supervisi->dibuatOlehId);
        $this->assertSame(
            JadwalSupervisi::butir(),
            $supervisi->skor()->get()->map(fn ($s) => ['kategori' => $s->kategori, 'uraian' => $s->uraian])->all(),
        );
        $this->assertSame(1, $this->notifikasiUntuk($this->guru));
        $this->assertSame(1, $this->notifikasiUntuk($this->guruLain));
        $this->assertSame(0, $this->notifikasiUntuk($this->kepsek));
    }

    public function test_supervisor_tidak_boleh_guru_itu_sendiri_dan_guru_harus_aktif(): void
    {
        $this->actingAs($this->kepsek)->post('/supervisi/jadwal', $this->dataJadwal(['supervisorId' => $this->guru->id]))
            ->assertSessionHasErrors('supervisorId');

        $this->guru->forceFill(['is_active' => false])->save();
        $this->post('/supervisi/jadwal', $this->dataJadwal())->assertSessionHasErrors('guruId');

        // Kepsek tidak bisa disupervisi lewat modul ini (hanya peran GURU).
        $this->post('/supervisi/jadwal', $this->dataJadwal(['guruId' => $this->kepsek->id]))->assertSessionHasErrors('guruId');
        $this->assertSame(0, Supervisi::count());
    }

    public function test_hanya_kepsek_dan_admin_yang_mengelola_jadwal_dan_rekap(): void
    {
        $this->actingAs($this->guru);
        $this->get('/supervisi/jadwal')->assertForbidden();
        $this->post('/supervisi/jadwal', $this->dataJadwal())->assertForbidden();
        $this->get('/supervisi/rekap')->assertForbidden();
        $this->get('/supervisi/rekap/export')->assertForbidden();

        $this->actingAsAdmin();
        $this->get('/supervisi/jadwal')->assertOk();
        $this->post('/supervisi/jadwal', $this->dataJadwal())->assertSessionHasNoErrors();
        $this->assertNull(Supervisi::sole()->dibuatOlehId);
    }

    public function test_halaman_instrumen_refleksi_dan_tandai_dibaca_sudah_tidak_ada(): void
    {
        $supervisi = $this->selesai();

        $this->actingAs($this->kepsek)->get('/supervisi/instrumen')->assertNotFound();
        $this->actingAs($this->guru)->put("/supervisi/{$supervisi->id}/refleksi", ['refleksiGuru' => 'x'])->assertNotFound();
        $this->post("/supervisi/{$supervisi->id}/dibaca")->assertNotFound();
    }

    public function test_staf_siswa_dan_akun_portal_tidak_bisa_membuka_modul(): void
    {
        $this->actingAs($this->akun('STAF', 'tu1'))->get('/supervisi/dashboard')->assertForbidden();
        $this->actingAs($this->akun('SISWA', '26270001'))->get('/supervisi/dashboard')->assertForbidden();
        $this->actingAsStudent();
        $this->get('/supervisi/dashboard')->assertForbidden();
    }

    public function test_kartu_menu_utama_untuk_admin_kepsek_dan_guru_saja(): void
    {
        $punyaKartu = fn (Akun|User $akun) => collect($this->actingAs($akun)->get('/menu-utama')->viewData('page')['props']['modul'] ?? [])
            ->contains('key', 'supervisi');

        $this->assertTrue($punyaKartu($this->kepsek));
        $this->assertTrue($punyaKartu($this->guru));
        $this->assertTrue($punyaKartu($this->admin()));
        $this->assertFalse($punyaKartu($this->akun('STAF', 'tu1')));
    }

    public function test_ubah_batal_dan_hapus_jadwal(): void
    {
        $supervisi = $this->jadwal();

        $this->actingAs($this->kepsek)->patch("/supervisi/jadwal/{$supervisi->id}", $this->dataJadwal(['tanggal' => '2026-11-20']))
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-11-20', $supervisi->fresh()->tanggal->toDateString());
        $this->assertSame(2, $this->notifikasiUntuk($this->guru));

        $this->post("/supervisi/jadwal/{$supervisi->id}/batal")->assertRedirect();
        $this->assertSame(Supervisi::DIBATALKAN, $supervisi->fresh()->status);
        $this->patch("/supervisi/jadwal/{$supervisi->id}", $this->dataJadwal())->assertStatus(409);

        $this->delete("/supervisi/jadwal/{$supervisi->id}")->assertRedirect();
        $this->assertModelMissing($supervisi);
        $this->assertSame(0, $this->notifikasiUntuk($this->guru), 'notifikasi yang menautkan jadwal terhapus ikut dibersihkan');
    }

    // ---- Lembar & penilaian ------------------------------------------------------------------------------------

    public function test_supervisor_menyelesaikan_lembar_dan_jumlah_serta_total_score_dihitung(): void
    {
        $supervisi = $this->jadwal();

        $this->actingAs($this->guruLain)->put("/supervisi/{$supervisi->id}/lembar", $this->isian($supervisi, ['Keselarasan' => 4]))
            ->assertSessionHasNoErrors();
        $draf = $supervisi->fresh();
        $this->assertSame(Supervisi::DIJADWALKAN, $draf->status);
        $this->assertSame('Langkah pembelajaran runtut', $draf->kelebihan);
        $this->assertNull($draf->jumlahSkor, 'skor baru dihitung saat diselesaikan');

        $this->post("/supervisi/{$supervisi->id}/selesai", $this->isian($supervisi, self::SKOR_CONTOH))
            ->assertSessionHasNoErrors();

        $supervisi->refresh();
        $this->assertSame(Supervisi::SELESAI, $supervisi->status);
        $this->assertSame(46, $supervisi->jumlahSkor);
        $this->assertSame(82.14, $supervisi->totalSkor);
        $this->assertSame('Asesmen diagnostik belum ada', $supervisi->halDitingkatkan);
        $this->assertSame('Tambahkan asesmen awal', $supervisi->rekomendasi);
        $this->assertNotNull($supervisi->selesaiAt);
        $this->assertSame(2, $this->notifikasiUntuk($this->guru), 'dijadwalkan + selesai');
    }

    public function test_selesaikan_ditolak_bila_masih_ada_butir_tanpa_skor(): void
    {
        $supervisi = $this->jadwal();

        $this->actingAs($this->guruLain)->post("/supervisi/{$supervisi->id}/selesai", $this->isian($supervisi, ['Keselarasan' => 3]))
            ->assertSessionHasErrors('skor');
        $this->assertSame(Supervisi::DIJADWALKAN, $supervisi->fresh()->status);

        $this->put("/supervisi/{$supervisi->id}/lembar", $this->isian($supervisi, ['Keselarasan' => 5]))->assertSessionHasErrors('skor.0.skor');
    }

    public function test_guru_lain_yang_bukan_supervisor_tidak_bisa_membuka_atau_mengisi(): void
    {
        $supervisi = $this->jadwal();
        $orangLain = $this->akun('GURU', 'guru3');

        $this->actingAs($orangLain)->get("/supervisi/{$supervisi->id}")->assertForbidden();
        $this->put("/supervisi/{$supervisi->id}/lembar", $this->isian($supervisi))->assertForbidden();
        $this->get("/supervisi/{$supervisi->id}/lembar.pdf")->assertForbidden();

        // Guru yang disupervisi tidak mengisi lembarnya sendiri.
        $this->actingAs($this->guru)->put("/supervisi/{$supervisi->id}/lembar", $this->isian($supervisi))->assertForbidden();
    }

    public function test_lembar_terkunci_setelah_selesai_dan_kepsek_bisa_membuka_kunci(): void
    {
        $supervisi = $this->selesai();

        $this->actingAs($this->guruLain)->put("/supervisi/{$supervisi->id}/lembar", $this->isian($supervisi))->assertStatus(409);
        $this->post("/supervisi/{$supervisi->id}/buka-kunci")->assertForbidden();

        $this->actingAs($this->kepsek)->post("/supervisi/{$supervisi->id}/buka-kunci")->assertRedirect();
        $supervisi->refresh();
        $this->assertSame(Supervisi::DIJADWALKAN, $supervisi->status);
        $this->assertNull($supervisi->jumlahSkor);
        $this->assertNull($supervisi->totalSkor);
        $this->assertSame(4, $supervisi->skor()->first()->skor, 'skor lama tetap ada untuk dikoreksi');
    }

    public function test_guru_hanya_melihat_jadwal_sebelum_selesai_lalu_penilaian_setelahnya(): void
    {
        $supervisi = $this->jadwal();
        $this->actingAs($this->guruLain)->put("/supervisi/{$supervisi->id}/lembar", $this->isian($supervisi, ['Keselarasan' => 2]));

        $this->actingAs($this->guru)->get("/supervisi/{$supervisi->id}")->assertInertia(fn (Assert $page) => $page
            ->component('supervisi/lembar-page')
            ->where('supervisi.penilaian', null)
            ->where('supervisi.totalSkor', null)
            ->where('boleh.isi', false)
            ->where('boleh.pdf', false)
            ->where('pemilik', true));
        $this->get("/supervisi/{$supervisi->id}/lembar.pdf")->assertForbidden();

        $this->selesai($supervisi);

        $this->actingAs($this->guru)->get("/supervisi/{$supervisi->id}")->assertInertia(fn (Assert $page) => $page
            ->where('supervisi.jumlahSkor', 46)
            ->where('supervisi.totalSkor', 82.14)
            ->where('supervisi.penilaian.kelebihan', 'Langkah pembelajaran runtut')
            ->has('supervisi.penilaian.skor', 14)
            ->where('supervisi.penilaian.skor.0.kategori', 'Keselarasan')
            ->where('skala.label.4', 'Memadai'));
    }

    public function test_butir_lembar_mengikuti_config_sampai_penilaian_dimulai_lalu_dibekukan(): void
    {
        $supervisi = $this->jadwal();
        $asli = config('supervisi.instrumen');
        $ubahUraianPertama = function (string $teks) use ($asli) {
            $baru = $asli;
            $baru['Keselarasan'][0] = $teks;
            config(['supervisi.instrumen' => $baru]);
        };

        // Belum ada skor: perubahan teks instrumen ikut terbawa saat lembar dibuka.
        $ubahUraianPertama('Uraian baru');
        $this->actingAs($this->guruLain)->get("/supervisi/{$supervisi->id}")->assertOk();
        $this->assertSame('Uraian baru', $supervisi->skor()->first()->uraian);

        // Setelah ada skor: salinan uraian dibekukan.
        $this->put("/supervisi/{$supervisi->id}/lembar", $this->isian($supervisi, ['Keselarasan' => 3]));
        $ubahUraianPertama('Diubah lagi');
        $this->get("/supervisi/{$supervisi->id}")->assertOk();
        $this->assertSame('Uraian baru', $supervisi->skor()->first()->uraian);
        $this->assertSame(14, $supervisi->skor()->count());
    }

    // ---- Laporan, notifikasi, akun -----------------------------------------------------------------------------

    public function test_pdf_lembar_mengikuti_format_dokumen_dan_ekspor_rekap(): void
    {
        $supervisi = $this->selesai();

        $this->actingAs($this->guru)->get("/supervisi/{$supervisi->id}/lembar.pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        $html = view('pdf.supervisi', [
            'supervisi' => $supervisi->load(['guru', 'supervisor', 'mapel', 'rombel', 'skor']),
            'skala' => config('supervisi.skor_label'),
        ])->render();
        foreach ([
            'INSTRUMEN OBSERVASI PEMBELAJARAN MENDALAM', 'Penyusun Perencanaan', 'Pemberi Umpan Balik', 'Tanggal Supervisi',
            'Petunjuk pengisian', '1 : Hampir tidak ada', '4 : Memadai', 'Kerangka Pembelajaran', 'JUMLAH SCORE',
            'Kelebihan Perencanaan', 'Hal yang perlu ditingkatkan', 'Rekomendasi Perbaikan', 'TOTAL SCORE', '82,14',
        ] as $teks) {
            $this->assertStringContainsString($teks, $html);
        }
        $this->assertStringNotContainsString('Refleksi', $html);
        $this->assertStringNotContainsString('YAYASAN', $html, 'tanpa kop sekolah, sama dengan dokumen');

        // Penilai bisa mencetak lembar kosong sebelum supervisi selesai.
        $jadwal = $this->jadwal();
        $this->actingAs($this->guruLain)->get("/supervisi/{$jadwal->id}/lembar.pdf")->assertOk();

        $this->actingAs($this->kepsek)->get('/supervisi/rekap/export')
            ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_rekap_dan_dashboard_menghitung_guru_yang_belum_disupervisi(): void
    {
        $this->selesai();
        $this->akun('GURU', 'guru4', 'Ahmad Belum');

        $this->actingAs($this->kepsek)->get('/supervisi/rekap')->assertInertia(fn (Assert $page) => $page
            ->component('supervisi/rekap-page')
            ->has('baris', 3)
            ->where('baris.0.guru', 'Ahmad Belum')
            ->where('baris.0.status', null)
            ->where('baris.1.jumlahSkor', 46)
            ->where('baris.1.totalSkor', 82.14)
            ->missing('baris.1.predikat'));

        $this->get('/supervisi/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('ringkasan.jumlahGuru', 3)
            ->where('ringkasan.sudah', 1)
            ->where('ringkasan.belum', 2)
            ->missing('ringkasan.predikat')
            ->has('ringkasan.belumDijadwalkan', 2));
    }

    public function test_notifikasi_supervisi_muncul_di_lonceng_dan_membuka_lembar(): void
    {
        $supervisi = $this->jadwal();
        $notifikasi = User::find($this->guru->id)->notifications()->sole();

        $this->actingAs($this->guru)->get('/menu-utama')->assertInertia(fn (Assert $page) => $page
            ->where('notifikasi.items.0.sumber', 'supervisi')
            ->where('notifikasi.items.0.tipe', 'SUPERVISI')
            ->where('notifikasi.belumDibaca', 1));

        $this->post("/notifikasi/sppd/{$notifikasi->id}/baca")->assertNotFound();
        $this->post("/notifikasi/supervisi/{$notifikasi->id}/baca")->assertRedirect("/supervisi/{$supervisi->id}");
        $this->assertNotNull($notifikasi->fresh()->read_at);
    }

    public function test_akun_dengan_riwayat_supervisi_ditolak_dihapus(): void
    {
        $this->jadwal();

        $this->assertContains('Supervisi', User::find($this->guru->id)->modulTerpakai());
        $this->assertContains('Supervisi', User::find($this->guruLain->id)->modulTerpakai());
        $this->assertNotContains('Supervisi', User::find($this->kepsek->id)->modulTerpakai());
    }

    public function test_daftar_saya_dan_tugas(): void
    {
        $supervisi = $this->jadwal();

        $this->actingAs($this->guru)->get('/supervisi/saya')->assertInertia(fn (Assert $page) => $page
            ->component('supervisi/daftar-page')->where('mode', 'saya')->where('list.0.id', $supervisi->id));
        $this->actingAs($this->guruLain)->get('/supervisi/tugas')->assertInertia(fn (Assert $page) => $page
            ->where('mode', 'tugas')->has('list', 1)->where('supervisiMenu.tugas', true));
        $this->actingAs($this->guru)->get('/supervisi/tugas')->assertInertia(fn (Assert $page) => $page
            ->has('list', 0)->where('supervisiMenu.tugas', false)->where('supervisiMenu.disupervisi', true));
    }

    // ---- Bantuan -----------------------------------------------------------------------------------------------

    /** @param  array<string, mixed>  $ubah */
    private function dataJadwal(array $ubah = []): array
    {
        return $ubah + [
            'tahunAjaranId' => $this->tahun->id,
            'guruId' => $this->guru->id,
            'supervisorId' => $this->guruLain->id,
            'mapelId' => Mapel::firstOrCreate(['nama' => 'Jaringan Dasar'])->id,
            'rombelId' => Rombel::firstOrCreate(['nama' => 'X TKJ 1'], ['tingkat' => 'X', 'jurusan' => 'TKJ'])->id,
            'tanggal' => '2026-11-10',
        ];
    }

    private function jadwal(): Supervisi
    {
        $this->actingAs($this->kepsek)->post('/supervisi/jadwal', $this->dataJadwal())->assertSessionHasNoErrors();

        return Supervisi::latest('createdAt')->first();
    }

    private function selesai(?Supervisi $supervisi = null): Supervisi
    {
        $supervisi ??= $this->jadwal();
        $this->actingAs($this->guruLain)->post("/supervisi/{$supervisi->id}/selesai", $this->isian($supervisi, self::SKOR_CONTOH))
            ->assertSessionHasNoErrors();

        return $supervisi->fresh();
    }

    /** @param  array<string, int>  $skorPerKategori  kategori tanpa entri dikirim kosong */
    private function isian(Supervisi $supervisi, array $skorPerKategori = []): array
    {
        return [
            'kelebihan' => 'Langkah pembelajaran runtut',
            'halDitingkatkan' => 'Asesmen diagnostik belum ada',
            'rekomendasi' => 'Tambahkan asesmen awal',
            'skor' => $supervisi->skor()->get()->map(fn ($s) => [
                'id' => $s->id,
                'skor' => $skorPerKategori[$s->kategori] ?? null,
                'catatan' => null,
            ])->all(),
        ];
    }

    private function notifikasiUntuk(Akun $akun): int
    {
        return User::find($akun->id)->notifications()->where('type', SupervisiNotifikasi::class)->count();
    }
}
