<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    public function test_admin_produksi_diberi_profil_admin_dan_tetap_satu_admin(): void
    {
        $admin = $this->admin('admin@gmail.com', 'admin123');
        $hashSebelum = $admin->password;

        $this->seed(AdminSeeder::class);

        $this->assertDatabaseHas('profil_akun', ['id' => $admin->id, 'username' => 'admin', 'role' => 'ADMIN']);
        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertSame($hashSebelum, $admin->fresh()->password); // sandi produksi tidak diubah
    }

    public function test_admin_masuk_dengan_username_dan_melihat_website_skl_classroom(): void
    {
        $this->admin('admin@gmail.com', 'admin123');
        $this->seed(AdminSeeder::class);

        $this->post('/login', ['email' => 'admin', 'password' => 'admin123'])->assertRedirect('/menu-utama');

        $this->get('/menu-utama')->assertInertia(fn (Assert $page) => $page
            ->component('module-picker-page')
            ->where('modul.0.key', 'website')->where('modul.1.key', 'skl')->where('modul.2.key', 'classroom'));
        $this->get('/admin')->assertOk();
        $this->get('/skl')->assertOk();
        $this->get('/classroom/kelas')->assertOk();
        $this->assertSame(1, Akun::where('role', 'ADMIN')->count());
    }

    public function test_tanpa_data_produksi_dibuat_admin_baru_dengan_sandi_awal(): void
    {
        $this->seed(AdminSeeder::class);

        $akun = Akun::where('username', 'admin')->firstOrFail();
        $this->assertSame('ADMIN', $akun->role);
        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertTrue(Hash::check('admin123', $akun->pengguna->password));

        $this->post('/login', ['email' => 'admin', 'password' => 'admin123'])->assertRedirect('/menu-utama');
        $this->assertAuthenticated();
    }

    public function test_dijalankan_ulang_tidak_menambah_admin(): void
    {
        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, Akun::where('role', 'ADMIN')->count());
        $this->assertSame(1, User::where('role', 'admin')->count());
    }
}
