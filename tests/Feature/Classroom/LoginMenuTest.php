<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Services\Classroom\AbsensiAccountService;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginMenuTest extends TestCase
{
    private function absensiPalsu(string $sandi = 'sandi-absensi'): void
    {
        $this->instance(AbsensiAccountService::class, new class($sandi) extends AbsensiAccountService
        {
            public function __construct(private string $sandi) {}

            public function cari(string $username): ?\stdClass
            {
                return $username === 'bu.dina' ? (object) [
                    'id' => '01JABSENSIDINA00000000000A', 'username' => 'bu.dina', 'name' => null,
                    'password' => Hash::make($this->sandi), 'role' => 'GURU', 'is_active' => 1,
                    'guru_nama' => 'Dina Qoyimah, S.Pd. Gr.', 'guru_aktif' => 1,
                ] : null;
            }
        });
    }

    public function test_siswa_login_dengan_username_lalu_ke_menu_dan_classroom(): void
    {
        $this->siswa($this->rombel(), 'siswa01');

        $this->post('/login', ['email' => 'siswa01', 'password' => 'rahasia123'])->assertRedirect('/menu-utama');
        $this->assertAuthenticated();

        $this->get('/menu-utama')->assertInertia(fn (Assert $page) => $page
            ->component('module-picker-page')
            ->where('modul.0.key', 'skl')->where('modul.1.key', 'classroom'));
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

    public function test_guru_diverifikasi_ke_absensi_lalu_disalin(): void
    {
        $this->absensiPalsu();

        $this->post('/login', ['email' => 'bu.dina', 'password' => 'sandi-absensi'])->assertRedirect('/menu-utama');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('profil_akun', ['username' => 'bu.dina', 'role' => 'GURU', 'absensi_user_id' => '01JABSENSIDINA00000000000A', 'name' => 'Dina Qoyimah, S.Pd. Gr.']);
        // Guru hanya punya Classroom, jadi menu langsung mengarahkan ke sana.
        $this->get('/menu-utama')->assertRedirect('/classroom/kelas');
    }

    public function test_sandi_guru_salah_ditolak_dan_tidak_membuat_akun(): void
    {
        $this->absensiPalsu();

        $this->post('/login', ['email' => 'bu.dina', 'password' => 'salah'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing('profil_akun', ['username' => 'bu.dina']);
    }

    public function test_login_email_lama_tetap_bekerja_dan_admin_melihat_tiga_kartu(): void
    {
        $this->admin('admin@test.local', 'Rahasia123');

        $this->post('/login', ['email' => 'admin@test.local', 'password' => 'Rahasia123'])->assertRedirect('/menu-utama');

        $this->get('/menu-utama')->assertInertia(fn (Assert $page) => $page
            ->component('module-picker-page')
            ->where('modul.0.key', 'website')->where('modul.1.key', 'skl')->where('modul.1.href', '/skl')->where('modul.2.key', 'classroom'));
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

    public function test_alumni_portal_tanpa_profil_classroom_langsung_ke_skl(): void
    {
        $this->actingAsStudent();

        $this->get('/menu-utama')->assertRedirect('/portal');
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
