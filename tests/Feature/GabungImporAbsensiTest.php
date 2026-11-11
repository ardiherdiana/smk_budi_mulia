<?php

namespace Tests\Feature;

use App\Console\Commands\GabungImporAbsensi;
use App\Models\Classroom\Akun;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use PDO;
use Tests\TestCase;

/**
 * `gabung:impor-absensi` terhadap basis data absensi tiruan (`landing_page_testing_absensi`, skema dari
 * tests/Fixtures/absensi-sumber-schema.sql). Basis data sumber dibuat lewat PDO terpisah supaya transaksi
 * RefreshDatabase di koneksi bawaan tidak ter-commit oleh DDL.
 */
class GabungImporAbsensiTest extends TestCase
{
    private const DB_SUMBER = 'landing_page_testing_absensi';

    private const ADMIN = '01JADMIN000000000000000000';

    private const GURU = '01JGURU0000000000000000000';

    private const STAF = '01JSTAF0000000000000000000';

    private const TERTAUT = '01JTERTAUT0000000000000000';

    protected function setUp(): void
    {
        parent::setUp();

        $mysql = config('database.connections.mysql');
        $pdo = new PDO("mysql:host={$mysql['host']};port={$mysql['port']}", $mysql['username'], $mysql['password']);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.self::DB_SUMBER.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        config(['database.connections.absensi' => ['database' => self::DB_SUMBER] + $mysql]);
        DB::purge('absensi');
        $this->sumber()->unprepared(file_get_contents(base_path('tests/Fixtures/absensi-sumber-schema.sql')));
    }

    private function sumber(): Connection
    {
        return DB::connection('absensi');
    }

