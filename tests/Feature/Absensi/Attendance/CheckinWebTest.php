<?php

namespace Tests\Feature\Absensi\Attendance;

use Tests\AbsensiTestCase;

class CheckinWebTest extends AbsensiTestCase
{
    /** 2025-01-06 is a Monday - a normal, active school day. */
    private const MONDAY = 1;

    // Same coordinates as App\Support\Geofence::SCHOOL_LAT/SCHOOL_LNG - kept as
    // literals here so a school relocation doesn't silently make this "inside
    // the fence" test always pass.
    private const SCHOOL_LAT = -6.3370648;

    private const SCHOOL_LNG = 107.2769934;

    public function test_checkin_on_time_is_marked_hadir(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $this->freezeAt('2025-01-06 07:05:00');
        $guru = $this->actingAsGuru();

        $response = $this->postJson('/checkin-web', ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG]);

        $response->assertOk();
        $response->assertJson(['type' => 'MASUK', 'statusMasuk' => 'HADIR']);
        $this->assertDatabaseHas('attendance', ['guruId' => $guru->id, 'statusMasuk' => 'HADIR']);
    }

    public function test_checkin_after_batas_telat_is_marked_telat(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $this->freezeAt('2025-01-06 07:20:00');
        $guru = $this->actingAsGuru();

        $response = $this->postJson('/checkin-web', ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG]);

        $response->assertOk();
        $response->assertJson(['statusMasuk' => 'TELAT']);
        $this->assertDatabaseHas('attendance', ['guruId' => $guru->id, 'statusMasuk' => 'TELAT']);
    }

    public function test_checkin_before_window_opens_is_rejected(): void
    {
        $this->setJadwalHari(self::MONDAY, ['jamMulaiAbsen' => '07:00']);
        $this->freezeAt('2025-01-06 06:50:00');
        $this->actingAsGuru();

        $response = $this->postJson('/checkin-web', ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG]);

        $response->assertStatus(409);
    }

    public function test_checkin_outside_geofence_radius_is_rejected(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $this->freezeAt('2025-01-06 07:05:00');
        $this->actingAsGuru();

        // Central Jakarta - kilometers away from the school coordinates.
        $response = $this->postJson('/checkin-web', ['lat' => -6.1754, 'lng' => 106.8272]);

        $response->assertStatus(403);
    }

    public function test_second_checkin_after_jam_pulang_records_checkout(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $guru = $this->actingAsGuru();

        $this->freezeAt('2025-01-06 07:05:00');
        $this->postJson('/checkin-web', ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG])->assertOk();

        $this->freezeAt('2025-01-06 15:35:00');
        $response = $this->postJson('/checkin-web', ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG]);

        $response->assertOk();
        $response->assertJson(['type' => 'PULANG']);
        $this->assertDatabaseHas('attendance', ['guruId' => $guru->id, 'statusPulang' => 'HADIR']);
    }

    public function test_second_checkin_before_jam_pulang_is_rejected(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $this->actingAsGuru();

        $this->freezeAt('2025-01-06 07:05:00');
        $this->postJson('/checkin-web', ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG])->assertOk();

        $this->freezeAt('2025-01-06 10:00:00');
        $response = $this->postJson('/checkin-web', ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG]);

        $response->assertStatus(409);
    }

    public function test_third_checkin_after_masuk_and_pulang_is_rejected(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $this->actingAsGuru();

        $this->freezeAt('2025-01-06 07:05:00');
        $this->postJson('/checkin-web', ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG])->assertOk();

        $this->freezeAt('2025-01-06 15:35:00');
        $this->postJson('/checkin-web', ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG])->assertOk();

        $response = $this->postJson('/checkin-web', ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG]);

        $response->assertStatus(409);
    }

    public function test_inactive_guru_cannot_check_in(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $this->freezeAt('2025-01-06 07:05:00');
        $this->actingAsGuru(['aktif' => false]);

        $response = $this->postJson('/checkin-web', ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG]);

        $response->assertStatus(403);
    }

    public function test_checkin_on_inactive_school_day_is_rejected(): void
    {
        // Sunday defaults to an inactive school day.
        $this->setJadwalHari(0, ['aktif' => false]);
        $this->freezeAt('2025-01-05 09:00:00');
        $this->actingAsGuru();

        $response = $this->postJson('/checkin-web', ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG]);

        $response->assertStatus(409);
    }

    public function test_admin_cannot_check_in_web_since_route_requires_guru_or_kepsek(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/checkin-web', ['lat' => self::SCHOOL_LAT, 'lng' => self::SCHOOL_LNG]);

        $response->assertStatus(403);
    }
}
