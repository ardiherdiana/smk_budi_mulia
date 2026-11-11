<?php

namespace Tests\Feature\Absensi\Admin;

use App\Models\Notifikasi;
use App\Models\Sppd\PengajuanSppd;
use App\Notifications\Absensi\AbsensiNotifikasi;
use App\Notifications\Sppd\PengajuanStatusChanged;
use Tests\AbsensiTestCase;

/** Semua notifikasi (absensi, SPPD, Supervisi) di tabel bersama `notifikasi` dihapus tepat 30 hari setelah dibuat. */
class NotificationTest extends AbsensiTestCase
{
    public function test_prune_menghapus_notifikasi_tepat_30_hari_setelah_dibuat(): void
    {
        $admin = $this->admin();
        $this->freezeAt('2025-09-25 13:39:00');
        $admin->notify(new AbsensiNotifikasi('CHECKIN', 'Absen Masuk', 'Test'));
        $notifikasi = $admin->notifications()->sole();

        $this->freezeAt('2025-10-25 13:38:59');
        $this->artisan('model:prune', ['--model' => Notifikasi::class])->assertSuccessful();
        $this->assertDatabaseHas('notifikasi', ['id' => $notifikasi->id]);

        $this->freezeAt('2025-10-25 13:39:00');
        $this->artisan('model:prune', ['--model' => Notifikasi::class])->assertSuccessful();
        $this->assertDatabaseMissing('notifikasi', ['id' => $notifikasi->id]);
    }

    public function test_prune_berlaku_untuk_semua_modul_dibaca_atau_belum(): void
    {
        $admin = $this->admin();
        $this->freezeAt('2025-09-01 08:00:00');
        $admin->notify(new AbsensiNotifikasi('CHECKOUT', 'Absen Pulang', 'Lama'));
        $admin->notify(new PengajuanStatusChanged(PengajuanSppd::factory()->create(), 'SPPD lama'));
        $admin->notifications()->first()->markAsRead();

        $this->freezeAt('2025-09-20 08:00:00');
        $admin->notify(new PengajuanStatusChanged(PengajuanSppd::factory()->create(), 'SPPD baru'));

        $this->freezeAt('2025-10-02 08:00:00');
        $this->artisan('model:prune', ['--model' => Notifikasi::class])->assertSuccessful();

        $this->assertSame(['SPPD baru'], $admin->notifications()->get()->pluck('data.pesan')->all());
    }

    public function test_prune_dijadwalkan_tiap_menit(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('model:prune')
            ->assertSuccessful();
    }
}
