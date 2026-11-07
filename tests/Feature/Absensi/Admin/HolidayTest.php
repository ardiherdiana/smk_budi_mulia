<?php

namespace Tests\Feature\Absensi\Admin;

use App\Models\Holiday;
use Tests\AbsensiTestCase;

class HolidayTest extends AbsensiTestCase
{
    public function test_admin_can_create_a_holiday(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/hari-libur', [
            'tanggal' => '2025-08-17',
            'keterangan' => 'Hari Kemerdekaan',
        ]);

        $response->assertSessionHas('toast');
        $this->assertDatabaseHas('holidays', ['keterangan' => 'Hari Kemerdekaan']);
    }

    public function test_cannot_create_two_holidays_on_the_same_date(): void
    {
        $this->actingAsAdmin();
        Holiday::create(['tanggal' => '2025-08-17', 'keterangan' => 'Hari Kemerdekaan']);

        $response = $this->post('/hari-libur', [
            'tanggal' => '2025-08-17',
            'keterangan' => 'Duplikat',
        ]);

        $response->assertStatus(409);
    }

    public function test_admin_can_delete_a_holiday(): void
    {
        $holiday = Holiday::create(['tanggal' => '2025-08-17', 'keterangan' => 'Hari Kemerdekaan']);
        $this->actingAsAdmin();

        $response = $this->delete("/hari-libur/{$holiday->id}");

        $response->assertSessionHas('toast');
        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }

    public function test_guru_cannot_manage_holidays(): void
    {
        $this->actingAsGuru();

        $this->get('/hari-libur')->assertStatus(403);
    }
}
