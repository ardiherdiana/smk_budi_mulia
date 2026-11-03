<?php

namespace App\Services\Classroom;

use App\Models\Classroom\Kelas;
use App\Models\Classroom\Pengumpulan;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Siswa;
use App\Models\Classroom\Soal;

/**
 * Rekap nilai satu ruang kelas: kolom = tugas dan ujian (urut waktu dibuat), baris = siswa di rombelnya.
 */
class NilaiService
{
    /**
     * @return array{
     *   kolom: list<array{id: string, judul: string, tipe: string, maks: float}>,
     *   baris: list<array{siswaId: string, nama: string, nis: ?string, nilai: array<string, ?float>, rata: ?float}>
     * }
     */
    public function matriks(Kelas $kelas): array
    {
        $posts = Post::where('kelasId', $kelas->id)->whereIn('tipe', ['TUGAS', 'UJIAN'])->orderBy('createdAt')->get();
        $idUjian = $posts->where('tipe', 'UJIAN')->pluck('id')->all();

        $totalPoin = $idUjian
            ? Soal::selectRaw('postId, sum(poin) as total')->whereIn('postId', $idUjian)->groupBy('postId')->pluck('total', 'postId')
            : collect();

        $kolom = $posts->map(fn (Post $p) => [
            'id' => $p->id,
            'judul' => $p->judul ?? '(tanpa judul)',
            'tipe' => $p->tipe,
            'maks' => $p->tipe === 'TUGAS' ? (float) ($p->poinMaks ?? 100) : (float) ($totalPoin[$p->id] ?? 0),
        ])->values()->all();

        $nilaiTugas = $posts->where('tipe', 'TUGAS')->isNotEmpty()
            ? Pengumpulan::whereIn('postId', $posts->where('tipe', 'TUGAS')->pluck('id'))->whereNotNull('nilai')->get()->groupBy('siswaId')
            : collect();
        $nilaiUjian = $idUjian
            ? Percobaan::whereIn('postId', $idUjian)->whereIn('status', ['SELESAI', 'DINILAI'])->get()->groupBy('siswaId')
            : collect();

        $baris = [];
        foreach (Siswa::where('rombelId', $kelas->rombelId)->orderBy('nama')->get() as $siswa) {
            $nilai = [];
            foreach ($nilaiTugas->get($siswa->id, collect()) as $p) {
                $nilai[$p->postId] = $p->nilai;
            }
            foreach ($nilaiUjian->get($siswa->id, collect()) as $p) {
                $nilai[$p->postId] = $p->skorTotal();
            }

            $persen = [];
            foreach ($kolom as $k) {
                if (isset($nilai[$k['id']]) && $k['maks'] > 0) {
                    $persen[] = $nilai[$k['id']] / $k['maks'] * 100;
                }
            }

            $baris[] = [
                'siswaId' => $siswa->id,
                'nama' => $siswa->nama,
                'nis' => $siswa->nis,
                'nilai' => collect($kolom)->mapWithKeys(fn ($k) => [$k['id'] => $nilai[$k['id']] ?? null])->all(),
                'rata' => $persen ? round(array_sum($persen) / count($persen), 1) : null,
            ];
        }

        return ['kolom' => $kolom, 'baris' => $baris];
    }
}
