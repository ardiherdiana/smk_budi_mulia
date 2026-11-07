<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use App\Models\Sppd\PengajuanSppd;
use App\Services\Sppd\SppdPdfData;
use App\Support\XlsxExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LaporanController extends Controller
{
    public function index(Request $request): Response
    {
        $tahun = $request->integer('tahun') ?: (int) now()->year;

        $query = PengajuanSppd::query()
            ->with(['pemohon', 'pencairans'])
            ->whereYear('tanggal_berangkat', $tahun);

        return Inertia::render('sppd/laporan/index', [
            'items' => $query->latest('tanggal_berangkat')->paginate($this->perPage($request))->withQueryString(),
            'filters' => ['tahun' => $tahun],
        ]);
    }

    public function exportExcel(Request $request)
    {
        $tahun = $request->integer('tahun') ?: null;

        $items = PengajuanSppd::query()
            ->with(['pemohon', 'pencairans'])
            ->when($tahun, fn ($q) => $q->whereYear('tanggal_berangkat', $tahun))
            ->orderBy('tanggal_berangkat')
            ->get();

        $rows = $items->map(fn (PengajuanSppd $pengajuan) => [
            $pengajuan->pemohon->name,
            $pengajuan->tujuan,
            $pengajuan->tanggal_berangkat->toDateString(),
            substr($pengajuan->jam_berangkat, 0, 5),
            $pengajuan->tanggal_kembali->toDateString(),
            substr($pengajuan->jam_kembali, 0, 5),
            $pengajuan->status->label(),
            number_format((float) $pengajuan->pencairans->sum('jumlah'), 2, ',', '.'),
        ])->all();

        return XlsxExport::download(
            filename: 'laporan-keuangan-sppd'.($tahun ? "-{$tahun}" : '').'.xlsx',
            schoolName: SppdPdfData::SEKOLAH,
            title: 'Laporan Keuangan Perjalanan Dinas',
            period: $tahun ? (string) $tahun : 'Semua tahun',
            headers: ['Pemohon', 'Tujuan', 'Tanggal Berangkat', 'Jam Berangkat', 'Tanggal Kembali', 'Jam Kembali', 'Status', 'Total Pencairan (Rp)'],
            rows: $rows,
            statusColumnIndex: 6,
        );
    }

    public function exportPdf(Request $request)
    {
        $tahun = $request->integer('tahun') ?: (int) now()->year;

        $items = PengajuanSppd::query()
            ->with(['pemohon', 'pencairans'])
            ->whereYear('tanggal_berangkat', $tahun)
            ->orderBy('tanggal_berangkat')
            ->get();

        return Pdf::loadView('pdf.laporan-keuangan', ['items' => $items, 'tahun' => $tahun])
            ->download("laporan-keuangan-sppd-{$tahun}.pdf");
    }
}
