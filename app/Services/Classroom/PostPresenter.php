<?php

namespace App\Services\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Komentar;
use App\Models\Classroom\Lampiran;
use App\Models\Classroom\Pengumpulan;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Bentuk data post untuk halaman: stream, daftar tugas, dan detail. Satu tempat supaya semua halaman konsisten
 * dan kueri status siswa/ringkasan guru dilakukan massal (bukan satu kueri per post).
 */
class PostPresenter
{
    public function __construct(private UjianService $ujian) {}

    public static function iso(?Carbon $waktu): ?string
    {
        return $waktu?->toIso8601String();
    }

    /** @return array<string, mixed> */
    public function kelas(Kelas $kelas, Akun $user): array
    {
        $kelas->loadMissing(['mapel:id,nama', 'rombel:id,nama,waliKelasId', 'guru:id,name,username', 'tahunAjaran']);

        return [
            'id' => $kelas->id,
            'mapel' => $kelas->mapel->nama,
            'rombel' => $kelas->rombel->nama,
            'guru' => $kelas->guru->namaTampil(),
            'tahunAjaran' => $kelas->tahunAjaran->label(),
            'deskripsi' => $kelas->deskripsi,
            'diarsipkan' => $kelas->diarsipkan,
            'jumlahSiswa' => $kelas->rombel->siswa()->count(),
            'dapatDikelola' => $kelas->dapatDikelola($user),
        ];
    }

    /**
     * Daftar post siap tampil, lengkap dengan status siswa (untuk SISWA) atau ringkasan pengumpulan (untuk staf).
     *
     * @param  Collection<int, Post>  $posts
     * @return list<array<string, mixed>>
     */
    public function daftar(Collection $posts, Akun $user, bool $denganKomentar = true): array
    {
        $posts->loadMissing('pembuat:id,name,username,role', 'lampiran');
        if ($denganKomentar) {
            $posts->loadMissing(['komentar.user:id,name,username,role,guruStaffId', 'komentar.user.staff:id,image']);
        }

        $idTugas = $posts->where('tipe', 'TUGAS')->pluck('id')->all();
        $idUjian = $posts->where('tipe', 'UJIAN')->pluck('id')->all();

        $siswaId = $user->isSiswa() ? $user->siswa?->id : null;

        $pengumpulanSaya = $siswaId && $idTugas
            ? Pengumpulan::whereIn('postId', $idTugas)->where('siswaId', $siswaId)->get()->keyBy('postId')
            : collect();
        $percobaanSaya = $siswaId && $idUjian
            ? Percobaan::whereIn('postId', $idUjian)->where('siswaId', $siswaId)->get()->keyBy('postId')
            : collect();

        $ringkasanTugas = collect();
        $ringkasanUjian = collect();
        if (! $user->isSiswa()) {
            $ringkasanTugas = $idTugas
                ? Pengumpulan::selectRaw('postId, count(*) as dikumpulkan, sum(status = ?) as dinilai', ['DINILAI'])
                    ->whereIn('postId', $idTugas)->groupBy('postId')->get()->keyBy('postId')
                : collect();
            $ringkasanUjian = $idUjian
                ? Percobaan::selectRaw('postId, count(*) as mulai, sum(status != ?) as selesai', ['BERJALAN'])
                    ->whereIn('postId', $idUjian)->groupBy('postId')->get()->keyBy('postId')
                : collect();
        }

        // Jumlah ruang kelas dalam grup postingan multi-kelas (satu kueri untuk semua post).
        $idGrup = $posts->pluck('grupId')->filter()->unique()->values()->all();
        $jumlahGrup = $idGrup
            ? Post::selectRaw('grupId, count(*) as total')->whereIn('grupId', $idGrup)->groupBy('grupId')->pluck('total', 'grupId')
            : collect();

        return $posts->map(function (Post $post) use ($user, $denganKomentar, $pengumpulanSaya, $percobaanSaya, $ringkasanTugas, $ringkasanUjian, $jumlahGrup) {
            $item = $this->dasar($post, $user);
            $item['jumlahGrup'] = $post->grupId ? (int) ($jumlahGrup[$post->grupId] ?? 1) : 1;

            if ($denganKomentar) {
                $item['komentar'] = $post->komentar->sortBy('createdAt')->map(fn (Komentar $k) => $this->komentar($k, $user, $post))->values()->all();
            }

            if ($post->tipe === 'TUGAS') {
                if ($user->isSiswa()) {
                    $item['pengumpulanSaya'] = $this->ringkasPengumpulan($pengumpulanSaya->get($post->id));
                } else {
                    $r = $ringkasanTugas->get($post->id);
                    $item['ringkasan'] = ['dikumpulkan' => (int) ($r->dikumpulkan ?? 0), 'dinilai' => (int) ($r->dinilai ?? 0)];
                }
            }

            if ($post->tipe === 'UJIAN') {
                if ($user->isSiswa()) {
                    $p = $percobaanSaya->get($post->id);
                    $item['percobaanSaya'] = $p ? ['status' => $p->status] : null;
                    $item['jendela'] = $this->ujian->jendela($post);
                } else {
                    $r = $ringkasanUjian->get($post->id);
                    $item['ringkasan'] = ['mulai' => (int) ($r->mulai ?? 0), 'selesai' => (int) ($r->selesai ?? 0)];
                }
            }

            return $item;
        })->values()->all();
    }

