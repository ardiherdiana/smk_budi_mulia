<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Kelas;
use App\Models\Classroom\Lampiran;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\TahunAjaran;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostMultiKelasTest extends TestCase
{
    /** Satu guru dengan tiga ruang kelas di rombel berbeda: X TKJ 1, X TKJ 2, dan XI RPL 1. */
    private function siapkan(): array
    {
        $guru = $this->akun('GURU', 'guru1');
        $a = $this->kelas($guru, $this->rombel('X TKJ 1', 'X', 'TKJ'));
        $b = $this->kelas($guru, $this->rombel('X TKJ 2', 'X', 'TKJ'));
        $c = $this->kelas($guru, $this->rombel('XI RPL 1', 'XI', 'RPL'));

        return [$guru, $a, $b, $c];
    }

    /** @return list<Post> */
    private function postsDi(Kelas ...$kelas): array
    {
        return array_map(fn (Kelas $k) => Post::where('kelasId', $k->id)->firstOrFail(), $kelas);
    }

    public function test_pengumuman_terbit_ke_beberapa_kelas_dengan_grup_yang_sama(): void
    {
        [$guru, $a, $b, $c] = $this->siapkan();

        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'PENGUMUMAN', 'isi' => 'Besok libur', 'kelasTujuan' => [$a->id, $b->id],
        ])->assertRedirect();

        $this->assertDatabaseCount('classroom_posts', 2);
        [$pa, $pb] = $this->postsDi($a, $b);
        $this->assertNotNull($pa->grupId);
        $this->assertSame($pa->grupId, $pb->grupId);
        $this->assertSame(0, Post::where('kelasId', $c->id)->count());
    }

    public function test_kelas_asal_selalu_ikut_dan_tanpa_tujuan_tidak_ada_grup(): void
    {
        [$guru, $a, $b] = $this->siapkan();

        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", ['tipe' => 'PENGUMUMAN', 'isi' => 'Sendiri'])->assertRedirect();
        $this->assertNull($this->postsDi($a)[0]->grupId);

        // Kelas asal tidak dikirim di daftar tujuan, tetapi tetap ikut menerima postingan.
        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'PENGUMUMAN', 'isi' => 'Berdua', 'kelasTujuan' => [$b->id],
        ])->assertRedirect();
        $this->assertSame(2, Post::where('kelasId', $a->id)->count());
        $this->assertSame(1, Post::where('kelasId', $b->id)->count());
    }

    public function test_tujuan_milik_guru_lain_diarsipkan_atau_tahun_lain_ditolak(): void
    {
        [$guru, $a, , $c] = $this->siapkan();
        $guruLain = $this->akun('GURU', 'guru2');
        $milikLain = $this->kelas($guruLain, $this->rombel('X AKL 1', 'X', 'AKL'), 'Akuntansi');
        $tahunLama = TahunAjaran::create(['nama' => '2025/2026', 'semester' => 'GENAP', 'aktif' => false]);
        $lama = Kelas::create([
            'rombelId' => $this->rombel('XII TKJ 1', 'XII', 'TKJ')->id,
            'mapelId' => $a->mapelId,
            'guruId' => $guru->id,
            'tahunAjaranId' => $tahunLama->id,
        ]);
        $c->update(['diarsipkan' => true]);

        foreach ([$milikLain, $lama, $c] as $tujuan) {
            $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", [
                'tipe' => 'PENGUMUMAN', 'isi' => 'x', 'kelasTujuan' => [$tujuan->id],
            ])->assertSessionHasErrors('kelasTujuan');
        }

        $this->assertDatabaseCount('classroom_posts', 0);
    }

    public function test_siswa_hanya_melihat_salinan_di_rombelnya(): void
    {
        [$guru, $a, $b] = $this->siapkan();
        $siswaB = $this->siswa($b->rombel, 'siswa-b');

        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'PENGUMUMAN', 'isi' => 'Untuk semua', 'kelasTujuan' => [$b->id],
        ])->assertRedirect();

        $this->actingAs($siswaB)->get("/classroom/kelas/{$b->id}")
            ->assertInertia(fn (Assert $page) => $page->has('posts', 1)->where('posts.0.kelasId', $b->id)->where('tujuan', []));
        $this->actingAs($siswaB)->get("/classroom/kelas/{$a->id}")->assertForbidden();
    }

    public function test_lampiran_digandakan_per_kelas_dan_hapus_di_satu_kelas_tidak_mengganggu_yang_lain(): void
    {
        [$guru, $a, $b] = $this->siapkan();

        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'MATERI', 'judul' => 'Bab 1', 'kelasTujuan' => [$b->id],
            'berkas' => [UploadedFile::fake()->create('bab1.pdf', 10, 'application/pdf')],
            'tautan' => ['https://example.com/bab1'],
        ])->assertRedirect();

        [$pa, $pb] = $this->postsDi($a, $b);
        $berkasA = Lampiran::where(['postId' => $pa->id, 'tipe' => 'FILE'])->firstOrFail();
        $berkasB = Lampiran::where(['postId' => $pb->id, 'tipe' => 'FILE'])->firstOrFail();
        $this->assertNotSame($berkasA->path, $berkasB->path);
        $this->assertSame('bab1.pdf', $berkasB->nama);
        $this->assertSame(1, Lampiran::where(['postId' => $pb->id, 'tipe' => 'LINK'])->count());
        Storage::disk('local')->assertExists([$berkasA->path, $berkasB->path]);

        $this->actingAs($guru)->delete("/classroom/post/{$pa->id}")->assertRedirect();

        Storage::disk('local')->assertMissing($berkasA->path);
        Storage::disk('local')->assertExists($berkasB->path);
        $this->assertNotNull(Post::find($pb->id));
    }

    public function test_ubah_hanya_satu_kelas_atau_semua_kelas(): void
    {
        [$guru, $a, $b] = $this->siapkan();
        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'PENGUMUMAN', 'isi' => 'Awal', 'kelasTujuan' => [$b->id],
        ])->assertRedirect();
        [$pa, $pb] = $this->postsDi($a, $b);

        $this->actingAs($guru)->patch("/classroom/post/{$pa->id}", ['isi' => 'Hanya A'])->assertRedirect();
        $this->assertSame('Hanya A', $pa->fresh()->isi);
        $this->assertSame('Awal', $pb->fresh()->isi);

        $this->actingAs($guru)->patch("/classroom/post/{$pa->id}", ['isi' => 'Semua', 'semuaKelas' => 1])->assertRedirect();
        $this->assertSame('Semua', $pa->fresh()->isi);
        $this->assertSame('Semua', $pb->fresh()->isi);
    }

    public function test_ubah_semua_kelas_meneruskan_lampiran_baru_dan_penghapusan(): void
    {
        [$guru, $a, $b] = $this->siapkan();
        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'MATERI', 'judul' => 'Bab 1', 'kelasTujuan' => [$b->id],
            'tautan' => ['https://example.com/lama'],
        ])->assertRedirect();
        [$pa, $pb] = $this->postsDi($a, $b);
        $lama = Lampiran::where('postId', $pa->id)->firstOrFail();

        $this->actingAs($guru)->patch("/classroom/post/{$pa->id}", [
            'judul' => 'Bab 1',
            'semuaKelas' => 1,
            'hapusLampiran' => [$lama->id],
            'berkas' => [UploadedFile::fake()->create('baru.pdf', 10, 'application/pdf')],
        ])->assertRedirect();

        foreach ([$pa, $pb] as $post) {
            $nama = Lampiran::where('postId', $post->id)->pluck('nama')->all();
            $this->assertSame(['baru.pdf'], $nama);
        }
    }

    public function test_hapus_hanya_satu_kelas_atau_semua_kelas(): void
    {
        [$guru, $a, $b, $c] = $this->siapkan();
        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'PENGUMUMAN', 'isi' => 'Hapus aku', 'kelasTujuan' => [$b->id, $c->id],
        ])->assertRedirect();
        [$pa, $pb, $pc] = $this->postsDi($a, $b, $c);

        $this->actingAs($guru)->delete("/classroom/post/{$pa->id}")->assertRedirect();
        $this->assertNull(Post::find($pa->id));
        $this->assertNotNull(Post::find($pb->id));

        $this->actingAs($guru)->delete("/classroom/post/{$pb->id}", ['semuaKelas' => 1])->assertRedirect();
        $this->assertNull(Post::find($pb->id));
        $this->assertNull(Post::find($pc->id));
    }

    public function test_soal_ujian_disalin_ke_kelas_lain_dan_yang_sudah_dikerjakan_dilewati(): void
    {
        [$guru, $a, $b, $c] = $this->siapkan();
        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'UJIAN', 'judul' => 'Kuis 1', 'durasiMenit' => 30, 'kelasTujuan' => [$b->id, $c->id],
        ])->assertRedirect();
        [$pa, $pb, $pc] = $this->postsDi($a, $b, $c);

        // Kelas C sudah ada siswa yang mengerjakan, jadi soalnya tidak boleh diubah.
        $siswaC = $this->siswa($c->rombel, 'siswa-c');
        Percobaan::create([
            'postId' => $pc->id, 'siswaId' => $siswaC->siswa->id,
            'mulaiAt' => now(), 'batasAt' => now()->addMinutes(30), 'status' => 'BERJALAN',
        ]);

        $this->actingAs($guru)->put("/classroom/post/{$pa->id}/soal", [
            'soal' => [['tipe' => 'PG', 'teks' => 'Berapa 2 + 2?', 'poin' => 2, 'opsi' => ['3', '4', '5'], 'kunci' => '1']],
        ])->assertRedirect("/classroom/post/{$pa->id}");

        $this->assertSame(1, $pa->soal()->count());
        $this->assertSame(1, $pb->soal()->count());
        $this->assertSame(0, $pc->soal()->count());

        $salinan = $pb->soal()->first();
        $this->assertSame('Berapa 2 + 2?', $salinan->teks);
        $this->assertSame('1', $salinan->kunci);
        $this->assertSame(['3', '4', '5'], $salinan->opsi);
        $this->assertNotSame($pa->soal()->first()->id, $salinan->id);

        // Menyimpan ulang menggantikan soal salinan, bukan menambahnya.
        $this->actingAs($guru)->put("/classroom/post/{$pa->id}/soal", [
            'soal' => [['tipe' => 'ESSAY', 'teks' => 'Jelaskan TCP/IP', 'poin' => 5]],
        ])->assertRedirect();
        $this->assertSame(['Jelaskan TCP/IP'], $pb->soal()->pluck('teks')->all());
    }

    public function test_admin_hanya_bisa_menarget_kelas_milik_guru_pengajar(): void
    {
        [$guru, $a, $b] = $this->siapkan();
        $guruLain = $this->akun('GURU', 'guru2');
        $milikLain = $this->kelas($guruLain, $this->rombel('X AKL 1', 'X', 'AKL'), 'Akuntansi');
        $admin = $this->akun('ADMIN', 'admin1');

        $this->actingAs($admin)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'PENGUMUMAN', 'isi' => 'x', 'kelasTujuan' => [$milikLain->id],
        ])->assertSessionHasErrors('kelasTujuan');

        $this->actingAs($admin)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'PENGUMUMAN', 'isi' => 'dari admin', 'kelasTujuan' => [$b->id],
        ])->assertRedirect();
        $this->assertSame(2, Post::count());
    }

    public function test_halaman_stream_memuat_pilihan_tujuan_hanya_untuk_pengelola(): void
    {
        [$guru, $a, $b, $c] = $this->siapkan();
        $siswa = $this->siswa($a->rombel, 'siswa-a');

        $this->actingAs($guru)->get("/classroom/kelas/{$a->id}")
            ->assertInertia(fn (Assert $page) => $page->component('classroom/kelas/stream-page')
                ->has('tujuan', 3)
                ->where('tujuan.0.rombel', 'X TKJ 1')
                ->where('tujuan.0.tingkat', 'X')
                ->where('tujuan.0.jurusan', 'TKJ')
                ->where('tujuan.2.tingkat', 'XI')
                ->where('tujuan.2.jurusan', 'RPL'));

        $this->actingAs($siswa)->get("/classroom/kelas/{$a->id}")->assertInertia(fn (Assert $page) => $page->where('tujuan', []));

        $b->update(['diarsipkan' => true]);
        $this->actingAs($guru)->get("/classroom/kelas/{$a->id}")->assertInertia(fn (Assert $page) => $page->has('tujuan', 2));
    }

    public function test_jumlah_grup_tampil_di_stream(): void
    {
        [$guru, $a, $b] = $this->siapkan();
        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'PENGUMUMAN', 'isi' => 'Grup', 'kelasTujuan' => [$b->id],
        ])->assertRedirect();
        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", ['tipe' => 'PENGUMUMAN', 'isi' => 'Sendiri'])->assertRedirect();

        // Urutan stream mengikuti waktu buat (bisa sama dalam satu milidetik), jadi bandingkan tanpa memedulikan urutan.
        $this->actingAs($guru)->get("/classroom/kelas/{$a->id}")->assertInertia(
            fn (Assert $page) => $page->has('posts', 2)
                ->where('posts', fn ($posts) => collect($posts)->pluck('jumlahGrup')->sort()->values()->all() === [1, 2])
        );
    }

    public function test_menghapus_ruang_kelas_membersihkan_postingan_dan_berkasnya(): void
    {
        [$guru, $a] = $this->siapkan();
        $this->actingAs($guru)->post("/classroom/kelas/{$a->id}/post", [
            'tipe' => 'MATERI', 'judul' => 'Bab 1',
            'berkas' => [UploadedFile::fake()->create('bab1.pdf', 10, 'application/pdf')],
        ])->assertRedirect();
        $path = Lampiran::firstOrFail()->path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($guru)->delete("/classroom/kelas/{$a->id}")->assertRedirect('/classroom/kelas');

        $this->assertNull(Kelas::find($a->id));
        $this->assertDatabaseCount('classroom_posts', 0);
        Storage::disk('local')->assertMissing($path);
    }
}
