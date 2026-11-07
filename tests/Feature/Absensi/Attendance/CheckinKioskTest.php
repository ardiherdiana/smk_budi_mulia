<?php

namespace Tests\Feature\Absensi\Attendance;

use App\Models\Attendance;
use App\Models\BriefingAttendance;
use App\Models\Guru;
use Tests\AbsensiTestCase;

class CheckinKioskTest extends AbsensiTestCase
{
    /** 2025-01-06 is a Monday - a normal, active school day. */
    private const MONDAY = 1;

    public function test_admin_scanning_a_valid_qr_token_records_checkin(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $this->freezeAt('2025-01-06 07:05:00');
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken]);

        $response->assertOk();
        $response->assertJson(['type' => 'MASUK', 'statusMasuk' => 'HADIR', 'nama' => $guru->nama]);
        $this->assertDatabaseHas('attendance', ['guruId' => $guru->id, 'statusMasuk' => 'HADIR']);
    }

    /** Briefing 06:30-06:50 sits inside the absen-masuk window (opens 06:00, telat after 07:15). */
    private function withBriefingSchedule(array $overrides = []): void
    {
        $this->setJadwalHari(self::MONDAY, array_merge([
            'jamMulaiAbsen' => '06:00',
            'jamBriefing' => '06:30',
            'jamSelesaiBriefing' => '06:50',
        ], $overrides));
    }

    public function test_first_scan_during_briefing_records_absen_masuk_and_briefing_together(): void
    {
        $this->withBriefingSchedule();
        $this->freezeAt('2025-01-06 06:35:00');
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken]);

        $response->assertOk();
        $response->assertJson(['type' => 'MASUK', 'statusMasuk' => 'HADIR', 'briefing' => true]);
        $this->assertDatabaseHas('attendance', ['guruId' => $guru->id, 'statusMasuk' => 'HADIR']);
        $briefing = BriefingAttendance::where('guruId', $guru->id)->sole();
        $this->assertSame('06:35:00', $briefing->waktu->format('H:i:s'));
        $this->assertNull($briefing->status);
    }

    public function test_first_scan_before_briefing_starts_records_only_absen_masuk(): void
    {
        $this->withBriefingSchedule();
        $this->freezeAt('2025-01-06 06:20:00');
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken]);

        $response->assertOk();
        $response->assertJson(['type' => 'MASUK', 'briefing' => false]);
        $this->assertDatabaseHas('attendance', ['guruId' => $guru->id]);
        $this->assertDatabaseMissing('briefing_attendance', ['guruId' => $guru->id]);
    }

    public function test_first_scan_after_briefing_closed_records_only_absen_masuk(): void
    {
        $this->withBriefingSchedule();
        $this->freezeAt('2025-01-06 06:55:00');
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken]);

        $response->assertOk();
        $response->assertJson(['type' => 'MASUK', 'briefing' => false]);
        $this->assertDatabaseHas('attendance', ['guruId' => $guru->id]);
        $this->assertDatabaseMissing('briefing_attendance', ['guruId' => $guru->id]);
    }

    public function test_briefing_without_closing_time_stays_open_for_the_kiosk_scan(): void
    {
        $this->withBriefingSchedule(['jamSelesaiBriefing' => null]);
        $this->freezeAt('2025-01-06 09:00:00');
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken]);

        $response->assertOk();
        $response->assertJson(['type' => 'MASUK', 'statusMasuk' => 'TELAT', 'briefing' => true]);
        $this->assertDatabaseHas('briefing_attendance', ['guruId' => $guru->id]);
    }

    public function test_day_without_briefing_records_only_absen_masuk(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $this->freezeAt('2025-01-06 07:05:00');
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken]);

        $response->assertOk();
        $response->assertJson(['type' => 'MASUK', 'briefing' => false]);
        $this->assertDatabaseMissing('briefing_attendance', ['guruId' => $guru->id]);
    }

    public function test_briefing_already_scanned_is_not_overwritten_by_the_kiosk_scan(): void
    {
        $this->withBriefingSchedule();
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $this->freezeAt('2025-01-06 06:32:00');
        $this->postJson('/briefing/scan', ['qrToken' => $guru->qrToken])->assertOk();

        $this->freezeAt('2025-01-06 06:40:00');
        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken]);

        $response->assertOk();
        $response->assertJson(['type' => 'MASUK', 'briefing' => false]);
        $briefing = BriefingAttendance::where('guruId', $guru->id)->sole();
        $this->assertSame('06:32:00', $briefing->waktu->format('H:i:s'));
    }

    public function test_kiosk_scan_supersedes_a_manual_briefing_correction_without_a_scan(): void
    {
        $this->withBriefingSchedule();
        $this->freezeAt('2025-01-06 06:35:00');
        $guru = Guru::factory()->create();
        BriefingAttendance::create(['guruId' => $guru->id, 'tanggal' => '2025-01-06', 'status' => 'ALPA', 'catatan' => 'salah input']);
        $this->actingAsAdmin();

        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken]);

        $response->assertOk();
        $response->assertJson(['type' => 'MASUK', 'briefing' => true]);
        $briefing = BriefingAttendance::where('guruId', $guru->id)->sole();
        $this->assertSame('06:35:00', $briefing->waktu->format('H:i:s'));
        $this->assertNull($briefing->status);
        $this->assertNull($briefing->catatan);
    }

    public function test_guru_who_already_checked_in_is_counted_for_briefing_by_a_kiosk_scan(): void
    {
        $this->withBriefingSchedule();
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $this->freezeAt('2025-01-06 06:10:00');
        $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken])->assertOk()->assertJson(['type' => 'MASUK', 'briefing' => false]);

        $this->freezeAt('2025-01-06 06:35:00');
        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken]);

        $response->assertOk();
        $response->assertJson(['type' => 'BRIEFING', 'briefing' => true, 'nama' => $guru->nama]);
        $briefing = BriefingAttendance::where('guruId', $guru->id)->sole();
        $this->assertSame('06:35:00', $briefing->waktu->format('H:i:s'));
        $this->assertNull($briefing->status);
        // Absen masuk stays as recorded at 06:10 and pulang is untouched.
        $attendance = Attendance::where('guruId', $guru->id)->sole();
        $this->assertSame('06:10:00', $attendance->jamMasuk->format('H:i:s'));
        $this->assertNull($attendance->jamPulang);
    }

    public function test_scanning_again_after_briefing_is_recorded_is_still_rejected_before_jam_pulang(): void
    {
        $this->withBriefingSchedule();
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $this->freezeAt('2025-01-06 06:10:00');
        $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken])->assertOk();
        $this->freezeAt('2025-01-06 06:35:00');
        $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken])->assertOk()->assertJson(['type' => 'BRIEFING']);

        $this->freezeAt('2025-01-06 06:40:00');
        $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken])->assertStatus(409);

        $this->assertSame(1, BriefingAttendance::where('guruId', $guru->id)->count());
        $this->assertNull(Attendance::where('guruId', $guru->id)->sole()->jamPulang);
    }

    public function test_checked_in_guru_scanning_after_briefing_closed_is_rejected_without_briefing(): void
    {
        $this->withBriefingSchedule();
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $this->freezeAt('2025-01-06 06:10:00');
        $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken])->assertOk();

        $this->freezeAt('2025-01-06 06:55:00');
        $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken])->assertStatus(409);

        $this->assertDatabaseMissing('briefing_attendance', ['guruId' => $guru->id]);
    }

    public function test_checked_in_guru_whose_briefing_was_scanned_by_the_admin_is_not_recorded_twice(): void
    {
        $this->withBriefingSchedule();
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $this->freezeAt('2025-01-06 06:10:00');
        $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken])->assertOk();
        $this->freezeAt('2025-01-06 06:32:00');
        $this->postJson('/briefing/scan', ['qrToken' => $guru->qrToken])->assertOk();

        $this->freezeAt('2025-01-06 06:40:00');
        $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken])->assertStatus(409);

        $briefing = BriefingAttendance::where('guruId', $guru->id)->sole();
        $this->assertSame('06:32:00', $briefing->waktu->format('H:i:s'));
    }

    public function test_pulang_scan_never_records_briefing(): void
    {
        $this->withBriefingSchedule(['jamSelesaiBriefing' => null]);
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $this->freezeAt('2025-01-06 06:10:00');
        $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken])->assertOk();

        $this->freezeAt('2025-01-06 15:31:00');
        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken]);

        $response->assertOk();
        $response->assertJson(['type' => 'PULANG', 'briefing' => false]);
        $this->assertDatabaseMissing('briefing_attendance', ['guruId' => $guru->id]);
    }

    public function test_scanning_unknown_qr_token_returns_404(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $this->freezeAt('2025-01-06 07:05:00');
        $this->actingAsAdmin();

        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => 'does-not-exist']);

        $response->assertStatus(404);
    }

    public function test_non_admin_cannot_reach_kiosk_scan_endpoint(): void
    {
        $this->actingAsGuru();

        $response = $this->postJson('/kiosk/scan-qr', ['qrToken' => 'whatever']);

        $response->assertStatus(403);
    }

    public function test_regenerated_qr_token_invalidates_the_old_one(): void
    {
        $this->setJadwalHari(self::MONDAY);
        $this->freezeAt('2025-01-06 07:05:00');
        $guru = Guru::factory()->create();
        $oldToken = $guru->qrToken;
        $this->actingAsAdmin();

        $this->post("/data-guru/{$guru->id}/qr/regenerate")->assertOk();

        $this->postJson('/kiosk/scan-qr', ['qrToken' => $oldToken])->assertStatus(404);

        $guru->refresh();
        $this->postJson('/kiosk/scan-qr', ['qrToken' => $guru->qrToken])->assertOk();
    }
}
