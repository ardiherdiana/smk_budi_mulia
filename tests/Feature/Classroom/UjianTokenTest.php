<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Soal;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Ujian ber-token: siswa memasukkan token saat memulai percobaan baru; ujian tanpa token tidak berubah. */
class UjianTokenTest extends TestCase
{
    /** @return array{0: Akun, 1: Akun, 2: Post, 3: Kelas} */
    private function siapkan(?string $token = 'ABC234'): array
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel('X TKJ 1');
        $siswa = $this->siswa($rombel, 'siswa01');
        $kelas = $this->kelas($guru, $rombel);
        $post = $this->buatPost($kelas, $guru, 'UJIAN', [
            'durasiMenit' => 30, 'mulaiAt' => now()->subDay(), 'selesaiAt' => now()->addDay(), 'tokenAkses' => $token,
        ]);
        Soal::create(['postId' => $post->id, 'urut' => 1, 'tipe' => 'PG', 'teks' => '1+1?', 'poin' => 10, 'opsi' => ['1', '2'], 'kunci' => '1']);

        return [$guru, $siswa, $post, $kelas];
    }

    private function dataUjian(array $ubah = []): array
    {
        return $ubah + ['tipe' => 'UJIAN', 'judul' => 'Kuis 1', 'durasiMenit' => 30];
    }

    public function test_ujian_tanpa_token_bisa_dimulai_seperti_biasa(): void
    {
        [, $siswa, $post] = $this->siapkan(null);

        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai")->assertRedirect("/classroom/post/{$post->id}/kerjakan");

        $this->assertSame(1, Percobaan::count());
    }

    public function test_token_salah_atau_kosong_ditolak_dan_tidak_membuat_percobaan(): void
    {
        [, $siswa, $post] = $this->siapkan();

        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai", ['token' => 'SALAH1'])->assertSessionHasErrors('token');
        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai")->assertSessionHasErrors('token');

        $this->assertSame(0, Percobaan::count());
    }

    public function test_token_benar_membuat_percobaan_huruf_kecil_dan_spasi_diabaikan(): void
    {
        [, $siswa, $post] = $this->siapkan();

        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai", ['token' => '  abc234 '])
            ->assertSessionHasNoErrors()->assertRedirect("/classroom/post/{$post->id}/kerjakan");

        $this->assertSame(1, Percobaan::count());
    }

    public function test_percobaan_yang_berjalan_bisa_dilanjutkan_tanpa_token(): void
    {
        [, $siswa, $post] = $this->siapkan();
        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai", ['token' => 'ABC234']);

        // Misalnya peramban tertutup: kembali ke halaman ujian dan lanjutkan tanpa mengetik token lagi.
        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai")->assertSessionHasNoErrors()->assertRedirect("/classroom/post/{$post->id}/kerjakan");
        $this->actingAs($siswa)->get("/classroom/post/{$post->id}/kerjakan")->assertOk();

        $this->assertSame(1, Percobaan::count());
    }

    public function test_lima_token_salah_memicu_batas_dan_token_benar_pun_ditahan(): void
    {
        [, $siswa, $post] = $this->siapkan();
        RateLimiter::clear("token-ujian:{$siswa->siswa->id}:{$post->id}");

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai", ['token' => 'SALAH'.$i])->assertSessionHasErrors('token');
        }

        $response = $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai", ['token' => 'ABC234']);
        $response->assertSessionHasErrors('token');
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('token'));
        $this->assertSame(0, Percobaan::count());
    }

    public function test_siswa_hanya_menerima_wajibtoken_dan_guru_menerima_tokennya(): void
    {
        [$guru, $siswa, $post] = $this->siapkan();

        $this->actingAs($siswa)->get("/classroom/post/{$post->id}")->assertInertia(fn (Assert $page) => $page
            ->where('post.wajibToken', true)->missing('post.tokenAkses'));
        $this->actingAs($siswa)->get("/classroom/kelas/{$post->kelasId}")->assertInertia(fn (Assert $page) => $page
            ->where('posts.0.wajibToken', true)->missing('posts.0.tokenAkses'));

        $this->actingAs($guru)->get("/classroom/post/{$post->id}")->assertInertia(fn (Assert $page) => $page
            ->where('post.wajibToken', true)->where('post.tokenAkses', 'ABC234'));
    }

    public function test_membuat_ujian_dengan_wajib_token_menghasilkan_token_enam_karakter(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $kelas = $this->kelas($guru, $this->rombel());

        $this->actingAs($guru)->post("/classroom/kelas/{$kelas->id}/post", $this->dataUjian(['wajibToken' => 1]))->assertRedirect();
        $this->actingAs($guru)->post("/classroom/kelas/{$kelas->id}/post", $this->dataUjian(['judul' => 'Tanpa token', 'wajibToken' => 0]))->assertRedirect();

        $dengan = Post::where('judul', 'Kuis 1')->firstOrFail();
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ2-9]{6}$/', $dengan->tokenAkses);
        $this->assertNull(Post::where('judul', 'Tanpa token')->firstOrFail()->tokenAkses);
    }

    public function test_ujian_multi_kelas_berbagi_satu_token(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $a = $this->kelas($guru, $this->rombel('X TKJ 1'));
        $b = $this->kelas($guru, $this->rombel('X TKJ 2'));

        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", $this->dataUjian(['wajibToken' => 1, 'kelasTujuan' => [$b->id]]))->assertRedirect();

        $tokens = Post::pluck('tokenAkses')->unique();
        $this->assertCount(2, Post::all());
        $this->assertCount(1, $tokens);
        $this->assertNotNull($tokens->first());
    }

    public function test_ubah_ujian_mempertahankan_menambah_dan_menghapus_token(): void
    {
        [$guru, , $post] = $this->siapkan(null);
        $ubah = fn (array $data) => $this->actingAs($guru)->patch("/classroom/post/{$post->id}", $this->dataUjian($data))->assertSessionHasNoErrors();

        $ubah(['wajibToken' => 1]);
        $token = $post->fresh()->tokenAkses;
        $this->assertNotNull($token);

        $ubah(['wajibToken' => 1, 'judul' => 'Judul baru']); // simpan ulang: token yang sama
        $this->assertSame($token, $post->fresh()->tokenAkses);

        $ubah(['wajibToken' => 0]);
        $this->assertNull($post->fresh()->tokenAkses);
    }

    public function test_buat_token_baru_mengganti_token_di_seluruh_grup(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $a = $this->kelas($guru, $this->rombel('X TKJ 1'));
        $b = $this->kelas($guru, $this->rombel('X TKJ 2'));
        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", $this->dataUjian(['wajibToken' => 1, 'kelasTujuan' => [$b->id]]))->assertRedirect();
        $pa = Post::where('kelasId', $a->id)->firstOrFail();
        $lama = $pa->tokenAkses;

        $this->actingAs($guru)->post("/classroom/post/{$pa->id}/token")->assertRedirect();

        $baru = $pa->fresh()->tokenAkses;
        $this->assertNotSame($lama, $baru);
        $this->assertSame($baru, Post::where('kelasId', $b->id)->firstOrFail()->tokenAkses);
    }

    public function test_buat_token_baru_ditolak_untuk_siswa_guru_lain_dan_ujian_tanpa_token(): void
    {
        [$guru, $siswa, $post] = $this->siapkan();
        $guruLain = $this->akun('GURU', 'guru2');

        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/token")->assertForbidden();
        $this->actingAs($guruLain)->post("/classroom/post/{$post->id}/token")->assertForbidden();
        $this->assertSame('ABC234', $post->fresh()->tokenAkses);

        $post->update(['tokenAkses' => null]);
        $this->actingAs($guru)->post("/classroom/post/{$post->id}/token")->assertStatus(409);
    }

    public function test_halaman_kerjakan_memuat_info_ruang_kelas_untuk_layar_ujian(): void
    {
        [, $siswa, $post] = $this->siapkan();
        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai", ['token' => 'ABC234']);

        $this->actingAs($siswa)->get("/classroom/post/{$post->id}/kerjakan")->assertInertia(fn (Assert $page) => $page
            ->component('classroom/ujian/kerjakan-page')
            ->where('kelas.mapel', 'Jaringan Dasar')->where('kelas.rombel', 'X TKJ 1')
            ->where('post.durasiMenit', 30)->where('totalPoin', 10)->has('soal', 1));
    }
}
