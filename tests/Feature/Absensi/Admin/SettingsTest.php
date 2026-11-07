<?php

namespace Tests\Feature\Absensi\Admin;

use Tests\AbsensiTestCase;

class SettingsTest extends AbsensiTestCase
{
    public function test_settings_page_creates_default_row_on_first_access(): void
    {
        $this->actingAsAdmin();

        $response = $this->get('/pengaturan');

        $response->assertOk();
        $this->assertDatabaseHas('settings', ['id' => 1, 'namaSekolah' => 'SMK Budi Mulia Karawang']);
    }

    public function test_admin_can_update_school_name(): void
    {
        $this->actingAsAdmin();

        $response = $this->patch('/pengaturan', ['namaSekolah' => 'SMK Budi Mulia Karawang Baru']);

        $response->assertSessionHas('toast');
        $this->assertDatabaseHas('settings', ['id' => 1, 'namaSekolah' => 'SMK Budi Mulia Karawang Baru']);
    }

    public function test_guru_cannot_access_settings(): void
    {
        $this->actingAsGuru();

        $this->get('/pengaturan')->assertStatus(403);
    }
}
