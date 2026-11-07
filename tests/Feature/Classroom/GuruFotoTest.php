<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Guru;
use App\Models\Principal;
use App\Models\Staff;
use App\Services\Classroom\GuruStaffSync;
use Database\Seeders\GuruSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Akun guru Classroom tertaut ke orangnya di `guru_staff` (#guru): foto satu sumber, guru baru otomatis tampil di website. */
class GuruFotoTest extends TestCase
{
    private function staff(string $nama, string $role = 'teacher', ?string $foto = null): Staff
    {
        return Staff::create(['name' => $nama, 'role' => $role, 'type' => 'teacher', 'image' => $foto]);
    }

    private function data(array $ubah = []): array
    {
        return $ubah + ['nama' => 'Dina Qoyimah', 'username' => 'bu.dina', 'password' => 'rahasia123', 'role' => 'GURU'];
    }

    public function test_kunci_nama_mengabaikan_gelar_tanda_baca_dan_keterangan(): void
    {
        $this->assertSame(GuruStaffSync::kunciNama('Rio Falentino, S.Pd. Gr.'), GuruStaffSync::kunciNama('Rio Falentino, S.Pd, Gr.'));
        $this->assertSame(GuruStaffSync::kunciNama('Latif Abdul Salam, S.Tr.Kom., Gr.'), GuruStaffSync::kunciNama('Latif .Abdul Salam,S.Tr.Kom'));
        $this->assertSame(GuruStaffSync::kunciNama('Irmawati, S.E., Gr.'), GuruStaffSync::kunciNama('Irmawati, SE, Gr.'));
        $this->assertSame('herianto saputra', GuruStaffSync::kunciNama('Herianto Saputra, M.Kom., Gr. (Mahasiswa PKL)'));
        $this->assertNotSame(GuruStaffSync::kunciNama('Rio Falentino'), GuruStaffSync::kunciNama('Rio Falen'));
    }

    public function test_seeder_menautkan_guru_ke_orang_di_website_dan_memilih_baris_guru(): void
    {
        $rio = $this->staff('Rio Falentino, S.Pd, Gr.', 'teacher', 'staff-image/rio.webp');
        $this->staff('Wulan Endarwati, S.Pd', 'vice_principal', 'staff-image/wulan-wk.webp');
        $wulan = $this->staff('Wulan Endarwati, S.Pd, Gr.', 'teacher', 'staff-image/wulan.webp');
        $ika = $this->staff('Ika Candi Permata, S.Pd, Gr.', 'teacher', 'staff-image/ika.webp'); // akunnya "Ika Candy P.": lewat pemetaan manual

        $this->seed(GuruSeeder::class);

        $this->assertSame($rio->id, Akun::where('username', 'SMKBM017')->value('guruStaffId'));
        $this->assertSame($wulan->id, Akun::where('username', 'SMKBM021')->value('guruStaffId')); // bukan baris wakil kepala
        $this->assertSame($ika->id, Akun::where('username', 'SMKBM011')->value('guruStaffId'));
        $this->assertSame('/media/staff-image/rio.webp', Akun::where('username', 'SMKBM017')->firstOrFail()->fotoUrl());
        $this->assertSame(3, Akun::whereNotNull('guruStaffId')->count());
        $this->assertSame(4, Staff::count()); // seeder tidak membuat orang baru di website
    }

    public function test_seeder_tanpa_data_website_tidak_error_dan_menautkan_pada_seed_berikutnya(): void
    {
        $this->seed(GuruSeeder::class);
        $this->assertSame(0, Akun::whereNotNull('guruStaffId')->count());

        $rio = $this->staff('Rio Falentino, S.Pd, Gr.', 'teacher', 'staff-image/rio.webp');
        $this->seed(GuruSeeder::class);
        $this->seed(GuruSeeder::class);

        $this->assertSame($rio->id, Akun::where('username', 'SMKBM017')->value('guruStaffId'));
        $this->assertSame(1, Akun::whereNotNull('guruStaffId')->count());
    }

    public function test_satu_orang_di_website_tidak_ditautkan_ke_dua_akun(): void
    {
        $orang = $this->staff('Dina Qoyimah, S.Pd', 'teacher', 'staff-image/dina.webp');
        $a = $this->akun('GURU', 'dina1', 'Dina Qoyimah, S.Pd.');
        $b = $this->akun('GURU', 'dina2', 'Dina Qoyimah');
        $sync = app(GuruStaffSync::class);

        $this->assertTrue($sync->tautkan($a));
        $this->assertFalse($sync->tautkan($b));
        $this->assertSame($orang->id, $a->fresh()->guruStaffId);
        $this->assertNull($b->fresh()->guruStaffId);
    }

    public function test_guru_baru_dengan_foto_otomatis_tampil_di_website(): void
    {
        $this->actingAsAdmin();

        $this->post('/classroom/data-guru', $this->data(['foto' => UploadedFile::fake()->image('dina.png', 300, 400)]))->assertSessionHasNoErrors();

        $akun = Akun::where('username', 'bu.dina')->firstOrFail();
        $staff = Staff::findOrFail($akun->guruStaffId);
        $this->assertSame('Dina Qoyimah', $staff->name);
        $this->assertSame('teacher', $staff->role);
        $this->assertNotNull($staff->image);
        Storage::disk('media')->assertExists($staff->image);
        $this->assertSame("/media/{$staff->image}", $akun->fotoUrl());

        // Muncul di data landing page (#guru).
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('staff.0.name', 'Dina Qoyimah')->where('staff.0.image_url', "/media/{$staff->image}"));

        // Dan di daftar Data Guru beserta fotonya.
        $this->get('/classroom/data-guru')->assertInertia(fn (Assert $page) => $page
            ->where('guru.data', fn ($data) => collect($data)->contains(fn ($g) => $g['username'] === 'bu.dina' && $g['tampilDiWebsite'] === true && $g['fotoUrl'] === "/media/{$staff->image}")));
    }

    public function test_guru_baru_tanpa_foto_tetap_tampil_di_website_dan_kepsek_tidak(): void
    {
        $this->actingAsAdmin();

        $this->post('/classroom/data-guru', $this->data())->assertSessionHasNoErrors();
        $this->post('/classroom/data-guru', $this->data(['username' => 'kepala', 'nama' => 'Kepala Sekolah', 'role' => 'KEPSEK']))->assertSessionHasNoErrors();

        $this->assertSame(['Dina Qoyimah'], Staff::pluck('name')->all());
        $this->assertNull(Akun::where('username', 'bu.dina')->firstOrFail()->fotoUrl());
        $this->assertNull(Akun::where('username', 'kepala')->value('guruStaffId'));
    }

    public function test_foto_bukan_gambar_ditolak_dan_akun_tidak_dibuat(): void
    {
        $this->actingAsAdmin();

        $this->post('/classroom/data-guru', $this->data(['foto' => UploadedFile::fake()->create('dina.pdf', 10, 'application/pdf')]))->assertSessionHasErrors('foto');

        $this->assertDatabaseMissing('profil_akun', ['username' => 'bu.dina']);
        $this->assertSame(0, Staff::count());
    }

    public function test_ubah_nama_menyinkronkan_nama_di_website_hanya_bila_berubah(): void
    {
        $this->actingAsAdmin();
        $akun = $this->akun('GURU', 'bu.dina', 'Dina Qoyimah');
        $orang = $this->staff('Dina Qoyimah, S.Pd, Gr.'); // penulisan resmi di website berbeda dari akun
        $akun->forceFill(['guruStaffId' => $orang->id])->save();

        // Hanya ganti sandi: nama akun tidak berubah, jadi nama di website tidak ditimpa.
        $this->post("/classroom/data-guru/{$akun->id}", ['_method' => 'patch', 'nama' => 'Dina Qoyimah', 'username' => 'bu.dina', 'password' => 'sandibaru1'])->assertSessionHasNoErrors();
        $this->assertSame('Dina Qoyimah, S.Pd, Gr.', $orang->fresh()->name);

        $this->post("/classroom/data-guru/{$akun->id}", ['_method' => 'patch', 'nama' => 'Dina Q., S.Pd', 'username' => 'bu.dina'])->assertSessionHasNoErrors();
        $this->assertSame('Dina Q., S.Pd', $orang->fresh()->name);
    }

    public function test_ganti_dan_hapus_foto_membersihkan_berkas_lama(): void
    {
        $this->actingAsAdmin();
        $this->post('/classroom/data-guru', $this->data(['foto' => UploadedFile::fake()->image('a.png', 300, 400)]))->assertSessionHasNoErrors();
        $akun = Akun::where('username', 'bu.dina')->firstOrFail();
        $lama = $akun->staff->image;

        $this->post("/classroom/data-guru/{$akun->id}", ['_method' => 'patch', 'nama' => 'Dina Qoyimah', 'username' => 'bu.dina', 'foto' => UploadedFile::fake()->image('b.png', 300, 400)])->assertSessionHasNoErrors();
        $baru = $akun->fresh()->staff->image;
        $this->assertNotSame($lama, $baru);
        Storage::disk('media')->assertMissing($lama);
        Storage::disk('media')->assertExists($baru);

        $this->post("/classroom/data-guru/{$akun->id}", ['_method' => 'patch', 'nama' => 'Dina Qoyimah', 'username' => 'bu.dina', 'hapusFoto' => 1])->assertSessionHasNoErrors();
        $this->assertNull($akun->fresh()->staff->image);
        Storage::disk('media')->assertMissing($baru);
        $this->assertNull($akun->fresh()->fotoUrl());
    }

    public function test_menghapus_akun_membiarkan_orangnya_tetap_di_website(): void
    {
        $this->actingAsAdmin();
        $this->post('/classroom/data-guru', $this->data(['foto' => UploadedFile::fake()->image('a.png', 300, 400)]))->assertSessionHasNoErrors();
        $akun = Akun::where('username', 'bu.dina')->firstOrFail();
        $orangId = $akun->guruStaffId;
        $foto = $akun->staff->image;

        $this->delete("/classroom/data-guru/{$akun->id}")->assertRedirect();

        $this->assertDatabaseMissing('profil_akun', ['username' => 'bu.dina']);
        $this->assertDatabaseHas('guru_staff', ['id' => $orangId, 'name' => 'Dina Qoyimah']);
        Storage::disk('media')->assertExists($foto);
    }

    public function test_menghapus_orang_di_website_memutus_tautan_akun_tanpa_menghapus_akun(): void
    {
        $akun = $this->akun('GURU', 'bu.dina', 'Dina Qoyimah');
        $orang = $this->staff('Dina Qoyimah');
        $akun->forceFill(['guruStaffId' => $orang->id])->save();

        $orang->delete();

        $this->assertNull($akun->fresh()->guruStaffId);
        $this->assertNull($akun->fresh()->fotoUrl());
    }

    public function test_data_guru_absensi_dan_classroom_memakai_foto_yang_sama_dari_website(): void
    {
        $dina = $this->akun('GURU', 'bu.dina', 'Dina Qoyimah');
        $dina->forceFill(['guruStaffId' => $this->staff('Dina Qoyimah', 'teacher', 'staff-image/dina.webp')->id])->save();
        Guru::factory()->create(['userId' => $dina->id, 'nama' => 'Dina Qoyimah', 'fotoUrl' => '/uploads/guru/absen-lama.jpg']);
        // Di website belum ada fotonya: kedua daftar memakai foto absen lama.
        $firdaus = $this->akun('GURU', 'firdaus', 'Muhammad Firdaus');
        Guru::factory()->create(['userId' => $firdaus->id, 'nama' => 'Muhammad Firdaus', 'fotoUrl' => '/uploads/guru/firdaus.jpg']);
        $this->actingAsAdmin();

        $foto = fn ($daftar, string $kunci, string $username) => collect($daftar)->first(fn ($g) => data_get($g, $kunci) === $username)['fotoUrl'];

        $this->get('/data-guru')->assertInertia(fn (Assert $page) => $page
            ->where('guruList', fn ($daftar) => $foto($daftar, 'user.username', 'bu.dina') === '/media/staff-image/dina.webp'
                && $foto($daftar, 'user.username', 'firdaus') === '/uploads/guru/firdaus.jpg'));
        $this->get('/classroom/data-guru')->assertInertia(fn (Assert $page) => $page
            ->where('guru.data', fn ($daftar) => $foto($daftar, 'username', 'bu.dina') === '/media/staff-image/dina.webp'
                && $foto($daftar, 'username', 'firdaus') === '/uploads/guru/firdaus.jpg'));
    }

    public function test_ganti_foto_dari_data_guru_absensi_mengganti_foto_website(): void
    {
        $dina = $this->akun('GURU', 'bu.dina', 'Dina Qoyimah');
        $dina->forceFill(['guruStaffId' => $this->staff('Dina Qoyimah', 'teacher', 'staff-image/dina.webp')->id])->save();
        Storage::disk('media')->put('staff-image/dina.webp', 'x');
        $absen = Guru::factory()->create(['userId' => $dina->id, 'nama' => 'Dina Qoyimah']);
        $this->actingAsAdmin();

        $this->post("/data-guru/{$absen->id}/foto", ['foto' => UploadedFile::fake()->image('baru.png', 300, 400)])->assertSessionHas('toast');

        $baru = $dina->fresh()->staff->image;
        $this->assertNotSame('staff-image/dina.webp', $baru);
        Storage::disk('media')->assertExists($baru);
        Storage::disk('media')->assertMissing('staff-image/dina.webp');
        $this->assertNull($absen->fresh()->fotoUrl);
        $this->assertSame(1, Staff::count()); // orang yang sama di website, bukan baris baru
    }

    public function test_kepala_sekolah_memakai_foto_di_bagian_kepala_sekolah_website(): void
    {
        Principal::create(['nama' => 'Mulyanto, S.Kom.', 'tahun_jabatan' => '2018-2022', 'foto' => 'principal-photo/31-lama.jpg']);
        Principal::create(['nama' => 'Teti Tresnawati, M.Pd', 'tahun_jabatan' => '2022-2030', 'foto' => 'principal-photo/32-teti.jpg']);
        $teti = $this->akun('KEPSEK', 'tetitresnawati', 'Teti Tresnawati, M.Pd.');
        $lain = $this->akun('KEPSEK', 'kepsek.baru', 'Kepala Baru');

        $this->assertSame('/media/principal-photo/32-teti.jpg', $teti->fotoUrl());
        $this->assertNull($lain->fotoUrl());
    }

    public function test_foto_guru_tampil_di_menu_pengguna_dan_profil(): void
    {
        $akun = $this->akun('GURU', 'bu.dina', 'Dina Qoyimah');
        $akun->forceFill(['guruStaffId' => $this->staff('Dina Qoyimah', 'teacher', 'staff-image/dina.webp')->id])->save();
        $this->actingAs($akun);

        $this->get('/profil')->assertInertia(fn (Assert $page) => $page
            ->where('akun.fotoUrl', '/media/staff-image/dina.webp')->where('auth.user.fotoUrl', '/media/staff-image/dina.webp'));
    }
}
