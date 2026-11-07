<?php

namespace Tests\Feature\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Models\Sppd\LogAudit;
use App\Models\Sppd\PengajuanSppd;
use App\Models\Sppd\Sppd;
use App\Models\User;
use App\Services\Sppd\ArsipSppd;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\AbsensiTestCase;

class PengajuanSppdWorkflowTest extends AbsensiTestCase
{
    private User $pemohon;

    private User $kepsek;

    private User $tu;

    private User $bendahara;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->siapkanTtdKepsek();
        $this->seed(RoleSeeder::class);

        $this->pemohon = User::factory()->create()->assignRole(RoleName::Pemohon->value);
        $this->kepsek = User::factory()->create()->assignRole(RoleName::KepalaSekolah->value);
        $this->tu = User::factory()->create()->assignRole(RoleName::Tu->value);
        $this->bendahara = User::factory()->create()->assignRole(RoleName::Bendahara->value);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pengajuanPayload(array $overrides = []): array
    {
        return array_merge([
            'tujuan' => 'Dinas Pendidikan Kabupaten Karawang',
            'maksud' => 'Mengikuti pelatihan kurikulum',
            'alat_angkutan' => 'Sepeda motor pribadi',
            'tanggal_berangkat' => now()->addDays(3)->toDateString(),
            'jam_berangkat' => '07:30',
            'tanggal_kembali' => now()->addDays(5)->toDateString(),
            'jam_kembali' => '16:00',
            'undangan' => UploadedFile::fake()->create('undangan.pdf', 100, 'application/pdf'),
        ], $overrides);
    }

    /** Pemohon mengajukan SPPD; hasilnya menunggu keputusan Kepala Sekolah. */
    private function pengajuanDiajukan(): PengajuanSppd
    {
        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->pengajuanPayload())->assertSessionHasNoErrors();