    /** Data absensi lama: admin, guru (dengan absen, izin, notifikasi, push), pegawai SPPD tanpa peran absensi, dan satu akun yang profilnya sudah tertaut di Classroom. */
    private function isiSumber(): void
    {
        $s = $this->sumber();
        // Seperti absensi produksi: sesi UTC, aplikasi menulis jam dinding WIB ke kolom TIMESTAMP.
        $s->statement("SET time_zone = '+00:00'");
        $s->table('migrations')->insert(['migration' => GabungImporAbsensi::MIGRASI_TERAKHIR, 'batch' => 9]);

        $waktu = '2026-09-01 07:00:00.000';
        foreach ([
            [self::ADMIN, 'admin', null, 'ADMIN', 'sandi-admin', null],
            [self::GURU, 'bu.dina', null, 'GURU', 'sandi-guru', 'Guru Produktif'],
            [self::STAF, 'bendahara', 'Siti Bendahara', null, 'sandi-staf', 'Bendahara'],
            [self::TERTAUT, 'pak.rio', 'Rio Falentino, S.Pd. Gr.', 'KEPSEK', 'sandi-rio', 'Kepala Sekolah'],
        ] as [$id, $username, $name, $role, $sandi, $jabatan]) {
            $s->table('users')->insert([
                'id' => $id, 'username' => $username, 'name' => $name, 'password' => Hash::make($sandi), 'role' => $role,
                'jabatan' => $jabatan, 'is_active' => 1, 'createdAt' => $waktu, 'updatedAt' => $waktu,
            ]);
        }

        $s->table('guru')->insert([
            ['id' => 'G-DINA', 'userId' => self::GURU, 'nama' => 'Dina Qoyimah, S.Pd. Gr.', 'qrToken' => 'qr-dina', 'aktif' => 1, 'createdAt' => $waktu, 'updatedAt' => $waktu],
            ['id' => 'G-RIO', 'userId' => self::TERTAUT, 'nama' => 'Rio Falentino', 'qrToken' => 'qr-rio', 'aktif' => 1, 'createdAt' => $waktu, 'updatedAt' => $waktu],
        ]);
        $s->table('attendance')->insert([
            ['id' => 'A1', 'guruId' => 'G-DINA', 'tanggal' => '2026-10-01', 'jamMasuk' => '2026-10-01 06:55:00.000', 'statusMasuk' => 'HADIR', 'createdAt' => $waktu, 'updatedAt' => $waktu],
            ['id' => 'A2', 'guruId' => 'G-DINA', 'tanggal' => '2026-10-02', 'jamMasuk' => '2026-10-02 07:20:00.000', 'statusMasuk' => 'TELAT', 'createdAt' => $waktu, 'updatedAt' => $waktu],
        ]);
        $s->table('leave_requests')->insert(['id' => 'L1', 'guruId' => 'G-DINA', 'jenis' => 'SAKIT', 'tanggalMulai' => '2026-10-05', 'tanggalSelesai' => '2026-10-05', 'alasan' => 'Demam', 'reviewedBy' => 'admin', 'createdAt' => $waktu, 'updatedAt' => $waktu]);
        $s->table('notifications')->insert(['id' => 'N1', 'type' => 'LEAVE_REQUEST', 'judul' => 'Izin', 'pesan' => 'Dina sakit', 'guruId' => 'G-DINA', 'createdAt' => $waktu]);
        $s->table('push_subscriptions')->insert(['id' => 'P1', 'userId' => self::GURU, 'endpoint' => 'https://push.example/1', 'p256dh' => 'k', 'auth' => 'a', 'createdAt' => $waktu]);
        $s->table('settings')->insert(['id' => 1, 'namaSekolah' => 'SMK Budi Mulia Karawang', 'updatedAt' => $waktu]);

        // Id role sengaja beda dengan yang dibuat RoleSeeder di basis data gabungan: harus dicocokkan lewat nama.
        $s->table('roles')->insert([
            ['id' => 71, 'name' => 'pemohon', 'guard_name' => 'web', 'created_at' => '2026-09-01 07:00:00', 'updated_at' => '2026-09-01 07:00:00'],
            ['id' => 72, 'name' => 'bendahara', 'guard_name' => 'web', 'created_at' => '2026-09-01 07:00:00', 'updated_at' => '2026-09-01 07:00:00'],
        ]);
        $s->table('model_has_roles')->insert([
            ['role_id' => 71, 'model_type' => 'App\\Models\\User', 'model_id' => self::GURU],
            ['role_id' => 72, 'model_type' => 'App\\Models\\User', 'model_id' => self::STAF],
        ]);

        $s->table('pengajuan_sppds')->insert([
            'id' => 5, 'pemohon_id' => self::GURU, 'tujuan' => 'Dinas Pendidikan', 'maksud' => 'Rapat', 'tanggal_berangkat' => '2026-10-10',
            'jam_berangkat' => '08:00:00', 'tanggal_kembali' => '2026-10-10', 'jam_kembali' => '15:00:00', 'status' => 'selesai',
            'disetujui_oleh' => self::TERTAUT, 'disetujui_at' => '2026-10-02 09:30:00', 'created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-02 09:30:00',
        ]);
        $s->table('sppds')->insert(['id' => 3, 'pengajuan_id' => 5, 'nomor_sppd' => '005/SMK-BM/X/2026', 'diterbitkan_oleh' => self::STAF, 'created_at' => '2026-10-02 10:00:00', 'updated_at' => '2026-10-02 10:00:00']);
        $s->table('pencairans')->insert(['id' => 2, 'pengajuan_id' => 5, 'jenis' => 'lumsum', 'jumlah' => 150000, 'tanggal' => '2026-10-03', 'dicairkan_oleh' => self::STAF, 'created_at' => '2026-10-03 10:00:00', 'updated_at' => '2026-10-03 10:00:00']);
        $s->table('pengajuan_pengikut')->insert(['id' => 1, 'pengajuan_id' => 5, 'user_id' => self::TERTAUT]);
        $s->table('log_audits')->insert(['id' => 9, 'entitas_terkait' => 'App\\Models\\Sppd\\PengajuanSppd', 'entitas_id' => 5, 'user_id' => self::ADMIN, 'aksi' => 'setujui', 'created_at' => '2026-10-02 09:30:00']);
        $s->table('laporan_perjalanans')->insert(['id' => 4, 'pengajuan_id' => 5, 'ditulis_oleh' => self::GURU, 'ringkasan' => 'Rapat selesai', 'created_at' => '2026-10-11 08:00:00', 'updated_at' => '2026-10-11 08:00:00']);
        $s->table('laporan_perjalanan_fotos')->insert(['id' => 6, 'laporan_id' => 4, 'path' => 'laporan-perjalanan/foto.jpg', 'created_at' => '2026-10-11 08:00:00', 'updated_at' => '2026-10-11 08:00:00']);
        $s->table('sppd_notifications')->insert([
            'id' => '9d6c3b1e-0000-4000-8000-000000000001', 'type' => 'App\\Notifications\\Sppd\\PengajuanStatusChanged', 'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => self::GURU, 'data' => '{"pengajuan_id":5}', 'created_at' => '2026-10-02 09:31:00', 'updated_at' => '2026-10-02 09:31:00',
        ]);
    }

