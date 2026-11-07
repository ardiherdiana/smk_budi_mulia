<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use App\Notifications\Sppd\PengajuanStatusChanged;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\AbsensiTestCase;

/** Lonceng notifikasi terpusat di topbar Menu Utama: gabungan notifikasi absensi (admin/kepsek) dan SPPD (per akun). */
class NotifikasiTerpusatTest extends AbsensiTestCase
{
    public function test_admin_melihat_notifikasi_absensi_dan_sppd_miliknya(): void
    {
        $admin = $this->actingAsAdmin();
        Notification::create(['type' => 'LEAVE_REQUEST', 'judul' => 'Izin', 'pesan' => 'Dina sakit']);
        $admin->notify(new PengajuanStatusChanged(PengajuanSppd::factory()->create(), 'SPPD baru'));

        $this->get('/menu-utama')->assertInertia(fn (Assert $page) => $page
            ->where('notifikasi.belumDibaca', 2)
            ->has('notifikasi.items', 2));
    }

    public function test_guru_tidak_melihat_notifikasi_absensi_tetapi_melihat_sppd_miliknya(): void
    {
        $guru = User::factory()->create();
        Notification::create(['type' => 'CHECKIN', 'judul' => 'Absen', 'pesan' => 'x']);
        $guru->notify(new PengajuanStatusChanged(PengajuanSppd::factory()->create(), 'Disetujui'));
        User::factory()->create()->notify(new PengajuanStatusChanged(PengajuanSppd::factory()->create(), 'Milik orang lain'));

        $this->actingAs($guru)->get('/menu-utama')->assertInertia(fn (Assert $page) => $page
            ->where('notifikasi.belumDibaca', 1)
            ->where('notifikasi.items.0.pesan', 'Disetujui'));
    }

    public function test_klik_menandai_dibaca_lalu_membuka_halamannya_dan_baca_semua(): void
    {
        $this->actingAsAdmin();
        $izin = Notification::create(['type' => 'LEAVE_REQUEST', 'judul' => 'Izin', 'pesan' => 'Dina sakit']);
        Notification::create(['type' => 'CHECKIN', 'judul' => 'Absen', 'pesan' => 'x']);

        $this->post("/notifikasi/absensi/{$izin->id}/baca")->assertRedirect('/persetujuan?status=all');
        $this->assertTrue($izin->fresh()->isRead);

        $this->post('/notifikasi/baca-semua')->assertRedirect();
        $this->assertSame(0, Notification::where('isRead', false)->count());
    }

    public function test_guru_tidak_bisa_menandai_notifikasi_absensi_dan_halaman_lama_hilang(): void
    {
        $izin = Notification::create(['type' => 'LEAVE_REQUEST', 'judul' => 'Izin', 'pesan' => 'x']);
        $this->actingAs(User::factory()->create());

        $this->post("/notifikasi/absensi/{$izin->id}/baca")->assertNotFound();
        $this->assertFalse($izin->fresh()->isRead);
        $this->get('/notifikasi')->assertNotFound();
        $this->get('/sppd/notifikasi')->assertNotFound();
    }
}
