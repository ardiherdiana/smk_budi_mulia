<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Post;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PoinSoalOtomatisTest extends TestCase
{
    /** @return array{0: Akun, 1: Post} */
    private function siapkan(): array
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel('X TKJ 1');
        $post = $this->buatPost($this->kelas($guru, $rombel), $guru, 'UJIAN', ['durasiMenit' => 30]);

        return [$guru, $post];
    }

    /** @return list<array<string, mixed>> */
    private function soalEssay(int $jumlah, array $tambahan = []): array
    {
        return array_map(fn (int $i) => ['tipe' => 'ESSAY', 'teks' => "Soal {$i}", ...$tambahan], range(1, $jumlah));
    }

    private function simpan(Akun $guru, Post $post, array $muatan)
    {
        return $this->actingAs($guru)->put("/classroom/post/{$post->id}/soal", $muatan);
    }

    public function test_dua_puluh_soal_dibagi_rata_lima_poin_dan_kiriman_poin_diabaikan(): void
    {
        [$guru, $post] = $this->siapkan();

        // Poin kiriman diabaikan (bahkan yang tidak sah) saat bagi rata aktif; yang menentukan hanya server.
        $this->simpan($guru, $post, ['bagiRata' => true, 'soal' => $this->soalEssay(20, ['poin' => 999])])->assertRedirect();

        $poin = $post->soal()->pluck('poin')->all();
        $this->assertCount(20, $poin);
        $this->assertSame(array_fill(0, 20, 5.0), array_map('floatval', $poin));
        $this->assertEquals(100, $post->soal()->sum('poin'));
    }

    public function test_tiga_soal_total_tepat_seratus_dan_poin_boleh_dihilangkan(): void
    {
        [$guru, $post] = $this->siapkan();

        $this->simpan($guru, $post, ['bagiRata' => true, 'soal' => $this->soalEssay(3)])->assertRedirect();

        $this->assertSame([33.33, 33.33, 33.34], array_map('floatval', $post->soal()->pluck('poin')->all()));
        $this->assertEquals(100, $post->soal()->sum('poin'));
    }

    public function test_tanpa_bagi_rata_poin_manual_dipakai_dan_wajib_diisi(): void
    {
        [$guru, $post] = $this->siapkan();

        $this->simpan($guru, $post, ['soal' => [
            ['tipe' => 'ESSAY', 'teks' => 'a', 'poin' => 70],
            ['tipe' => 'ESSAY', 'teks' => 'b', 'poin' => 12.5],
        ]])->assertRedirect();
        $this->assertSame([70.0, 12.5], array_map('floatval', $post->soal()->pluck('poin')->all()));

        $this->simpan($guru, $post, ['bagiRata' => false, 'soal' => [['tipe' => 'ESSAY', 'teks' => 'a']]])->assertSessionHasErrors('soal.0.poin');
        $this->assertSame(2, $post->soal()->count());
    }

    public function test_editor_membuka_mode_bagi_rata_untuk_soal_kosong_dan_poin_rata_tetapi_tidak_untuk_poin_manual(): void
    {
        [$guru, $post] = $this->siapkan();
        $buka = fn () => $this->actingAs($guru)->get("/classroom/post/{$post->id}/soal");

        $buka()->assertInertia(fn (Assert $p) => $p->where('bagiRata', true));

        $this->simpan($guru, $post, ['bagiRata' => true, 'soal' => $this->soalEssay(3)]);
        $buka()->assertInertia(fn (Assert $p) => $p->where('bagiRata', true)->has('soal', 3));

        // Poin lama yang diatur manual tidak diubah diam-diam: dibuka dalam mode manual.
        $this->simpan($guru, $post, ['soal' => [
            ['tipe' => 'ESSAY', 'teks' => 'a', 'poin' => 7],
            ['tipe' => 'ESSAY', 'teks' => 'b', 'poin' => 3],
        ]]);
        $buka()->assertInertia(fn (Assert $p) => $p->where('bagiRata', false));
    }

    public function test_pembagian_ikut_tersalin_ke_ruang_kelas_lain(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $a = $this->kelas($guru, $this->rombel('X TKJ 1'));
        $b = $this->kelas($guru, $this->rombel('X TKJ 2'));
        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'UJIAN', 'judul' => 'Kuis', 'durasiMenit' => 30, 'kelasTujuan' => [$b->id],
        ])->assertRedirect();
        $pa = Post::where('kelasId', $a->id)->firstOrFail();
        $pb = Post::where('kelasId', $b->id)->firstOrFail();

        $this->simpan($guru, $pa, ['bagiRata' => true, 'soal' => $this->soalEssay(7)])->assertRedirect();

        $this->assertEquals(100, $pa->soal()->sum('poin'));
        $this->assertEquals(100, $pb->soal()->sum('poin'));
        $this->assertSame($pa->soal()->pluck('poin')->all(), $pb->soal()->pluck('poin')->all());
    }
}
