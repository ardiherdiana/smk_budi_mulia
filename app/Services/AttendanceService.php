<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Guru;
use App\Models\Holiday;
use App\Models\JadwalHari;
use App\Models\LeaveRequest;
use App\Support\Geofence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        private JadwalService $jadwal,
        private NotificationService $notifications,
        private BriefingService $briefing,
    ) {}

    /** Blocks an action from happening before `hhmm` on `tanggal` - shared by
     * both the absen-masuk-opens and absen-pulang-opens gates below. */
    private function assertWindowOpen(Carbon $tanggal, Carbon $now, string $hhmm, string $message): void
    {
        $gate = Carbon::parse($tanggal->toDateString().' '.$hhmm);
        if ($now->lt($gate)) {
            abort(409, $message);
        }
    }

    /** Determines Hadir vs Telat for a check-in happening at `jamMasuk` on
     * `tanggal`, against that day's own schedule (jadwal). */
    private function resolveStatusMasuk(Carbon $tanggal, Carbon $jamMasuk, JadwalHari $jadwal): string
    {
        $batas = Carbon::parse($tanggal->toDateString().' '.$jadwal->batasTelat);

        return $jamMasuk->lte($batas) ? 'HADIR' : 'TELAT';
    }

    /** Shared masuk/pulang state machine behind both check-in paths (QR
     * statis scan di alat sekolah, dan geofenced web check-in) - each path
     * only differs in how it verifies the guru is legitimately present
     * before calling this. `$viaKiosk` lets a scan from a guru who already
     * checked in count as their briefing attendance instead (see below). */
    private function recordAttendance(string $userId, bool $viaKiosk = false): array
    {
        $guru = Guru::where('userId', $userId)->first();
        if (! $guru) {
            abort(403, 'Akun ini bukan akun guru');
        }
        if (! $guru->aktif) {
            abort(403, 'Akun guru sudah dinonaktifkan');
        }

        $tanggal = Carbon::today();
        $jadwal = $this->jadwal->getJadwalForDate($tanggal);
        if (! $jadwal->aktif) {
            abort(409, 'Hari ini bukan hari aktif sekolah sesuai jadwal');
        }
        $now = Carbon::now();

        $existing = Attendance::where('guruId', $guru->id)->whereDate('tanggal', $tanggal)->first();

        if (! $existing) {
            $this->assertWindowOpen($tanggal, $now, $jadwal->jamMulaiAbsen, "Absen masuk belum dibuka (mulai {$jadwal->jamMulaiAbsen})");
            $statusMasuk = $this->resolveStatusMasuk($tanggal, $now, $jadwal);
            $attendance = Attendance::create([
                'guruId' => $guru->id,
                'tanggal' => $tanggal,
                'jamMasuk' => $now,
                'statusMasuk' => $statusMasuk,
            ]);
            $this->notifications->create(
                'CHECKIN',
                'Absen Masuk',
                "{$guru->nama} absen masuk (".($statusMasuk === 'TELAT' ? 'Telat' : 'Hadir').')',
                $guru->id
            );

            return ['type' => 'MASUK', 'statusMasuk' => $statusMasuk, 'jam' => $now, 'attendance' => $attendance, 'nama' => $guru->nama, 'fotoUrl' => $guru->fotoTampil()];
        }

        if (! $existing->jamMasuk) {
            $this->assertWindowOpen($tanggal, $now, $jadwal->jamMulaiAbsen, "Absen masuk belum dibuka (mulai {$jadwal->jamMulaiAbsen})");
            $statusMasuk = $this->resolveStatusMasuk($tanggal, $now, $jadwal);
            $existing->update(['jamMasuk' => $now, 'statusMasuk' => $statusMasuk]);
            $this->notifications->create(
                'CHECKIN',
                'Absen Masuk',
                "{$guru->nama} absen masuk (".($statusMasuk === 'TELAT' ? 'Telat' : 'Hadir').')',
                $guru->id
            );

            return ['type' => 'MASUK', 'statusMasuk' => $statusMasuk, 'jam' => $now, 'attendance' => $existing, 'nama' => $guru->nama, 'fotoUrl' => $guru->fotoTampil()];
        }

        if (! $existing->jamPulang) {
            // Already checked in, scanning at the kiosk again before jam pulang:
            // while the briefing is in progress and not yet recorded, that scan
            // is the guru's briefing attendance. From jam pulang on it is always
            // the pulang scan, even for a briefing with no closing time.
            $gatePulang = Carbon::parse($tanggal->toDateString().' '.$jadwal->jamPulang);
            if ($viaKiosk && $now->lt($gatePulang) && $this->briefing->catatDariScanKiosk($guru, $now)) {
                return ['type' => 'BRIEFING', 'jam' => $now, 'attendance' => $existing, 'nama' => $guru->nama, 'fotoUrl' => $guru->fotoTampil()];
            }

            $this->assertWindowOpen(
                $tanggal,
                $now,
                $jadwal->jamPulang,
                "Belum waktunya absen pulang (mulai {$jadwal->jamPulang}). Absen masuk Anda sudah tercatat, jangan scan/absen lagi sebelum jam pulang."
            );
            $existing->update(['jamPulang' => $now, 'statusPulang' => 'HADIR']);
            $this->notifications->create('CHECKOUT', 'Absen Pulang', "{$guru->nama} absen pulang", $guru->id);

            return ['type' => 'PULANG', 'jam' => $now, 'attendance' => $existing, 'nama' => $guru->nama, 'fotoUrl' => $guru->fotoTampil()];
        }

        abort(409, 'Anda sudah absen masuk dan pulang hari ini');
    }

    /** Scan-station check-in: the school's kiosk device reads a guru's
     * permanent static QR (their `qrToken`) through its own camera and this
     * resolves it back to a guru identity - the device itself authenticates
     * as ADMIN, physical presence at the device is what's actually being
     * verified here, not who is logged into the browser.
     *
     * While today's briefing is in progress the scan also counts as the
     * guru's briefing attendance (`briefing` in the result), in the same
     * transaction: a guru who hasn't checked in yet gets absen masuk and
     * briefing from one scan (type MASUK); one who already checked in gets
     * the briefing only (type BRIEFING). The other way to record a briefing
     * is the admin scanning on the briefing page (BriefingService::checkinBriefing). */
    public function checkinByQrToken(string $qrToken): array
    {
        $guru = Guru::where('qrToken', $qrToken)->first();
        if (! $guru) {
            abort(404, 'QR tidak dikenali');
        }

        return DB::transaction(function () use ($guru) {
            $result = $this->recordAttendance($guru->userId, viaKiosk: true);
            $result['briefing'] = match ($result['type']) {
                'MASUK' => $this->briefing->catatDariScanKiosk($guru, $result['jam']),
                'BRIEFING' => true,
                default => false,
            };

            return $result;
        });
    }

    /** Web check-in from the guru's own device, gated on being physically at
     * school instead of a scanned QR - the browser-reported GPS position
     * must fall within SCHOOL_RADIUS_METERS of the school's coordinates. */
    public function checkinWeb(string $userId, float $lat, float $lng): array
    {
        $distance = Geofence::distanceFromSchoolMeters($lat, $lng);
        if ($distance > Geofence::SCHOOL_RADIUS_METERS) {
            $rounded = round($distance);
            abort(403, "Anda berada sekitar {$rounded}m dari sekolah - di luar radius ".Geofence::SCHOOL_RADIUS_METERS.'m yang diizinkan untuk absen web');
        }

        return $this->recordAttendance($userId);
    }

    public function myAttendance(string $guruId, Carbon $from, Carbon $to)
    {
        return Attendance::where('guruId', $guruId)
            ->whereDate('tanggal', '>=', $from->toDateString())
            ->whereDate('tanggal', '<=', $to->toDateString())
            ->orderByDesc('tanggal')
            ->get();
    }

    /** @return array<int, array{guruId: string, nama: string, tanggal: string, jamMasuk: ?string, jamPulang: ?string, status: ?string, catatan: ?string}> */
    public function rekap(Carbon $from, Carbon $to, ?string $guruId = null): array
    {
        $guruQuery = Guru::query()->orderBy('nama');
        if ($guruId) {
            $guruQuery->where('id', $guruId);
        }
        $guruList = $guruQuery->get(['id', 'nama']);

        $attendances = Attendance::query()
            ->when($guruId, fn ($q) => $q->where('guruId', $guruId))
            ->whereDate('tanggal', '>=', $from->toDateString())
            ->whereDate('tanggal', '<=', $to->toDateString())
            ->get();

        $leaves = LeaveRequest::query()
            ->when($guruId, fn ($q) => $q->where('guruId', $guruId))
            ->where('status', 'APPROVED')
            ->whereDate('tanggalMulai', '<=', $to->toDateString())
            ->whereDate('tanggalSelesai', '>=', $from->toDateString())
            ->get();

        $holidays = Holiday::whereDate('tanggal', '>=', $from->toDateString())
            ->whereDate('tanggal', '<=', $to->toDateString())
            ->get();
        $holidaySet = $holidays->map(fn ($h) => $h->tanggal->toDateString())->flip();
        $jadwalMap = $this->jadwal->getJadwalMap();

        $attendanceMap = $attendances->keyBy(fn ($a) => $a->guruId.'_'.$a->tanggal->toDateString());
        $today = Carbon::today();
        $rows = [];

        foreach ($guruList as $guru) {
            foreach ($this->eachDate($from, $to) as $date) {
                $isSchoolDay = optional($jadwalMap->get($date->dayOfWeek))->aktif ?? false;
                if (! $isSchoolDay || $holidaySet->has($date->toDateString())) {
                    continue;
                }

                $key = $guru->id.'_'.$date->toDateString();
                $attendance = $attendanceMap->get($key);

                if ($attendance) {
                    $rows[] = [
                        'guruId' => $guru->id,
                        'nama' => $guru->nama,
                        'tanggal' => $date->toDateString(),
                        'jamMasuk' => $attendance->jamMasuk?->toIso8601String(),
                        'jamPulang' => $attendance->jamPulang?->toIso8601String(),
                        'status' => $attendance->statusMasuk,
                        'catatan' => $attendance->catatan,
                    ];

                    continue;
                }

                $leave = $leaves->first(
                    fn ($l) => $l->guruId === $guru->id
                        && $l->tanggalMulai->toDateString() <= $date->toDateString()
                        && $l->tanggalSelesai->toDateString() >= $date->toDateString()
                );

                if ($leave) {
                    $rows[] = [
                        'guruId' => $guru->id,
                        'nama' => $guru->nama,
                        'tanggal' => $date->toDateString(),
                        'jamMasuk' => null,
                        'jamPulang' => null,
                        'status' => $leave->jenis,
                        'catatan' => null,
                    ];

                    continue;
                }

                $rows[] = [
                    'guruId' => $guru->id,
                    'nama' => $guru->nama,
                    'tanggal' => $date->toDateString(),
                    'jamMasuk' => null,
                    'jamPulang' => null,
                    'status' => $date->lte($today) ? 'ALPA' : null,
                    'catatan' => null,
                ];
            }
        }

        return $rows;
    }

    /** @return Carbon[] */
    private function eachDate(Carbon $from, Carbon $to): array
    {
        $dates = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $dates[] = $cursor->copy();
            $cursor->addDay();
        }

        return $dates;
    }

    const STREAK_LOOKBACK_DAYS = 365;

    /** Current consecutive-working-day attendance streak for one guru -
     * HADIR, TELAT, IZIN, and SAKIT all continue it, only ALPA breaks it.
     * Weekends and holidays are skipped (neither counted nor able to break
     * the streak). If today is a working day the guru hasn't checked in /
     * gone on leave for yet, it's excluded from the count rather than
     * treated as an in-progress ALPA - otherwise the streak would show 0
     * every morning before the guru has had a chance to scan in. */
    public function myStreak(string $guruId): int
    {
        $today = Carbon::today();
        $windowStart = $today->copy()->subDays(self::STREAK_LOOKBACK_DAYS);

        $attendances = Attendance::where('guruId', $guruId)
            ->whereDate('tanggal', '>=', $windowStart->toDateString())
            ->whereDate('tanggal', '<=', $today->toDateString())
            ->get()
            ->keyBy(fn ($a) => $a->tanggal->toDateString());

        $leaves = LeaveRequest::where('guruId', $guruId)
            ->where('status', 'APPROVED')
            ->whereDate('tanggalMulai', '<=', $today->toDateString())
            ->whereDate('tanggalSelesai', '>=', $windowStart->toDateString())
            ->get();

        $holidaySet = Holiday::whereDate('tanggal', '>=', $windowStart->toDateString())
            ->whereDate('tanggal', '<=', $today->toDateString())
            ->get()
            ->map(fn ($h) => $h->tanggal->toDateString())
            ->flip();

        $jadwalMap = $this->jadwal->getJadwalMap();

        $isWorkday = function (Carbon $date) use ($jadwalMap, $holidaySet) {
            $isSchoolDay = optional($jadwalMap->get($date->dayOfWeek))->aktif ?? false;

            return $isSchoolDay && ! $holidaySet->has($date->toDateString());
        };

        $findLeave = fn (Carbon $date) => $leaves->first(
            fn ($l) => $l->tanggalMulai->toDateString() <= $date->toDateString()
                && $l->tanggalSelesai->toDateString() >= $date->toDateString()
        );

        $isResolved = fn (Carbon $date) => $attendances->has($date->toDateString()) || $findLeave($date) !== null;

        $statusFor = function (Carbon $date) use ($attendances, $findLeave) {
            $attendance = $attendances->get($date->toDateString());
            if ($attendance) {
                return $attendance->statusMasuk ?? 'ALPA';
            }
            $leave = $findLeave($date);

            return $leave ? $leave->jenis : 'ALPA';
        };

        $cursor = $today->copy();
        if ($isWorkday($cursor) && ! $isResolved($cursor)) {
            $cursor->subDay();
        }

        $streak = 0;
        for ($i = 0; $i < self::STREAK_LOOKBACK_DAYS; $i++) {
            if ($cursor->lt($windowStart)) {
                break;
            }
            if (! $isWorkday($cursor)) {
                $cursor->subDay();

                continue;
            }
            if ($statusFor($cursor) === 'ALPA') {
                break;
            }
            $streak++;
            $cursor->subDay();
        }

        return $streak;
    }

    /** One guru's streak plus a day-by-day breakdown of the current calendar
     * month (including future/not-yet-elapsed days, which come back with
     * `status: null` from rekap() - the view renders those as empty cells) -
     * backs the admin "lihat detail" popup on the Data Guru page. */
    public function guruDetail(string $guruId): array
    {
        $guru = Guru::find($guruId, ['id', 'userId', 'nama', 'fotoUrl']);
        if (! $guru) {
            abort(404, 'Guru tidak ditemukan');
        }

        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth()->startOfDay();

        $days = $this->rekap($monthStart, $monthEnd, $guruId);
        $streak = $this->myStreak($guruId);

        return ['guruId' => $guru->id, 'nama' => $guru->nama, 'fotoUrl' => $guru->fotoTampil(), 'streak' => $streak, 'days' => $days];
    }

    public function manualUpsert(string $guruId, Carbon $tanggal, string $status, ?string $jamMasuk, ?string $jamPulang, ?string $catatan): Attendance
    {
        return Attendance::updateOrCreate(
            ['guruId' => $guruId, 'tanggal' => $tanggal->toDateString()],
            [
                'statusMasuk' => $status,
                'jamMasuk' => $jamMasuk ? Carbon::parse($tanggal->toDateString().' '.$jamMasuk) : null,
                'jamPulang' => $jamPulang ? Carbon::parse($tanggal->toDateString().' '.$jamPulang) : null,
                'catatan' => $catatan,
            ]
        );
    }
}
