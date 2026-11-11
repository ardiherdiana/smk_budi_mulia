<?php

namespace App\Http\Controllers\Supervisi;

use App\Http\Controllers\Controller;
use App\Models\Classroom\Akun;
use App\Models\Supervisi\Supervisi;
use App\Services\Supervisi\AksesSupervisi;
use App\Services\Supervisi\SupervisiPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Ringkasan semester: progres (kepsek/admin), jadwal terdekat, tugas sebagai supervisor, dan supervisi milik guru. */
class DashboardController extends Controller
{
    public function __invoke(Request $request, AksesSupervisi $akses)
    {
        $user = $request->user();
        $tahun = SupervisiPresenter::tahunAjaranTerpilih($request->query('tahunAjaranId'));
        $kelola = $akses->kelola($user);
        $diTahun = fn () => Supervisi::with(SupervisiPresenter::RELASI)->where('tahunAjaranId', $tahun?->id ?? '-');

        $props = [
            'tahunAjaran' => SupervisiPresenter::daftarTahunAjaran(),
            'tahunAjaranId' => $tahun?->id,
            'kelola' => $kelola,
            'tugas' => $diTahun()->where('supervisorId', $user->id)->where('status', Supervisi::DIJADWALKAN)
                ->orderBy('tanggal')->get()->map(SupervisiPresenter::ringkas(...))->values(),
            'saya' => $diTahun()->where('guruId', $user->id)->where('status', '!=', Supervisi::DIBATALKAN)
                ->orderByDesc('tanggal')->get()->map(SupervisiPresenter::ringkas(...))->values(),
            'ringkasan' => null,
        ];

        if ($kelola) {
            $guru = Akun::where('role', 'GURU')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'username', 'role']);
            $baris = $diTahun()->where('status', '!=', Supervisi::DIBATALKAN)->get();
            $guruSelesai = $baris->where('status', Supervisi::SELESAI)->pluck('guruId')->unique();
            $guruTerjadwal = $baris->where('status', Supervisi::DIJADWALKAN)->pluck('guruId')->unique()->diff($guruSelesai);
            $belum = $guru->reject(fn (Akun $g) => $guruSelesai->contains($g->id) || $guruTerjadwal->contains($g->id));

            $props['ringkasan'] = [
                'jumlahGuru' => $guru->count(),
                'sudah' => $guru->filter(fn (Akun $g) => $guruSelesai->contains($g->id))->count(),
                'dijadwalkan' => $guru->filter(fn (Akun $g) => $guruTerjadwal->contains($g->id))->count(),
                'belum' => $belum->count(),
                'akanDatang' => $baris->where('status', Supervisi::DIJADWALKAN)
                    ->filter(fn (Supervisi $s) => $s->tanggal->gte(today()))
                    ->sortBy('tanggal')->take(5)->map(SupervisiPresenter::ringkas(...))->values(),
                'belumDijadwalkan' => $belum->map(fn (Akun $g) => SupervisiPresenter::orang($g))->values(),
            ];
        }

        return Inertia::render('supervisi/dashboard-page', $props);
    }
}
