<?php

namespace App\Http\Controllers\Supervisi;

use App\Http\Controllers\Controller;
use App\Models\Supervisi\Supervisi;
use App\Services\Supervisi\SupervisiPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Daftar milik pengguna: "Supervisi Saya" (sebagai guru) dan "Tugas Supervisi" (sebagai supervisor). */
class DaftarController extends Controller
{
    public function saya(Request $request)
    {
        return $this->render($request, 'saya', 'guruId');
    }

    public function tugas(Request $request)
    {
        return $this->render($request, 'tugas', 'supervisorId');
    }

    private function render(Request $request, string $mode, string $kolom)
    {
        $tahun = SupervisiPresenter::tahunAjaranTerpilih($request->query('tahunAjaranId'));

        $list = Supervisi::with(SupervisiPresenter::RELASI)
            ->where($kolom, $request->user()->id)
            ->where('tahunAjaranId', $tahun?->id ?? '-')
            ->when($mode === 'saya', fn ($q) => $q->where('status', '!=', Supervisi::DIBATALKAN))
            ->orderByDesc('tanggal')
            ->get();

        return Inertia::render('supervisi/daftar-page', [
            'mode' => $mode,
            'list' => $list->map(SupervisiPresenter::ringkas(...))->values(),
            'tahunAjaran' => SupervisiPresenter::daftarTahunAjaran(),
            'tahunAjaranId' => $tahun?->id,
        ]);
    }
}
