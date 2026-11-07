<?php

namespace Tests\Feature\Absensi\Attendance;

use App\Models\Attendance;
use App\Models\Guru;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Services\AttendanceService;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\AbsensiTestCase;

class RekapAndStreakTest extends AbsensiTestCase
{
    /** Mon..Fri active, Sat/Sun inactive - a normal school week. */
    private function seedWeekSchedule(): void
    {
        foreach (range(0, 6) as $hari) {
            $this->setJadwalHari($hari, ['aktif' => $hari !== 0 && $hari !== 6]);
        }
    }

    public function test_rekap_classifies_each_day_correctly_and_skips_non_school_days(): void
    {
        $this->seedWeekSchedule();
        $this->freezeAt('2025-01-10 12:00:00'); // Friday - "today" for this test
        $guru = Guru::factory()->create();

        // Mon 2025-01-06: present, with a correction note attached.
        Attendance::create([
            'guruId' => $guru->id,
            'tanggal' => '2025-01-06',
            'jamMasuk' => '2025-01-06 07:05:00',
            'statusMasuk' => 'HADIR',
            'catatan' => 'Datang naik motor, telat isi bensin',
        ]);

        // Tue 2025-01-07: on approved leave.
        LeaveRequest::create([
            'guruId' => $guru->id,
            'jenis' => 'IZIN',
            'tanggalMulai' => '2025-01-07',
            'tanggalSelesai' => '2025-01-07',
            'alasan' => 'Acara keluarga',
            'status' => 'APPROVED',
        ]);

        // Wed 2025-01-08: declared a school holiday - must not appear at all.
        Holiday::create(['tanggal' => '2025-01-08', 'keterangan' => 'Libur Umum']);

        // Thu 2025-01-09 and Fri 2025-01-10 (today): left unresolved -> ALPA.
        // Sat/Sun 2025-01-11/12: non-school days, must not appear.
        // Mon 2025-01-13: in the future relative to "today" -> status null.

        $rows = app(AttendanceService::class)->rekap(Carbon::parse('2025-01-06'), Carbon::parse('2025-01-13'), $guru->id);
        $byDate = collect($rows)->keyBy('tanggal');

        $this->assertCount(5, $rows);
        $this->assertSame('HADIR', $byDate['2025-01-06']['status']);
        $this->assertSame('Datang naik motor, telat isi bensin', $byDate['2025-01-06']['catatan']);
        $this->assertSame('IZIN', $byDate['2025-01-07']['status']);
        $this->assertNull($byDate['2025-01-07']['catatan']);
        $this->assertFalse($byDate->has('2025-01-08'));
        $this->assertSame('ALPA', $byDate['2025-01-09']['status']);
        $this->assertNull($byDate['2025-01-09']['catatan']);
        $this->assertSame('ALPA', $byDate['2025-01-10']['status']);
        $this->assertFalse($byDate->has('2025-01-11'));
        $this->assertFalse($byDate->has('2025-01-12'));
        $this->assertNull($byDate['2025-01-13']['status']);
    }

    public function test_streak_counts_consecutive_present_school_days_and_skips_weekends(): void
    {
        $this->seedWeekSchedule();
        $this->freezeAt('2025-01-10 12:00:00'); // Friday
        $guru = Guru::factory()->create();

        foreach (['2025-01-06', '2025-01-07', '2025-01-08', '2025-01-09', '2025-01-10'] as $tanggal) {
            Attendance::create(['guruId' => $guru->id, 'tanggal' => $tanggal, 'statusMasuk' => 'HADIR']);
        }

        $streak = app(AttendanceService::class)->myStreak($guru->id);

        // Mon-Fri all HADIR; the Friday before (2025-01-03) has no record at
        // all, which resolves to ALPA and stops the count there.
        $this->assertSame(5, $streak);
    }

    public function test_streak_breaks_on_an_unresolved_past_workday(): void
    {
        $this->seedWeekSchedule();
        $this->freezeAt('2025-01-10 12:00:00'); // Friday
        $guru = Guru::factory()->create();

        // Wed 2025-01-08 is deliberately left unresolved (ALPA).
        Attendance::create(['guruId' => $guru->id, 'tanggal' => '2025-01-09', 'statusMasuk' => 'HADIR']);
        Attendance::create(['guruId' => $guru->id, 'tanggal' => '2025-01-10', 'statusMasuk' => 'HADIR']);

        $streak = app(AttendanceService::class)->myStreak($guru->id);

        $this->assertSame(2, $streak);
    }

    public function test_streak_does_not_penalize_today_before_the_guru_has_checked_in(): void
    {
        $this->seedWeekSchedule();
        $this->freezeAt('2025-01-10 07:00:00'); // Friday morning, not checked in yet
        $guru = Guru::factory()->create();

        foreach (['2025-01-06', '2025-01-07', '2025-01-08', '2025-01-09'] as $tanggal) {
            Attendance::create(['guruId' => $guru->id, 'tanggal' => $tanggal, 'statusMasuk' => 'HADIR']);
        }

        $streak = app(AttendanceService::class)->myStreak($guru->id);

        $this->assertSame(4, $streak);
    }

    public function test_admin_manual_upsert_corrects_attendance(): void
    {
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $response = $this->post('/rekap/manual', [
            'guruId' => $guru->id,
            'tanggal' => '2025-01-06',
            'status' => 'SAKIT',
            'catatan' => 'Surat dokter menyusul',
        ]);

        $response->assertSessionHas('toast');
        $this->assertDatabaseHas('attendance', [
            'guruId' => $guru->id,
            'statusMasuk' => 'SAKIT',
            'catatan' => 'Surat dokter menyusul',
        ]);
    }

    public function test_rekap_export_returns_an_xlsx_download(): void
    {
        $guru = Guru::factory()->create();
        $this->actingAsAdmin();

        $response = $this->get('/rekap/export?'.http_build_query([
            'from' => '2025-01-06',
            'to' => '2025-01-06',
            'guruId' => $guru->id,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_rekap_export_includes_the_catatan_column_and_correction_note(): void
    {
        $this->setJadwalHari(1); // Monday, active
        $guru = Guru::factory()->create();
        Attendance::create([
            'guruId' => $guru->id,
            'tanggal' => '2025-01-06',
            'jamMasuk' => '2025-01-06 07:05:00',
            'statusMasuk' => 'SAKIT',
            'catatan' => 'Surat dokter menyusul',
        ]);
        $this->actingAsAdmin();

        $response = $this->get('/rekap/export?'.http_build_query([
            'from' => '2025-01-06',
            'to' => '2025-01-06',
            'guruId' => $guru->id,
        ]));
        $response->assertOk();

        $tmpFile = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
        file_put_contents($tmpFile, $response->streamedContent());
        $sheet = IOFactory::load($tmpFile)->getActiveSheet();
        unlink($tmpFile);

        // Header row is row 5 and data starts at row 6 - see XlsxExport::download().
        $this->assertSame('Status', $sheet->getCell('E5')->getValue());
        $this->assertSame('Catatan', $sheet->getCell('F5')->getValue());
        $this->assertSame('SAKIT', $sheet->getCell('E6')->getValue());
        $this->assertSame('Surat dokter menyusul', $sheet->getCell('F6')->getValue());
    }

    public function test_guru_cannot_access_rekap(): void
    {
        $this->actingAsGuru();

        $this->get('/rekap')->assertStatus(403);
    }
}
