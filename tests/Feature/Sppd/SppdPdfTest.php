<?php

namespace Tests\Feature\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Models\Sppd\LogAudit;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use App\Services\Sppd\ArsipSppd;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\AbsensiTestCase;

class SppdPdfTest extends AbsensiTestCase
{
    private User $tu;

    private User $pemohon;

    private User $kepsek;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->siapkanTtdKepsek();
        $this->seed(RoleSeeder::class);

        $this->tu = User::factory()->create()->assignRole(RoleName::Tu->value);
        $this->pemohon = User::factory()->create(['name' => 'Doni Wijaya, S.H. Gr.', 'jabatan' => 'Guru'])->assignRole(RoleName::Pemohon->value);
        $this->kepsek = User::factory()->create(['name' => 'Teti Tresnawati, M.Pd'])->assignRole(RoleName::KepalaSekolah->value);
    }

    private function pengajuanTerbit(): PengajuanSppd
    {
        $rekan = User::factory()->create(['name' => 'Rio Falentino, S.Pd. Gr.', 'jabatan' => 'Guru'])->assignRole(RoleName::Pemohon->value);

        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', [
            'tujuan' => 'Universitas Singaperbangsa Karawang (UNSIKA)',
            'maksud' => 'MGMP Pendidikan Pancasila',
            'alat_angkutan' => 'Sepeda motor pribadi',
            'keterangan' => 'Membawa laptop sekolah',
            'pengikut_ids' => [$rekan->id],
            'tanggal_berangkat' => now()->addDays(2)->toDateString(),
            'jam_berangkat' => '07:30',
            'tanggal_kembali' => now()->addDays(3)->toDateString(),
            'jam_kembali' => '16:00',
            'undangan' => UploadedFile::fake()->create('undangan.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $pengajuan = $this->isiNomorSppd($this->pemohon->pengajuanSppds()->firstOrFail(), $this->tu);

        $this->actingAs($this->tu)
            ->patch(route('sppd.pengajuan.akun-anggaran', $pengajuan), ['akun_anggaran' => '5.2.02.01 Belanja Perjalanan Dinas'])
            ->assertSessionHasNoErrors();

        return $pengajuan->fresh(['pemohon', 'penyetuju', 'sppd', 'pengikuts']);
    }

    public function test_sppd_download_is_a_one_page_landscape_pdf(): void
    {
        $pengajuan = $this->pengajuanTerbit();

        $response = $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('/MediaBox [0.000 0.000 841.890 595.280]', $response->getContent());
        $this->assertStringContainsString('/Count 1', $response->getContent());
    }

    public function test_template_contains_all_fixed_labels_and_data(): void
    {
        $pengajuan = $this->pengajuanTerbit();

        $isi = [
            // Kop dan judul
            'YAYASAN AL-ULYA KARAWANG', 'SMK BUDI MULIA', 'Terakreditasi A, berdasarkan SK BAN-SM nomor 763/BAN-SM/SK/2019',
            'Jl. Ciherang Wadas, Kec. Telukjambe Timur Kab. Karawang 41361', 'Telepon (0267) 8458459 e-Mail smk_budimulia@teachers.org',
            'Lampiran', 'Surat Perjalanan Dinas', 'SURAT PERJALANAN DINAS (SPPD)', $pengajuan->sppd->nomor_sppd,
            // Label baris 1-12
            'Pengguna Anggaran/kuasa Pengguna Anggaran', 'Nama/NIP pegawai yang melaksanakan Perjalanan Dinas',
            'Pangkat dan Golongan', 'Tingkat Biaya Perjalanan Dinas', 'Disesuaikan', 'Maksud Perjalanan Dinas',
            'Alat angkutan yang digunakan', 'Tempat Berangkat', 'Tempat tujuan', 'Lama berangkat', 'Tanggal berangkat',
            'Tanggal harus kembali/tiba di tempat baru*)', 'Pengikut : Nama', 'Pembebanan Anggaran', 'Instansi', 'Akun',
            'Lumsum Biaya :', 'Keterangan lain-lain', '*)coret yang tidak perlu',
            // Isi data
            'Teti Tresnawati, M.Pd', 'Doni Wijaya, S.H. Gr.', 'MGMP Pendidikan Pancasila', 'Sepeda motor pribadi',
            'Universitas Singaperbangsa Karawang (UNSIKA)', 'SMK Budi Mulia Karawang', '07.30 WIB', '16.00 WIB', '2 Hari',
            'Rio Falentino, S.Pd. Gr.', '5.2.02.01 Belanja Perjalanan Dinas', 'Membawa laptop sekolah',
            // Tanda tangan dan sisi belakang
            'Dikeluarkan di', 'Kuasa Pengguna Anggaran', 'NIP. -,-', 'Berangkat dari', 'Tiba di', 'Pada Tanggal', 'Kepala',
            'Tiba kembali di', 'Pada tanggal',
            'Telah diperiksa dengan keterangan bahwa perjalanan tersebut atas perintahnya',
            'kepentingan jabatan dalam waktu sesingkat-singkatnya',
        ];

        $html = preg_replace('/\s+/', ' ', view('pdf.sppd', ['pengajuan' => $pengajuan])->render());

        foreach ($isi as $potongan) {
            $this->assertStringContainsString($potongan, $html, "template tidak memuat: {$potongan}");
        }
    }

    public function test_lumsum_biaya_is_row_11_below_pembebanan_anggaran_and_empty_before_any_pencairan(): void
    {
        $pengajuan = $this->pengajuanTerbit();

        $html = preg_replace('/\s+/', ' ', view('pdf.sppd', ['pengajuan' => $pengajuan])->render());

        // Seperti format Word: baris 11 di bawah Pembebanan Anggaran (10); selama bendahara belum mencairkan lumsum
        // nilainya kosong untuk ditulis tangan. Keterangan lain-lain bergeser menjadi baris 12.
        $this->assertMatchesRegularExpression(
            '#<td class="no">10</td> <td>Pembebanan Anggaran.*?</tr> <tr> <td class="no">11</td> <td>Lumsum Biaya :</td> <td>&nbsp;</td> </tr> <tr> <td class="no">12</td> <td>Keterangan lain-lain</td>#',
            $html,
        );
    }

    public function test_lumsum_biaya_prints_the_total_lumsum_paid_by_the_bendahara(): void
    {
        $pengajuan = $this->pengajuanTerbit();
        $bendahara = User::factory()->create()->assignRole(RoleName::Bendahara->value);

        foreach ([150_000, 50_000] as $jumlah) {
            $this->actingAs($bendahara)
                ->post(route('sppd.pengajuan.cairkan-lumsum', $pengajuan), ['jumlah' => $jumlah])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(['lumsum', 'lumsum'], $pengajuan->pencairans()->pluck('jenis')->map->value->all());
        $this->assertTrue(LogAudit::where('entitas_id', $pengajuan->id)->where('aksi', 'Pencairan Lumsum sebesar Rp150.000')->exists());

        $html = preg_replace('/\s+/', ' ', view('pdf.sppd', ['pengajuan' => $pengajuan->fresh(['pemohon', 'penyetuju', 'sppd', 'pengikuts'])])->render());

        $this->assertStringContainsString('<td class="no">11</td> <td>Lumsum Biaya :</td> <td>Rp200.000&nbsp;</td>', $html);
    }

    public function test_typical_content_uses_the_large_table_font(): void
    {
        $pengajuan = $this->pengajuanTerbit();

        $html = view('pdf.sppd', ['pengajuan' => $pengajuan])->render();

        // Isi biasa memakai 10pt (sama dengan format Word); font hanya turun bila isi panjang.
        $this->assertMatchesRegularExpression('/table\.isian td \{[^}]*font-size: 10pt;/', $html);
    }

    public function test_document_renders_without_pengikut(): void
    {
        $pengajuan = PengajuanSppd::factory()->create([
            'pemohon_id' => $this->pemohon->id,
            'status' => PengajuanStatus::SedangDitugaskan,
            'disetujui_oleh' => $this->kepsek->id,
            'disetujui_at' => now(),
        ]);
        $pengajuan->sppd()->create(['nomor_sppd' => '001/SMK-BM/IX/2026', 'diterbitkan_oleh' => $this->tu->id]);
        $pengajuan->load(['pemohon', 'penyetuju', 'sppd', 'pengikuts']);

        $pdf = Pdf::loadView('pdf.sppd', ['pengajuan' => $pengajuan])->setPaper('a4', 'landscape')->output();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('/Count 1', $pdf);
    }

    public function test_document_stays_on_one_page_with_the_longest_content(): void
    {
        $pengajuan = $this->pengajuanTerbit();

        $rekan = collect(['Faradilla Ferhat Arina Shandy, S.Kom., S.M., Gr.', 'Marsono, S.Kom., Gr.'])
            ->map(fn (string $nama) => User::factory()->create(['name' => $nama, 'jabatan' => 'Guru Produktif Rekayasa Perangkat Lunak'])->assignRole(RoleName::Pemohon->value));
        $pengajuan->pengikuts()->attach($rekan->pluck('id'));
        $pengajuan->forceFill([
            'alat_angkutan' => str_repeat('Kendaraan ', 4),
            'keterangan' => substr(str_repeat('Membawa peralatan ', 4), 0, 70),
        ])->save();
        // Nomor sepanjang batas (50 karakter) dengan huruf lebar, karena TU mengetiknya bebas.
        $nomorTerpanjang = '123/SMK-BM/XII/2026/'.str_repeat('W', 30);
        $this->assertSame(50, strlen($nomorTerpanjang));
        $pengajuan->sppd->forceFill(['nomor_sppd' => $nomorTerpanjang, 'akun_anggaran' => str_repeat('5.2.02.01 Belanja ', 5)])->save();
        // Lumsum terbesar yang muat di kolom jumlah (decimal 15,2) tetap satu baris.
        $pengajuan->pencairans()->create([
            'jenis' => 'lumsum', 'jumlah' => 9_999_999_999_999, 'tanggal' => now(), 'dicairkan_oleh' => $this->tu->id,
        ]);
        $pengajuan->load(['pemohon', 'penyetuju', 'sppd', 'pengikuts']);

        $this->assertSame(3, $pengajuan->pengikuts->count());

        $satuHalaman = fn () => Pdf::loadView('pdf.sppd', ['pengajuan' => $pengajuan])->setPaper('a4', 'landscape')->output();

        // Template menyesuaikan ukuran font, jadi tetap satu halaman bahkan dengan maksud sepanjang batas (300 karakter).
        $pengajuan->maksud = substr(str_repeat('Mengikuti kegiatan MGMP ', 20), 0, 300);
        $this->assertStringContainsString('/Count 1', $satuHalaman(), 'harus satu halaman');

        // Kasus paling ekstrem: tujuan sepanjang batas (255 karakter) bersama semua isian panjang lainnya.
        $pengajuan->tujuan = substr(str_repeat('Universitas Singaperbangsa Karawang ', 8), 0, 255);
        $this->assertStringContainsString('/Count 1', $satuHalaman(), 'harus satu halaman pada isi ekstrem');

        // Arsip bertanda tangan memakai ruang kosong yang sama, jadi tetap satu halaman.
        $arsip = Pdf::loadView('pdf.sppd', [
            'pengajuan' => $pengajuan,
            'ttdKepsek' => str_replace('\\', '/', Storage::disk('local')->path(ArsipSppd::BERKAS_TTD)),
        ])->setPaper('a4', 'landscape')->output();
        $this->assertStringContainsString('/Count 1', $arsip, 'arsip bertanda tangan harus satu halaman');
        $this->assertStringContainsString('/DCTDecode', $arsip);
    }

    public function test_ttd_kepsek_is_printed_at_the_three_signature_spots_only_when_given(): void
    {
        $pengajuan = $this->pengajuanTerbit();

        $kosong = view('pdf.sppd', ['pengajuan' => $pengajuan])->render();
        $bertandaTangan = view('pdf.sppd', ['pengajuan' => $pengajuan, 'ttdKepsek' => '/jalur/ttd-kepsek.jpeg'])->render();

        $this->assertSame(1, substr_count($kosong, '<img'), 'hanya logo sekolah bila tanpa tanda tangan');
        $this->assertSame(4, substr_count($bertandaTangan, '<img'), 'logo + tiga tanda tangan Kepala Sekolah');
        $this->assertSame(3, substr_count($bertandaTangan, '/jalur/ttd-kepsek.jpeg'));
    }

    public function test_template_setting_page_is_gone(): void
    {
        $this->actingAs($this->tu)->get('/sppd/pengaturan/template-sppd')->assertNotFound();
    }
}