    /** Profil Classroom lama yang sudah tertaut ke akun absensi Rio (hasil sinkron sebelum digabung), sandi acak. */
    private function profilTertaut(): Akun
    {
        $akun = $this->akun('KEPSEK', 'pak.rio', 'Rio');
        Akun::whereKey($akun->id)->update(['absensi_user_id' => self::TERTAUT]);

        return $akun->fresh();
    }

    private function impor(array $opsi = []): PendingCommand
    {
        return $this->artisan('gabung:impor-absensi', $opsi + ['--zona-sumber' => '+00:00']);
    }

    public function test_impor_memindahkan_akun_dan_semua_data_dengan_id_baru(): void
    {
        $this->seed(RoleSeeder::class);
        $this->isiSumber();
        $rio = $this->profilTertaut();

        $this->impor()->assertSuccessful();

        $id = fn (string $absensi) => (int) Akun::where('absensi_user_id', $absensi)->value('id');

        // Akun: baru (admin, guru, pegawai SPPD) dan tertaut (profil Rio dipakai lagi, bukan diduplikasi).
        $this->assertSame($rio->id, $id(self::TERTAUT));
        $this->assertSame(1, Akun::where('username', 'pak.rio')->count());
        $this->assertDatabaseHas('profil_akun', ['id' => $id(self::GURU), 'username' => 'bu.dina', 'role' => 'GURU', 'name' => 'Dina Qoyimah, S.Pd. Gr.', 'jabatan' => 'Guru Produktif']);
        $this->assertDatabaseHas('profil_akun', ['id' => $id(self::STAF), 'username' => 'bendahara', 'role' => 'STAF', 'name' => 'Siti Bendahara']);
        $this->assertDatabaseHas('profil_akun', ['id' => $id(self::TERTAUT), 'role' => 'KEPSEK', 'name' => 'Rio Falentino, S.Pd. Gr.']);
        $this->assertDatabaseHas('users', ['id' => $id(self::ADMIN), 'role' => 'admin', 'email' => 'admin@akun.local']);
        $this->assertDatabaseHas('users', ['id' => $id(self::GURU), 'role' => 'user']);

        // Kolom pengguna diterjemahkan.
        $this->assertDatabaseHas('guru', ['id' => 'G-DINA', 'userId' => $id(self::GURU)]);
        $this->assertDatabaseHas('guru', ['id' => 'G-RIO', 'userId' => $rio->id]);
        $this->assertDatabaseCount('attendance', 2);
        $this->assertDatabaseHas('push_subscriptions', ['id' => 'P1', 'userId' => $id(self::GURU)]);
        $this->assertDatabaseHas('pengajuan_sppds', ['id' => 5, 'pemohon_id' => $id(self::GURU), 'disetujui_oleh' => $rio->id]);
        $this->assertDatabaseHas('sppds', ['id' => 3, 'diterbitkan_oleh' => $id(self::STAF)]);
        $this->assertDatabaseHas('pencairans', ['id' => 2, 'dicairkan_oleh' => $id(self::STAF)]);
        $this->assertDatabaseHas('pengajuan_pengikut', ['pengajuan_id' => 5, 'user_id' => $rio->id]);
        $this->assertDatabaseHas('log_audits', ['id' => 9, 'user_id' => $id(self::ADMIN)]);
        $this->assertDatabaseHas('laporan_perjalanans', ['id' => 4, 'ditulis_oleh' => $id(self::GURU)]);
        // Notifikasi SPPD masuk ke tabel notifikasi bersama; notifikasi absensi lama sengaja tidak disalin.
        $this->assertDatabaseHas('notifikasi', ['notifiable_id' => (string) $id(self::GURU)]);
        $this->assertDatabaseCount('notifikasi', 1);

        // Role SPPD dicocokkan lewat nama, bukan id lama.
        $pemohon = DB::table('roles')->where('name', 'pemohon')->value('id');
        $this->assertNotSame(71, (int) $pemohon);
        $this->assertDatabaseHas('model_has_roles', ['role_id' => $pemohon, 'model_id' => (string) $id(self::GURU)]);
        $this->assertSame(['bendahara'], User::find($id(self::STAF))->getRoleNames()->all());
    }

