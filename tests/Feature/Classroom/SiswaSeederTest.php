<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\Siswa;
use Database\Seeders\RombelSeeder;
use Database\Seeders\SiswaSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SiswaSeederTest extends TestCase
{
    public function test_data_siswa_lengkap_dan_konsisten(): void
    {
        $data = SiswaSeeder::daftar();
        $this->assertCount(380, $data);

        $usernames = array_column($data, 2);
        $this->assertSame($usernames, array_values(array_unique($usernames)), 'username harus unik');

        foreach ($data as [$rombel, $nama, $username, $hash]) {
            $this->assertContains($rombel, RombelSeeder::DAFTAR, "{$username}: rombel '{$rombel}' tidak ada di RombelSeeder::DAFTAR");
            $this->assertNotSame('', trim($nama));
            $this->assertSame(1, preg_match('/^2627\d{4}$/', $username));
            $this->assertSame(1, preg_match('/^\$2y\$\d{2}\$/', $hash), "{$username} bukan hash bcrypt");
        }

        // Semua 14 rombel terisi; sebarannya sama dengan berkas kredensial produksi.
        $perRombel = array_count_values(array_column($data, 0));
        $this->assertCount(14, $perRombel);
        $this->assertSame(33, $perRombel['XII TKJ 1']);
        $this->assertSame(29, $perRombel['XI TKJ 1']);
        $this->assertSame(11, $perRombel['XI AKT']);
        $this->assertSame(39, $perRombel['X RPL']);
    }

    public function test_seeder_membuat_siswa_di_rombel_baru_dengan_hash_apa_adanya(): void
    {
        $this->seed(RombelSeeder::class);
        $this->seed(SiswaSeeder::class);

        $this->assertSame(14, Rombel::count(), 'tidak boleh membuat rombel tambahan');
        $this->assertSame(380, Siswa::count());
        $this->assertSame(380, Akun::where('role', 'SISWA')->count());

        foreach (SiswaSeeder::daftar() as [$namaRombel, $nama, $username, $hash]) {
            $akun = Akun::where('username', $username)->firstOrFail();
            $this->assertTrue($akun->is_active);
            $this->assertSame($hash, DB::table('users')->where('id', $akun->id)->value('password'), "hash {$username} berubah");
            $this->assertSame($nama, $akun->siswa->nama);
            $this->assertSame($namaRombel, $akun->siswa->rombel->nama);
            $this->assertNull($akun->siswa->nis);
        }
    }

    public function test_biaya_bcrypt_dikembalikan_setelah_seed(): void
    {
        $this->seed(SiswaSeeder::class);

        $this->assertSame((int) config('hashing.bcrypt.rounds', 12), password_get_info(Hash::make('contoh'))['options']['cost']);
    }

    public function test_tanpa_rombelseeder_rombel_dibuat_sendiri_tanpa_wali(): void
    {
        $this->seed(SiswaSeeder::class);

        $this->assertSame(14, Rombel::count());
        $this->assertSame(0, Rombel::whereNotNull('waliKelasId')->count());
        $this->assertDatabaseHas('rombel', ['nama' => 'XI TKJ 1', 'tingkat' => 'XI', 'jurusan' => 'TKJ']);
    }

    public function test_dijalankan_ulang_hanya_memperbarui_nama(): void
    {
        $this->seed(SiswaSeeder::class);
        [, $nama, $username] = SiswaSeeder::daftar()[0];
        $pindah = Rombel::create(['nama' => 'X TKJ 9', 'tingkat' => 'X', 'jurusan' => 'TKJ']);

        // Admin mengubah nama, rombel, NIS, dan sandi siswa lewat aplikasi.
        $akun = Akun::where('username', $username)->firstOrFail();
        $akun->name = 'Nama Lama';
        $akun->password = 'sandi-baru-siswa';
        $akun->save();
        $akun->siswa->update(['nama' => 'Nama Lama', 'rombelId' => $pindah->id, 'nis' => '12345']);

        $this->seed(SiswaSeeder::class);

        $akun->refresh();
        $this->assertSame(380, Siswa::count());
        $this->assertSame($nama, $akun->name);
        $this->assertSame($nama, $akun->siswa->nama);
        $this->assertSame($pindah->id, $akun->siswa->rombelId);
        $this->assertSame('12345', $akun->siswa->nis);
        $this->assertTrue(Hash::check('sandi-baru-siswa', $akun->password));
    }

    public function test_siswa_hasil_seed_bisa_masuk_dengan_username(): void
    {
        // Sandi asli hanya ada di Excel produksi, jadi masuknya diuji dengan mengganti hash akun contoh memakai sandi yang diketahui.
        $this->seed(SiswaSeeder::class);
        [, , $username] = SiswaSeeder::daftar()[0];
        DB::table('users')->where('id', Akun::where('username', $username)->value('id'))->update(['password' => Hash::make('rahasia123')]);

        $this->post('/login', ['email' => $username, 'password' => 'rahasia123'])->assertRedirect('/menu-utama');
        $this->assertAuthenticated();
    }
}