        return $this->pemohon->pengajuanSppds()->latest('id')->firstOrFail();
    }

    /** Pengajuan yang sudah disetujui Kepala Sekolah lewat aplikasi, sehingga menunggu nomor dari TU. */
    private function pengajuanDisetujui(): PengajuanSppd
    {
        $pengajuan = $this->pengajuanDiajukan();

        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.approve', $pengajuan))->assertSessionHasNoErrors();

        return $pengajuan->fresh();
    }

    /** Pengajuan yang ditolak Kepala Sekolah dengan alasan "Tujuan kurang jelas". */
    private function pengajuanDitolak(): PengajuanSppd
    {
        $pengajuan = $this->pengajuanDiajukan();

        $this->actingAs($this->kepsek)
            ->post(route('sppd.pengajuan.reject', $pengajuan), ['catatan' => 'Tujuan kurang jelas'])
            ->assertSessionHasNoErrors();

        return $pengajuan->fresh();
    }

    public function test_pengajuan_tanpa_surat_undangan_diterima(): void
    {
        $payload = $this->pengajuanPayload();
        unset($payload['undangan']);

        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $payload)->assertSessionHasNoErrors();

        $pengajuan = $this->pemohon->pengajuanSppds()->firstOrFail();
        $this->assertNull($pengajuan->undangan_path);
        $this->assertSame(PengajuanStatus::DiajukanKeKepsek, $pengajuan->status);
        $this->assertNull($pengajuan->sppd);
    }

    public function test_berangkat_dan_kembali_di_hari_yang_sama_diterima(): void
    {
        $tanggal = now()->addDays(3)->toDateString();

        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->pengajuanPayload([
            'tanggal_berangkat' => $tanggal,
            'tanggal_kembali' => $tanggal,
            'jam_berangkat' => '07:30',
            'jam_kembali' => '15:00',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, $this->pemohon->pengajuanSppds()->count());
    }

    public function test_kembali_di_hari_yang_sama_harus_setelah_jam_berangkat(): void
    {
        $tanggal = now()->addDays(3)->toDateString();

        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->pengajuanPayload([
            'tanggal_berangkat' => $tanggal,
            'tanggal_kembali' => $tanggal,
            'jam_berangkat' => '15:00',
            'jam_kembali' => '07:30',
        ]))->assertSessionHasErrors('jam_kembali');

        $this->assertSame(0, $this->pemohon->pengajuanSppds()->count());
    }

    public function test_full_sppd_lifecycle_from_pengajuan_to_selesai(): void
    {
        // 1. Pemohon mengajukan SPPD dengan tujuan diketik sendiri beserta jam berangkat/kembali. SPPD diajukan tanpa
        //    nomor dan menunggu keputusan Kepala Sekolah.
        $response = $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->pengajuanPayload());

        $pengajuan = $this->pemohon->pengajuanSppds()->firstOrFail();
        $response->assertRedirect(route('sppd.pengajuan.show', $pengajuan));
        $this->assertSame('Dinas Pendidikan Kabupaten Karawang', $pengajuan->tujuan);
        $this->assertSame('07:30', substr($pengajuan->jam_berangkat, 0, 5));
        $this->assertSame('16:00', substr($pengajuan->jam_kembali, 0, 5));
        $this->assertSame(PengajuanStatus::DiajukanKeKepsek, $pengajuan->status);
        $this->assertNull($pengajuan->sppd, 'nomor tidak dibangkitkan otomatis');
        $this->assertNull($pengajuan->disetujui_oleh);

        // 2. Kepala Sekolah menyetujui di aplikasi; pengajuan lanjut ke TU dan penyetujunya tercatat.
        $this->actingAs($this->kepsek)
            ->post(route('sppd.pengajuan.approve', $pengajuan), ['catatan' => 'Silakan berangkat'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
        $pengajuan = $pengajuan->fresh();
        $this->assertSame(PengajuanStatus::MenungguNomor, $pengajuan->status);
        $this->assertSame($this->kepsek->id, $pengajuan->disetujui_oleh);
        $this->assertNotNull($pengajuan->disetujui_at);
        $this->assertSame('Silakan berangkat', $pengajuan->catatan_kepsek);

        // 3. Selama menunggu nomor, SPPD belum bisa diunduh siapa pun.
        $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan))->assertNotFound();
        $this->actingAs($this->tu)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan))->assertNotFound();

        // 4. TU mengetik nomor surat secara manual: SPPD terbit dan status berjalan.
        $this->actingAs($this->tu)
            ->patch(route('sppd.pengajuan.nomor', $pengajuan), ['nomor_sppd' => '001/SMK-BM/X/2026'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
        $pengajuan = $pengajuan->fresh(['pemohon', 'penyetuju', 'sppd']);
        $this->assertSame(PengajuanStatus::SedangDitugaskan, $pengajuan->status);
        $this->assertSame('001/SMK-BM/X/2026', $pengajuan->sppd->nomor_sppd);
        $this->assertSame($this->tu->id, $pengajuan->sppd->diterbitkan_oleh);

        // PDF SPPD memuat nomor dan nama Kepala Sekolah (dari akun berperan Kepala Sekolah), tanpa gambar tanda tangan.
        $html = view('pdf.sppd', ['pengajuan' => $pengajuan])->render();
        $this->assertStringContainsString('001/SMK-BM/X/2026', $html);
        $this->assertStringContainsString($this->kepsek->name, $html);
        $this->assertSame(1, substr_count($html, '<img'), 'hanya logo sekolah yang boleh berupa gambar');

        // Pemohon sekarang bisa mengunduh SPPD sebagai PDF.
        $pdfResponse = $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan));
        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('sppd-001-SMK-BM-', $pdfResponse->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('/DCTDecode', $pdfResponse->getContent(), 'sebelum diarsipkan kolom tanda tangan kosong untuk pulpen');
        Storage::disk('local')->assertMissing("arsip-sppd/{$pengajuan->id}.pdf");

        // 5. Bendahara mencairkan lumsum.
        $this->actingAs($this->bendahara)
            ->post(route('sppd.pengajuan.cairkan-lumsum', $pengajuan), ['jumlah' => 500_000])
            ->assertRedirect();
        $this->assertCount(1, $pengajuan->fresh()->pencairans);

        // 6. TU menandai selesai: SPPD diarsipkan sebagai PDF yang sudah berisi tanda tangan Kepala Sekolah.
        $this->actingAs($this->tu)
            ->post(route('sppd.pengajuan.selesai', $pengajuan))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame(PengajuanStatus::Selesai, $pengajuan->fresh()->status);
        Storage::disk('local')->assertExists("arsip-sppd/{$pengajuan->id}.pdf");

        // 7. Sesudahnya pemohon mengunduh arsip bertanda tangan itu.
        $arsip = $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan));
        $arsip->assertOk();
        $this->assertStringContainsString('sppd-001-SMK-BM-', $arsip->headers->get('Content-Disposition'));
        $isiArsip = $arsip->streamedContent();
        $this->assertStringStartsWith('%PDF', $isiArsip);
        $this->assertStringContainsString('/DCTDecode', $isiArsip, 'gambar tanda tangan JPEG harus tertanam');
        $this->assertStringContainsString('/Count 1', $isiArsip);
    }

    public function test_arsip_is_a_frozen_copy_that_only_those_who_may_view_can_download(): void
    {
        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->pengajuanPayload())->assertSessionHasNoErrors();
        $pengajuan = $this->isiNomorSppd($this->pemohon->pengajuanSppds()->firstOrFail(), $this->tu);

        $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.show', $pengajuan))
            ->assertInertia(fn (Assert $page) => $page->where('diarsipkan', false));

        $this->actingAs($this->tu)->post(route('sppd.pengajuan.selesai', $pengajuan))->assertSessionHas('success');

        $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.show', $pengajuan))
            ->assertInertia(fn (Assert $page) => $page->where('diarsipkan', true));

        $sebelum = $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan))->streamedContent();

        // Data yang berubah sesudah diarsipkan tidak memengaruhi salinan beku.
        $this->kepsek->update(['name' => 'Kepala Sekolah Pengganti']);
        $sesudah = $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan))->streamedContent();
        $this->assertSame($sebelum, $sesudah);

        $lain = User::factory()->create()->assignRole(RoleName::Pemohon->value);
        $this->actingAs($lain)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan))->assertForbidden();
    }

    public function test_arsip_is_not_made_and_status_stays_when_the_signature_file_is_missing(): void
    {
        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->pengajuanPayload())->assertSessionHasNoErrors();
        $pengajuan = $this->isiNomorSppd($this->pemohon->pengajuanSppds()->firstOrFail(), $this->tu);
        Storage::disk('local')->delete(ArsipSppd::BERKAS_TTD);

        $this->actingAs($this->tu)
            ->post(route('sppd.pengajuan.selesai', $pengajuan))
            ->assertSessionHas('error', 'Berkas tanda tangan Kepala Sekolah tidak ditemukan, arsip belum bisa dibuat. Hubungi admin.');

        $this->assertSame(PengajuanStatus::SedangDitugaskan, $pengajuan->fresh()->status);
        Storage::disk('local')->assertMissing("arsip-sppd/{$pengajuan->id}.pdf");
    }

    public function test_akun_anggaran_is_locked_once_the_sppd_is_archived(): void
    {
        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->pengajuanPayload())->assertSessionHasNoErrors();
        $pengajuan = $this->isiNomorSppd($this->pemohon->pengajuanSppds()->firstOrFail(), $this->tu);

        $this->actingAs($this->tu)->post(route('sppd.pengajuan.selesai', $pengajuan))->assertSessionHas('success');

        $this->actingAs($this->tu)->patch(route('sppd.pengajuan.akun-anggaran', $pengajuan), ['akun_anggaran' => '5.2.02.01'])->assertForbidden();
        $this->actingAs($this->bendahara)->patch(route('sppd.pengajuan.akun-anggaran', $pengajuan), ['akun_anggaran' => '5.2.02.01'])->assertForbidden();
    }

    public function test_selesai_pengajuan_from_before_the_archive_existed_still_downloads_the_blank_sppd(): void
    {
        $pengajuan = PengajuanSppd::factory()->create(['pemohon_id' => $this->pemohon->id, 'status' => PengajuanStatus::Selesai]);
        $pengajuan->sppd()->create(['nomor_sppd' => '001/SMK-BM/I/'.now()->year, 'diterbitkan_oleh' => $this->pemohon->id]);

        $response = $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.sppd-pdf', $pengajuan));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringNotContainsString('/DCTDecode', $response->getContent());
    }

    public function test_no_nomor_is_generated_automatically(): void
    {
        $this->pengajuanDiajukan();
        $this->pengajuanDisetujui();

        $this->assertSame(1, $this->pemohon->pengajuanSppds()->where('status', PengajuanStatus::DiajukanKeKepsek)->count());
        $this->assertSame(1, $this->pemohon->pengajuanSppds()->where('status', PengajuanStatus::MenungguNomor)->count());
        $this->assertSame(0, Sppd::count());
    }

    public function test_notifications_follow_each_step_of_the_flow(): void
    {
        // Pengajuan baru hanya memberi tahu Kepala Sekolah; TU baru diminta bertindak setelah disetujui.
        $pengajuan = $this->pengajuanDiajukan();

        $this->assertSame(1, $this->kepsek->notifications()->where('data->pesan', 'Pengajuan SPPD baru menunggu persetujuan Anda.')->count());
        $this->assertSame(0, $this->tu->notifications()->count());
        $this->assertSame(0, $this->bendahara->notifications()->count());
        $this->assertSame(0, $this->pemohon->notifications()->count());

        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.approve', $pengajuan))->assertSessionHasNoErrors();

        $this->assertSame(1, $this->tu->notifications()->where('data->pesan', 'Pengajuan SPPD disetujui, silakan isi nomor surat.')->count());
        $this->assertSame(1, $this->pemohon->notifications()->where('data->pesan', 'Pengajuan SPPD Anda disetujui Kepala Sekolah. Menunggu TU mengisi nomor surat.')->count());
        $this->assertSame(0, $this->bendahara->notifications()->count());

        // Nomor terisi: pemohon dan bendahara diberi tahu.
        $this->isiNomorSppd($pengajuan, $this->tu, '009/SMK-BM/X/2026');

        $this->assertSame(1, $this->pemohon->notifications()->where('data->pesan', 'SPPD Anda sudah bernomor 009/SMK-BM/X/2026 dan siap diunduh.')->count());
        $this->assertSame(1, $this->bendahara->notifications()->count());
        $this->assertSame(1, $this->kepsek->notifications()->count());

        // Memperbaiki nomor tidak memberi tahu siapa pun lagi.
        $this->isiNomorSppd($pengajuan, $this->tu, '010/SMK-BM/X/2026');
        $this->assertSame(2, $this->pemohon->notifications()->count());
        $this->assertSame(1, $this->bendahara->notifications()->count());
    }

    public function test_only_tu_can_fill_the_nomor(): void
    {
        $pengajuan = $this->pengajuanDisetujui();
        $url = route('sppd.pengajuan.nomor', $pengajuan);

        foreach ([$this->pemohon, $this->kepsek, $this->bendahara] as $bukanTu) {
            $this->actingAs($bukanTu)->patch($url, ['nomor_sppd' => '001/SMK-BM/X/2026'])->assertForbidden();
        }

        $this->assertNull($pengajuan->fresh()->sppd);
        $this->assertSame(PengajuanStatus::MenungguNomor, $pengajuan->fresh()->status);
    }

    public function test_tu_cannot_fill_the_nomor_before_kepala_sekolah_approves(): void
    {
        foreach ([$this->pengajuanDiajukan(), $this->pengajuanDitolak()] as $pengajuan) {
            $this->actingAs($this->tu)
                ->patch(route('sppd.pengajuan.nomor', $pengajuan), ['nomor_sppd' => '001/SMK-BM/X/2026'])
                ->assertForbidden();

            $this->assertNull($pengajuan->fresh()->sppd);
        }
    }

    public function test_nomor_is_validated_and_must_be_unique(): void
    {
        $pertama = $this->pengajuanDisetujui();
        $kedua = $this->pengajuanDisetujui();
        $this->isiNomorSppd($pertama, $this->tu, '001/SMK-BM/X/2026');

        $url = route('sppd.pengajuan.nomor', $kedua);

        $this->actingAs($this->tu)->patch($url, ['nomor_sppd' => ''])->assertSessionHasErrors(['nomor_sppd' => 'Nomor SPPD wajib diisi.']);
        $this->actingAs($this->tu)->patch($url, ['nomor_sppd' => str_repeat('1', 51)])->assertSessionHasErrors(['nomor_sppd' => 'Nomor SPPD maksimal 50 karakter.']);
        $this->actingAs($this->tu)->patch($url, ['nomor_sppd' => '001/SMK-BM/X/2026'])->assertSessionHasErrors(['nomor_sppd' => 'Nomor SPPD ini sudah dipakai SPPD lain.']);
        $this->assertNull($kedua->fresh()->sppd);
        $this->assertSame(PengajuanStatus::MenungguNomor, $kedua->fresh()->status);

        $this->actingAs($this->tu)->patch($url, ['nomor_sppd' => str_repeat('1', 50)])->assertSessionHasNoErrors();
        $this->assertSame(PengajuanStatus::SedangDitugaskan, $kedua->fresh()->status);
    }

    public function test_tu_can_correct_the_nomor_until_the_trip_is_marked_done(): void
    {
        $this->actingAs($this->pemohon)->post('/sppd/pengajuan', $this->pengajuanPayload())->assertSessionHasNoErrors();
        $pengajuan = $this->isiNomorSppd($this->pemohon->pengajuanSppds()->firstOrFail(), $this->tu, '001/SMK-BM/X/2026');
        $diterbitkanOleh = $pengajuan->sppd->diterbitkan_oleh;

        // Nomor yang sama boleh disimpan ulang (tidak dianggap kembar dengan dirinya sendiri), nomor lain mengganti.
        $this->isiNomorSppd($pengajuan, $this->tu, '001/SMK-BM/X/2026');
        $pengajuan = $this->isiNomorSppd($pengajuan, $this->tu, '008/SMK-BM/X/2026');

        $this->assertSame('008/SMK-BM/X/2026', $pengajuan->sppd->nomor_sppd);
        $this->assertSame($diterbitkanOleh, $pengajuan->sppd->diterbitkan_oleh);
        $this->assertSame(PengajuanStatus::SedangDitugaskan, $pengajuan->status);
        $this->assertSame(1, Sppd::count());
        $this->assertTrue(LogAudit::where('entitas_id', $pengajuan->id)->where('aksi', 'Nomor SPPD diubah dari 001/SMK-BM/X/2026 menjadi 008/SMK-BM/X/2026.')->exists());

        // Sesudah diarsipkan salinannya beku, jadi nomor terkunci.
        $this->actingAs($this->tu)->post(route('sppd.pengajuan.selesai', $pengajuan))->assertSessionHas('success');
        $this->actingAs($this->tu)->patch(route('sppd.pengajuan.nomor', $pengajuan), ['nomor_sppd' => '009/SMK-BM/X/2026'])->assertForbidden();
        $this->assertSame('008/SMK-BM/X/2026', $pengajuan->fresh()->sppd->nomor_sppd);
    }

    public function test_nothing_but_the_nomor_can_happen_while_waiting_for_it(): void
    {
        $pengajuan = $this->pengajuanDisetujui();

        $this->actingAs($this->tu)->post(route('sppd.pengajuan.selesai', $pengajuan))->assertForbidden();
        $this->actingAs($this->tu)->patch(route('sppd.pengajuan.akun-anggaran', $pengajuan), ['akun_anggaran' => '5.2.02.01'])->assertForbidden();
        $this->actingAs($this->bendahara)->post(route('sppd.pengajuan.cairkan-lumsum', $pengajuan), ['jumlah' => 500_000])->assertForbidden();

        $this->assertSame(PengajuanStatus::MenungguNomor, $pengajuan->fresh()->status);
        $this->assertCount(0, $pengajuan->fresh()->pencairans);
    }

    public function test_show_page_exposes_who_may_fill_the_nomor(): void
    {
        $pengajuan = $this->pengajuanDisetujui();

        foreach ([[$this->tu, true], [$this->pemohon, false], [$this->kepsek, false], [$this->bendahara, false]] as [$user, $boleh]) {
            $this->actingAs($user)->get(route('sppd.pengajuan.show', $pengajuan))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('pengajuan.status', PengajuanStatus::MenungguNomor->value)
                    ->where('pengajuan.sppd', null)
                    ->where('can.isiNomor', $boleh));
        }

        // Boleh diperbaiki selama berjalan, terkunci setelah selesai.
        $this->isiNomorSppd($pengajuan, $this->tu);
        $this->actingAs($this->tu)->get(route('sppd.pengajuan.show', $pengajuan))
            ->assertInertia(fn (Assert $page) => $page->where('can.isiNomor', true));

        $this->actingAs($this->tu)->post(route('sppd.pengajuan.selesai', $pengajuan));
        $this->actingAs($this->tu)->get(route('sppd.pengajuan.show', $pengajuan))
            ->assertInertia(fn (Assert $page) => $page->where('can.isiNomor', false));
    }

    public function test_kepala_sekolah_can_reject_with_a_reason_and_the_pemohon_is_told(): void
    {
        $pengajuan = $this->pengajuanDiajukan();

        $this->actingAs($this->kepsek)
            ->post(route('sppd.pengajuan.reject', $pengajuan), ['catatan' => 'Tujuan kurang jelas'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $pengajuan = $pengajuan->fresh();
        $this->assertSame(PengajuanStatus::Ditolak, $pengajuan->status);
        $this->assertSame('Tujuan kurang jelas', $pengajuan->catatan_kepsek);
        $this->assertNotNull($pengajuan->ditolak_at);
        $this->assertNull($pengajuan->disetujui_oleh);
        $this->assertTrue(LogAudit::where('entitas_id', $pengajuan->id)->where('aksi', 'Pengajuan ditolak: Tujuan kurang jelas')->exists());
        $this->assertSame(1, $this->pemohon->notifications()->where('data->pesan', 'Pengajuan SPPD Anda ditolak Kepala Sekolah. Silakan revisi lalu ajukan ulang.')->count());
        $this->assertSame(0, $this->tu->notifications()->count(), 'TU tidak dilibatkan sebelum disetujui');
    }

    public function test_reject_requires_a_reason(): void
    {
        $pengajuan = $this->pengajuanDiajukan();

        $this->actingAs($this->kepsek)
            ->post(route('sppd.pengajuan.reject', $pengajuan), ['catatan' => ''])
            ->assertSessionHasErrors(['catatan' => 'Catatan wajib diisi untuk menolak pengajuan.']);

        $this->assertSame(PengajuanStatus::DiajukanKeKepsek, $pengajuan->fresh()->status);
    }

    public function test_only_kepala_sekolah_can_decide_and_only_while_the_pengajuan_waits_for_it(): void
    {
        $pengajuan = $this->pengajuanDiajukan();

        // Pemohon tidak bisa menyetujui pengajuannya sendiri; TU dan bendahara juga bukan pengambil keputusan.
        foreach ([$this->pemohon, $this->tu, $this->bendahara] as $bukanKepsek) {
            $this->actingAs($bukanKepsek)->post(route('sppd.pengajuan.approve', $pengajuan))->assertForbidden();
            $this->actingAs($bukanKepsek)->post(route('sppd.pengajuan.reject', $pengajuan), ['catatan' => 'x'])->assertForbidden();
        }
        $this->assertSame(PengajuanStatus::DiajukanKeKepsek, $pengajuan->fresh()->status);

        // Keputusan yang sudah diambil tidak bisa diambil lagi.
        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.approve', $pengajuan))->assertSessionHasNoErrors();
        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.approve', $pengajuan))->assertForbidden();
        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.reject', $pengajuan), ['catatan' => 'x'])->assertForbidden();
        $this->assertSame(PengajuanStatus::MenungguNomor, $pengajuan->fresh()->status);
    }

    public function test_show_page_exposes_who_may_decide(): void
    {
        $pengajuan = $this->pengajuanDiajukan();

        foreach ([[$this->kepsek, true], [$this->pemohon, false], [$this->tu, false], [$this->bendahara, false]] as [$user, $boleh]) {
            $this->actingAs($user)->get(route('sppd.pengajuan.show', $pengajuan))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('pengajuan.status', PengajuanStatus::DiajukanKeKepsek->value)
                    ->where('can.approve', $boleh)
                    ->where('can.isiNomor', false));
        }
    }

    public function test_pemohon_revises_a_rejected_pengajuan_and_resubmits_it_without_a_new_nomor_request(): void
    {
        $pengajuan = $this->pengajuanDitolak();

        $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.edit', $pengajuan))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('sppd/pengajuan/revisi')
                ->where('pengajuan.id', $pengajuan->id)
                ->where('pengajuan.tujuan', 'Dinas Pendidikan Kabupaten Karawang')
                ->where('pengajuan.catatan_kepsek', 'Tujuan kurang jelas')
                ->where('pengajuan.jam_berangkat', '07:30'));

        $rekan = User::factory()->create()->assignRole(RoleName::Pemohon->value);
        $this->actingAs($this->pemohon)
            ->post(route('sppd.pengajuan.revisi', $pengajuan), $this->pengajuanPayload([
                'tujuan' => 'Dinas Pendidikan Provinsi Jawa Barat',
                'pengikut_ids' => [$rekan->id],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sppd.pengajuan.show', $pengajuan));

        // Pengajuan yang sama diperbarui dan diajukan ulang; tidak ada pengajuan baru dan tidak ada nomor.
        $this->assertSame(1, PengajuanSppd::count());
        $pengajuan = $pengajuan->fresh(['pengikuts']);
        $this->assertSame(PengajuanStatus::DiajukanKeKepsek, $pengajuan->status);
        $this->assertSame('Dinas Pendidikan Provinsi Jawa Barat', $pengajuan->tujuan);
        $this->assertSame([$rekan->id], $pengajuan->pengikuts->pluck('id')->all());
        $this->assertNull($pengajuan->catatan_kepsek);
        $this->assertNull($pengajuan->ditolak_at);
        $this->assertNull($pengajuan->sppd);
        $this->assertTrue(LogAudit::where('entitas_id', $pengajuan->id)->where('aksi', 'Pengajuan direvisi dan diajukan ulang ke Kepala Sekolah.')->exists());
        $this->assertTrue(LogAudit::where('entitas_id', $pengajuan->id)->where('aksi', 'Pengajuan ditolak: Tujuan kurang jelas')->exists(), 'alasan lama tetap terbaca di riwayat');
        $this->assertSame(1, $this->kepsek->notifications()->where('data->pesan', 'Pengajuan SPPD yang direvisi menunggu persetujuan Anda.')->count());

        // Kepala Sekolah bisa memutuskan lagi, lalu TU mengisi nomor seperti biasa.
        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.approve', $pengajuan))->assertSessionHasNoErrors();
        $pengajuan = $this->isiNomorSppd($pengajuan, $this->tu);
        $this->assertSame(PengajuanStatus::SedangDitugaskan, $pengajuan->status);
    }

    public function test_revisi_keeps_the_old_undangan_unless_a_new_one_is_uploaded(): void
    {
        $pengajuan = $this->pengajuanDiajukan();
        $lama = $pengajuan->undangan_path;
        Storage::disk('public')->assertExists($lama);
        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.reject', $pengajuan), ['catatan' => 'Perbaiki'])->assertSessionHasNoErrors();

        $tanpaBerkas = $this->pengajuanPayload();
        unset($tanpaBerkas['undangan']);
        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.revisi', $pengajuan), $tanpaBerkas)->assertSessionHasNoErrors();
        $this->assertSame($lama, $pengajuan->fresh()->undangan_path);
        Storage::disk('public')->assertExists($lama);

        $this->actingAs($this->kepsek)->post(route('sppd.pengajuan.reject', $pengajuan), ['catatan' => 'Masih kurang'])->assertSessionHasNoErrors();
        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.revisi', $pengajuan), $this->pengajuanPayload())->assertSessionHasNoErrors();

        $baru = $pengajuan->fresh()->undangan_path;
        $this->assertNotSame($lama, $baru);
        Storage::disk('public')->assertExists($baru);
        Storage::disk('public')->assertMissing($lama);
    }

    public function test_revisi_is_validated_like_a_new_pengajuan(): void
    {
        $pengajuan = $this->pengajuanDitolak();

        $this->actingAs($this->pemohon)
            ->post(route('sppd.pengajuan.revisi', $pengajuan), $this->pengajuanPayload(['tujuan' => '', 'jam_berangkat' => '']))
            ->assertSessionHasErrors(['tujuan', 'jam_berangkat']);

        $this->assertSame(PengajuanStatus::Ditolak, $pengajuan->fresh()->status);
    }

    public function test_only_the_owner_can_revise_and_only_a_rejected_pengajuan(): void
    {
        $ditolak = $this->pengajuanDitolak();
        $lain = User::factory()->create()->assignRole(RoleName::Pemohon->value);

        foreach ([$lain, $this->kepsek, $this->tu, $this->bendahara] as $bukanPemilik) {
            $this->actingAs($bukanPemilik)->get(route('sppd.pengajuan.edit', $ditolak))->assertForbidden();
        }
        $this->actingAs($lain)->post(route('sppd.pengajuan.revisi', $ditolak), $this->pengajuanPayload())->assertForbidden();
        $this->assertSame(PengajuanStatus::Ditolak, $ditolak->fresh()->status);

        // Yang menunggu keputusan, sudah disetujui, atau sudah terbit tidak bisa direvisi pemiliknya.
        foreach ([$this->pengajuanDiajukan(), $this->pengajuanDisetujui()] as $bukanDitolak) {
            $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.edit', $bukanDitolak))->assertForbidden();
            $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.revisi', $bukanDitolak), $this->pengajuanPayload())->assertForbidden();
        }
    }

    public function test_show_page_exposes_the_whatsapp_contacts_only_to_the_pemohon(): void
    {
        $pengajuan = $this->pengajuanDiajukan();

        $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.show', $pengajuan))
            ->assertInertia(fn (Assert $page) => $page
                ->where('kontak.kepala_sekolah', '6281280884107')
                ->where('kontak.tu', '6285174263354')
                ->where('can.revisi', false));

        foreach ([$this->kepsek, $this->tu, $this->bendahara] as $bukanPemohon) {
            $this->actingAs($bukanPemohon)->get(route('sppd.pengajuan.show', $pengajuan))
                ->assertInertia(fn (Assert $page) => $page->where('kontak', null));
        }

        $ditolak = $this->pengajuanDitolak();
        $this->actingAs($this->pemohon)->get(route('sppd.pengajuan.show', $ditolak))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pengajuan.status', PengajuanStatus::Ditolak->value)
                ->where('can.revisi', true));
    }

    public function test_dashboard_counts_pengajuan_waiting_for_kepala_sekolah(): void
    {
        $this->pengajuanDiajukan();
        $this->pengajuanDisetujui();

        $this->actingAs($this->kepsek)->get(route('sppd.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.menunggu_persetujuan', 1)
                ->where('stats.menunggu_nomor', 1));
    }

    public function test_status_filter_is_null_without_a_query_and_ignores_unknown_values(): void
    {
        $this->actingAs($this->pemohon)->get('/sppd/pengajuan')
            ->assertInertia(fn (Assert $page) => $page->where('filters.status', null));

        $this->actingAs($this->pemohon)->get('/sppd/pengajuan?status=bukan-status')
            ->assertInertia(fn (Assert $page) => $page->where('filters.status', null));

        $this->actingAs($this->pemohon)->get('/sppd/pengajuan?status=selesai')
            ->assertInertia(fn (Assert $page) => $page->where('filters.status', 'selesai'));
    }

    public function test_tujuan_and_jam_are_required(): void
    {
        $this->actingAs($this->pemohon)
            ->post('/sppd/pengajuan', $this->pengajuanPayload(['tujuan' => '', 'jam_berangkat' => '', 'jam_kembali' => '']))
            ->assertSessionHasErrors(['tujuan', 'jam_berangkat', 'jam_kembali']);

        $this->assertSame(0, PengajuanSppd::count());
    }

    public function test_jam_kembali_must_be_after_jam_berangkat_on_the_same_day(): void
    {
        $tanggal = now()->addDays(3)->toDateString();

        $this->actingAs($this->pemohon)
            ->post('/sppd/pengajuan', $this->pengajuanPayload([
                'tanggal_berangkat' => $tanggal,
                'tanggal_kembali' => $tanggal,
                'jam_berangkat' => '10:00',
                'jam_kembali' => '09:00',
            ]))
            ->assertSessionHasErrors('jam_kembali');

        $this->actingAs($this->pemohon)
            ->post('/sppd/pengajuan', $this->pengajuanPayload([
                'tanggal_berangkat' => $tanggal,
                'tanggal_kembali' => $tanggal,
                'jam_berangkat' => '10:00',
                'jam_kembali' => '15:00',
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_only_pemohon_can_create_a_sppd(): void
    {
        $this->actingAs($this->kepsek)->post('/sppd/pengajuan', $this->pengajuanPayload())->assertForbidden();
        $this->actingAs($this->tu)->post('/sppd/pengajuan', $this->pengajuanPayload())->assertForbidden();

        $this->assertSame(0, PengajuanSppd::count());
    }

    public function test_signature_upload_page_is_gone(): void
    {
        $this->actingAs($this->kepsek)->get('/sppd/tanda-tangan-saya')->assertNotFound();
        $this->actingAs($this->kepsek)->post('/sppd/tanda-tangan-saya', [])->assertNotFound();
    }

    public function test_only_tu_can_finish_a_pengajuan_that_is_being_carried_out(): void
    {
        $pengajuan = PengajuanSppd::factory()->create([
            'pemohon_id' => $this->pemohon->id,
            'status' => PengajuanStatus::SedangDitugaskan,
        ]);

        $this->actingAs($this->pemohon)->post(route('sppd.pengajuan.selesai', $pengajuan))->assertForbidden();
        $this->actingAs($this->bendahara)->post(route('sppd.pengajuan.selesai', $pengajuan))->assertForbidden();
        $this->assertSame(PengajuanStatus::SedangDitugaskan, $pengajuan->fresh()->status);
    }

    public function test_tu_cannot_finish_a_pengajuan_that_is_already_done(): void
    {
        $pengajuan = PengajuanSppd::factory()->create([
            'pemohon_id' => $this->pemohon->id,
            'status' => PengajuanStatus::Selesai,
        ]);

        $this->actingAs($this->tu)->post(route('sppd.pengajuan.selesai', $pengajuan))->assertForbidden();
    }
}
