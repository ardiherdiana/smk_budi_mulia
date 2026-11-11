<?php

namespace Tests\Feature;

use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use App\Notifications\Absensi\AbsensiNotifikasi;
use App\Notifications\Sppd\PengajuanStatusChanged;
use App\Services\NotificationService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\AbsensiTestCase;

/**
 * Lonceng notifikasi terpusat di topbar Menu Utama. Semua modul memakai satu tabel `notifikasi`, satu baris per
 * penerima: notifikasi absensi dikirim ke tiap admin dan kepsek aktif, SPPD/Supervisi ke akun yang bersangkutan.
 */
class NotifikasiTerpusatTest extends AbsensiTestCase
{
    public function test_notifikasi_absensi_dikirim_ke_tiap_admin_dan_kepsek_aktif_saja(): void
    {
        $admin = $this->admin();
        $kepsek = $this->akun('KEPSEK', 'kepsek', 'Ibu Kepala');
        $kepsekNonaktif = $this->akun('KEPSEK', 'kepsek2');
        $kepsekNonaktif->forceFill(['is_active' => false])->save();
        $guru = $this->akun('GURU', 'guru1');
        $staf = $this->akun('STAF', 'tu1');

        app(NotificationService::class)->create('LEAVE_REQUEST', 'Pengajuan Izin/Sakit', 'Dina mengajukan sakit');

        $jumlah = fn (int $id) => User::find($id)->notifications()->where('type', AbsensiNotifikasi::class)->count();
        $this->assertSame(1, $jumlah($admin->id));
        $this->assertSame(1, $jumlah($kepsek->id));
        $this->assertSame(0, $jumlah($kepsekNonaktif->id));
        $this->assertSame(0, $jumlah($guru->id));
        $this->assertSame(0, $jumlah($staf->id));
    }

    public function test_admin_melihat_notifikasi_absensi_dan_sppd_miliknya(): void
    {
        $admin = $this->actingAsAdmin();
        app(NotificationService::class)->create('LEAVE_REQUEST', 'Pengajuan Izin/Sakit', 'Dina sakit');
        $admin->notify(new PengajuanStatusChanged(PengajuanSppd::factory()->create(), 'SPPD baru'));

        // Dibuat di detik yang sama, jadi urutannya tidak dipastikan.
        $this->get('/menu-utama')->assertInertia(fn (Assert $page) => $page
            ->where('notifikasi.belumDibaca', 2)
            ->has('notifikasi.items', 2)
            ->where('notifikasi.items', fn ($items) => collect($items)->sortBy('sumber')->map(fn ($n) => [$n['sumber'], $n['tipe'], $n['judul']])->values()->all() === [
                ['absensi', 'LEAVE_REQUEST', 'Pengajuan Izin/Sakit'],
                ['sppd', 'SPPD', 'Pengajuan SPPD'],
            ]));
    }

    public function test_guru_hanya_melihat_notifikasi_miliknya(): void
    {
        $guru = User::factory()->create();
        app(NotificationService::class)->create('CHECKIN', 'Absen Masuk', 'x');
        $guru->notify(new PengajuanStatusChanged(PengajuanSppd::factory()->create(), 'Disetujui'));
        User::factory()->create()->notify(new PengajuanStatusChanged(PengajuanSppd::factory()->create(), 'Milik orang lain'));

        $this->actingAs($guru)->get('/menu-utama')->assertInertia(fn (Assert $page) => $page
            ->where('notifikasi.belumDibaca', 1)
            ->has('notifikasi.items', 1)
            ->where('notifikasi.items.0.pesan', 'Disetujui'));
    }

    public function test_status_dibaca_per_orang_dan_baca_semua_hanya_milik_sendiri(): void
    {
        $admin = $this->actingAsAdmin();
        $kepsek = User::find($this->akun('KEPSEK', 'kepsek')->id);
        app(NotificationService::class)->create('LEAVE_REQUEST', 'Pengajuan Izin/Sakit', 'Dina sakit');
        app(NotificationService::class)->create('CHECKIN', 'Absen Masuk', 'Budi absen masuk', 'G-1');

        $izin = $admin->notifications()->where('data->tipe', 'LEAVE_REQUEST')->sole();
        $this->post("/notifikasi/absensi/{$izin->id}/baca")->assertRedirect('/persetujuan?status=all');
        $this->assertNotNull($izin->fresh()->read_at);
        $this->assertSame(2, $kepsek->unreadNotifications()->count(), 'kepsek punya salinannya sendiri');

        $absen = $admin->notifications()->where('data->tipe', 'CHECKIN')->sole();
        $hariIni = now()->toDateString();
        $this->post("/notifikasi/absensi/{$absen->id}/baca")
            ->assertRedirect("/rekap?from={$hariIni}&to={$hariIni}&guruId=G-1");

        $admin->notify(new PengajuanStatusChanged(PengajuanSppd::factory()->create(), 'SPPD baru'));
        $this->post('/notifikasi/baca-semua')->assertRedirect();
        $this->assertSame(0, $admin->unreadNotifications()->count());
        $this->assertSame(2, $kepsek->unreadNotifications()->count());
    }

    public function test_tidak_bisa_menandai_notifikasi_orang_lain_dan_halaman_lama_hilang(): void
    {
        $admin = $this->admin();
        app(NotificationService::class)->create('LEAVE_REQUEST', 'Pengajuan Izin/Sakit', 'x');
        $izin = $admin->notifications()->sole();

        $this->actingAs(User::factory()->create());
        $this->post("/notifikasi/absensi/{$izin->id}/baca")->assertNotFound();
        $this->assertNull($izin->fresh()->read_at);

        $this->actingAs($admin)->post("/notifikasi/sppd/{$izin->id}/baca")->assertNotFound();
        $this->get('/notifikasi')->assertNotFound();
        $this->get('/sppd/notifikasi')->assertNotFound();
    }
}
