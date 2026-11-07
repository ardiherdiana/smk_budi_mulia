<?php

namespace Tests\Feature\Absensi\Admin;

use App\Models\Attendance;
use App\Models\Classroom\Akun;
use App\Models\Guru;
use App\Models\Staff;
use Tests\AbsensiTestCase;

class GuruManagementTest extends AbsensiTestCase
{
    public function test_admin_can_create_a_guru(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/data-guru', [
            'nama' => 'Budi Santoso, S.Pd.',
            'password' => 'rahasia123',
        ]);

        $response->assertSessionHas('toast');
        $this->assertDatabaseHas('profil_akun', ['username' => 'budisantoso', 'role' => 'GURU']);
        $this->assertDatabaseHas('guru', ['nama' => 'Budi Santoso, S.Pd.']);
    }

    public function test_username_takes_only_the_first_two_words_without_a_dot(): void
    {
        $this->actingAsAdmin();

        $this->post('/data-guru', ['nama' => 'Ardi Herdiana Putra', 'password' => 'rahasia123'])->assertSessionHas('toast');

        $this->assertDatabaseHas('profil_akun', ['username' => 'ardiherdiana']);
        $this->assertDatabaseMissing('profil_akun', ['username' => 'ardi.herdiana']);
    }

    public function test_username_collision_gets_a_numeric_suffix(): void
    {
        $this->actingAsAdmin();

        $this->post('/data-guru', ['nama' => 'Budi Santoso', 'password' => 'rahasia123'])->assertSessionHas('toast');
        $this->post('/data-guru', ['nama' => 'Budi Santoso', 'password' => 'rahasia123'])->assertSessionHas('toast');

        $this->assertDatabaseHas('profil_akun', ['username' => 'budisantoso']);
        $this->assertDatabaseHas('profil_akun', ['username' => 'budisantoso2']);
    }

    public function test_admin_can_update_a_guru(): void
    {
        $guru = Guru::factory()->create(['nama' => 'Nama Lama']);
        $this->actingAsAdmin();

        $response = $this->patch("/data-guru/{$guru->id}", [
            'nama' => 'Nama Baru',
            'aktif' => false,
        ]);

        $response->assertSessionHas('toast');
        $this->assertDatabaseHas('guru', ['id' => $guru->id, 'nama' => 'Nama Baru', 'aktif' => false]);
    }

    public function test_nama_dan_status_ikut_ke_akun_classroom_dan_website(): void
    {
        $guru = Guru::factory()->create(['nama' => 'Nama Lama']);
        $staff = Staff::create(['name' => 'Nama Lama', 'role' => 'teacher', 'type' => 'teacher']);
        Akun::whereKey($guru->userId)->update(['name' => 'Nama Lama', 'guruStaffId' => $staff->id]);
        $this->actingAsAdmin();

        $this->patch("/data-guru/{$guru->id}", ['nama' => 'Nama Baru', 'aktif' => false])->assertSessionHas('toast');

        $akun = Akun::findOrFail($guru->userId);
        $this->assertSame('Nama Baru', $akun->name);
        $this->assertFalse($akun->is_active);
        $this->assertSame('Nama Baru', $staff->fresh()->name);

        // Mengaktifkan lagi juga ikut, dan perubahan lain (mapel) tidak mengubah status.
        $this->patch("/data-guru/{$guru->id}", ['aktif' => true])->assertSessionHas('toast');
        $this->patch("/data-guru/{$guru->id}", ['mapel' => 'Matematika'])->assertSessionHas('toast');
        $this->assertTrue(Akun::findOrFail($guru->userId)->is_active);
        $this->assertTrue($guru->fresh()->aktif);
    }

    public function test_guru_baru_ikut_tampil_di_website_seperti_dari_classroom(): void
    {
        $this->actingAsAdmin();

        $this->post('/data-guru', ['nama' => 'Budi Santoso, S.Pd.', 'password' => 'rahasia123'])->assertSessionHas('toast');
        $this->post('/data-guru', ['nama' => 'Kepala Baru', 'password' => 'rahasia123', 'role' => 'KEPSEK'])->assertSessionHas('toast');

        $budi = Akun::where('username', 'budisantoso')->firstOrFail();
        $this->assertDatabaseHas('guru_staff', ['id' => $budi->guruStaffId, 'name' => 'Budi Santoso, S.Pd.', 'role' => 'teacher']);
        // Kepsek tampil lewat tabel `principals`, bukan `guru_staff`.
        $this->assertNull(Akun::where('username', 'kepalabaru')->value('guruStaffId'));
    }

    public function test_admin_can_delete_a_guru_and_it_cascades(): void
    {
        $guru = Guru::factory()->create();
        Attendance::create([
            'guruId' => $guru->id,
            'tanggal' => '2025-01-06',
            'statusMasuk' => 'HADIR',
        ]);
        $this->actingAsAdmin();

        $response = $this->delete("/data-guru/{$guru->id}");

        $response->assertSessionHas('toast');
        $this->assertDatabaseMissing('guru', ['id' => $guru->id]);
        $this->assertDatabaseMissing('users', ['id' => $guru->userId]);
        $this->assertDatabaseMissing('attendance', ['guruId' => $guru->id]);
    }

    public function test_admin_can_regenerate_a_guru_qr_token(): void
    {
        $guru = Guru::factory()->create();
        $oldToken = $guru->qrToken;
        $this->actingAsAdmin();

        $response = $this->post("/data-guru/{$guru->id}/qr/regenerate");

        $response->assertOk();
        $guru->refresh();
        $this->assertNotSame($oldToken, $guru->qrToken);
    }

    public function test_kepsek_can_view_but_not_create_guru(): void
    {
        $this->actingAsKepsek();

        $this->get('/data-guru')->assertOk();

        $response = $this->post('/data-guru', [
            'nama' => 'Guru Baru',
            'password' => 'rahasia123',
        ]);
        $response->assertStatus(403);
    }

    public function test_guru_role_cannot_reach_guru_management_at_all(): void
    {
        $this->actingAsGuru();

        $this->get('/data-guru')->assertStatus(403);
    }
}
