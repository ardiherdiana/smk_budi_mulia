<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Guru;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginMenuTest extends TestCase
{
    private function recaller(): string
    {
        return $this->app['auth']->guard()->getRecallerName();
    }

    public function test_siswa_login_dengan_username_lalu_ke_menu_dan_classroom(): void
    {
        $this->siswa($this->rombel(), 'siswa01');

        $this->post('/login', ['email' => 'siswa01', 'password' => 'rahasia123'])->assertRedirect('/menu-utama');
        $this->assertAuthenticated();

        $this->get('/menu-utama')->assertInertia(fn (Assert $page) => $page
            ->component('module-picker-page')
            ->where('modul.0.key', 'skl')->where('modul.1.key', 'classroom')->where('modul.2.key', 'profil'));
    }

    public function test_sandi_siswa_salah_atau_nonaktif_ditolak(): void
    {
        $akun = $this->siswa($this->rombel(), 'siswa01');

        $this->post('/login', ['email' => 'siswa01', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $akun->update(['is_active' => false]);
        $this->post('/login', ['email' => 'siswa01', 'password' => 'rahasia123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guru_login_dengan_username_dan_sandi_lokal_lalu_melihat_classroom_absensi_sppd(): void
    {
        // Sejak absensi digabung, sandi staf ada di users.password (dipindah oleh gabung:impor-absensi); tidak ada
        // lagi verifikasi ke basis data absensi.
        $this->akun('GURU', 'bu.dina', 'Dina Qoyimah, S.Pd. Gr.');

        $this->post('/login', ['email' => 'bu.dina', 'password' => 'rahasia123'])->assertRedirect('/menu-utama');

        $this->assertAuthenticated();
        $this->get('/menu-utama')->assertInertia(fn (Assert $page) => $page
            ->component('module-picker-page')
            ->where('modul', [
                ['key' => 'classroom', 'href' => '/classroom/kelas'],
                ['key' => 'absensi', 'href' => '/absen/dashboard'],
                ['key' => 'sppd', 'href' => '/sppd/dashboard'],
                ['key' => 'profil', 'href' => '/profil'],
            ]));
    }

    public function test_kepsek_membuka_data_guru_dari_menu_utama_bukan_dari_modul(): void
    {
        $this->actingAs($this->akun('KEPSEK', 'kepala', 'Kepala Sekolah'));

        $this->get('/menu-utama')->assertInertia(fn (Assert $page) => $page->where('modul', [
            ['key' => 'classroom', 'href' => '/classroom/kelas'],
            ['key' => 'absensi', 'href' => '/absen/dashboard'],
            ['key' => 'sppd', 'href' => '/sppd/dashboard'],
            ['key' => 'dataguru', 'href' => '/data-guru'],
            ['key' => 'profil', 'href' => '/profil'],
        ]));
        // Halaman mandiri (kop sendiri, tanpa sidebar Absen).
        $this->get('/data-guru')->assertOk()->assertInertia(fn (Assert $page) => $page->component('data-guru-page'));
    }

    public function test_sandi_guru_salah_ditolak(): void
    {
        $this->akun('GURU', 'bu.dina');

        $this->post('/login', ['email' => 'bu.dina', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guru_yang_dinonaktifkan_di_data_guru_absensi_ditolak(): void
    {
        $akun = $this->akun('GURU', 'bu.dina');
        Guru::factory()->inactive()->create(['userId' => $akun->id]);

        $this->post('/login', ['email' => 'bu.dina', 'password' => 'rahasia123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_pegawai_sppd_saja_melihat_sppd_dan_profil(): void
    {
        $this->actingAs(User::factory()->staf()->create());

        $this->get('/menu-utama')->assertInertia(fn (Assert $page) => $page->where('modul', [
            ['key' => 'sppd', 'href' => '/sppd/dashboard'],
            ['key' => 'profil', 'href' => '/profil'],
        ]));
        $this->get('/classroom/kelas')->assertForbidden();
        $this->get('/absen/dashboard')->assertForbidden();
    }

    public function test_ingat_saya_hanya_untuk_staf(): void
    {
        $guru = $this->akun('GURU', 'bu.dina');

        $this->post('/login', ['email' => 'bu.dina', 'password' => 'rahasia123'])->assertCookie($this->recaller());
        $this->assertNotNull($guru->fresh()->remember_token);
        $this->post('/logout');

        $this->siswa($this->rombel(), 'siswa01');
        $this->post('/login', ['email' => 'siswa01', 'password' => 'rahasia123'])->assertCookieMissing($this->recaller());
    }

    public function test_login_email_lama_tetap_bekerja_dan_admin_melihat_semua_kartu(): void
    {
        $this->admin('admin@test.local', 'Rahasia123');

        $this->post('/login', ['email' => 'admin@test.local', 'password' => 'Rahasia123'])->assertRedirect('/menu-utama');

        $this->get('/menu-utama')->assertInertia(fn (Assert $page) => $page
            ->component('module-picker-page')
            ->where('modul.0.key', 'website')->where('modul.1.key', 'skl')->where('modul.1.href', '/skl')
            ->where('modul.2.key', 'classroom')->where('modul.3.key', 'absensi')->where('modul.4.key', 'sppd')
            ->where('modul.5.key', 'dataguru')->where('modul.5.href', '/data-guru')
            ->where('modul.6.key', 'profil')->has('modul', 7));
        $this->assertDatabaseHas('profil_akun', ['role' => 'ADMIN']);
    }

    public function test_admin_lama_tetap_bisa_masuk_saat_username_awalan_emailnya_sudah_dipakai(): void
    {
        $this->akun('ADMIN', 'admin'); // admin hasil sinkron absensi
        $this->admin('admin@gmail.com', 'admin123');

        $this->post('/login', ['email' => 'admin@gmail.com', 'password' => 'admin123'])->assertRedirect('/menu-utama');
        $this->get('/menu-utama')->assertOk();
        $this->get('/classroom/kelas')->assertOk();
        $this->assertSame(2, Akun::where('role', 'ADMIN')->count());
    }

    public function test_alumni_portal_tanpa_profil_classroom_melihat_skl_dan_profil(): void
    {
        $this->actingAsStudent();

        $this->get('/menu-utama')->assertInertia(fn (Assert $page) => $page->where('modul', [
            ['key' => 'skl', 'href' => '/portal'],
            ['key' => 'profil', 'href' => '/profil'],
        ]));
        $this->get('/classroom/kelas')->assertForbidden();
    }

    public function test_classroom_dan_menu_butuh_login(): void
    {
        $this->get('/menu-utama')->assertRedirect('/login');
        $this->get('/classroom/kelas')->assertRedirect('/login');
    }

    public function test_profil_tercipta_hanya_satu_baris_users_per_akun(): void
    {
        $this->akun('GURU', 'guru1');

        $this->assertSame(1, Akun::count());
        $this->assertDatabaseCount('users', 1);
    }
}
