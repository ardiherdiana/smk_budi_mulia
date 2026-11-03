<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Rombel;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuruCrudTest extends TestCase
{
    /** Admin yang sedang masuk beserta profil Classroom-nya (dibuat otomatis untuk admin lama). */
    private function profilAdmin(): Akun
    {
        $pengguna = $this->actingAsAdmin();

        return Akun::untukPengguna($pengguna);
    }

    private function data(array $ubah = []): array
    {
        return $ubah + ['nama' => 'Dina Qoyimah', 'username' => 'bu.dina', 'password' => 'rahasia123', 'role' => 'GURU'];
    }

    public function test_admin_menambah_guru_dan_guru_bisa_masuk_dengan_sandi_lokal(): void
    {
        $this->actingAsAdmin();

        $this->post('/classroom/data-guru', $this->data())->assertSessionHasNoErrors();

        $akun = Akun::where('username', 'bu.dina')->firstOrFail();
        $this->assertSame('GURU', $akun->role);
        $this->assertSame('Dina Qoyimah', $akun->name);
        $this->assertNull($akun->absensi_user_id);
        $this->assertTrue(Hash::check('rahasia123', $akun->password));

        $this->post('/logout');
        $this->post('/login', ['email' => 'bu.dina', 'password' => 'rahasia123'])->assertRedirect('/menu-utama');
        $this->assertAuthenticatedAs(User::find($akun->id));
    }

    public function test_validasi_username_dobel_karakter_terlarang_dan_peran(): void
    {
        $this->akun('GURU', 'bu.dina');
        $this->actingAsAdmin();

        $this->post('/classroom/data-guru', $this->data())->assertSessionHasErrors('username');
        $this->post('/classroom/data-guru', $this->data(['username' => 'a@b']))->assertSessionHasErrors('username');
        $this->post('/classroom/data-guru', $this->data(['username' => 'baru', 'password' => '123']))->assertSessionHasErrors('password');
        $this->post('/classroom/data-guru', $this->data(['username' => 'baru', 'role' => 'ADMIN']))->assertSessionHasErrors('role');
        $this->post('/classroom/data-guru', $this->data(['username' => 'baru', 'role' => 'SISWA']))->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('profil_akun', ['username' => 'baru']);
    }

    public function test_hanya_admin_yang_mengubah_data_guru(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $kepsek = $this->akun('KEPSEK', 'kepala');

        foreach ([$guru, $kepsek] as $pengguna) {
            $this->actingAs($pengguna);
            $this->post('/classroom/data-guru', $this->data(['username' => 'x'.$pengguna->id]))->assertForbidden();
            $this->patch("/classroom/data-guru/{$guru->id}", $this->data(['username' => 'guru1']))->assertForbidden();
            $this->delete("/classroom/data-guru/{$guru->id}")->assertForbidden();
        }

        // Kepsek tetap boleh melihat daftarnya.
        $this->actingAs($kepsek);
        $this->get('/classroom/data-guru')->assertOk();
    }

    public function test_admin_mengubah_nama_peran_sandi_dan_status(): void
    {
        $guru = $this->akun('GURU', 'guru1', 'Nama Lama');
        $this->actingAsAdmin();

        $this->patch("/classroom/data-guru/{$guru->id}", $this->data([
            'nama' => 'Nama Baru', 'username' => 'guru.baru', 'password' => 'sandibaru1', 'role' => 'KEPSEK', 'aktif' => 0,
        ]))->assertSessionHasNoErrors();

        $guru->refresh();
        $this->assertSame('Nama Baru', $guru->name);
        $this->assertSame('guru.baru', $guru->username);
        $this->assertSame('KEPSEK', $guru->role);
        $this->assertFalse($guru->is_active);
        $this->assertTrue(Hash::check('sandibaru1', $guru->password));
    }

    public function test_sandi_dibiarkan_bila_kosong_dan_username_sendiri_tidak_dianggap_dobel(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $lama = $guru->password;
        $this->actingAsAdmin();

        $this->patch("/classroom/data-guru/{$guru->id}", $this->data(['username' => 'guru1', 'password' => '']))->assertSessionHasNoErrors();

        $this->assertSame($lama, $guru->fresh()->password);
    }

    public function test_sandi_baru_melepas_tautan_absensi_agar_sandi_lokal_berlaku(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        Akun::whereKey($guru->id)->update(['absensi_user_id' => '01JABSENSI']);
        $this->actingAsAdmin();

        $this->patch("/classroom/data-guru/{$guru->id}", $this->data(['username' => 'guru1', 'password' => '']))->assertSessionHasNoErrors();
        $this->assertSame('01JABSENSI', $guru->fresh()->absensi_user_id);

        $this->patch("/classroom/data-guru/{$guru->id}", $this->data(['username' => 'guru1', 'password' => 'sandibaru1']))->assertSessionHasNoErrors();
        $this->assertNull($guru->fresh()->absensi_user_id);
    }

    public function test_admin_tidak_bisa_menonaktifkan_diri_sendiri_dan_peran_admin_tidak_berubah(): void
    {
        $admin = $this->profilAdmin();

        $this->patch("/classroom/data-guru/{$admin->id}", $this->data(['username' => $admin->username, 'aktif' => 0]))->assertStatus(409);
        $this->patch("/classroom/data-guru/{$admin->id}", $this->data(['username' => $admin->username, 'role' => 'GURU']))->assertSessionHasNoErrors();

        $this->assertSame('ADMIN', $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_hapus_guru_menghapus_akun_dan_mengosongkan_wali_kelas(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        Rombel::whereKey($rombel->id)->update(['waliKelasId' => $guru->id]);
        $this->actingAsAdmin();

        $this->delete("/classroom/data-guru/{$guru->id}")->assertRedirect();

        $this->assertDatabaseMissing('profil_akun', ['id' => $guru->id]);
        $this->assertDatabaseMissing('users', ['id' => $guru->id]);
        $this->assertNull($rombel->fresh()->waliKelasId);
    }

    public function test_guru_yang_masih_mengajar_admin_dan_siswa_tidak_bisa_dihapus(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $this->kelas($guru, $this->rombel());
        $siswa = $this->siswa($this->rombel('X TKJ 2'), 'siswa01');
        $admin = $this->profilAdmin();

        $this->delete("/classroom/data-guru/{$guru->id}")->assertStatus(409);
        $this->delete("/classroom/data-guru/{$admin->id}")->assertStatus(409);
        $this->delete("/classroom/data-guru/{$siswa->id}")->assertNotFound();
        $this->patch("/classroom/data-guru/{$siswa->id}", $this->data(['username' => 'siswa01']))->assertNotFound();

        $this->assertDatabaseHas('profil_akun', ['id' => $guru->id]);
        $this->assertDatabaseHas('profil_akun', ['id' => $siswa->id]);
    }

    public function test_tombol_sinkron_absensi_sudah_tidak_ada(): void
    {
        $this->actingAsAdmin();

        $this->post('/classroom/data-guru/sinkron')->assertStatus(405);
    }

    public function test_siswa_bisa_ganti_sandi_sendiri_dan_sandi_lama_salah_ditolak(): void
    {
        $siswa = $this->siswa($this->rombel(), 'siswa01');
        $this->actingAs($siswa);

        $this->post('/classroom/profil/password', ['oldPassword' => 'salah', 'newPassword' => 'sandibaru1'])->assertSessionHasErrors('oldPassword');
        $this->assertTrue(Hash::check('rahasia123', $siswa->fresh()->password));

        $this->post('/classroom/profil/password', ['oldPassword' => 'rahasia123', 'newPassword' => 'sandibaru1'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('sandibaru1', $siswa->fresh()->password));
    }

    public function test_guru_lokal_bisa_ganti_sandi_sendiri_tetapi_akun_absensi_tidak(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $this->actingAs($guru);

        $this->post('/classroom/profil/password', ['oldPassword' => 'rahasia123', 'newPassword' => 'sandibaru1'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('sandibaru1', $guru->fresh()->password));

        Akun::whereKey($guru->id)->update(['absensi_user_id' => '01JABSENSI']);
        $this->post('/classroom/profil/password', ['oldPassword' => 'sandibaru1', 'newPassword' => 'lainlagi1'])->assertForbidden();
    }
}
