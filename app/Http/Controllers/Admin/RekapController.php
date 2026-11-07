<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Services\GuruService;
use App\Services\SettingsService;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class RekapController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private GuruService $guruService,
        private SettingsService $settings,
    ) {}

    public function index(Request $request)
    {
        $today = Carbon::today()->toDateString();
        $from = $request->query('from', $today);
        $to = $request->query('to', $today);
        $guruId = $request->query('guruId');

        return Inertia::render('absensi/admin/rekap-page', [
            'rows' => fn () => $this->attendance->rekap(Carbon::parse($from), Carbon::parse($to), $guruId ?: null),
            'from' => $from,
            'to' => $to,
            'guruId' => $guruId ?: 'all',
            'guruList' => fn () => $this->guruService->list(),
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
            'guruId' => ['nullable', 'string'],
        ]);

        $rows = $this->attendance->rekap(Carbon::parse($validated['from']), Carbon::parse($validated['to']), $validated['guruId'] ?? null);

        $body = collect($rows)->map(fn ($r) => [
            $r['nama'],
            $r['tanggal'],
            $r['jamMasuk'] ? Carbon::parse($r['jamMasuk'])->format('H:i') : '',
            $r['jamPulang'] ? Carbon::parse($r['jamPulang'])->format('H:i') : '',
            $r['status'] ?? '',
            $r['catatan'] ?? '',
        ])->all();

        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);
        $period = $from->isSameDay($to)
            ? $from->format('d/m/Y')
            : "{$from->format('d/m/Y')} s/d {$to->format('d/m/Y')}";

        return XlsxExport::download(
            filename: "rekap-absensi-{$validated['from']}-{$validated['to']}.xlsx",
            schoolName: $this->settings->getSettings()->namaSekolah,
            title: 'Rekap Absensi Guru',
            period: $period,
            headers: ['Nama', 'Tanggal', 'Jam Masuk', 'Jam Pulang', 'Status', 'Catatan'],
            rows: $body,
            statusColumnIndex: 4,
        );
    }

    public function manualUpsert(Request $request)
    {
        $validated = $request->validate([
            'guruId' => ['required', 'string'],
            'tanggal' => ['required', 'string'],
            'status' => ['required', 'in:HADIR,TELAT,IZIN,SAKIT,ALPA'],
            'jamMasuk' => ['nullable', 'date_format:H:i'],
            'jamPulang' => ['nullable', 'date_format:H:i'],
            'catatan' => ['nullable', 'string'],
        ]);

        $this->attendance->manualUpsert(
            $validated['guruId'],
            Carbon::parse($validated['tanggal']),
            $validated['status'],
            $validated['jamMasuk'] ?? null,
            $validated['jamPulang'] ?? null,
            $validated['catatan'] ?? null,
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Koreksi kehadiran berhasil disimpan']);
    }
}
