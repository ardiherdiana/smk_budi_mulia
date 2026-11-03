<?php

namespace Tests\Feature\Classroom;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TugasTest extends TestCase
{
    public function test_siswa_mengumpulkan_guru_menilai_lalu_terkunci(): void
    {
        Storage::fake('local');
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $kelas = $this->kelas($guru, $rombel);
        $siswa = $this->siswa($rombel, 'siswa01');
        $tugas = $this->buatPost($kelas, $guru, 'TUGAS', ['deadline' => now()->addDay(), 'poinMaks' => 100]);

        $this->actingAs($siswa)->post("/classroom/post/{$tugas->id}/kumpulkan", [
            'teks' => 'Ini jawaban saya',
            'berkas' => [UploadedFile::fake()->create('jawaban.pdf', 100, 'application/pdf')],
        ])->assertRedirect();

        $pengumpulan = $tugas->pengumpulan()->first();
        $this->assertSame('DIKUMPULKAN', $pengumpulan->status);
        $this->assertFalse($pengumpulan->terlambat);
        $this->assertCount(1, $pengumpulan->lampiran);

        $this->actingAs($guru)->patch("/classroom/pengumpulan/{$pengumpulan->id}/nilai", ['nilai' => 88, 'feedback' => 'Bagus'])->assertRedirect();
        $pengumpulan->refresh();
        $this->assertSame('DINILAI', $pengumpulan->status);
        $this->assertEquals(88.0, $pengumpulan->nilai);

        // Setelah dinilai, siswa tidak bisa mengubah atau membatalkan.
        $this->actingAs($siswa)->withHeader('X-Inertia', 'true')->post("/classroom/post/{$tugas->id}/kumpulkan", ['teks' => 'ubah'])->assertSessionHasErrors('message');
        $this->actingAs($siswa)->withHeader('X-Inertia', 'true')->delete("/classroom/post/{$tugas->id}/kumpulkan")->assertSessionHasErrors('message');
        $this->assertSame('Ini jawaban saya', $pengumpulan->refresh()->teks);
    }

    public function test_nilai_tidak_boleh_melebihi_poin_maksimal(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $kelas = $this->kelas($guru, $rombel);
        $siswa = $this->siswa($rombel, 'siswa01');
        $tugas = $this->buatPost($kelas, $guru, 'TUGAS', ['poinMaks' => 50]);
        $this->actingAs($siswa)->post("/classroom/post/{$tugas->id}/kumpulkan", ['teks' => 'x']);
        $pengumpulan = $tugas->pengumpulan()->first();

        $this->actingAs($guru)->patch("/classroom/pengumpulan/{$pengumpulan->id}/nilai", ['nilai' => 51])->assertSessionHasErrors('nilai');
        $this->assertSame('DIKUMPULKAN', $pengumpulan->refresh()->status);
    }

    public function test_terlambat_ditandai_atau_ditolak_sesuai_pengaturan(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $kelas = $this->kelas($guru, $rombel);
        $siswa = $this->siswa($rombel, 'siswa01');
        $boleh = $this->buatPost($kelas, $guru, 'TUGAS', ['deadline' => now()->subHour(), 'izinTerlambat' => true]);
        $tidak = $this->buatPost($kelas, $guru, 'TUGAS', ['deadline' => now()->subHour(), 'izinTerlambat' => false]);

        $this->actingAs($siswa)->post("/classroom/post/{$boleh->id}/kumpulkan", ['teks' => 'telat'])->assertRedirect();
        $this->assertTrue($boleh->pengumpulan()->first()->terlambat);

        $this->actingAs($siswa)->withHeader('X-Inertia', 'true')->post("/classroom/post/{$tidak->id}/kumpulkan", ['teks' => 'telat'])->assertSessionHasErrors('message');
        $this->assertSame(0, $tidak->pengumpulan()->count());
    }

    public function test_pengumpulan_kosong_ditolak(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $tugas = $this->buatPost($this->kelas($guru, $rombel), $guru, 'TUGAS');
        $siswa = $this->siswa($rombel, 'siswa01');

        $this->actingAs($siswa)->withHeader('X-Inertia', 'true')->post("/classroom/post/{$tugas->id}/kumpulkan", ['teks' => ''])->assertSessionHasErrors('message');
    }

    public function test_berkas_hasil_kerja_tidak_terlihat_teman_sekelas(): void
    {
        Storage::fake('local');
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $kelas = $this->kelas($guru, $rombel);
        $pemilik = $this->siswa($rombel, 'siswa01');
        $teman = $this->siswa($rombel, 'siswa02');
        $tugas = $this->buatPost($kelas, $guru, 'TUGAS');

        $this->actingAs($pemilik)->post("/classroom/post/{$tugas->id}/kumpulkan", [
            'berkas' => [UploadedFile::fake()->create('rahasia.pdf', 50, 'application/pdf')],
        ]);
        $lampiran = $tugas->pengumpulan()->first()->lampiran()->first();

        $this->actingAs($pemilik)->get("/classroom/lampiran/{$lampiran->id}")->assertOk();
        $this->actingAs($guru)->get("/classroom/lampiran/{$lampiran->id}")->assertOk();
        $this->actingAs($teman)->get("/classroom/lampiran/{$lampiran->id}")->assertForbidden();
    }

    public function test_guru_lain_tidak_bisa_menilai_tugas_kelas_orang(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $lain = $this->akun('GURU', 'guru2');
        $rombel = $this->rombel();
        $tugas = $this->buatPost($this->kelas($guru, $rombel), $guru, 'TUGAS');
        $siswa = $this->siswa($rombel, 'siswa01');
        $this->actingAs($siswa)->post("/classroom/post/{$tugas->id}/kumpulkan", ['teks' => 'x']);

        $this->actingAs($lain)->patch('/classroom/pengumpulan/'.$tugas->pengumpulan()->first()->id.'/nilai', ['nilai' => 10])->assertForbidden();
    }

    public function test_halaman_detail_tugas_untuk_guru_memuat_semua_siswa_rombel(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $tugas = $this->buatPost($this->kelas($guru, $rombel), $guru, 'TUGAS');
        $this->siswa($rombel, 'siswa01');
        $this->siswa($rombel, 'siswa02');

        $this->actingAs($guru)->get("/classroom/post/{$tugas->id}")
            ->assertInertia(fn (Assert $page) => $page->component('classroom/post/detail-page')->has('pengumpulan', 2));
    }
}
