<?php

namespace App\Services;

use App\Models\BriefingAttendance;
use App\Models\Guru;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use Illuminate\Support\Carbon;

class BriefingService
{
    public function __construct(private JadwalService $jadwal) {}

    /** Today's briefing time, or null if today isn't a school day or has no
     * briefing scheduled - backs the admin scan page's empty state so the
     * camera only turns on once there's actually something to scan for. */
    public function jamBriefingHariIni(): ?string
    {
        $jadwalHari = $this->jadwal->getJadwalForDate(Carbon::today());

        return $jadwalHari->aktif ? $jadwalHari->jamBriefing : null;
    }

    /** Today's briefing closing time, or null if there's no closing gate
     * configured (or no briefing today at all) - null here means the scan
     * window simply never closes, matching this feature's original
     * behavior before this field existed. */
    public function jamSelesaiBriefingHariIni(): ?string
    {
        $jadwalHari = $this->jadwal->getJadwalForDate(Carbon::today());

        return $jadwalHari->aktif && $jadwalHari->jamBriefing ? $jadwalHari->jamSelesaiBriefing : null;
    }

    /** @return array{nama: string, fotoUrl: ?string, waktu: Carbon} */
    public function checkinBriefing(string $qrToken): array
    {
        $guru = Guru::where('qrToken', $qrToken)->first();
        if (! $guru) {
            abort(404, 'QR tidak dikenali');
        }
        if (! $guru->aktif) {
            abort(403, 'Akun guru sudah dinonaktifkan');
        }

        $tanggal = Carbon::today();
        $jadwalHari = $this->jadwal->getJadwalForDate($tanggal);
        if (! $jadwalHari->aktif) {
            abort(409, 'Hari ini bukan hari aktif sekolah sesuai jadwal');
        }
        if (! $jadwalHari->jamBriefing) {
            abort(409, 'Tidak ada jadwal briefing hari ini');
        }

        $now = Carbon::now();
        $gate = Carbon::parse($tanggal->toDateString().' '.$jadwalHari->jamBriefing);
        if ($now->lt($gate)) {
            abort(409, "Absen briefing belum dibuka (mulai {$jadwalHari->jamBriefing})");
        }

        if ($jadwalHari->jamSelesaiBriefing) {
            $closeGate = Carbon::parse($tanggal->toDateString().' '.$jadwalHari->jamSelesaiBriefing);
            if ($now->gt($closeGate)) {
                abort(409, "Absen briefing sudah ditutup (berakhir jam {$jadwalHari->jamSelesaiBriefing})");
            }
        }

        $existing = BriefingAttendance::where('guruId', $guru->id)->whereDate('tanggal', $tanggal)->first();
        if ($existing && $existing->waktu) {
            abort(409, "{$guru->nama} sudah absen briefing hari ini");
        }

        $record = $this->simpanScan($guru, $tanggal, $now, $existing);

        return ['nama' => $guru->nama, 'fotoUrl' => $guru->fotoTampil(), 'waktu' => $record->waktu];
    }

    /** Briefing attendance from a scan at the kiosk (the school's scanner
     * device), for both a guru's first absen-masuk scan and a later scan
     * after they already checked in: when today's briefing is in progress
     * (started, not yet closed) and the guru has no scanned briefing yet, the
     * scan records the briefing. Outside that window - no briefing today, not
     * started, already closed, or already scanned - it does nothing and never
     * aborts, so the caller's own check-in logic is unaffected.
     *
     * @return bool whether a briefing attendance was recorded by this call */
    public function catatDariScanKiosk(Guru $guru, Carbon $now): bool
    {
        $tanggal = $now->copy()->startOfDay();
        $jadwalHari = $this->jadwal->getJadwalForDate($tanggal);
        if (! $jadwalHari->aktif || ! $jadwalHari->jamBriefing) {
            return false;
        }

        if ($now->lt(Carbon::parse($tanggal->toDateString().' '.$jadwalHari->jamBriefing))) {
            return false;
        }
        if ($jadwalHari->jamSelesaiBriefing && $now->gt(Carbon::parse($tanggal->toDateString().' '.$jadwalHari->jamSelesaiBriefing))) {
            return false;
        }

        $existing = BriefingAttendance::where('guruId', $guru->id)->whereDate('tanggal', $tanggal)->first();
        if ($existing && $existing->waktu) {
            return false;
        }

        $this->simpanScan($guru, $tanggal, $now, $existing);

        return true;
    }

