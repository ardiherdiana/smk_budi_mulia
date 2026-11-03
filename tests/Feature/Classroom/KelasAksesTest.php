<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\TahunAjaran;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KelasAksesTest extends TestCase
{
    public function test_siswa_hanya_melihat_ruang_kelas_rombelnya(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombelA = $this->rombel('X TKJ 1');
        $rombelB = $this->rombel('X TKJ 2');
        $saya = $this->kelas($guru, $rombelA, 'Jaringan Dasar');
        $lain = $this->kelas($guru, $rombelB, 'Jaringan Dasar');
        $siswa = $this->siswa($rombelA, 'siswa01');

        $this->actingAs($siswa)->get('/classroom/kelas')
            ->assertInertia(fn (Assert $page) => $page->component('classroom/kelas/daftar-page')->has('kelas', 1)->where('kelas.0.id', $saya->id));

        $this->actingAs($siswa)->get("/classroom/kelas/{$saya->id}")->assertOk();
        $this->actingAs($siswa)->get("/classroom/kelas/{$lain->id}")->assertForbidden();
    }

    public function test_guru_hanya_melihat_ruang_kelas_yang_diajarnya(): void
    {
        $guru1 = $this->akun('GURU', 'guru1');
        $guru2 = $this->akun('GURU', 'guru2');
        $rombel = $this->rombel();
        $punya1 = $this->kelas($guru1, $rombel, 'Jaringan Dasar');
        $punya2 = $this->kelas($guru2, $rombel, 'Matematika');

        $this->actingAs($guru1)->get('/classroom/kelas')
            ->assertInertia(fn (Assert $page) => $page->has('kelas', 1)->where('kelas.0.id', $punya1->id));
        $this->actingAs($guru1)->get("/classroom/kelas/{$punya2->id}")->assertForbidden();
    }

    public function test_siswa_tidak_bisa_memposting_di_ruang_kelas(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $kelas = $this->kelas($guru, $rombel);
        $siswa = $this->siswa($rombel, 'siswa01');

        $this->actingAs($siswa)->post("/classroom/kelas/{$kelas->id}/post", ['tipe' => 'PENGUMUMAN', 'isi' => 'halo'])->assertForbidden();
        $this->assertDatabaseCount('classroom_posts', 0);
    }

    public function test_guru_memposting_pengumuman_dan_siswa_berkomentar(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $kelas = $this->kelas($guru, $rombel);
        $siswa = $this->siswa($rombel, 'siswa01');

        $this->actingAs($guru)->post("/classroom/kelas/{$kelas->id}/post", ['tipe' => 'PENGUMUMAN', 'isi' => 'Besok kuis'])->assertRedirect();
        $post = $kelas->posts()->first();

        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/komentar", ['isi' => 'Siap, Bu'])->assertRedirect();
        $this->assertDatabaseHas('komentar', ['postId' => $post->id, 'userId' => $siswa->id, 'isi' => 'Siap, Bu']);

        $this->actingAs($siswa)->get("/classroom/kelas/{$kelas->id}")
            ->assertInertia(fn (Assert $page) => $page->has('posts', 1)->where('posts.0.komentar.0.isi', 'Siap, Bu'));
    }

    public function test_guru_membuat_ruang_kelas_untuk_diri_sendiri(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        TahunAjaran::create(['nama' => '2026/2027', 'semester' => 'GANJIL', 'aktif' => true]);

        $this->actingAs($guru)->post('/classroom/kelas', ['rombelId' => $rombel->id, 'mapelBaru' => 'Basis Data'])->assertRedirect();

        $this->assertDatabaseHas('kelas', ['rombelId' => $rombel->id, 'guruId' => $guru->id]);
        $this->assertDatabaseHas('mapel', ['nama' => 'Basis Data']);

        // Mapel dan rombel yang sama di tahun ajaran yang sama ditolak.
        $this->actingAs($guru)->withHeader('X-Inertia', 'true')->post('/classroom/kelas', ['rombelId' => $rombel->id, 'mapelBaru' => 'Basis Data'])->assertSessionHasErrors('message');
        $this->assertDatabaseCount('kelas', 1);
    }
}
