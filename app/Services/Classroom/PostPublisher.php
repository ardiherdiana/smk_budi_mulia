<?php

namespace App\Services\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Lampiran;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Menerbitkan satu postingan ke beberapa ruang kelas sekaligus. Hasilnya satu baris `classroom_posts` per kelas
 * (jadi pengumpulan, nilai, komentar, dan agenda tetap per kelas) yang ditautkan lewat `grupId`. Edit, hapus, dan
 * soal ujian bisa diterapkan ke seluruh grup.
 */
class PostPublisher
{
    public function __construct(private LampiranService $lampiran, private SoalGambarService $soalGambar) {}

    /**
     * Ruang kelas yang boleh dituju dari `$asal`: milik guru pengajar yang sama, tahun ajaran sama, belum diarsipkan.
     *
     * @return Collection<int, Kelas>
     */
    public function tujuan(Kelas $asal): Collection
    {
        return Kelas::with(['rombel:id,nama,tingkat,jurusan', 'mapel:id,nama'])
            ->where('guruId', $asal->guruId)
            ->where('tahunAjaranId', $asal->tahunAjaranId)
            ->where('diarsipkan', false)
            ->get()
            ->sortBy(fn (Kelas $k) => $k->rombel->nama.'|'.$k->mapel->nama)
            ->values();
    }

    /**
     * Buat postingan di `$asal` dan di setiap kelas tujuan. Lampiran disimpan sekali lalu digandakan per kelas.
     *
     * @param  array<string, mixed>  $atribut  kolom post tanpa kelasId, grupId, tipe, dibuatOleh
     * @param  list<string>  $idTujuan
     * @param  list<UploadedFile>  $berkas
     * @param  list<string>  $tautan
     * @return list<Post> post di kelas asal selalu pertama
     */
    public function terbitkan(Kelas $asal, Akun $user, string $tipe, array $atribut, array $idTujuan, array $berkas, array $tautan): array
    {
        $kelasTujuan = $this->pilihTujuan($asal, $idTujuan);
        $grupId = count($kelasTujuan) > 1 ? (string) Str::ulid() : null;

        return $this->transaksi(function (array &$jejak) use ($kelasTujuan, $grupId, $user, $tipe, $atribut, $berkas, $tautan) {
            $posts = [];
            foreach ($kelasTujuan as $kelas) {
                $posts[] = Post::create($atribut + [
                    'kelasId' => $kelas->id,
                    'grupId' => $grupId,
                    'tipe' => $tipe,
                    'dibuatOleh' => $user->id,
                ]);
            }

            $utama = $posts[0];
            $this->lampiran->simpan('postId', $utama->id, $berkas, $tautan);
            $lampiranUtama = Lampiran::where('postId', $utama->id)->get();
            array_push($jejak, ...$lampiranUtama->where('tipe', 'FILE')->pluck('path')->all());

            foreach (array_slice($posts, 1) as $salinan) {
                foreach ($lampiranUtama as $l) {
                    $path = $this->lampiran->salin($l, 'postId', $salinan->id);
                    if ($path) {
                        $jejak[] = $path;
                    }
                }
            }

            return $posts;
        });
    }

    /**
     * Terapkan perubahan sebuah post ke saudaranya dalam grup (yang boleh dikelola `$user` dan belum diarsipkan).
     * Lampiran yang dihapus dicari padanannya lewat (tipe, nama, url); lampiran baru digandakan ke tiap saudara.
     *
     * @param  array<string, mixed>  $atribut
     * @param  iterable<Lampiran>  $dihapus  salinan data lampiran yang baru dihapus dari `$post`
     * @param  iterable<Lampiran>  $baru  lampiran yang baru ditambahkan ke `$post`
     * @return int jumlah saudara yang diperbarui
     */
    public function terapkanKeSaudara(Post $post, Akun $user, array $atribut, iterable $dihapus, iterable $baru): int
    {
        $saudara = $this->saudaraDapatDikelola($post, $user, tanpaArsip: true);

        $this->transaksi(function (array &$jejak) use ($saudara, $atribut, $dihapus, $baru) {
            foreach ($saudara as $s) {
                $s->update($atribut);

                foreach ($dihapus as $d) {
                    $padanan = Lampiran::where('postId', $s->id)->where('tipe', $d->tipe)->where('nama', $d->nama)
                        ->when($d->tipe === 'LINK', fn ($q) => $q->where('url', $d->url))
                        ->first();
                    if ($padanan) {
                        $this->lampiran->hapus($padanan);
                    }
                }

                foreach ($baru as $b) {
                    $path = $this->lampiran->salin($b, 'postId', $s->id);
                    if ($path) {
                        $jejak[] = $path;
                    }
                }
            }
        });

        return $saudara->count();
    }