    /** Writes the briefing row for a real scan, shared by the briefing scan
     * page and the kiosk absen-masuk scan. */
    private function simpanScan(Guru $guru, Carbon $tanggal, Carbon $now, ?BriefingAttendance $existing): BriefingAttendance
    {
        if ($existing) {
            // A manual correction (e.g. ALPA) already exists for today without
            // an actual scan - the real scan now taking place supersedes it.
            $existing->update(['waktu' => $now, 'status' => null, 'catatan' => null]);

            return $existing;
        }

        return BriefingAttendance::create([
            'guruId' => $guru->id,
            'tanggal' => $tanggal,
            'waktu' => $now,
        ]);
    }

    /** Admin correction from the briefing rekap table - lets an admin set/fix
     * the status, check-in time, and reason for one guru's briefing
     * attendance on one date, the same way AttendanceService::manualUpsert
     * does for regular attendance. */
    public function manualUpsert(string $guruId, Carbon $tanggal, string $status, ?string $waktu, ?string $catatan): BriefingAttendance
    {
        return BriefingAttendance::updateOrCreate(
            ['guruId' => $guruId, 'tanggal' => $tanggal->toDateString()],
            [
                'status' => $status,
                'waktu' => $waktu ? Carbon::parse($tanggal->toDateString().' '.$waktu) : null,
                'catatan' => $catatan,
            ]
        );
    }

    /** @return array<int, array{guruId: string, nama: string, tanggal: string, waktu: ?string, status: 'HADIR'|'TELAT'|'IZIN'|'SAKIT'|'ALPA'|null}> */
    public function rekapBriefing(Carbon $from, Carbon $to, ?string $guruId = null): array
    {
        $guruQuery = Guru::query()->orderBy('nama');
        if ($guruId) {
            $guruQuery->where('id', $guruId);
        }
        $guruList = $guruQuery->get(['id', 'nama']);

        $records = BriefingAttendance::query()
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

        $holidaySet = Holiday::whereDate('tanggal', '>=', $from->toDateString())
            ->whereDate('tanggal', '<=', $to->toDateString())
            ->get()
            ->map(fn ($h) => $h->tanggal->toDateString())
            ->flip();

        $jadwalMap = $this->jadwal->getJadwalMap();

        $recordMap = $records->keyBy(fn ($r) => $r->guruId.'_'.$r->tanggal->toDateString());
        $today = Carbon::today();
        $rows = [];

        foreach ($guruList as $guru) {
            foreach ($this->eachDate($from, $to) as $date) {
                $jadwalHari = $jadwalMap->get($date->dayOfWeek);
                $adaBriefing = ($jadwalHari->aktif ?? false) && ! empty($jadwalHari?->jamBriefing) && ! $holidaySet->has($date->toDateString());

                if (! $adaBriefing) {
                    $rows[] = ['guruId' => $guru->id, 'nama' => $guru->nama, 'tanggal' => $date->toDateString(), 'waktu' => null, 'status' => null];

                    continue;
                }

                $record = $recordMap->get($guru->id.'_'.$date->toDateString());
                if ($record) {
                    $rows[] = [
                        'guruId' => $guru->id,
                        'nama' => $guru->nama,
                        'tanggal' => $date->toDateString(),
                        'waktu' => $record->waktu?->toIso8601String(),
                        // A real scan never sets `status` (only `waktu`), so it
                        // defaults to HADIR; a manual correction always sets it
                        // explicitly, including to HADIR/TELAT with no `waktu`.
                        'status' => $record->status ?? 'HADIR',
                    ];

                    continue;
                }

                $onLeave = $leaves->contains(
                    fn ($l) => $l->guruId === $guru->id
                        && $l->tanggalMulai->toDateString() <= $date->toDateString()
                        && $l->tanggalSelesai->toDateString() >= $date->toDateString()
                );

                $rows[] = [
                    'guruId' => $guru->id,
                    'nama' => $guru->nama,
                    'tanggal' => $date->toDateString(),
                    'waktu' => null,
                    'status' => $onLeave || $date->gt($today) ? null : 'ALPA',
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
}
