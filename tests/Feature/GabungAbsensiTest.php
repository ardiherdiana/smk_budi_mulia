<?php

namespace Tests\Feature;

use App\Enums\Sppd\RoleName;
use App\Models\Attendance;
use App\Models\BriefingAttendance;
use App\Models\Guru;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Tests\AbsensiTestCase;

/**
 * Batas akses setelah absensi + SPPD digabung ke situs sekolah (satu aplikasi, satu `users`): siswa dan akun portal
 * tidak boleh menyentuh modul staf, akun tidak boleh terhapus lintas modul, dan modul SKL hanya melihat siswa.
 */
class GabungAbsensiTest extends AbsensiTestCase
{
    /** @return list<Route> Semua rute ber-middleware `staf` (berkas routes/absensi.php). */
    private function ruteStaf(): array
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => collect($r->gatherMiddleware())->contains(fn ($m) => is_string($m) && Str::startsWith($m, 'staf')))
            ->values()
            ->all();
    }

    private function sapu(User $pengguna): void
    {
        $rute = $this->ruteStaf();
        $this->assertGreaterThan(50, count($rute), 'Rute absensi/SPPD tidak ditemukan');

        foreach ($rute as $r) {
            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $r->uri());
            $method = collect($r->methods())->reject(fn ($m) => $m === 'HEAD')->first();

            $status = $this->actingAs($pengguna)->call($method, $uri)->getStatusCode();

            // Rute berparameter boleh berhenti di 404 (model tidak ada) sebelum gerbang; yang penting tidak pernah terbuka.
            $boleh = str_contains($r->uri(), '{') ? [403, 404] : [403];
            $this->assertContains($status, $boleh, "{$method} {$uri} membalas {$status} untuk {$pengguna->email}");
        }
    }

    public function test_siswa_classroom_ditolak_di_semua_rute_absensi_dan_sppd(): void
    {
        $this->sapu(User::factory()->siswa()->create());
    }

    public function test_akun_portal_tanpa_profil_ditolak_di_semua_rute_absensi_dan_sppd(): void
    {
        $this->sapu($this->student());
    }

    public function test_profil_nonaktif_ditolak_walau_peran_guru(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => false]))
            ->get('/sppd/dashboard')->assertForbidden();
    }

    public function test_admin_website_lama_tanpa_profil_bisa_membuka_absensi_dan_sppd(): void
    {
        $this->actingAs($this->admin());

        $this->get('/absen/dashboard')->assertOk();
        $this->get('/sppd/dashboard')->assertOk();
        // Rute khusus guru/kepsek tetap tertutup untuk admin, sama seperti absensi lama.
        $this->get('/qr')->assertForbidden();
    }

    public function test_akun_dengan_riwayat_classroom_tidak_bisa_dihapus_dari_data_guru_absensi(): void
    {
        $guru = Guru::factory()->create();
        $akun = $guru->user->akun;
        $this->kelas($akun, $this->rombel());
        $this->actingAsAdmin();

        $this->delete("/data-guru/{$guru->id}")->assertStatus(409);
        $this->assertModelExists($guru);
    }

    public function test_pegawai_dengan_riwayat_absensi_tidak_bisa_dihapus_dari_sppd(): void
    {
        $this->seed(RoleSeeder::class);
        $tu = User::factory()->staf()->create()->assignRole(RoleName::Tu->value);
        $guru = Guru::factory()->create();
        Attendance::create(['guruId' => $guru->id, 'tanggal' => '2026-10-01', 'statusMasuk' => 'HADIR']);

        $this->actingAs($tu)->delete(route('sppd.pegawai.destroy', $guru->user))->assertStatus(409);
        $this->assertModelExists($guru->user);
    }

    public function test_baris_guru_tanpa_riwayat_absen_tidak_dihitung_data_absensi(): void
    {
        // Setiap akun guru/kepsek punya baris `guru` (sinkron Data Guru), jadi barisnya saja bukan alasan menolak hapus.
        $guru = Guru::factory()->create();
        $this->assertSame([], $guru->user->modulTerpakai());

        BriefingAttendance::create(['guruId' => $guru->id, 'tanggal' => '2026-10-01', 'status' => 'HADIR']);
        $this->assertSame(['Absensi'], $guru->user->fresh()->modulTerpakai());
    }

    public function test_pegawai_dengan_riwayat_sppd_saja_tetap_bisa_dihapus_dari_sppd(): void
    {
        $this->seed(RoleSeeder::class);
        $tu = User::factory()->staf()->create()->assignRole(RoleName::Tu->value);
        $pegawai = User::factory()->staf()->create();
        PengajuanSppd::factory()->create(['pemohon_id' => $pegawai->id]);

        $this->actingAs($tu)->delete(route('sppd.pegawai.destroy', $pegawai))->assertSessionHas('success');
        $this->assertModelMissing($pegawai);
    }

    public function test_tu_tidak_bisa_mengubah_akun_admin_atau_siswa(): void
    {
        $this->seed(RoleSeeder::class);
        $tu = User::factory()->staf()->create()->assignRole(RoleName::Tu->value);
        $admin = User::factory()->admin()->create();
        $siswa = User::factory()->siswa()->create();
        $this->actingAs($tu);

        $this->get(route('sppd.pegawai.edit', $admin))->assertForbidden();
        $this->delete(route('sppd.pegawai.destroy', $admin))->assertForbidden();
        $this->get(route('sppd.pegawai.edit', $siswa))->assertNotFound();
        $this->delete(route('sppd.pegawai.destroy', $siswa))->assertNotFound();
        $this->assertModelExists($admin);
        $this->assertModelExists($siswa);
    }

    public function test_daftar_pegawai_sppd_hanya_berisi_staf(): void
    {
        $this->seed(RoleSeeder::class);
        $tu = User::factory()->staf()->create(['username' => 'tu.sekolah'])->assignRole(RoleName::Tu->value);
        User::factory()->siswa()->create(['username' => 'siswa.satu']);
        $this->student('alumni@test.local');

        $this->actingAs($tu)->get(route('sppd.pegawai.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->where('items.data', fn ($data) => collect($data)->pluck('username')->all() === ['tu.sekolah']));
    }

    public function test_skl_tidak_menampilkan_dan_tidak_mereset_sandi_staf(): void
    {
        $guru = User::factory()->create(['username' => 'guru.satu']);
        $alumni = $this->student('alumni@test.local');
        $sandiGuru = $guru->password;
        $this->actingAs($this->admin());

        $this->get('/skl/siswa')->assertOk()->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($data) => collect($data)->pluck('id')->all() === [$alumni->id]));

        $this->patch('/skl/siswa/password', ['ids' => [$guru->id, $alumni->id]]);
        $this->assertSame($sandiGuru, $guru->fresh()->password);
    }

    public function test_user_create_dengan_atribut_profil_membuat_profil_sekali(): void
    {
        $user = User::create(['username' => 'pegawai.baru', 'name' => 'Pegawai Baru', 'jabatan' => 'Bendahara', 'password' => 'rahasia123']);

        $this->assertDatabaseHas('profil_akun', ['id' => $user->id, 'username' => 'pegawai.baru', 'role' => 'STAF', 'jabatan' => 'Bendahara']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'pegawai.baru@akun.local', 'role' => 'user']);
        $this->assertSame('Pegawai Baru', $user->fresh()->name);
    }

    public function test_peran_admin_profil_selalu_berpasangan_dengan_role_admin(): void
    {
        $user = User::factory()->create();
        $this->assertSame('user', $user->fresh()->role);

        $user->update(['peran' => 'ADMIN']);
        $this->assertSame('admin', $user->fresh()->role);

        $user->akun->update(['role' => 'GURU']);
        $this->assertSame('user', $user->fresh()->role);
    }
}
