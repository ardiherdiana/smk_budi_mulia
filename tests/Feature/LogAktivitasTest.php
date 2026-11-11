<?php

namespace Tests\Feature;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Mapel;
use App\Models\LogAktivitas;
use App\Models\Sppd\LogAudit;
use App\Models\User;
use App\Support\LogAktivitas\Perekam;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Log aktivitas bersama semua modul: tambah/ubah/hapus data, masuk/keluar/gagal masuk, hapus otomatis 30 hari. */
class LogAktivitasTest extends TestCase
{
    public function test_tambah_ubah_hapus_data_tercatat_dengan_modul_dan_selisih(): void
    {
        $admin = $this->actingAsAdmin();

        $this->post('/classroom/mapel', ['nama' => 'Jaringan Dasar'])->assertSessionHasNoErrors();
        $mapel = Mapel::where('nama', 'Jaringan Dasar')->sole();
        $this->patch("/classroom/mapel/{$mapel->id}", ['nama' => 'Jaringan Lanjut'])->assertSessionHasNoErrors();
        $this->delete("/classroom/mapel/{$mapel->id}")->assertRedirect();

        $log = LogAktivitas::where('entitas', 'Mata pelajaran')->orderBy('id')->get();
        $this->assertSame(['tambah', 'ubah', 'hapus'], $log->pluck('aksi')->all());
        $this->assertSame(['Classroom'], $log->pluck('modul')->unique()->values()->all());
        $this->assertSame($admin->id, $log[0]->userId);
        $this->assertSame((string) $mapel->id, $log[1]->entitasId);
        $this->assertSame(Mapel::class, $log[1]->entitasTipe);
        $this->assertSame(['nama' => ['Jaringan Dasar', 'Jaringan Lanjut']], $log[1]->perubahan);
        // Nama pelaku = nama profil (admin dibuatkan profil "Admin" saat pertama membuka Classroom) atau emailnya.
        $this->assertStringEndsWith(' mengubah Mata pelajaran "Jaringan Lanjut"', $log[1]->deskripsi);
        $this->assertSame(['nama' => ['Jaringan Lanjut', null]], $log[2]->perubahan);
        $this->assertSame('127.0.0.1', $log[0]->ip);
    }

    public function test_sandi_tidak_pernah_tersimpan_di_log(): void
    {
        $admin = $this->admin();
        $this->aktifkanPerekam($admin);

        $admin->update(['password' => 'SandiBaru12345']);

        $log = LogAktivitas::where('aksi', 'ubah')->sole();
        $this->assertSame('Akun', $log->modul);
        $this->assertSame(['password' => 'diubah'], $log->perubahan);
        $semua = LogAktivitas::all()->toJson();
        $this->assertStringNotContainsString('SandiBaru12345', $semua);
        $this->assertStringNotContainsString((string) $admin->fresh()->password, $semua);
    }

    public function test_akun_baru_dengan_profil_dicatat_sekali(): void
    {
        $this->aktifkanPerekam($this->admin());

        $guru = User::create(['username' => 'guru9', 'name' => 'Guru Sembilan', 'password' => 'Rahasia12345', 'peran' => 'GURU']);

        $this->assertTrue(Akun::whereKey($guru->id)->exists());
        $tambah = LogAktivitas::where('modul', 'Akun')->where('aksi', 'tambah')->get();
        $this->assertCount(1, $tambah);
        $this->assertSame('diisi', $tambah[0]->perubahan['password']);
    }

    public function test_model_tak_terdaftar_dan_di_luar_permintaan_web_tidak_dicatat(): void
    {
        // Seeder, impor, dan perintah konsol: perekam mati.
        Mapel::create(['nama' => 'Dari konsol']);
        $this->assertSame(0, LogAktivitas::count());

        // Model yang tidak didaftarkan (riwayat SPPD) tidak dicatat walau perekam menyala.
        $this->aktifkanPerekam($this->admin());
        LogAudit::create(['entitas_terkait' => 'X', 'entitas_id' => 1, 'aksi' => 'uji']);
        $this->assertSame(0, LogAktivitas::count());
    }

