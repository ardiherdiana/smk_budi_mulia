<?php

namespace Tests\Feature;

use App\Enums\Sppd\RoleName;
use App\Models\Classroom\Akun;
use App\Models\Principal;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\AbsensiTestCase;

/**
 * Modul Profil: satu halaman profil dan satu ganti sandi untuk semua akun dan semua modul. Menggantikan profil absensi,
 * profil SPPD, profil Classroom, dan dialog "Ganti password" panel Website/SKL/portal.
 */
class ProfilTest extends AbsensiTestCase
{
    private function gantiSandi(string $lama, string $baru, ?string $ulang = null)
    {
        return $this->patch('/profil/password', ['current_password' => $lama, 'password' => $baru, 'password_confirmation' => $ulang ?? $baru]);
    }

    public function test_profil_butuh_login(): void
    {
        $this->get('/profil')->assertRedirect('/login');
        $this->patch('/profil/password')->assertRedirect('/login');
    }

    public function test_guru_melihat_data_guru_dan_role_sppd_tanpa_kehadiran(): void
    {
        $this->seed(RoleSeeder::class);
        $guru = $this->actingAsGuru(['mapel' => 'Matematika']);
        $guru->user->assignRole(RoleName::Pemohon->value);

        $this->get('/profil')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('profil-page')
            ->where('akun.peran', 'GURU')
            ->where('akun.username', $guru->user->username)
            ->where('akun.email', null)
            ->where('akun.peranSppd', ['pemohon'])
            ->where('guru.mapel', 'Matematika')
            ->where('siswa', null)
            ->missing('jumlahKelas')
            // Profil hanya data akun; rekap kehadiran ada di modul Absensi.
            ->missing('kehadiranBulanIni'));
    }

    public function test_siswa_melihat_nis_dan_rombel_tanpa_data_guru(): void
    {
        $siswa = $this->siswa($this->rombel('XI TKJ 1', 'XI'), 'siswa01');
        $this->actingAs($siswa);

        $this->get('/profil')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('akun.peran', 'SISWA')
            ->where('siswa.rombel', 'XI TKJ 1')
            ->where('guru', null)
            ->missing('jumlahKelas'));
    }

    public function test_alumni_portal_tanpa_profil_dikenali_lewat_email(): void
    {
        $this->actingAs($this->student('alumni@test.local'));

        $this->get('/profil')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('akun.username', null)
            ->where('akun.email', 'alumni@test.local')
            ->where('akun.peran', null));
    }

    public function test_admin_lama_tanpa_profil_dibuatkan_profil_admin(): void
    {
        $admin = $this->admin('admin@test.local');
        $this->actingAs($admin);

        $this->get('/profil')->assertOk()->assertInertia(fn (Assert $page) => $page->where('akun.peran', 'ADMIN'));
        $this->assertDatabaseHas('profil_akun', ['id' => $admin->id, 'role' => 'ADMIN']);
    }

    public function test_pegawai_sppd_saja_bisa_membuka_profil(): void
    {
        $this->actingAs(User::factory()->staf()->create(['jabatan' => 'Bendahara']));

        $this->get('/profil')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('akun.peran', 'STAF')
            ->where('akun.jabatan', 'Bendahara'));
    }

    public function test_halaman_profil_lama_dialihkan_ke_profil_bersama(): void
    {
        $this->actingAsGuru();
        $this->get('/sppd/profil')->assertRedirect('/profil');
        $this->get('/classroom/profil')->assertRedirect('/profil');
    }

    public function test_semua_jenis_akun_bisa_ganti_sandi_di_satu_tempat(): void
    {
        $guru = User::factory()->create(['password' => Hash::make('rahasia123')]);
        $siswa = $this->siswa($this->rombel(), 'siswa01');
        $alumni = $this->student('alumni@test.local', 'Rahasia123');
        Akun::whereKey($guru->id)->update(['absensi_user_id' => '01JABSENSI']);

        foreach ([[$guru, 'rahasia123'], [$siswa->pengguna, 'rahasia123'], [$alumni, 'Rahasia123']] as [$akun, $lama]) {
            $this->actingAs($akun);
            $this->gantiSandi($lama, 'SandiBaru123')->assertSessionHasNoErrors()->assertSessionHas('toast');
            $this->assertTrue(Hash::check('SandiBaru123', $akun->fresh()->password));
        }
    }

    public function test_aturan_sandi_baru_sama_untuk_semua_akun(): void
    {
        $guru = User::factory()->create(['password' => Hash::make('rahasia123')]);
        $this->actingAs($guru);

        $this->gantiSandi('salah', 'SandiBaru123')->assertSessionHasErrors('current_password');
        $this->gantiSandi('rahasia123', 'pendek1')->assertSessionHasErrors('password');
        $this->gantiSandi('rahasia123', 'hanyahuruf')->assertSessionHasErrors('password');
        $this->gantiSandi('rahasia123', 'SandiBaru123', 'beda')->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('rahasia123', $guru->fresh()->password));
    }

    public function test_foto_guru_dari_profil_menjadi_foto_website_dan_foto_absen_lama_dihapus(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('guru/lama.jpg', 'x');
        $guru = $this->actingAsGuru(['fotoUrl' => '/uploads/guru/lama.jpg']);

        $this->post('/profil/foto', ['foto' => UploadedFile::fake()->image('profil.png')])->assertSessionHas('toast');

        // Satu foto: tersimpan di orangnya di website (dibuatkan karena belum tertaut), foto absen lama tidak dipakai lagi.
        $akun = Akun::findOrFail($guru->userId);
        $this->assertNotNull($akun->staff?->image);
        Storage::disk('media')->assertExists($akun->staff->image);
        $this->assertNull($guru->fresh()->fotoUrl);
        Storage::disk('public')->assertMissing('guru/lama.jpg');
        $this->get('/profil')->assertInertia(fn (Assert $page) => $page->where('akun.fotoUrl', $akun->fotoUrl()));

        $this->actingAs(User::factory()->staf()->create());
        $this->post('/profil/foto', ['foto' => UploadedFile::fake()->image('profil.png')])->assertForbidden();
    }

    public function test_foto_kepsek_mengganti_foto_kepala_sekolah_di_website_bila_namanya_ada(): void
    {
        $kepsek = $this->actingAsKepsek();
        Akun::whereKey($kepsek->userId)->update(['name' => 'Teti Tresnawati, M.Pd.']);
        Storage::disk('media')->put('principal-photo/lama.jpg', 'x');
        $principal = Principal::create(['nama' => 'Teti Tresnawati, M.Pd', 'tahun_jabatan' => '2022-2030', 'foto' => 'principal-photo/lama.jpg']);

        $this->post('/profil/foto', ['foto' => UploadedFile::fake()->image('kepsek.png')])->assertSessionHas('toast');

        $baru = $principal->fresh()->foto;
        $this->assertNotSame('principal-photo/lama.jpg', $baru);
        Storage::disk('media')->assertExists($baru);
        Storage::disk('media')->assertMissing('principal-photo/lama.jpg');
        $this->assertStringEndsWith($baru, (string) Akun::findOrFail($kepsek->userId)->fotoUrl());
    }

    public function test_foto_kepsek_tanpa_baris_kepala_sekolah_disimpan_sebagai_foto_absen(): void
    {
        Storage::fake('public');
        $kepsek = $this->actingAsKepsek();

        $this->post('/profil/foto', ['foto' => UploadedFile::fake()->image('kepsek.png')])->assertSessionHas('toast');

        Storage::disk('public')->assertExists('guru/'.basename((string) $kepsek->fresh()->fotoUrl));
        $this->get('/profil')->assertInertia(fn (Assert $page) => $page->where('akun.fotoUrl', $kepsek->fresh()->fotoUrl));
    }

    public function test_endpoint_sandi_lama_sudah_tidak_ada(): void
    {
        $this->actingAs($this->student());

        $this->patch('/akun/password')->assertNotFound();
        $this->post('/classroom/profil/password')->assertNotFound();
    }
}