    public function test_jam_timestamp_tidak_bergeser_dan_datetime_tetap(): void
    {
        $this->seed(RoleSeeder::class);
        $this->isiSumber();

        $this->impor()->assertSuccessful();

        // Sumber ditulis di sesi UTC, gabungan membaca di sesi +07:00: jam dinding harus sama persis.
        $this->assertSame('2026-10-01 08:00:00', DB::table('pengajuan_sppds')->where('id', 5)->value('created_at'));
        $this->assertSame('2026-10-02 09:30:00', DB::table('pengajuan_sppds')->where('id', 5)->value('disetujui_at'));
        $this->assertStringStartsWith('2026-10-01 06:55:00', DB::table('attendance')->where('id', 'A1')->value('jamMasuk'));
    }

    public function test_staf_bisa_login_dengan_sandi_lama_absensi_sesudah_impor(): void
    {
        $this->seed(RoleSeeder::class);
        $this->isiSumber();
        $this->profilTertaut();
        $this->impor()->assertSuccessful();

        $this->post('/login', ['email' => 'bu.dina', 'password' => 'sandi-guru'])->assertRedirect('/menu-utama');
        $this->post('/logout');
        // Profil tertaut tadinya bersandi acak; sekarang memakai sandi absensi.
        $this->post('/login', ['email' => 'pak.rio', 'password' => 'sandi-rio'])->assertRedirect('/menu-utama');
        $this->get('/sppd/dashboard')->assertOk();
    }

    public function test_dry_run_tidak_menulis_apa_pun(): void
    {
        $this->isiSumber();

        $this->impor(['--dry-run' => true])->expectsOutputToContain('Dry-run')->assertSuccessful();

        $this->assertDatabaseCount('guru', 0);
        $this->assertDatabaseMissing('profil_akun', ['username' => 'bu.dina']);
    }

    public function test_bentrok_username_menghentikan_impor_tanpa_menulis(): void
    {
        $this->isiSumber();
        $this->siswa($this->rombel(), 'bu.dina');

        $this->impor()->expectsOutputToContain('bu.dina')->assertFailed();

        $this->assertDatabaseCount('guru', 0);
        $this->assertSame(1, Akun::where('username', 'bu.dina')->count());
    }

