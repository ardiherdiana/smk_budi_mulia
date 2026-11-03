<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Siswa;
use App\Services\Classroom\PostPresenter;
use App\Services\Classroom\SoalGambarService;
use App\Services\Classroom\UjianService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Sisi siswa: mulai, mengerjakan (simpan otomatis), dan menyelesaikan ujian. */
class UjianController extends Controller
{
    public function __construct(private UjianService $ujian, private PostPresenter $presenter, private SoalGambarService $gambar) {}

    public function mulai(Request $request, Post $post)
    {
        $siswa = $this->siswa($request, $post);
        $validated = $request->validate(['token' => ['nullable', 'string', 'max:20']]);
        $this->ujian->mulai($post, $siswa, $validated['token'] ?? null);

        return redirect("/classroom/post/{$post->id}/kerjakan");
    }

    public function kerjakan(Request $request, Post $post)
    {
        $siswa = $this->siswa($request, $post);
        $percobaan = Percobaan::where(['postId' => $post->id, 'siswaId' => $siswa->id])->first();

        if (! $percobaan) {
            return redirect("/classroom/post/{$post->id}");
        }

        $this->ujian->finalisasiJikaKedaluwarsa($percobaan);
        $percobaan->refresh();
        if ($percobaan->status !== 'BERJALAN') {
            return redirect("/classroom/post/{$post->id}");
        }

        $jawaban = $percobaan->jawaban()->pluck('jawaban', 'soalId');
        $soal = $post->soal()->get();
        if ($post->acakSoal) {
            // Urutan acak yang tetap untuk percobaan ini (dasar: id percobaan), jadi refresh tidak mengocok ulang.
            $soal = $soal->sortBy(fn ($s) => crc32($percobaan->id.$s->id))->values();
        }

        $post->loadMissing('kelas.mapel:id,nama', 'kelas.rombel:id,nama');

        return Inertia::render('classroom/ujian/kerjakan-page', [
            'post' => ['id' => $post->id, 'judul' => $post->judul, 'isi' => $post->isi, 'kelasId' => $post->kelasId, 'durasiMenit' => $post->durasiMenit],
            'kelas' => ['mapel' => $post->kelas->mapel->nama, 'rombel' => $post->kelas->rombel->nama],
            'totalPoin' => $this->ujian->totalPoin($post),
            'percobaanId' => $percobaan->id,
            'sisaDetik' => $this->ujian->sisaDetik($percobaan),
            // Kunci jawaban tidak pernah dikirim ke siswa.
            'soal' => $soal->map(fn ($s) => [
                'id' => $s->id,
                'tipe' => $s->tipe,
                'teks' => $s->teks,
                'poin' => $s->poin,
                'opsi' => $s->opsi,
                ...$this->gambar->tampil($s),
                'jawaban' => $jawaban[$s->id] ?? null,
            ])->values()->all(),
        ]);
    }

    /** Simpan satu jawaban (dipanggil otomatis tiap siswa memilih/mengetik). */
    public function jawab(Request $request, Percobaan $percobaan)
    {
        $this->milikSaya($request, $percobaan);

        if ($this->ujian->finalisasiJikaKedaluwarsa($percobaan) || $percobaan->refresh()->status !== 'BERJALAN') {
            return response()->json(['message' => 'Waktu ujian sudah habis'], 409);
        }

        $validated = $request->validate([
            'soalId' => ['required', 'string'],
            'jawaban' => ['nullable', 'string', 'max:20000'],
        ]);

        abort_unless($percobaan->post->soal()->whereKey($validated['soalId'])->exists(), 404, 'Soal tidak ditemukan');

        $this->ujian->simpanJawaban($percobaan, $validated['soalId'], $validated['jawaban'] ?? null);

        return response()->json(['ok' => true, 'sisaDetik' => $this->ujian->sisaDetik($percobaan)]);
    }

    public function selesai(Request $request, Percobaan $percobaan)
    {
        $this->milikSaya($request, $percobaan);

        $this->ujian->selesai($percobaan);

        return redirect("/classroom/post/{$percobaan->postId}")->with('toast', ['type' => 'success', 'message' => 'Ujian selesai dikumpulkan']);
    }

    private function siswa(Request $request, Post $post): Siswa
    {
        $post->loadMissing('kelas');
        abort_unless($post->tipe === 'UJIAN', 404, 'Postingan ini bukan ujian');
        $siswa = $request->user()->siswa;
        abort_unless($siswa && $post->kelas->dapatDiakses($request->user()), 403, 'Anda tidak memiliki akses');

        return $siswa;
    }

    private function milikSaya(Request $request, Percobaan $percobaan): void
    {
        $percobaan->loadMissing('post');
        abort_unless($request->user()->siswa?->id === $percobaan->siswaId, 403, 'Anda tidak memiliki akses');
    }
}
