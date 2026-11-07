<?php

namespace App\Http\Controllers;

use App\Services\BriefingService;
use App\Services\GuruService;
use App\Services\SettingsService;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class BriefingController extends Controller
{
    public function __construct(
        private BriefingService $briefing,
        private GuruService $guruService,
        private SettingsService $settings,
    ) {}

    public function index()
    {
        $today = Carbon::today();

        return Inertia::render('absensi/admin/briefing-page', [
            'guruList' => fn () => $this->guruService->list(),
            'rows' => fn () => $this->briefing->rekapBriefing($today, $today),
            'from' => $today->toDateString(),
            'to' => $today->toDateString(),
            'jamBriefing' => fn () => $this->briefing->jamBriefingHariIni(),
            'jamSelesaiBriefing' => fn () => $this->briefing->jamSelesaiBriefingHariIni(),
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'qrToken' => ['required', 'string', 'min:1'],
        ]);

        return response()->json($this->briefing->checkinBriefing($validated['qrToken']));
    }

    public function manualUpsert(Request $request)
    {
        $validated = $request->validate([
            'guruId' => ['required', 'string'],
            'tanggal' => ['required', 'string'],
            'status' => ['required', 'in:HADIR,TELAT,IZIN,SAKIT,ALPA'],
            'waktu' => ['nullable', 'date_format:H:i'],
            'catatan' => ['nullable', 'string'],
        ]);

        $this->briefing->manualUpsert(
            $validated['guruId'],
            Carbon::parse($validated['tanggal']),
            $validated['status'],
            $validated['waktu'] ?? null,
            $validated['catatan'] ?? null,
        );

        return response()->json(['success' => true]);
    }

    public function rekap(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
            'guruId' => ['nullable', 'string'],
        ]);

        return response()->json($this->briefing->rekapBriefing(
            Carbon::parse($validated['from']),
            Carbon::parse($validated['to']),
            $validated['guruId'] ?? null,
        ));
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
            'guruId' => ['nullable', 'string'],
        ]);

        $rows = $this->briefing->rekapBriefing(Carbon::parse($validated['from']), Carbon::parse($validated['to']), $validated['guruId'] ?? null);

        $body = collect($rows)->filter(fn ($r) => $r['status'] !== null)->map(fn ($r) => [
            $r['nama'],
            $r['tanggal'],
            $r['waktu'] ? Carbon::parse($r['waktu'])->format('H:i') : '',
            $r['status'] ?? '',
        ])->values()->all();

        $from = Carbon::parse($validated['from']);
        $to = Carbon::parse($validated['to']);
        $period = $from->isSameDay($to)
            ? $from->format('d/m/Y')
            : "{$from->format('d/m/Y')} s/d {$to->format('d/m/Y')}";

        return XlsxExport::download(
            filename: "rekap-briefing-{$validated['from']}-{$validated['to']}.xlsx",
            schoolName: $this->settings->getSettings()->namaSekolah,
            title: 'Rekap Absen Briefing',
            period: $period,
            headers: ['Nama', 'Tanggal', 'Waktu', 'Status'],
            rows: $body,
        );
    }
}
