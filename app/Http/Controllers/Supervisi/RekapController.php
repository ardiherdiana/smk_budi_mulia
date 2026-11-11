<?php

namespace App\Http\Controllers\Supervisi;

use App\Http\Controllers\Controller;
use App\Models\Classroom\Akun;
use App\Models\Classroom\TahunAjaran;
use App\Models\Supervisi\Supervisi;
use App\Services\Supervisi\AksesSupervisi;
use App\Services\Supervisi\SupervisiPresenter;
use App\Support\XlsxExport;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Rekap per tahun ajaran (kepsek/admin): satu baris per supervisi yang tidak dibatalkan, ditambah guru aktif yang
 * belum punya jadwal sama sekali. Ekspor ke xlsx memakai XlsxExport bersama absensi.
 */
class RekapController extends Controller
{
    public function __construct(private AksesSupervisi $akses) {}

    public function index(Request $request)
    {
        $this->pastikanKelola($request);
        $tahun = SupervisiPresenter::tahunAjaranTerpilih($request->query('tahunAjaranId'));

        return Inertia::render('supervisi/rekap-page', [
            'baris' => $this->baris($tahun),
            'tahunAjaran' => SupervisiPresenter::daftarTahunAjaran(),
            'tahunAjaranId' => $tahun?->id,
        ]);
    }

    public function export(Request $request)
    {
        $this->pastikanKelola($request);
        $tahun = SupervisiPresenter::tahunAjaranTerpilih($request->query('tahunAjaranId'));
        abort_if($tahun === null, 404, 'Belum ada tahun ajaran.');

        $label = SupervisiPresenter::labelStatus();
        $angka = fn (?float $n) => $n === null ? '' : number_format($n, 2, ',', '');
        $rows = collect($this->baris($tahun))->values()->map(fn (array $b, int $i) => [
            (string) ($i + 1),
            $b['guru'],
            $b['mapel'] ?? '',
            $b['rombel'] ?? '',
            $b['tanggal'] ? date('d/m/Y', strtotime($b['tanggal'])) : '',
            $b['supervisor'] ?? '',
            $b['jumlahSkor'] === null ? '' : (string) $b['jumlahSkor'],
            $angka($b['totalSkor']),
            $b['status'] ? $label[$b['status']] : 'Belum dijadwalkan',
        ])->all();

        return XlsxExport::download(
            filename: 'rekap-supervisi-'.str($tahun->label())->slug().'.xlsx',
            schoolName: config('school.name'),
            title: 'Rekap Instrumen Observasi Pembelajaran Mendalam',
            period: 'Tahun Ajaran '.$tahun->label(),
            headers: ['No', 'Penyusun Perencanaan', 'Mata Pelajaran', 'Kelas', 'Tanggal Supervisi', 'Pemberi Umpan Balik', 'Jumlah Score', 'Total Score', 'Status'],
            rows: $rows,
            statusColumnIndex: 8,
        );
    }

    /**
     * @return list<array{id: string|null, guruId: int, guru: string, tanggal: string|null, supervisor: string|null,
     *   mapel: string|null, rombel: string|null, jumlahSkor: int|null, totalSkor: float|null, status: string|null}>
     */
    private function baris(?TahunAjaran $tahun): array
    {
        $supervisi = Supervisi::with(SupervisiPresenter::RELASI)
            ->where('tahunAjaranId', $tahun?->id ?? '-')
            ->where('status', '!=', Supervisi::DIBATALKAN)
            ->get();

        $baris = $supervisi->map(fn (Supervisi $s) => [
            'id' => $s->id,
            'guruId' => $s->guruId,
            'guru' => $s->guru?->namaTampil() ?? '-',
            'tanggal' => $s->tanggal->toDateString(),
            'supervisor' => $s->supervisor?->namaTampil(),
            'mapel' => $s->mapel?->nama,
            'rombel' => $s->rombel?->nama,
            'jumlahSkor' => $s->selesai() ? $s->jumlahSkor : null,
            'totalSkor' => $s->selesai() ? $s->totalSkor : null,
            'status' => $s->status,
        ]);

        $sudahAda = $supervisi->pluck('guruId')->unique();
        $belum = Akun::where('role', 'GURU')->where('is_active', true)->whereNotIn('id', $sudahAda)->get(['id', 'name', 'username', 'role'])
            ->map(fn (Akun $g) => [
                'id' => null,
                'guruId' => $g->id,
                'guru' => $g->namaTampil(),
                'tanggal' => null,
                'supervisor' => null,
                'mapel' => null,
                'rombel' => null,
                'jumlahSkor' => null,
                'totalSkor' => null,
                'status' => null,
            ]);

        return $baris->concat($belum)
            ->sortBy(fn (array $b) => mb_strtolower($b['guru']).'|'.($b['tanggal'] ?? ''))
            ->values()->all();
    }

    private function pastikanKelola(Request $request): void
    {
        abort_unless($this->akses->kelola($request->user()), 403, 'Hanya kepala sekolah dan admin yang melihat rekap.');
    }
}
