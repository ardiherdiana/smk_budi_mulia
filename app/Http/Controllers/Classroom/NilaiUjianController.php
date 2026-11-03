<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Post;
use App\Services\Classroom\NilaiUjianService;
use App\Support\Classroom\XlsxExport;
use Illuminate\Http\Request;

/** Ekspor nilai satu ujian ke Excel: satu atau beberapa ruang kelas dari ujian yang sama. */
class NilaiUjianController extends Controller
{
    public function __construct(private NilaiUjianService $nilai) {}

    /**
     * Query: `kelas[]` = id post tiap ruang kelas yang dipilih (kosong = kelas ini saja), `soal` (skor tiap soal, bawaan ya),
     * `belum` (siswa yang belum mengerjakan ikut dicatat, bawaan ya).
     */
    public function ekspor(Request $request, Post $post)
    {
        $user = $request->user();
        abort_if($user->isSiswa(), 403, 'Anda tidak memiliki akses');
        abort_unless($post->tipe === 'UJIAN', 404, 'Postingan ini bukan ujian');

        $kandidat = $this->nilai->kandidat($post, $user);
        abort_unless($kandidat->has($post->id), 403, 'Anda tidak memiliki akses');

        $request->validate([
            'kelas' => ['nullable', 'array', 'max:100'],
            'kelas.*' => ['string', 'max:40'],
            'soal' => ['nullable', 'boolean'],
            'belum' => ['nullable', 'boolean'],
        ]);

        $diminta = collect((array) $request->input('kelas', []))->filter()->unique()->values();
        // Kelas di luar grup ujian ini atau yang tidak boleh diakses ditolak, bukan diabaikan diam-diam.
        abort_if($diminta->contains(fn (string $id) => ! $kandidat->has($id)), 403, 'Anda tidak memiliki akses ke salah satu kelas yang dipilih');

        $dipilih = $diminta->isEmpty()
            ? $kandidat->only([$post->id])
            : $kandidat->filter(fn (Post $p) => $diminta->contains($p->id));

        $berkas = $this->nilai->lembar($dipilih, $request->boolean('soal', true), $request->boolean('belum', true));

        return XlsxExport::downloadSheets($berkas['nama'], config('classroom.nama_sekolah'), $berkas['sheets']);
    }
}
