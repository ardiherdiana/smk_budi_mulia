<?php

namespace Tests\Feature\Classroom;

use App\Models\Attendance;
use App\Models\Classroom\Akun;
use App\Models\Classroom\Rombel;
use App\Models\Guru;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_sandi_baru_akun_bekas_absensi_langsung_berlaku_dan_jejak_absensi_tetap(): void
    {
        // Sejak digabung, sandi akun bekas absensi ada di users.password; `absensi_user_id` hanya jejak pemetaan impor.
        $guru = $this->akun('GURU', 'guru1');
        Akun::whereKey($guru->id)->update(['absensi_user_id' => '01JABSENSI']);
        $this->actingAsAdmin();

        $this->patch("/classroom/data-guru/{$guru->id}", $this->data(['username' => 'guru1', 'password' => 'sandibaru1']))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('sandibaru1', $guru->fresh()->password));
        $this->assertSame('01JABSENSI', $guru->fresh()->absensi_user_id);
    }

    public function test_akun_yang_punya_riwayat_absensi_atau_sppd_tidak_bisa_dihapus_dari_classroom(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $absen = Guru::factory()->create(['userId' => $guru->id]);
        Attendance::create(['guruId' => $absen->id, 'tanggal' => '2026-10-01', 'statusMasuk' => 'HADIR']);
        $this->actingAsAdmin();

        $this->delete("/classroom/data-guru/{$guru->id}")->assertStatus(409);
        $this->assertDatabaseHas('profil_akun', ['id' => $guru->id]);
    }

    public function test_guru_dan_kepsek_baru_langsung_ada_di_data_guru_absensi(): void
    {
        $this->actingAsAdmin();

        $this->post('/classroom/data-guru', $this->data())->assertSessionHasNoErrors();
        $this->post('/classroom/data-guru', $this->data(['nama' => 'Teti', 'username' => 'kepsek', 'role' => 'KEPSEK']))->assertSessionHasNoErrors();

        $dina = Akun::where('username', 'bu.dina')->firstOrFail();
        $teti = Akun::where('username', 'kepsek')->firstOrFail();
        $this->assertDatabaseHas('guru', ['userId' => $dina->id, 'nama' => 'Dina Qoyimah', 'aktif' => true]);
        $this->assertDatabaseHas('guru', ['userId' => $teti->id, 'nama' => 'Teti', 'aktif' => true]);

        // Daftar di /data-guru (absensi) berisi username yang sama dengan Data Guru Classroom.
        $this->get('/data-guru')->assertInertia(fn (Assert $page) => $page
            ->component('data-guru-page')
            ->where('guruList', fn ($daftar) => collect($daftar)->pluck('user.username')->sort()->values()->all() === ['bu.dina', 'kepsek']));
    }

    public function test_ubah_nama_status_dan_peran_ikut_ke_data_guru_absensi(): void
    {
        $guru = $this->akun('GURU', 'guru1', 'Nama Lama');
        $absen = Guru::factory()->create(['userId' => $guru->id, 'nama' => 'Nama Lama', 'mapel' => 'Matematika']);
        $this->actingAsAdmin();

        $this->patch("/classroom/data-guru/{$guru->id}", $this->data([
            'nama' => 'Nama Baru', 'username' => 'guru1', 'password' => '', 'role' => 'KEPSEK', 'aktif' => 0,
        ]))->assertSessionHasNoErrors();

        // Baris yang sama diperbarui (QR dan mapel tetap), bukan dibuat baru.
        $absen->refresh();
        $this->assertSame('Nama Baru', $absen->nama);
        $this->assertFalse($absen->aktif);
        $this->assertSame('Matematika', $absen->mapel);
        $this->assertSame(1, Guru::where('userId', $guru->id)->count());
    }

    public function test_akun_lama_tanpa_data_absensi_dibuatkan_saat_diubah_dan_admin_tidak(): void
    {
        $guru = $this->akun('GURU', 'guru1', 'Guru Lama');
        $admin = $this->profilAdmin();

        $this->patch("/classroom/data-guru/{$guru->id}", $this->data(['nama' => 'Guru Lama', 'username' => 'guru1', 'password' => '']))->assertSessionHasNoErrors();
        $this->patch("/classroom/data-guru/{$admin->id}", $this->data(['username' => $admin->username, 'password' => '']))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('guru', ['userId' => $guru->id, 'nama' => 'Guru Lama']);
        $this->assertDatabaseMissing('guru', ['userId' => $admin->id]);
    }

    public function test_guru_tanpa_riwayat_absen_tetap_bisa_dihapus_beserta_data_guru_absensinya(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        $absen = Guru::factory()->create(['userId' => $guru->id]);
        $this->actingAsAdmin();

        $this->delete("/classroom/data-guru/{$guru->id}")->assertRedirect();

        $this->assertDatabaseMissing('profil_akun', ['id' => $guru->id]);
        $this->assertModelMissing($absen);
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

    // Ganti sandi sendiri (siswa, guru) sekarang di modul Profil bersama: lihat tests/Feature/ProfilTest.php.
}
