<?php

namespace Tests\Feature\Absensi\Briefing;

use App\Models\BriefingAttendance;
use App\Models\Guru;
use Tests\AbsensiTestCase;

class BriefingAttendanceTest extends AbsensiTestCase
{
    private const MONDAY = 1;

    private function withBriefingSchedule(): void
    {
        $this->setJadwalHari(self::MONDAY, [
            'jamBriefing' => '06:30',
            'jamSelesaiBriefing' => '06:50',
        ]);
    }

    public function test_admin_scanning_guru_qr_during_window_records_briefing_checkin(): void
    {
        $this->withBriefingSchedule();
        $this->freezeAt('2025-01-06 06:35:00');
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $response = $this->postJson('/briefing/scan', ['qrToken' => $guru->qrToken]);

        $response->assertOk();
        $response->assertJson(['nama' => $guru->nama]);
        $this->assertDatabaseHas('briefing_attendance', ['guruId' => $guru->id]);
    }

    public function test_scan_before_briefing_window_opens_is_rejected(): void
    {
        $this->withBriefingSchedule();
        $this->freezeAt('2025-01-06 06:00:00');
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $response = $this->postJson('/briefing/scan', ['qrToken' => $guru->qrToken]);

        $response->assertStatus(409);
    }

    public function test_scan_after_briefing_window_closes_is_rejected(): void
    {
        $this->withBriefingSchedule();
        $this->freezeAt('2025-01-06 07:00:00');
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $response = $this->postJson('/briefing/scan', ['qrToken' => $guru->qrToken]);

        $response->assertStatus(409);
    }

    public function test_scan_on_day_without_briefing_scheduled_is_rejected(): void
    {
        $this->setJadwalHari(self::MONDAY, ['jamBriefing' => null, 'jamSelesaiBriefing' => null]);
        $this->freezeAt('2025-01-06 06:35:00');
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $response = $this->postJson('/briefing/scan', ['qrToken' => $guru->qrToken]);

        $response->assertStatus(409);
    }

    public function test_scanning_twice_in_one_day_is_rejected(): void
    {
        $this->withBriefingSchedule();
        $this->freezeAt('2025-01-06 06:35:00');
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $this->postJson('/briefing/scan', ['qrToken' => $guru->qrToken])->assertOk();
        $response = $this->postJson('/briefing/scan', ['qrToken' => $guru->qrToken]);

        $response->assertStatus(409);
    }

    public function test_real_scan_supersedes_a_prior_manual_alpa_correction(): void
    {
        $this->withBriefingSchedule();
        $this->freezeAt('2025-01-06 06:35:00');
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $this->postJson('/briefing/manual', [
            'guruId' => $guru->id,
            'tanggal' => '2025-01-06',
            'status' => 'ALPA',
        ])->assertOk();

        $this->postJson('/briefing/scan', ['qrToken' => $guru->qrToken])->assertOk();

        $record = BriefingAttendance::where('guruId', $guru->id)->first();
        $this->assertNotNull($record->waktu);
        $this->assertNull($record->status);
    }

    public function test_non_admin_cannot_access_briefing_page(): void
    {
        $this->actingAsGuru();

        $response = $this->get('/briefing');

        $response->assertStatus(403);
    }
}