    public function test_masuk_keluar_dan_gagal_masuk_tercatat_tanpa_sandi(): void
    {
        $admin = $this->admin();

        $this->post('/login', ['email' => 'admin@test.local', 'password' => 'SalahSekali99'])->assertSessionHasErrors('email');
        $gagal = LogAktivitas::sole();
        $this->assertSame('gagal_masuk', $gagal->aksi);
        $this->assertNull($gagal->userId);
        $this->assertSame('Gagal masuk sebagai "admin@test.local"', $gagal->deskripsi);
        $this->assertStringNotContainsString('SalahSekali99', LogAktivitas::all()->toJson());

        $this->post('/login', ['email' => 'admin@test.local', 'password' => 'Rahasia123'])->assertRedirect('/menu-utama');
        $this->post('/logout')->assertRedirect('/login');

        $this->assertSame(['gagal_masuk', 'masuk', 'keluar'], LogAktivitas::orderBy('id')->pluck('aksi')->all());
        $this->assertSame([null, $admin->id, $admin->id], LogAktivitas::orderBy('id')->pluck('userId')->all());
    }

    public function test_log_lebih_dari_30_hari_terhapus_dan_riwayat_sppd_tetap(): void
    {
        $this->travelTo('2026-11-11 10:00:00');
        $lama = LogAktivitas::create(['modul' => 'Akun', 'aksi' => 'masuk', 'deskripsi' => 'lama', 'createdAt' => '2026-10-12 09:59:59']);
        $batas = LogAktivitas::create(['modul' => 'Akun', 'aksi' => 'masuk', 'deskripsi' => 'tepat 30 hari', 'createdAt' => '2026-10-12 10:00:00']);
        $baru = LogAktivitas::create(['modul' => 'Akun', 'aksi' => 'masuk', 'deskripsi' => 'baru', 'createdAt' => '2026-10-12 10:00:01']);
        $riwayatSppd = LogAudit::create(['entitas_terkait' => 'X', 'entitas_id' => 1, 'aksi' => 'disetujui']);
        $riwayatSppd->forceFill(['created_at' => '2025-01-01 00:00:00'])->save();

        $this->artisan('model:prune', ['--model' => LogAktivitas::class])->assertSuccessful();

        $this->assertModelMissing($lama);
        $this->assertModelMissing($batas);
        $this->assertModelExists($baru);
        $this->assertModelExists($riwayatSppd);
    }

    public function test_halaman_hanya_untuk_admin_dan_bisa_disaring(): void
    {
        LogAktivitas::create(['modul' => 'SPPD', 'aksi' => 'ubah', 'deskripsi' => 'Rina mengubah Pengajuan SPPD "Bandung"', 'namaPengguna' => 'Rina']);
        LogAktivitas::create(['modul' => 'Website', 'aksi' => 'tambah', 'deskripsi' => 'Admin menambah Berita "PPDB"', 'namaPengguna' => 'Admin']);
        LogAktivitas::create(['modul' => 'Akun', 'aksi' => 'gagal_masuk', 'deskripsi' => 'Gagal masuk sebagai "x"']);

        $this->actingAs($this->akun('KEPSEK', 'kepsek'))->get('/log-aktivitas')->assertForbidden();
        $this->actingAs($this->akun('GURU', 'guru1'))->get('/log-aktivitas')->assertForbidden();

        $this->actingAsAdmin();
        $this->get('/log-aktivitas')->assertInertia(fn (Assert $page) => $page
            ->component('log-aktivitas-page')
            ->has('log.data', 3)
            ->where('masaSimpanHari', 30));
        $this->get('/log-aktivitas?modul=SPPD')->assertInertia(fn (Assert $page) => $page
            ->has('log.data', 1)
            ->where('log.data.0.pengguna', 'Rina'));
        $this->get('/log-aktivitas?aksi=gagal_masuk')->assertInertia(fn (Assert $page) => $page->has('log.data', 1));
        $this->get('/log-aktivitas?cari=PPDB')->assertInertia(fn (Assert $page) => $page
            ->has('log.data', 1)
            ->where('log.data.0.modul', 'Website'));
        $this->get('/log-aktivitas?modul=Tidakada')->assertSessionHasErrors('modul');
    }

    public function test_kartu_menu_utama_log_aktivitas_hanya_untuk_admin(): void
    {
        $punyaKartu = fn (Akun|User $akun) => collect($this->actingAs($akun)->get('/menu-utama')->viewData('page')['props']['modul'] ?? [])
            ->contains('key', 'log');

        $this->assertTrue($punyaKartu($this->admin()));
        $this->assertFalse($punyaKartu($this->akun('KEPSEK', 'kepsek')));
    }

    /** Menyalakan perekam seperti di dalam permintaan web, dengan pengguna yang sedang masuk. */
    private function aktifkanPerekam(User $pengguna): void
    {
        $this->actingAs($pengguna);
        app(Perekam::class)->aktifkan();
    }
}
