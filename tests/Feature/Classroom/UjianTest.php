<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\Soal;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UjianTest extends TestCase
{
    /** @return array{0: Akun, 1: Akun, 2: Post, 3: Collection} */
    private function siapkan(array $ujian = [], string $sufiks = ''): array
    {
        $guru = $this->akun('GURU', "guru1{$sufiks}");
        $rombel = $this->rombel("X TKJ 1{$sufiks}");
        $siswa = $this->siswa($rombel, "siswa01{$sufiks}");
        $post = $this->buatPost($this->kelas($guru, $rombel), $guru, 'UJIAN', $ujian + [
            'durasiMenit' => 30, 'mulaiAt' => now()->subDay(), 'selesaiAt' => now()->addDay(), 'tampilkanNilai' => true,
        ]);

        $soal = collect([
            Soal::create(['postId' => $post->id, 'urut' => 1, 'tipe' => 'PG', 'teks' => '1+1?', 'poin' => 10, 'opsi' => ['1', '2', '3'], 'kunci' => '1']),
            Soal::create(['postId' => $post->id, 'urut' => 2, 'tipe' => 'PG', 'teks' => '2+2?', 'poin' => 10, 'opsi' => ['3', '4', '5'], 'kunci' => '1']),
            Soal::create(['postId' => $post->id, 'urut' => 3, 'tipe' => 'ESSAY', 'teks' => 'Jelaskan', 'poin' => 20]),
        ]);

        return [$guru, $siswa, $post, $soal];
    }

    public function test_alur_lengkap_pg_otomatis_essay_dikoreksi_guru(): void
    {
        [$guru, $siswa, $post, $soal] = $this->siapkan();

        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai")->assertRedirect("/classroom/post/{$post->id}/kerjakan");
        $percobaan = Percobaan::firstOrFail();

        $this->actingAs($siswa)->patchJson("/classroom/percobaan/{$percobaan->id}/jawab", ['soalId' => $soal[0]->id, 'jawaban' => '1'])->assertOk();
        $this->actingAs($siswa)->patchJson("/classroom/percobaan/{$percobaan->id}/jawab", ['soalId' => $soal[1]->id, 'jawaban' => '0'])->assertOk();
        $this->actingAs($siswa)->patchJson("/classroom/percobaan/{$percobaan->id}/jawab", ['soalId' => $soal[2]->id, 'jawaban' => 'Karena begitu'])->assertOk();
        // Mengubah jawaban menimpa, bukan menambah.
        $this->actingAs($siswa)->patchJson("/classroom/percobaan/{$percobaan->id}/jawab", ['soalId' => $soal[0]->id, 'jawaban' => '1'])->assertOk();
        $this->assertSame(3, $percobaan->jawaban()->count());

        $this->actingAs($siswa)->post("/classroom/percobaan/{$percobaan->id}/selesai")->assertRedirect("/classroom/post/{$post->id}");

        $percobaan->refresh();
        $this->assertSame('SELESAI', $percobaan->status);
        $this->assertEquals(10.0, $percobaan->skorOtomatis);

        // Essay belum dikoreksi: nilai tidak ditampilkan ke siswa.
        $this->actingAs($siswa)->get("/classroom/post/{$post->id}")
            ->assertInertia(fn (Assert $p) => $p->where('percobaanSaya.menungguKoreksi', true)->where('percobaanSaya.skor', null));

        $essay = $percobaan->jawaban()->where('soalId', $soal[2]->id)->firstOrFail();
        $this->actingAs($guru)->patch("/classroom/jawaban/{$essay->id}/skor", ['skor' => 15])->assertRedirect();

        $percobaan->refresh();
        $this->assertSame('DINILAI', $percobaan->status);
        $this->assertEquals(25.0, $percobaan->skorTotal());

        $this->actingAs($siswa)->get("/classroom/post/{$post->id}")
            ->assertInertia(fn (Assert $p) => $p->where('percobaanSaya.skor', 25)->where('totalPoin', 40));
    }

    public function test_kunci_jawaban_tidak_dikirim_ke_siswa(): void
    {
        [, $siswa, $post] = $this->siapkan();
        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai");

        $respons = $this->actingAs($siswa)->get("/classroom/post/{$post->id}/kerjakan");
        $respons->assertInertia(fn (Assert $p) => $p->component('classroom/ujian/kerjakan-page')->has('soal', 3)->missing('soal.0.kunci'));
        $this->assertStringNotContainsString('"kunci"', $respons->getContent());
    }

    public function test_satu_siswa_satu_percobaan(): void
    {
        [, $siswa, $post] = $this->siapkan();

        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai");
        $this->actingAs($siswa)->post('/classroom/percobaan/'.Percobaan::first()->id.'/selesai');
        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai");

        $this->assertSame(1, Percobaan::count());
        $this->actingAs($siswa)->get("/classroom/post/{$post->id}/kerjakan")->assertRedirect("/classroom/post/{$post->id}");
    }

    public function test_ujian_di_luar_jendela_waktu_ditolak(): void
    {
        [, $siswa, $belum] = $this->siapkan(['mulaiAt' => now()->addHour(), 'selesaiAt' => now()->addDay()]);
        $this->actingAs($siswa)->withHeader('X-Inertia', 'true')->post("/classroom/post/{$belum->id}/mulai")->assertSessionHasErrors('message');

        [, $siswa2, $tutup] = $this->siapkan(['mulaiAt' => now()->subDay(), 'selesaiAt' => now()->subHour()], 'b');
        $this->actingAs($siswa2)->withHeader('X-Inertia', 'true')->post("/classroom/post/{$tutup->id}/mulai")->assertSessionHasErrors('message');

        $this->assertSame(0, Percobaan::count());
    }

    public function test_waktu_habis_mengakhiri_otomatis_dan_menolak_jawaban_baru(): void
    {
        [, $siswa, $post, $soal] = $this->siapkan();
        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai");
        $percobaan = Percobaan::firstOrFail();
        $this->actingAs($siswa)->patchJson("/classroom/percobaan/{$percobaan->id}/jawab", ['soalId' => $soal[0]->id, 'jawaban' => '1']);

        $this->travel(31)->minutes();

        $this->actingAs($siswa)->patchJson("/classroom/percobaan/{$percobaan->id}/jawab", ['soalId' => $soal[1]->id, 'jawaban' => '1'])->assertStatus(409);

        $percobaan->refresh();
        $this->assertNotSame('BERJALAN', $percobaan->status);
        // Hanya jawaban sebelum waktu habis yang dihitung.
        $this->assertEquals(10.0, $percobaan->skorOtomatis);
    }

    public function test_siswa_lain_tidak_bisa_mengisi_percobaan_orang(): void
    {
        [, $siswa, $post, $soal] = $this->siapkan();
        $lain = $this->siswa(Rombel::first(), 'siswa02');
        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai");

        $this->actingAs($lain)->patchJson('/classroom/percobaan/'.Percobaan::first()->id.'/jawab', ['soalId' => $soal[0]->id, 'jawaban' => '0'])->assertForbidden();
    }

    public function test_simpan_massal_membuat_mengubah_menghapus_dan_mengurutkan_soal(): void
    {
        [$guru, , $post, $soal] = $this->siapkan();

        $this->actingAs($guru)->get("/classroom/post/{$post->id}/soal")
            ->assertInertia(fn (Assert $p) => $p->component('classroom/ujian/editor-soal-page')->has('soal', 3)->where('terkunci', false));

        // Soal 2 dibuang, soal 1 diubah dan dipindah ke belakang, tiga soal baru bentuk TKA ditambah.
        $this->actingAs($guru)->put("/classroom/post/{$post->id}/soal", ['soal' => [
            ['id' => $soal[2]->id, 'tipe' => 'ESSAY', 'teks' => 'Jelaskan lagi', 'poin' => 20],
            ['id' => null, 'tipe' => 'PG', 'teks' => 'Baru?', 'kompetensi' => 'Komp A', 'subKompetensi' => 'Sub A', 'poin' => 5, 'opsi' => ['a', '', 'c'], 'kunci' => '2'],
            ['id' => null, 'tipe' => 'PGK_MCMA', 'teks' => 'Pilih semua', 'poin' => 6, 'opsi' => ['a', 'b', 'c', 'd'], 'kunci' => '3,0'],
            ['id' => null, 'tipe' => 'PGK_KATEGORI', 'teks' => 'Benar atau salah', 'poin' => 6, 'opsi' => ['p1', 'p2', 'p3'], 'kunci' => '1,0,1'],
            ['id' => $soal[0]->id, 'tipe' => 'PG', 'teks' => '1+1 = ?', 'poin' => 10, 'opsi' => ['1', '2', '3'], 'kunci' => '1'],
        ]])->assertRedirect("/classroom/post/{$post->id}");

        $urut = $post->soal()->get();
        $this->assertSame(['Jelaskan lagi', 'Baru?', 'Pilih semua', 'Benar atau salah', '1+1 = ?'], $urut->pluck('teks')->all());
        $this->assertSame([1, 2, 3, 4, 5], $urut->pluck('urut')->all());
        $this->assertDatabaseMissing('soal', ['id' => $soal[1]->id]);

        // Pilihan kosong dibuang dan kunci dipetakan ulang ke 'c' (indeks 1); kunci MCMA diurutkan.
        $this->assertSame(['a', 'c'], $urut[1]->opsi);
        $this->assertSame('1', $urut[1]->kunci);
        $this->assertSame('Komp A', $urut[1]->kompetensi);
        $this->assertSame('0,3', $urut[2]->kunci);
        $this->assertSame('1,0,1', $urut[3]->kunci);
        $this->assertSame($soal[0]->id, $urut[4]->id);
    }

    public function test_validasi_tiap_bentuk_soal_menyebut_nomor_soal(): void
    {
        [$guru, , $post] = $this->siapkan();

        $respons = $this->actingAs($guru)->put("/classroom/post/{$post->id}/soal", ['soal' => [
            ['tipe' => 'PG', 'teks' => 'x', 'poin' => 1, 'opsi' => ['a', 'b'], 'kunci' => ''],
            ['tipe' => 'PGK_MCMA', 'teks' => 'x', 'poin' => 1, 'opsi' => ['a', 'b'], 'kunci' => ''],
            ['tipe' => 'PGK_KATEGORI', 'teks' => 'x', 'poin' => 1, 'opsi' => ['a', 'b'], 'kunci' => '1,'],
            ['tipe' => 'PG', 'teks' => 'x', 'poin' => 1, 'opsi' => ['a', ''], 'kunci' => '0'],
        ]]);

        $respons->assertSessionHasErrors(['soal.0.kunci', 'soal.1.kunci', 'soal.2.kunci', 'soal.3.opsi']);
        // Tidak ada yang tersimpan sebagian: soal awal tetap utuh.
        $this->assertSame(3, $post->soal()->count());
    }

    public function test_soal_terkunci_setelah_ada_yang_mengerjakan(): void
    {
        [$guru, $siswa, $post, $soal] = $this->siapkan();

        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai");

        $this->actingAs($guru)->get("/classroom/post/{$post->id}/soal")->assertInertia(fn (Assert $p) => $p->where('terkunci', true));
        $this->actingAs($guru)->withHeader('X-Inertia', 'true')->put("/classroom/post/{$post->id}/soal", ['soal' => [
            ['id' => $soal[0]->id, 'tipe' => 'ESSAY', 'teks' => 'ubah', 'poin' => 1],
        ]])->assertSessionHasErrors('message');
        $this->assertDatabaseHas('soal', ['id' => $soal[0]->id, 'tipe' => 'PG']);
        $this->assertSame(3, $post->soal()->count());
    }

    public function test_penilaian_parsial_pgk_mcma_dan_kategori(): void
    {
        [, $siswa, $post] = $this->siapkan();
        $mcma = Soal::create(['postId' => $post->id, 'urut' => 4, 'tipe' => 'PGK_MCMA', 'teks' => 'm', 'poin' => 6, 'opsi' => ['a', 'b', 'c', 'd', 'e'], 'kunci' => '0,1,4']);
        $kategori = Soal::create(['postId' => $post->id, 'urut' => 5, 'tipe' => 'PGK_KATEGORI', 'teks' => 'k', 'poin' => 6, 'opsi' => ['p1', 'p2', 'p3'], 'kunci' => '1,0,1']);

        // MCMA: 0 dan 1 benar, 2 salah => (2 - 1) / 3 dari 6 poin. Kategori: 2 dari 3 pernyataan cocok => 4 poin.
        $this->assertEquals(2.0, $mcma->skorUntuk('0,1,2'));
        $this->assertEquals(6.0, $mcma->skorUntuk('0,1,4'));
        $this->assertEquals(0.0, $mcma->skorUntuk('2,3'));
        $this->assertEquals(0.0, $mcma->skorUntuk(''));
        $this->assertEquals(4.0, $kategori->skorUntuk('1,0,0'));
        $this->assertEquals(2.0, $kategori->skorUntuk('1,,'));
        $this->assertEquals(6.0, $kategori->skorUntuk('1,0,1'));

        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai");
        $percobaan = Percobaan::firstOrFail();
        $this->actingAs($siswa)->patchJson("/classroom/percobaan/{$percobaan->id}/jawab", ['soalId' => $mcma->id, 'jawaban' => '0,1,2'])->assertOk();
        $this->actingAs($siswa)->patchJson("/classroom/percobaan/{$percobaan->id}/jawab", ['soalId' => $kategori->id, 'jawaban' => '1,0,0'])->assertOk();
        $this->actingAs($siswa)->post("/classroom/percobaan/{$percobaan->id}/selesai");

        $this->assertEquals(6.0, $percobaan->refresh()->skorOtomatis);
    }

    public function test_siswa_tidak_bisa_membuka_koreksi_atau_mengubah_soal(): void
    {
        [, $siswa, $post] = $this->siapkan();
        $this->actingAs($siswa)->post("/classroom/post/{$post->id}/mulai");

        $this->actingAs($siswa)->get('/classroom/percobaan/'.Percobaan::first()->id)->assertForbidden();
        $this->actingAs($siswa)->put("/classroom/post/{$post->id}/soal", ['soal' => []])->assertForbidden();
        $this->actingAs($siswa)->get("/classroom/post/{$post->id}/soal")->assertForbidden();
    }

    public function test_rekap_nilai_menggabungkan_tugas_dan_ujian(): void
    {
        [$guru, $siswa, $ujian, $soal] = $this->siapkan();
        $kelas = $ujian->kelas;
        $tugas = $this->buatPost($kelas, $guru, 'TUGAS', ['poinMaks' => 100]);

        $this->actingAs($siswa)->post("/classroom/post/{$tugas->id}/kumpulkan", ['teks' => 'x']);
        $this->actingAs($guru)->patch('/classroom/pengumpulan/'.$tugas->pengumpulan()->first()->id.'/nilai', ['nilai' => 80]);

        $this->actingAs($siswa)->post("/classroom/post/{$ujian->id}/mulai");
        $p = Percobaan::firstOrFail();
        $this->actingAs($siswa)->patchJson("/classroom/percobaan/{$p->id}/jawab", ['soalId' => $soal[0]->id, 'jawaban' => '1']);
        $this->actingAs($siswa)->patchJson("/classroom/percobaan/{$p->id}/jawab", ['soalId' => $soal[1]->id, 'jawaban' => '1']);
        $this->actingAs($siswa)->post("/classroom/percobaan/{$p->id}/selesai");

        $this->actingAs($guru)->get("/classroom/kelas/{$kelas->id}/nilai")->assertInertia(fn (Assert $page) => $page
            ->component('classroom/kelas/nilai-page')
            ->has('kolom', 2)
            // Tugas 80/100 = 80%, ujian 20/40 (essay kosong = 0) = 50% -> rata-rata 65.
            ->where('baris.0.rata', 65));

        $this->actingAs($guru)->get("/classroom/kelas/{$kelas->id}/nilai/export")->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($siswa)->get("/classroom/kelas/{$kelas->id}/nilai/export")->assertForbidden();
    }
}