    public function test_menolak_tanpa_zona_sumber_atau_migrasi_absensi_belum_lengkap(): void
    {
        $this->isiSumber();
        $this->artisan('gabung:impor-absensi')->expectsOutputToContain('--zona-sumber')->assertFailed();

        $this->sumber()->table('migrations')->delete();
        $this->impor()->expectsOutputToContain(GabungImporAbsensi::MIGRASI_TERAKHIR)->assertFailed();
    }

    public function test_menolak_bila_tabel_absensi_gabungan_sudah_berisi(): void
    {
        $this->isiSumber();
        DB::table('holidays')->insert(['id' => 'H1', 'tanggal' => '2026-12-25', 'keterangan' => 'Natal', 'createdAt' => now()]);

        $this->impor()->expectsOutputToContain('sudah berisi data')->assertFailed();
    }

    public function test_nilai_default_konfigurasi_dari_aplikasi_gabungan_ditimpa_nilai_absensi(): void
    {
        $this->seed(RoleSeeder::class);
        $this->isiSumber();
        $this->sumber()->table('jadwal_hari')->insert(['hari' => 1, 'aktif' => 1, 'jamMulaiAbsen' => '06:00', 'jamMasuk' => '07:05', 'batasTelat' => '07:10', 'jamPulang' => '11:40', 'updatedAt' => '2026-10-14 07:07:57.000']);
        // Dibuat otomatis begitu halaman absensi dibuka di aplikasi gabungan (SettingsService, JadwalService).
        DB::table('settings')->insert(['id' => 1, 'namaSekolah' => 'Default', 'updatedAt' => now()]);
        DB::table('jadwal_hari')->insert(['hari' => 1, 'aktif' => 1, 'jamMasuk' => '07:00', 'updatedAt' => now()]);
        DB::table('jadwal_hari')->insert(['hari' => 2, 'aktif' => 1, 'jamMasuk' => '07:00', 'updatedAt' => now()]);

        $this->impor()->assertSuccessful();

        $this->assertSame('SMK Budi Mulia Karawang', DB::table('settings')->value('namaSekolah'));
        $this->assertSame(['07:05'], DB::table('jadwal_hari')->pluck('jamMasuk')->all());
    }

    public function test_guru_yang_sudah_punya_akun_classroom_dengan_username_lain_dilaporkan_dan_impor_berhenti(): void
    {
        $this->seed(RoleSeeder::class);
        $this->isiSumber();
        $this->profilTertaut();
        $classroom = $this->akun('GURU', 'SMKBM017', 'Dina Qoyimah, S.Pd.');

        $this->impor(['--dry-run' => true])->expectsOutputToContain('SMKBM017')->assertSuccessful();
        $this->impor()->expectsOutputToContain('--abaikan-mirip')->assertFailed();
        $this->assertDatabaseCount('guru', 0);

        // Orang yang sama: tautkan, lalu impor memakai akun Classroom itu (satu akun, sandi ikut absensi).
        Akun::whereKey($classroom->id)->update(['absensi_user_id' => self::GURU]);
        $this->impor()->assertSuccessful();

        $this->assertDatabaseHas('guru', ['id' => 'G-DINA', 'userId' => $classroom->id]);
        $this->assertDatabaseHas('profil_akun', ['id' => $classroom->id, 'username' => 'bu.dina']);
        $this->assertTrue(Hash::check('sandi-guru', $classroom->fresh()->password));
    }

    public function test_abaikan_mirip_mengimpor_sebagai_akun_terpisah(): void
    {
        $this->seed(RoleSeeder::class);
        $this->isiSumber();
        $this->profilTertaut();
        $classroom = $this->akun('GURU', 'SMKBM017', 'Dina Qoyimah, S.Pd.');

        $this->impor(['--abaikan-mirip' => true])->assertSuccessful();

        $this->assertNotSame($classroom->id, (int) DB::table('guru')->where('id', 'G-DINA')->value('userId'));
        $this->assertDatabaseHas('profil_akun', ['id' => $classroom->id, 'username' => 'SMKBM017', 'absensi_user_id' => null]);
    }
}
