<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Guru;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Services\JadwalService;
use App\Services\WebPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendAttendanceReminders extends Command
{
    protected $signature = 'attendance:send-reminders';

    protected $description = 'Send push reminders 15 minutes before jam masuk/pulang';

    const REMINDER_MINUTES_BEFORE = 15;

    public function handle(JadwalService $jadwalService, WebPushService $webPush): void
    {
        $today = Carbon::today();
        $jadwal = $jadwalService->getJadwalForDate($today);
        if (! $jadwal->aktif) {
            return;
        }

        if (Holiday::whereDate('tanggal', $today->toDateString())->exists()) {
            return;
        }

        $masukReminderAt = Carbon::parse($today->toDateString().' '.$jadwal->jamMasuk)->subMinutes(self::REMINDER_MINUTES_BEFORE);
        $pulangReminderAt = Carbon::parse($today->toDateString().' '.$jadwal->jamPulang)->subMinutes(self::REMINDER_MINUTES_BEFORE);

        $now = Carbon::now()->format('H:i');
        if ($now === $masukReminderAt->format('H:i')) {
            $this->remindMasuk($today, $webPush);
        }
        if ($now === $pulangReminderAt->format('H:i')) {
            $this->remindPulang($today, $webPush);
        }
    }

    private function remindMasuk(Carbon $today, WebPushService $webPush): void
    {
        $guruList = Guru::where('aktif', true)->get(['id', 'userId']);
        $attendedIds = Attendance::whereDate('tanggal', $today->toDateString())->whereNotNull('jamMasuk')->pluck('guruId')->all();
        $onLeaveIds = LeaveRequest::where('status', 'APPROVED')
            ->whereDate('tanggalMulai', '<=', $today->toDateString())
            ->whereDate('tanggalSelesai', '>=', $today->toDateString())
            ->pluck('guruId')->all();

        foreach ($guruList as $guru) {
            if (in_array($guru->id, $attendedIds, true) || in_array($guru->id, $onLeaveIds, true)) {
                continue;
            }
            $webPush->sendPushToUser($guru->userId, 'Jangan lupa absen masuk', '15 menit lagi jam masuk - jangan lupa absen ya!', '/dashboard');
        }
    }

    private function remindPulang(Carbon $today, WebPushService $webPush): void
    {
        $belumPulang = Attendance::whereDate('tanggal', $today->toDateString())
            ->whereNotNull('jamMasuk')
            ->whereNull('jamPulang')
            ->with('guru:id,userId')
            ->get();

        foreach ($belumPulang as $attendance) {
            $webPush->sendPushToUser($attendance->guru->userId, 'Jangan lupa absen pulang', '15 menit lagi jam pulang - jangan lupa absen ya!', '/dashboard');
        }
    }
}
