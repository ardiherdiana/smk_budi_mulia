<?php

namespace App\Http\Controllers\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Sppd\PengajuanSppd;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $query = PengajuanSppd::query()->with('pemohon');

        if (! $user->isAdmin() && ! $user->hasAnyRole([RoleName::KepalaSekolah->value, RoleName::Tu->value, RoleName::Bendahara->value])) {
            $query->where('pemohon_id', $user->id);
        }

        $semua = (clone $query)->get();

        $stats = [
            'total' => $semua->count(),
            'menunggu_persetujuan' => $semua->where('status', PengajuanStatus::DiajukanKeKepsek)->count(),
            'menunggu_nomor' => $semua->where('status', PengajuanStatus::MenungguNomor)->count(),
            'sedang_ditugaskan' => $semua->where('status', PengajuanStatus::SedangDitugaskan)->count(),
            'selesai' => $semua->where('status', PengajuanStatus::Selesai)->count(),
        ];

        $terbaru = (clone $query)->latest()->take(8)->get()->map(fn (PengajuanSppd $p) => [
            'id' => $p->id,
            'pemohon' => $p->pemohon->name,
            'tujuan' => $p->tujuan,
            'status' => $p->status->value,
            'status_label' => $p->status->label(),
            'tanggal_berangkat' => $p->tanggal_berangkat->toDateString(),
        ]);

        return Inertia::render('sppd/dashboard', [
            'stats' => $stats,
            'terbaru' => $terbaru,
        ]);
    }
}
