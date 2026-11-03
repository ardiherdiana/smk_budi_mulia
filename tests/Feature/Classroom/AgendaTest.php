<?php

namespace Tests\Feature\Classroom;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    public function test_siswa_hanya_melihat_tugas_dan_kelas_rombelnya(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombelA = $this->rombel('X TKJ 1');
        $rombelB = $this->rombel('X TKJ 2');
        $kelasA = $this->kelas($guru, $rombelA, 'Jaringan Dasar');
        $kelasB = $this->kelas($guru, $rombelB, 'Jaringan Dasar');
        $siswa = $this->siswa($rombelA, 'siswa01');
        $tugasA = $this->buatPost($kelasA, $guru, 'TUGAS', ['deadline' => now()->addDay()]);
        $this->buatPost($kelasB, $guru, 'TUGAS', ['deadline' => now()->addDay()]);
        $this->buatPost($kelasA, $guru, 'PENGUMUMAN');

        $this->actingAs($siswa)->get('/classroom/tugas')->assertInertia(fn (Assert $page) => $page
            ->component('classroom/agenda/tugas-page')
            ->has('items', 1)
            ->where('items.0.id', $tugasA->id)
            ->where('items.0.status', 'BELUM')
            ->has('kelasSaya', 1)
            ->where('kelasSaya.0.id', $kelasA->id));
    }

    public function test_status_pengumpulan_siswa_dan_hitungan_menunggu_nilai_guru(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $kelas = $this->kelas($guru, $rombel);
        $siswa = $this->siswa($rombel, 'siswa01');
        $tugas = $this->buatPost($kelas, $guru, 'TUGAS', ['deadline' => now()->addDay()]);
        $this->actingAs($siswa)->post("/classroom/post/{$tugas->id}/kumpulkan", ['teks' => 'jawaban']);

        $this->actingAs($siswa)->get('/classroom/tugas')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.status', 'DIKUMPULKAN'));
        $this->actingAs($guru)->get('/classroom/tugas')->assertInertia(fn (Assert $page) => $page
            ->where('items.0.menunggu', 1)
            ->missing('items.0.status'));
    }

    public function test_guru_hanya_melihat_kelasnya_dan_arsip_tidak_muncul(): void
    {
        $guru1 = $this->akun('GURU', 'guru1');
        $guru2 = $this->akun('GURU', 'guru2');
        $rombel = $this->rombel();
        $milik = $this->kelas($guru1, $rombel, 'Jaringan Dasar');
        $lain = $this->kelas($guru2, $rombel, 'Pemrograman Web');
        $arsip = $this->kelas($guru1, $rombel, 'Basis Data');
        $arsip->update(['diarsipkan' => true]);
        $this->buatPost($milik, $guru1, 'TUGAS');
        $this->buatPost($lain, $guru2, 'TUGAS');
        $this->buatPost($arsip, $guru1, 'TUGAS');

        $this->actingAs($guru1)->get('/classroom/tugas')->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.kelasId', $milik->id)
            ->has('kelasSaya', 1));
    }

    public function test_kalender_menyaring_per_bulan(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $kelas = $this->kelas($guru, $this->rombel());
        $bulanIni = $this->buatPost($kelas, $guru, 'TUGAS', ['deadline' => now()->startOfMonth()->addDays(2)]);
        $this->buatPost($kelas, $guru, 'TUGAS', ['deadline' => now()->addMonths(2)]);

        $this->actingAs($guru)->get('/classroom/kalender?bulan='.now()->format('Y-m'))->assertInertia(fn (Assert $page) => $page
            ->component('classroom/agenda/kalender-page')
            ->has('items', 1)
            ->where('items.0.id', $bulanIni->id));
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/classroom/tugas')->assertRedirect('/login');
        $this->get('/classroom/kalender')->assertRedirect('/login');
    }
}
