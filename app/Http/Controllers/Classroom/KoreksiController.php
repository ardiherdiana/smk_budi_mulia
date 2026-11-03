<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Jawaban;
use App\Models\Classroom\Percobaan;
use App\Services\Classroom\PostPresenter;
use App\Services\Classroom\SoalGambarService;
use App\Services\Classroom\UjianService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Sisi guru: melihat jawaban satu siswa dan memberi skor essay. */
class KoreksiController extends Controller
{
    public function __construct(private UjianService $ujian, private PostPresenter $presenter, private SoalGambarService $gambar) {}

    public function show(Request $request, Percobaan $percobaan)
    {
        $percobaan->loadMissing('post.kelas', 'siswa');
        $post = $percobaan->post;
        abort_unless($post->kelas->dapatDikelola($request->user()), 403, 'Anda tidak memiliki akses');

        $this->ujian->finalisasiJikaKedaluwarsa($percobaan);
        $percobaan->refresh();

        $jawaban = $percobaan->jawaban()->get()->keyBy('soalId');

        return Inertia::render('classroom/ujian/koreksi-page', [
            'kelas' => $this->presenter->kelas($post->kelas, $request->user()),
            'post' => ['id' => $post->id, 'judul' => $post->judul],
            'siswa' => ['nama' => $percobaan->siswa->nama, 'nis' => $percobaan->siswa->nis],
            'percobaan' => [
                'id' => $percobaan->id,
                'status' => $percobaan->status,
                'mulaiAt' => PostPresenter::iso($percobaan->mulaiAt),
                'selesaiAt' => PostPresenter::iso($percobaan->selesaiAt),
                'skorOtomatis' => $percobaan->skorOtomatis,
                'skorManual' => $percobaan->skorManual,
                'skorTotal' => $percobaan->skorTotal(),
            ],
            'totalPoin' => $this->ujian->totalPoin($post),
            'soal' => $post->soal()->get()->map(function ($s) use ($jawaban) {
                $j = $jawaban->get($s->id);

                return [
                    'id' => $s->id,
                    'urut' => $s->urut,
                    'tipe' => $s->tipe,
                    'teks' => $s->teks,
                    'poin' => $s->poin,
                    'opsi' => $s->opsi,
                    ...$this->gambar->tampil($s),
                    'kunci' => $s->kunci,
                    'kompetensi' => $s->kompetensi,
                    'subKompetensi' => $s->subKompetensi,
                    'jawabanId' => $j?->id,
                    'jawaban' => $j?->jawaban,
                    'skor' => $j?->skor,
                ];
            })->values()->all(),
        ]);
    }

    public function skor(Request $request, Jawaban $jawaban)
    {
        $jawaban->loadMissing('soal', 'percobaan.post.kelas');
        abort_unless($jawaban->percobaan->post->kelas->dapatDikelola($request->user()), 403, 'Anda tidak memiliki akses');
        abort_unless($jawaban->soal->tipe === 'ESSAY', 422, 'Hanya soal essay yang dinilai manual');
        abort_if($jawaban->percobaan->status === 'BERJALAN', 409, 'Siswa masih mengerjakan ujian');

        $maks = $jawaban->soal->poin;
        $validated = $request->validate([
            'skor' => ['required', 'numeric', 'min:0', "max:{$maks}"],
        ], [
            'skor.required' => 'Skor wajib diisi',
            'skor.max' => "Skor maksimal {$maks}",
            'skor.min' => 'Skor tidak boleh negatif',
        ]);

        $jawaban->update(['skor' => $validated['skor']]);
        $this->ujian->hitungUlangKoreksi($jawaban->percobaan);

        return back()->with('toast', ['type' => 'success', 'message' => 'Skor disimpan']);
    }
}
