<?php

namespace App\Services\Classroom;

use App\Models\Classroom\Kelas;
use App\Models\Classroom\Lampiran;
use App\Models\Classroom\Pengumpulan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Soal;
use Illuminate\Support\Collection;

/**
 * Menghapus post/ruang kelas beserta berkas fisiknya. Baris database ikut terhapus lewat cascade,
 * tetapi berkas di disk tidak, jadi dibereskan di sini lebih dulu.
 */
class PostCleaner
{
    public function __construct(private LampiranService $lampiran, private SoalGambarService $soalGambar) {}

    public function hapusPost(Post $post): void
    {
        $this->lampiran->hapusBerkasFisik($this->lampiranDariPost([$post->id]));
        $gambar = $this->gambarSoalDariPost([$post->id]);
        $post->delete();
        $this->soalGambar->lepas($gambar);
    }

    public function hapusKelas(Kelas $kelas): void
    {
        $idPost = $kelas->posts()->pluck('id')->all();
        $this->lampiran->hapusBerkasFisik($this->lampiranDariPost($idPost));
        $gambar = $this->gambarSoalDariPost($idPost);
        $kelas->delete();
        $this->soalGambar->lepas($gambar);
    }

    /**
     * Path gambar soal milik post-post ini. Dicatat sebelum cascade menghapus barisnya; berkasnya baru dilepas sesudah
     * itu, dan hanya bila tidak ada soal lain (ruang kelas saudara) yang masih memakainya.
     *
     * @param  list<string>  $idPost
     * @return list<string>
     */
    private function gambarSoalDariPost(array $idPost): array
    {
        if ($idPost === []) {
            return [];
        }

        return Soal::whereIn('postId', $idPost)->get()->flatMap(fn (Soal $s) => $s->semuaGambar())->unique()->values()->all();
    }

    /**
     * @param  list<string>  $idPost
     * @return Collection<int, Lampiran>
     */
    private function lampiranDariPost(array $idPost)
    {
        if ($idPost === []) {
            return collect();
        }

        $idPengumpulan = Pengumpulan::whereIn('postId', $idPost)->pluck('id');

        return Lampiran::where('tipe', 'FILE')
            ->where(fn ($q) => $q->whereIn('postId', $idPost)->orWhereIn('pengumpulanId', $idPengumpulan))
            ->get();
    }
}
