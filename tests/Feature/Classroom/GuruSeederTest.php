<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use Database\Seeders\GuruSeeder;
use Database\Seeders\MapelSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuruSeederTest extends TestCase
{
    public function test_daftar_guru_konsisten_dengan_daftar_mapel(): void
    {
        $this->assertCount(23, GuruSeeder::DAFTAR);
        $usernames = array_column(GuruSeeder::DAFTAR, 'username');
        $this->assertSame($usernames, array_values(array_unique($usernames)), 'username harus unik');

        foreach (GuruSeeder::DAFTAR as $guru) {
            $this->assertNotSame([], $guru['mapel'], "{$guru['nama']} tidak punya mapel");
            $this->assertSame($guru['mapel'], array_values(array_unique($guru['mapel'])), "{$guru['nama']} punya mapel ganda");
            $this->assertSame([], array_diff($guru['mapel'], MapelSeeder::DAFTAR), "{$guru['nama']} memakai mapel yang tidak ada di MapelSeeder::DAFTAR");
            $this->assertSame(1, preg_match('/^\$2y\$\d{2}\$/', $guru['hash']), "{$guru['username']} bukan hash bcrypt");
        }

        // Setiap mapel resmi dipakai minimal satu guru.
        $dipakai = array_unique(array_merge(...array_column(GuruSeeder::DAFTAR, 'mapel')));
        $this->assertSame([], array_diff(MapelSeeder::DAFTAR, $dipakai));
    }

    public function test_seeder_membuat_akun_guru_dengan_hash_apa_adanya(): void
    {
        $this->seed(GuruSeeder::class);

        $this->assertSame(23, Akun::where('role', 'GURU')->count());

        // Hash harus tersimpan persis (tidak di-hash ulang oleh cast `hashed`, apa pun biaya bcrypt di lingkungan tes).
        foreach (GuruSeeder::DAFTAR as $guru) {
            $akun = Akun::where('username', $guru['username'])->firstOrFail();
            $this->assertSame($guru['nama'], $akun->name);
            $this->assertTrue($akun->is_active);
            $this->assertSame($guru['hash'], DB::table('users')->where('id', $akun->id)->value('password'));
            $this->assertNull($akun->absensi_user_id);
        }
    }

    public function test_dijalankan_ulang_memperbarui_nama_tanpa_menimpa_sandi_peran_atau_status(): void
    {
        $this->seed(GuruSeeder::class);
        $contoh = GuruSeeder::DAFTAR[0];
        $akun = Akun::where('username', $contoh['username'])->firstOrFail();

        // Admin mengubah sandi, peran, dan status lewat Data Guru; nama lama juga masih berbeda.
        $akun->name = 'Nama Lama';
        $akun->role = 'KEPSEK';
        $akun->is_active = false;
        $akun->password = 'sandi-baru-admin';
        $akun->save();

        $this->seed(GuruSeeder::class);

        $akun->refresh();
        $this->assertSame(23, Akun::whereIn('role', ['GURU', 'KEPSEK'])->count());
        $this->assertSame($contoh['nama'], $akun->name);
        $this->assertSame('KEPSEK', $akun->role);
        $this->assertFalse($akun->is_active);
        $this->assertTrue(Hash::check('sandi-baru-admin', $akun->password));
    }

    public function test_guru_hasil_seed_bisa_masuk_dengan_username(): void
    {
        // Sandi asli hanya ada di Excel, jadi masuknya diuji dengan mengganti hash akun contoh memakai sandi yang diketahui.
        $this->seed(GuruSeeder::class);
        $contoh = GuruSeeder::DAFTAR[0];
        DB::table('users')->where('id', Akun::where('username', $contoh['username'])->value('id'))
            ->update(['password' => Hash::make('rahasia123')]);

        $this->post('/login', ['email' => $contoh['username'], 'password' => 'rahasia123'])->assertRedirect('/menu-utama');
        $this->assertAuthenticated();
    }
}
