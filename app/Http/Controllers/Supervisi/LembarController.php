<?php

namespace App\Http\Controllers\Supervisi;

use App\Http\Controllers\Controller;
use App\Models\Supervisi\Supervisi;
use App\Services\Supervisi\AksesSupervisi;
use App\Services\Supervisi\JadwalSupervisi;
use App\Services\Supervisi\PenilaianSupervisi;
use App\Services\Supervisi\SupervisiPresenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Lembar supervisi: lihat, simpan draf, selesaikan, buka kunci, dan unduh PDF. */
class LembarController extends Controller
{
    public function __construct(
        private AksesSupervisi $akses,
        private JadwalSupervisi $jadwal,
        private PenilaianSupervisi $penilaian,
    ) {}

    public function show(Request $request, Supervisi $supervisi)
    {
        $user = $request->user();
        abort_unless($this->akses->lihat($user, $supervisi), 403, 'Anda tidak memiliki akses ke supervisi ini.');

        $bolehIsi = $this->akses->isi($user, $supervisi);
        if ($bolehIsi) {
            $this->jadwal->siapkanSkor($supervisi);
        }
        $supervisi->load([...SupervisiPresenter::RELASI, 'skor']);
        $lihatPenilaian = $this->akses->lihatPenilaian($user, $supervisi);

        return Inertia::render('supervisi/lembar-page', [
            'supervisi' => SupervisiPresenter::lembar($supervisi, $lihatPenilaian),
            'boleh' => [
                'isi' => $bolehIsi && $supervisi->bisaDiisi(),
                'bukaKunci' => $this->akses->kelola($user) && $supervisi->selesai(),
                'pdf' => $lihatPenilaian && $supervisi->status !== Supervisi::DIBATALKAN,
            ],
            'pemilik' => $this->akses->guruPemilik($user, $supervisi),
            'skala' => [
                'maks' => (int) config('supervisi.skor_maks'),
                'label' => config('supervisi.skor_label'),
            ],
        ]);
    }

    public function simpan(Request $request, Supervisi $supervisi)
    {
        abort_unless($this->akses->isi($request->user(), $supervisi), 403, 'Anda bukan supervisor untuk lembar ini.');

        [$teks, $skor] = $this->validasi($request);
        $this->penilaian->simpan($supervisi, $teks, $skor);

        return back()->with('toast', ['type' => 'success', 'message' => 'Draf lembar supervisi tersimpan.']);
    }

    /** Simpan isian terakhir lalu selesaikan dalam satu klik. */
    public function selesai(Request $request, Supervisi $supervisi)
    {
        abort_unless($this->akses->isi($request->user(), $supervisi), 403, 'Anda bukan supervisor untuk lembar ini.');

        [$teks, $skor] = $this->validasi($request);
        $this->penilaian->simpan($supervisi, $teks, $skor);
        $this->penilaian->selesaikan($supervisi->refresh(), $request->user());

        $total = $this->angka($supervisi->totalSkor);

        return back()->with('toast', ['type' => 'success', 'message' => "Supervisi selesai. Jumlah Score {$supervisi->jumlahSkor}, Total Score {$total}."]);
    }

    public function bukaKunci(Request $request, Supervisi $supervisi)
    {
        abort_unless($this->akses->kelola($request->user()), 403, 'Hanya kepala sekolah dan admin yang bisa membuka kunci.');

        $this->penilaian->bukaKunci($supervisi);

        return back()->with('toast', ['type' => 'success', 'message' => 'Kunci lembar dibuka. Lembar bisa diisi ulang.']);
    }

    /** Lembar PDF. Sebelum selesai, penilai bisa mencetaknya sebagai formulir kosong untuk diisi di kertas. */
    public function pdf(Request $request, Supervisi $supervisi)
    {
        $user = $request->user();
        abort_unless($this->akses->lihatPenilaian($user, $supervisi), 403, 'Hasil supervisi belum bisa diunduh.');
        abort_if($supervisi->status === Supervisi::DIBATALKAN, 404, 'Supervisi ini dibatalkan.');

        if ($this->akses->isi($user, $supervisi)) {
            $this->jadwal->siapkanSkor($supervisi);
        }
        $supervisi->load([...SupervisiPresenter::RELASI, 'skor']);

        $nama = str($supervisi->guru?->namaTampil() ?? 'guru')->slug();

        return Pdf::loadView('pdf.supervisi', [
            'supervisi' => $supervisi,
            'skala' => config('supervisi.skor_label'),
        ])->setPaper('a4')->download("supervisi-{$nama}-{$supervisi->tanggal->toDateString()}.pdf");
    }

    /** @return array{0: array<string, mixed>, 1: list<array{id: string, skor: int|null, catatan: string|null}>} */
    private function validasi(Request $request): array
    {
        $maks = (int) config('supervisi.skor_maks');
        $data = $request->validate([
            'kelebihan' => ['nullable', 'string', 'max:5000'],
            'halDitingkatkan' => ['nullable', 'string', 'max:5000'],
            'rekomendasi' => ['nullable', 'string', 'max:5000'],
            'skor' => ['present', 'array'],
            'skor.*.id' => ['required', 'string'],
            'skor.*.skor' => ['nullable', 'integer', "between:1,{$maks}"],
            'skor.*.catatan' => ['nullable', 'string', 'max:500'],
        ], [], [
            'kelebihan' => 'kelebihan perencanaan',
            'halDitingkatkan' => 'hal yang perlu ditingkatkan',
            'rekomendasi' => 'rekomendasi perbaikan',
            'skor.*.skor' => 'skor',
            'skor.*.catatan' => 'catatan butir',
        ]);

        $teks = array_intersect_key($data, array_flip(PenilaianSupervisi::TEKS));

        return [$teks, array_values($data['skor'] ?? [])];
    }

    private function angka(?float $nilai): string
    {
        return $nilai === null ? '-' : rtrim(rtrim(number_format($nilai, 2, ',', '.'), '0'), ',');
    }
}