    /** @return array<string, mixed> */
    public function dasar(Post $post, Akun $user): array
    {
        return [
            'id' => $post->id,
            'kelasId' => $post->kelasId,
            'tipe' => $post->tipe,
            'judul' => $post->judul,
            'isi' => $post->isi,
            'deadline' => self::iso($post->deadline),
            'poinMaks' => $post->poinMaks,
            'izinTerlambat' => $post->izinTerlambat,
            'durasiMenit' => $post->durasiMenit,
            'mulaiAt' => self::iso($post->mulaiAt),
            'selesaiAt' => self::iso($post->selesaiAt),
            'acakSoal' => $post->acakSoal,
            'tampilkanNilai' => $post->tampilkanNilai,
            'wajibToken' => $post->tokenAkses !== null,
            'createdAt' => self::iso($post->createdAt),
            'updatedAt' => self::iso($post->updatedAt),
            'pembuat' => $post->pembuat?->namaTampil(),
            'lampiran' => $post->lampiran->map(fn (Lampiran $l) => $this->lampiran($l))->values()->all(),
        ] + ($user->isSiswa() ? [] : ['tokenAkses' => $post->tokenAkses]); // token tidak pernah dikirim ke siswa (seperti kunci jawaban)
    }

    /** @return array<string, mixed> */
    public function lampiran(Lampiran $lampiran): array
    {
        return [
            'id' => $lampiran->id,
            'tipe' => $lampiran->tipe,
            'nama' => $lampiran->nama,
            'url' => $lampiran->tipe === 'FILE' ? "/classroom/lampiran/{$lampiran->id}" : $lampiran->url,
            'ukuran' => $lampiran->ukuran,
            'mime' => $lampiran->mime,
        ];
    }

    /** @return array<string, mixed> */
    public function komentar(Komentar $komentar, Akun $user, Post $post): array
    {
        return [
            'id' => $komentar->id,
            'isi' => $komentar->isi,
            'createdAt' => self::iso($komentar->createdAt),
            'user' => [
                'id' => $komentar->user->id,
                'name' => $komentar->user->namaTampil(),
                'role' => $komentar->user->role,
                'fotoUrl' => $komentar->user->fotoUrl(),
            ],
            'dapatDihapus' => $komentar->userId === $user->id || $post->kelas->dapatDikelola($user),
        ];
    }

    /** @return array<string, mixed>|null */
    public function ringkasPengumpulan(?Pengumpulan $p): ?array
    {
        if (! $p) {
            return null;
        }

        return [
            'id' => $p->id,
            'status' => $p->status,
            'terlambat' => $p->terlambat,
            'nilai' => $p->nilai,
            'feedback' => $p->feedback,
            'teks' => $p->teks,
            'submittedAt' => self::iso($p->submittedAt),
            'gradedAt' => self::iso($p->gradedAt),
        ];
    }
}
