<?php

namespace Tests\Feature\Absensi\Admin;

use Tests\AbsensiTestCase;

class JadwalTest extends AbsensiTestCase
{
    public function test_admin_can_update_a_days_schedule(): void
    {
        $this->actingAsAdmin();

        $response = $this->patchJson('/pengaturan/jadwal/1', [
            'aktif' => true,
            'jamMasuk' => '07:15',
            'batasTelat' => '07:30',
            'jamPulang' => '16:00',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('jadwal_hari', ['hari' => 1, 'jamMasuk' => '07:15', 'batasTelat' => '07:30']);
    }

    public function test_invalid_hari_is_rejected(): void
    {
        $this->actingAsAdmin();

        $response = $this->patchJson('/pengaturan/jadwal/7', ['aktif' => true]);

        $response->assertStatus(422);
    }

    public function test_invalid_time_format_is_rejected(): void
    {
        $this->actingAsAdmin();

        $response = $this->patchJson('/pengaturan/jadwal/1', ['jamMasuk' => '7am']);

        // Sejak digabung ke landing, ValidationException JSON memakai 422 bawaan Laravel
        // (absensi lama mengembalikan 400; frontend tidak bergantung pada kodenya).
        $response->assertStatus(422);
    }

    public function test_guru_cannot_update_schedule(): void
    {
        $this->actingAsGuru();

        $response = $this->patchJson('/pengaturan/jadwal/1', ['aktif' => true]);

        $response->assertStatus(403);
    }
}
