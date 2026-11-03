<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Post;
use App\Models\Classroom\Soal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SoalGambarTest extends TestCase
{
    /** @return array{0: Akun, 1: Akun, 2: Post} */
    private function siapkan(): array
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel('X TKJ 1');
        $siswa = $this->siswa($rombel, 'siswa01');
        $post = $this->buatPost($this->kelas($guru, $rombel), $guru, 'UJIAN', [
            'durasiMenit' => 30, 'mulaiAt' => now()->subDay(), 'selesaiAt' => now()->addDay(), 'tampilkanNilai' => true,
        ]);

        return [$guru, $siswa, $post];
    }

    /** Unggah satu gambar lewat endpoint dan kembalikan path sementaranya. */
    private function unggah(Akun $guru, Post $post, string $nama = 'a.png'): string
    {
        $respons = $this->actingAs($guru)
            ->postJson("/classroom/post/{$post->id}/soal/gambar", ['gambar' => UploadedFile::fake()->image($nama, 60, 40)])
            ->assertOk();

        return $respons->json('path');
    }

    private function simpan(Akun $guru, Post $post, array $soal)
    {
        return $this->actingAs($guru)->put("/classroom/post/{$post->id}/soal", ['soal' => $soal]);
    }

    public function test_unggah_gambar_disimpan_sementara_lalu_dipindah_saat_soal_disimpan(): void
    {
        [$guru, , $post] = $this->siapkan();

        $sementara = $this->unggah($guru, $post);
        $this->assertStringStartsWith('soal-tmp/', $sementara);
        Storage::disk('media')->assertExists($sementara);

        $this->simpan($guru, $post, [
            ['id' => null, 'tipe' => 'ESSAY', 'teks' => 'Perhatikan gambar', 'poin' => 5, 'gambar' => [$sementara]],
        ])->assertRedirect("/classroom/post/{$post->id}");

        $final = 'soal/'.basename($sementara);
        $this->assertSame([$final], $post->soal()->first()->gambar);
        Storage::disk('media')->assertExists($final);
        Storage::disk('media')->assertMissing($sementara);

        // Editor menerima path (untuk dikirim balik) dan URL (pratinjau).
        $this->actingAs($guru)->get("/classroom/post/{$post->id}/soal")
            ->assertInertia(fn (Assert $p) => $p->where('soal.0.gambar.0.path', $final)->where('soal.0.gambar.0.url', "/media/{$final}"));
    }

    public function test_unggah_gambar_menolak_berkas_bukan_gambar(): void
    {
        [$guru, , $post] = $this->siapkan();

        $this->actingAs($guru)
            ->postJson("/classroom/post/{$post->id}/soal/gambar", ['gambar' => UploadedFile::fake()->createWithContent('x.png', '<?php echo 1;')])
            ->assertStatus(422)->assertJsonValidationErrors('gambar');
    }

    public function test_path_gambar_asing_atau_kedaluwarsa_ditolak_dan_tidak_ada_yang_tersimpan(): void
    {
        [$guru, , $post] = $this->siapkan();
        $punyaLain = $this->unggah($guru, $post);
        $punyaLain = 'soal/'.basename($punyaLain);
        Storage::disk('media')->put($punyaLain, 'x');

        $this->simpan($guru, $post, [
            ['tipe' => 'ESSAY', 'teks' => 'a', 'poin' => 1, 'gambar' => ['../../.env']],
            ['tipe' => 'ESSAY', 'teks' => 'b', 'poin' => 1, 'gambar' => [$punyaLain]],
            ['tipe' => 'ESSAY', 'teks' => 'c', 'poin' => 1, 'gambar' => ['soal-tmp/tidak-ada.png']],
            ['tipe' => 'PG', 'teks' => 'd', 'poin' => 1, 'opsi' => ['', 'x'], 'opsiGambar' => ['soal/asing.png', null], 'kunci' => '1'],
        ])->assertSessionHasErrors(['soal.0.gambar', 'soal.1.gambar', 'soal.2.gambar', 'soal.3.opsiGambar']);

        $this->assertSame(0, $post->soal()->count());
        Storage::disk('media')->assertExists($punyaLain);
    }

    public function test_pilihan_hanya_gambar_lolos_dan_pilihan_kosong_tanpa_gambar_dibuang(): void
    {
        [$guru, , $post] = $this->siapkan();
        $gambar = $this->unggah($guru, $post);

        $this->simpan($guru, $post, [
            ['tipe' => 'PG', 'teks' => 'Pilih gambar yang benar', 'poin' => 2, 'opsi' => ['', 'Teks saja', '', ''], 'opsiGambar' => [$gambar, null, null, null], 'kunci' => '0'],
        ])->assertRedirect();

        $soal = $post->soal()->first();
        $this->assertSame(['', 'Teks saja'], $soal->opsi);
        $this->assertSame(['soal/'.basename($gambar), null], $soal->opsiGambar);
        $this->assertSame('0', $soal->kunci);

        // Semua pilihan kosong tanpa gambar tetap ditolak.
        $this->simpan($guru, $post, [
            ['tipe' => 'PG', 'teks' => 'x', 'poin' => 1, 'opsi' => ['', ''], 'kunci' => '0'],
        ])->assertSessionHasErrors('soal.0.opsi');
    }

    public function test_berkas_hanya_dihapus_bila_tidak_ada_soal_lain_yang_memakainya(): void
    {
        [$guru, , $post] = $this->siapkan();
        $sementara = $this->unggah($guru, $post);
        $final = 'soal/'.basename($sementara);

        // Dua soal memakai gambar yang sama (wacana bersama): satu berkas.
        $this->simpan($guru, $post, [
            ['tipe' => 'ESSAY', 'teks' => 'Soal 1', 'poin' => 1, 'gambar' => [$sementara]],
            ['tipe' => 'ESSAY', 'teks' => 'Soal 2', 'poin' => 1, 'gambar' => [$sementara]],
        ])->assertRedirect();
        $this->assertSame([[$final], [$final]], $post->soal()->get()->map->gambar->all());
        Storage::disk('media')->assertExists($final);

        $idDua = $post->soal()->where('teks', 'Soal 2')->value('id');
        $this->simpan($guru, $post, [
            ['id' => $idDua, 'tipe' => 'ESSAY', 'teks' => 'Soal 2', 'poin' => 1, 'gambar' => [$final]],
        ])->assertRedirect();
        Storage::disk('media')->assertExists($final);

        // Gambar dicabut dari soal terakhir yang memakainya: berkasnya ikut hilang.
        $this->simpan($guru, $post, [
            ['id' => $idDua, 'tipe' => 'ESSAY', 'teks' => 'Soal 2', 'poin' => 1, 'gambar' => []],
        ])->assertRedirect();
        $this->assertNull($post->soal()->first()->gambar);
        Storage::disk('media')->assertMissing($final);
    }

    public function test_hapus_postingan_ujian_membersihkan_gambar_soalnya(): void
    {
        [$guru, , $post] = $this->siapkan();
        $sementara = $this->unggah($guru, $post);
        $this->simpan($guru, $post, [['tipe' => 'ESSAY', 'teks' => 'x', 'poin' => 1, 'gambar' => [$sementara]]])->assertRedirect();
        $final = 'soal/'.basename($sementara);
        Storage::disk('media')->assertExists($final);

        $this->actingAs($guru)->delete("/classroom/post/{$post->id}")->assertRedirect();

        $this->assertSame(0, Soal::count());
        Storage::disk('media')->assertMissing($final);
    }

    public function test_gambar_dibagi_ke_ruang_kelas_lain_dan_berkas_lama_dilepas(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $a = $this->kelas($guru, $this->rombel('X TKJ 1'));
        $b = $this->kelas($guru, $this->rombel('X TKJ 2'));
        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'UJIAN', 'judul' => 'Kuis', 'durasiMenit' => 30, 'kelasTujuan' => [$b->id],
        ])->assertRedirect();
        $pa = Post::where('kelasId', $a->id)->firstOrFail();
        $pb = Post::where('kelasId', $b->id)->firstOrFail();

        $pertama = $this->unggah($guru, $pa);
        $this->simpan($guru, $pa, [['tipe' => 'ESSAY', 'teks' => 'Soal', 'poin' => 1, 'gambar' => [$pertama]]])->assertRedirect();
        $final = 'soal/'.basename($pertama);

        // Salinan di kelas B memakai berkas yang sama (dibagi, bukan digandakan).
        $this->assertSame([$final], $pb->soal()->first()->gambar);
        $this->assertCount(1, Storage::disk('media')->files('soal'));

        // Mengganti gambar: berkas lama dilepas karena tidak dirujuk soal di kelas A maupun B.
        $kedua = $this->unggah($guru, $pa, 'b.png');
        $this->simpan($guru, $pa, [['id' => $pa->soal()->value('id'), 'tipe' => 'ESSAY', 'teks' => 'Soal', 'poin' => 1, 'gambar' => [$kedua]]])->assertRedirect();

        $this->assertSame(['soal/'.basename($kedua)], $pb->soal()->first()->gambar);
        Storage::disk('media')->assertMissing($final);
        Storage::disk('media')->assertExists('soal/'.basename($kedua));
    }

    public function test_siswa_menerima_url_gambar_tanpa_kunci(): void
    {
        [$guru, $siswa, $post] = $this->siapkan();
        $gambar = $this->unggah($guru, $post);
        $pilihan = $this->unggah($guru, $post, 'p.png');
        $this->simpan($guru, $post, [
            ['tipe' => 'PG', 'teks' => 'Lihat gambar', 'poin' => 2, 'gambar' => [$gambar], 'opsi' => ['', 'dua'], 'opsiGambar' => [$pilihan, null], 'kunci' => '1'],
        ])->assertRedirect();
        $gambarFinal = 'soal/'.basename($gambar);
        $pilihanFinal = 'soal/'.basename($pilihan);

        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai");
        $this->actingAs($siswa)->get("/classroom/post/{$post->id}/kerjakan")
            ->assertInertia(fn (Assert $p) => $p
                ->where('soal.0.gambar', ["/media/{$gambarFinal}"])
                ->where('soal.0.opsiGambar', ["/media/{$pilihanFinal}", null])
                ->missing('soal.0.kunci'));
    }
}