    /**
     * Samakan soal semua saudara ujian dengan soal `$sumber`. Saudara yang sudah dikerjakan siswa dilewati
     * karena mengubah soal akan merusak nilai yang sudah keluar.
     *
     * @return array{disalin: int, dilewati: int}
     */
    public function sinkronkanSoal(Post $sumber, Akun $user): array
    {
        $soal = $sumber->soal()->get();
        $disalin = 0;
        $dilewati = 0;
        $gambarLama = [];

        foreach ($this->saudaraDapatDikelola($sumber, $user, tanpaArsip: true) as $saudara) {
            if ($saudara->tipe !== 'UJIAN') {
                continue;
            }
            if (Percobaan::where('postId', $saudara->id)->exists()) {
                $dilewati++;

                continue;
            }

            $gambarLama = [...$gambarLama, ...$saudara->soal()->get()->flatMap(fn ($s) => $s->semuaGambar())->all()];

            DB::transaction(function () use ($saudara, $soal) {
                $saudara->soal()->delete();
                foreach ($soal as $item) {
                    // Gambar dibagi (path yang sama), bukan digandakan; berkas baru dihapus bila tak ada soal yang merujuknya.
                    $saudara->soal()->create($item->only(['urut', 'tipe', 'teks', 'gambar', 'kompetensi', 'subKompetensi', 'poin', 'opsi', 'opsiGambar', 'kunci']));
                }
            });
            $disalin++;
        }

        $this->soalGambar->lepas($gambarLama);

        return ['disalin' => $disalin, 'dilewati' => $dilewati];
    }

    /**
     * Post lain dalam grup yang sama (kosong bila post berdiri sendiri), lengkap dengan kelasnya.
     *
     * @return Collection<int, Post>
     */
    public function saudara(Post $post): Collection
    {
        if (! $post->grupId) {
            return new Collection;
        }

        return Post::with('kelas.rombel:id,nama')->where('grupId', $post->grupId)->where('id', '!=', $post->id)->get();
    }

    /** @return Collection<int, Post> */
    public function saudaraDapatDikelola(Post $post, Akun $user, bool $tanpaArsip = false): Collection
    {
        return $this->saudara($post)
            ->filter(fn (Post $p) => $p->kelas->dapatDikelola($user) && ! ($tanpaArsip && $p->kelas->diarsipkan))
            ->values();
    }

    /**
     * Kelas asal ditambah kelas tujuan yang valid. ID di luar `tujuan()` (milik guru lain, diarsipkan, tahun ajaran lain)
     * menggagalkan seluruh permintaan.
     *
     * @param  list<mixed>  $idTujuan
     * @return list<Kelas>
     */
    private function pilihTujuan(Kelas $asal, array $idTujuan): array
    {
        $idTujuan = array_values(array_unique(array_filter($idTujuan, 'is_string')));
        $boleh = $this->tujuan($asal)->keyBy('id');

        if (array_diff($idTujuan, $boleh->keys()->all())) {
            throw ValidationException::withMessages(['kelasTujuan' => 'Ada ruang kelas tujuan yang tidak valid']);
        }

        $lain = array_values(array_diff($idTujuan, [$asal->id]));

        return [$asal, ...array_map(fn (string $id) => $boleh[$id], $lain)];
    }

    /**
     * Jalankan `$kerja` dalam transaksi. Path berkas yang dicatat di `$jejak` dihapus bila transaksi gagal,
     * supaya tidak ada berkas yatim tanpa baris lampiran.
     *
     * @param  callable  $kerja  menerima `$jejak` (daftar path) per referensi dan mengembalikan hasil transaksi
     */
    private function transaksi(callable $kerja): mixed
    {
        $jejak = [];

        try {
            return DB::transaction(function () use (&$jejak, $kerja) {
                return $kerja($jejak);
            });
        } catch (Throwable $e) {
            $this->lampiran->hapusPath($jejak);

            throw $e;
        }
    }
}
