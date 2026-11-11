<?php

namespace App\Console\Commands;

use App\Models\Classroom\Akun;
use App\Services\Classroom\AbsensiAccountService;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use stdClass;

/**
 * Sekali jalan saat penggabungan: memindahkan data absensi + SPPD dari basis data absensi lama (koneksi `absensi`,
 * baca saja) ke basis data gabungan (koneksi bawaan) yang sudah menjalankan migrasi `create_absensi_tables`.
 *
 * Akun: tiap baris `users` absensi menjadi satu baris `users` + `profil_akun` di sini. Profil yang sudah tertaut
 * (`profil_akun.absensi_user_id`) dipakai lagi; selain itu dibuat baru. Bila username (atau email sintetisnya) sudah
 * dipakai akun lain, perintah berhenti dan melaporkannya: keputusan penggabungan ada di manusia. `absensi_user_id`
 * menjadi peta id lama -> id baru dan dipakai untuk menerjemahkan 9 kolom pengguna + 2 kolom polimorfik.
 *
 * Notifikasi SPPD (`sppd_notifications`) masuk ke tabel notifikasi bersama `notifikasi`; notifikasi absensi lama
 * (`notifications`) tidak disalin (lihat TUJUAN dan DILEWATI).
 *
 * Kolom TIMESTAMP (tabel SPPD, spatie, notifikasi SPPD) dibaca dengan zona sesi absensi produksi (--zona-sumber)
 * supaya jam yang tampil tidak bergeser saat ditulis ulang di bawah zona sesi gabungan (+07:00). Kolom DATETIME absensi
 * tidak terpengaruh zona sesi.
 *
 * Guru yang sudah punya akun Classroom dengan username lain (mis. SMKBM0xx dari GuruSeeder) dicocokkan lewat nama dan
 * dilaporkan sebagai "mungkin orang yang sama"; impor berhenti sampai ditautkan atau dijalankan dengan --abaikan-mirip.
 *
 * Kerjakan di SALINAN kedua basis data dulu (gladi), baru di produksi. Perintah menolak bila tabel absensi di basis data
 * gabungan sudah berisi data, kecuali tabel konfigurasi (`settings`, `jadwal_hari`) yang ditimpa nilai absensi lama.
 */
class GabungImporAbsensi extends Command
{
    protected $signature = 'gabung:impor-absensi
        {--zona-sumber= : Zona waktu sesi MySQL absensi produksi, mis. +00:00 atau SYSTEM (cek: SELECT @@global.time_zone, @@session.time_zone)}
        {--dry-run : Hanya petakan akun dan tampilkan laporan, tanpa menulis apa pun}
        {--abaikan-mirip : Tetap impor walau ada akun yang mungkin orang yang sama dengan akun Classroom (jadi akun terpisah)}';

    protected $description = 'Pindahkan data absensi + SPPD dari basis data absensi lama ke basis data gabungan';

    /** Migrasi absensi terakhir yang wajib sudah dijalankan di basis data sumber (pakai kode absensi). */
    public const MIGRASI_TERAKHIR = '2026_11_02_100000_ganti_uang_muka_jadi_lumsum';

    private const TIPE_USER = 'App\\Models\\User';

    /**
     * Tabel disalin berurutan (induk dulu). Nilai = kolom berisi id pengguna absensi yang diterjemahkan ke id baru.
     * `roles`/`permissions` ditangani terpisah (dicocokkan lewat nama, karena RoleSeeder mungkin sudah mengisinya).
     *
     * @var array<string, list<string>>
     */
    public const TABEL = [
        'settings' => [],
        'jadwal_hari' => [],
        'holidays' => [],
        'guru' => ['userId'],
        'attendance' => [],
        'briefing_attendance' => [],
        'leave_requests' => [],
        'push_subscriptions' => ['userId'],
        'role_has_permissions' => [],
        'model_has_permissions' => ['model_id'],
        'model_has_roles' => ['model_id'],
        'pengajuan_sppds' => ['pemohon_id', 'disetujui_oleh'],
        'sppds' => ['diterbitkan_oleh'],
        'pencairans' => ['dicairkan_oleh'],
        'log_audits' => ['user_id'],
        'pengajuan_pengikut' => ['user_id'],
        'pengaturans' => [],
        'sppd_notifications' => ['notifiable_id'],
        'laporan_perjalanans' => ['ditulis_oleh'],
        'laporan_perjalanan_fotos' => [],
    ];

    /**
     * Tabel konfigurasi tunggal yang dibuat otomatis dengan nilai default oleh aplikasi gabungan begitu halaman absensi
     * dibuka (SettingsService, JadwalService). Isinya DITIMPA nilai absensi lama, bukan dianggap "sudah berisi data".
     */
    public const KONFIGURASI = ['settings', 'jadwal_hari'];

    /**
     * Tabel sumber yang namanya berbeda di basis data gabungan. Notifikasi SPPD masuk ke tabel notifikasi bersama
     * semua modul (bentuk kolomnya sama).
     *
     * @var array<string, string>
     */
    public const TUJUAN = ['sppd_notifications' => 'notifikasi'];

    /**
     * Tabel sumber yang sengaja tidak disalin: notifikasi absensi lama (status dibaca bersama, umurnya maksimal 7 hari)
     * hanya pemberitahuan sesaat; notifikasi absensi baru dikirim per orang ke tabel `notifikasi`.
     */
    public const DILEWATI = ['notifications'];

    /** @var array<string, int> id pengguna absensi (ULID) => id `users` gabungan */
    private array $peta = [];

    /** @var array<string, array<int, int>> 'roles'|'permissions' => id lama => id baru */
    private array $petaSpatie = ['roles' => [], 'permissions' => []];

    public function handle(): int
    {
        $sumber = DB::connection('absensi');

        try {
            $this->aturZonaSumber($sumber);
            $this->periksaPrasyarat($sumber);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        [$rencana, $bentrok, $mirip] = $this->petakanAkun($sumber);
        $this->laporkanRencana($rencana, $bentrok, $mirip);

        if ($this->option('dry-run')) {
            $this->info('Dry-run: tidak ada yang ditulis.');

            return self::SUCCESS;
        }

        if ($bentrok !== []) {
            $this->error('Impor dibatalkan: selesaikan bentrok username di atas dulu (lihat petunjuk), lalu jalankan ulang.');

            return self::FAILURE;
        }

        if ($mirip !== [] && ! $this->option('abaikan-mirip')) {
            $this->error('Impor dibatalkan: putuskan dulu akun yang mungkin orang yang sama (tautkan, atau jalankan dengan --abaikan-mirip).');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($sumber, $rencana) {
                $this->tulisAkun($rencana);
                $this->salinSpatie($sumber);
                foreach (self::TABEL as $tabel => $kolomPengguna) {
                    $this->salinTabel($sumber, $tabel, $kolomPengguna);
                }
            });
            foreach (self::DILEWATI as $tabel) {
                $this->line(sprintf('  %-28s %6s (notifikasi absensi lama, sengaja tidak disalin)', $tabel, 'lewat'));
            }
        } catch (RuntimeException $e) {
            $this->error('Impor dibatalkan, tidak ada yang ditulis: '.$e->getMessage());

            return self::FAILURE;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->verifikasi($sumber) ? self::SUCCESS : self::FAILURE;
    }

    private function aturZonaSumber(Connection $sumber): void
    {
        $zona = (string) $this->option('zona-sumber');
        if (! preg_match('/^([+-]\d{2}:\d{2}|SYSTEM)$/', $zona)) {
            throw new RuntimeException(
                'Isi --zona-sumber dengan zona sesi MySQL absensi produksi (mis. +00:00 atau SYSTEM). Cek di produksi: '.
                'SELECT @@global.time_zone, @@session.time_zone. Tanpa ini jam di SPPD bisa bergeser.'
            );
        }

        $sumber->statement("SET time_zone = '{$zona}'");
    }

    private function periksaPrasyarat(Connection $sumber): void
    {
        $sudahMigrasi = $sumber->getSchemaBuilder()->hasTable('migrations')
            && $sumber->table('migrations')->where('migration', self::MIGRASI_TERAKHIR)->exists();
        if (! $sudahMigrasi) {
            throw new RuntimeException('Basis data absensi belum menjalankan semua migrasinya ('.self::MIGRASI_TERAKHIR.'). Jalankan `php artisan migrate` dengan kode absensi di basis data sumber dulu.');
        }

        foreach ([...array_map($this->tujuan(...), array_keys(self::TABEL)), 'roles', 'permissions'] as $tabel) {
            if (! Schema::hasTable($tabel)) {
                throw new RuntimeException("Tabel {$tabel} belum ada di basis data gabungan. Jalankan `php artisan migrate` dulu.");
            }
        }

        // Tabel konfigurasi boleh sudah berisi nilai default (dibuat otomatis saat halaman absensi dibuka); ditimpa sumber.
        $berisi = array_values(array_filter(
            array_map($this->tujuan(...), array_diff(array_keys(self::TABEL), self::KONFIGURASI)),
            fn ($t) => DB::table($t)->exists(),
        ));
        if ($berisi !== []) {
            throw new RuntimeException('Tabel absensi di basis data gabungan sudah berisi data ('.implode(', ', $berisi).'). Mulai lagi dari salinan bersih.');
        }

        $fk = DB::selectOne(
            "select count(*) as n from information_schema.table_constraints where table_schema = database() and table_name = 'profil_akun' and constraint_name = 'profil_akun_id_fkey' and constraint_type = 'FOREIGN KEY'"
        );
        if ((int) $fk->n === 0) {
            throw new RuntimeException('FK profil_akun_id_fkey tidak ada di basis data gabungan (profil_akun -> users). Perbaiki data profil yang yatim dan pasang FK-nya dulu.');
        }
    }

    /**
     * @return array{
     *     0: list<array{status: string, sumber: stdClass, id: ?int}>,
     *     1: list<array{username: string, alasan: string}>,
     *     2: list<array{username: string, absensi_id: string, nama: string, profil: Akun}>
     * }
     */
    private function petakanAkun(Connection $sumber): array
    {
        $baris = $sumber->table('users')
            ->leftJoin('guru', 'guru.userId', '=', 'users.id')
            ->select('users.*', 'guru.nama as guru_nama')
            ->orderBy('users.createdAt')
            ->get();

        $rencana = [];
        $bentrok = [];
        $mirip = [];
        foreach ($baris as $u) {
            $tertaut = DB::table('profil_akun')->where('absensi_user_id', $u->id)->value('id');
            // Profil tertaut memakai username absensi; username itu tidak boleh sedang dipakai profil LAIN.
            $profilLain = DB::table('profil_akun')->where('username', $u->username)
                ->when($tertaut !== null, fn ($q) => $q->where('id', '!=', $tertaut))
                ->first(['id', 'role']);

            if ($tertaut !== null && ! $profilLain) {
                $rencana[] = ['status' => 'tertaut', 'sumber' => $u, 'id' => (int) $tertaut];

                continue;
            }

            if ($profilLain) {
                $bentrok[] = ['username' => $u->username, 'alasan' => "username sudah dipakai profil id {$profilLain->id} ({$profilLain->role})"];

                continue;
            }

            if (DB::table('users')->where('email', $this->emailSintetis($u->username))->exists()) {
                $bentrok[] = ['username' => $u->username, 'alasan' => 'email sintetis '.$this->emailSintetis($u->username).' sudah dipakai'];

                continue;
            }

            // Guru yang sudah punya akun Classroom sendiri (mis. SMKBM0xx dari GuruSeeder) dengan username lain tidak
            // tertangkap sebagai bentrok; tanpa ditautkan ia akan punya dua akun. Cocokkan lewat nama (gelar diabaikan).
            $nama = $u->name ?: $u->guru_nama;
            $profil = $nama ? app(AbsensiAccountService::class)->cocokkanNama($nama) : null;
            if ($profil && $profil->absensi_user_id === null) {
                $mirip[] = ['username' => $u->username, 'absensi_id' => $u->id, 'nama' => $nama, 'profil' => $profil];
            }

            $rencana[] = ['status' => 'baru', 'sumber' => $u, 'id' => null];
        }

        return [$rencana, $bentrok, $mirip];
    }

    /**
     * @param  list<array{status: string, sumber: stdClass, id: ?int}>  $rencana
     * @param  list<array{username: string, alasan: string}>  $bentrok
     * @param  list<array{username: string, absensi_id: string, nama: string, profil: Akun}>  $mirip
     */
    private function laporkanRencana(array $rencana, array $bentrok, array $mirip): void
    {
        $hitung = array_count_values(array_column($rencana, 'status'));
        $this->table(['Akun absensi', 'Jumlah'], [
            ['Tertaut ke profil yang ada (dipakai lagi)', $hitung['tertaut'] ?? 0],
            ['Dibuat baru', $hitung['baru'] ?? 0],
            ['  di antaranya mungkin orang yang sama dengan akun Classroom', count($mirip)],
            ['BENTROK (tidak diimpor)', count($bentrok)],
        ]);

        if ($mirip !== []) {
            $this->warn('Mungkin orang yang sama: akun absensi ini akan jadi akun BARU di samping akun Classroom-nya. Bila memang');
            $this->warn('orang yang sama, tautkan supaya jadi satu akun (data Classroom tetap, sandi ikut absensi), lalu ulangi:');
            $this->warn("  UPDATE profil_akun SET absensi_user_id = '<id absensi>' WHERE id = <id profil>;");
            $this->warn('Bila memang orang berbeda, jalankan dengan --abaikan-mirip.');
            $this->table(['Username absensi', 'Id absensi', 'Nama di absensi', 'Profil Classroom (id, username, nama)'], array_map(
                fn ($m) => [$m['username'], $m['absensi_id'], $m['nama'], "{$m['profil']->id}, {$m['profil']->username}, {$m['profil']->name}"],
                $mirip,
            ));
        }

        if ($bentrok !== []) {
            $this->warn('Bentrok username. Putuskan per akun: bila orangnya SAMA, tautkan profil yang ada lalu ulangi,');
            $this->warn("  UPDATE profil_akun SET absensi_user_id = '<id users absensi>' WHERE id = <id profil>;");
            $this->warn('bila orangnya BEDA, ganti username salah satunya.');
            $this->table(['Username', 'Alasan'], array_map(fn ($b) => [$b['username'], $b['alasan']], $bentrok));
        }
    }

    /** @param  list<array{status: string, sumber: stdClass, id: ?int}>  $rencana */
    private function tulisAkun(array $rencana): void
    {
        foreach ($rencana as $r) {
            $u = $r['sumber'];
            $role = $u->role === 'ADMIN' ? 'admin' : 'user';
            $profil = [
                'username' => $u->username,
                'name' => $u->name ?: $u->guru_nama,
                'jabatan' => $u->jabatan,
                'role' => $u->role ?? 'STAF',
                'is_active' => (bool) $u->is_active,
                'absensi_user_id' => $u->id,
                'updatedAt' => now(),
            ];

            if ($r['status'] === 'tertaut') {
                // Absensi sumber kebenaran untuk akun staf: sandi, peran, nama, status.
                $id = $r['id'];
                $pengguna = ['password' => $u->password, 'role' => $role];
                // Email sintetis mengikuti username baru (seperti Akun::saved), selama belum dipakai akun lain.
                $email = (string) DB::table('users')->where('id', $id)->value('email');
                $emailBaru = $this->emailSintetis($u->username);
                if (str_ends_with($email, '@akun.local') && ! DB::table('users')->where('email', $emailBaru)->where('id', '!=', $id)->exists()) {
                    $pengguna['email'] = $emailBaru;
                }
                DB::table('users')->where('id', $id)->update($pengguna);
                DB::table('profil_akun')->where('id', $id)->update($profil);
            } else {
                // Langsung lewat query (bukan model): hash sandi disalin apa adanya dan hook Akun tidak ikut jalan.
                $id = DB::table('users')->insertGetId([
                    'email' => $this->emailSintetis($u->username),
                    'password' => $u->password,
                    'role' => $role,
                ]);
                DB::table('profil_akun')->insert($profil + ['id' => $id, 'createdAt' => $u->createdAt]);
            }

            $this->peta[$u->id] = (int) $id;
        }
    }

    /** Role/permission SPPD dicocokkan lewat (name, guard_name); yang belum ada dibuat. */
    private function salinSpatie(Connection $sumber): void
    {
        foreach (['roles', 'permissions'] as $tabel) {
            foreach ($sumber->table($tabel)->get() as $baris) {
                $id = DB::table($tabel)->where('name', $baris->name)->where('guard_name', $baris->guard_name)->value('id')
                    ?? DB::table($tabel)->insertGetId([
                        'name' => $baris->name,
                        'guard_name' => $baris->guard_name,
                        'created_at' => $baris->created_at,
                        'updated_at' => $baris->updated_at,
                    ]);
                $this->petaSpatie[$tabel][(int) $baris->id] = (int) $id;
            }
        }
    }

    /** @param  list<string>  $kolomPengguna */
    private function salinTabel(Connection $sumber, string $tabel, array $kolomPengguna): void
    {
        $tujuan = $this->tujuan($tabel);
        $kolomSumber = $sumber->getSchemaBuilder()->getColumnListing($tabel);
        $kolomTujuan = Schema::getColumnListing($tujuan);
        sort($kolomSumber);
        sort($kolomTujuan);
        if ($kolomSumber !== $kolomTujuan) {
            throw new RuntimeException("Kolom tabel {$tabel} berbeda antara sumber dan gabungan: ".
                implode(', ', array_merge(array_diff($kolomSumber, $kolomTujuan), array_diff($kolomTujuan, $kolomSumber))));
        }

        $baris = $sumber->table($tabel)->get()->map(function (stdClass $b) use ($tabel, $kolomPengguna) {
            $b = (array) $b;

            foreach ($kolomPengguna as $kolom) {
                $polimorfik = ['model_id' => 'model_type', 'notifiable_id' => 'notifiable_type'][$kolom] ?? null;
                if ($b[$kolom] === null || ($polimorfik && $b[$polimorfik] !== self::TIPE_USER)) {
                    continue;
                }
                $b[$kolom] = $this->peta[$b[$kolom]]
                    ?? throw new RuntimeException("{$tabel}.{$kolom} = {$b[$kolom]} tidak menunjuk akun absensi mana pun.");
            }

            if (array_key_exists('role_id', $b)) {
                $b['role_id'] = $this->petaSpatie['roles'][(int) $b['role_id']];
            }
            if (array_key_exists('permission_id', $b)) {
                $b['permission_id'] = $this->petaSpatie['permissions'][(int) $b['permission_id']];
            }

            return $b;
        });

        // Nilai default konfigurasi yang dibuat aplikasi gabungan diganti nilai absensi lama (sumber kebenaran).
        if (in_array($tabel, self::KONFIGURASI, true)) {
            DB::table($tabel)->delete();
        }

        foreach ($baris->chunk(500) as $potongan) {
            DB::table($tujuan)->insert($potongan->values()->all());
        }

        $this->line(sprintf('  %-28s %6d baris', $this->labelTabel($tabel), $baris->count()));
    }

    /** Nama tabel di basis data gabungan untuk tabel sumber ini. */
    private function tujuan(string $tabel): string
    {
        return self::TUJUAN[$tabel] ?? $tabel;
    }

    private function labelTabel(string $tabel): string
    {
        return $this->tujuan($tabel) === $tabel ? $tabel : "{$tabel} -> {$this->tujuan($tabel)}";
    }

    private function verifikasi(Connection $sumber): bool
    {
        $baris = [];
        $cocok = true;

        $akunSumber = $sumber->table('users')->count();
        $akunTujuan = DB::table('profil_akun')->whereIn('absensi_user_id', array_keys($this->peta))->count();
        $baris[] = ['users -> profil_akun', $akunSumber, $akunTujuan, $akunSumber === $akunTujuan ? 'ok' : 'BEDA'];
        $cocok = $cocok && $akunSumber === $akunTujuan;

        foreach (array_keys(self::TABEL) as $tabel) {
            $a = $sumber->table($tabel)->count();
            $b = DB::table($this->tujuan($tabel))->count();
            $baris[] = [$this->labelTabel($tabel), $a, $b, $a === $b ? 'ok' : 'BEDA'];
            $cocok = $cocok && $a === $b;
        }

        $this->table(['Tabel', 'Sumber', 'Gabungan', ''], $baris);
        $cocok ? $this->info('Impor selesai, jumlah baris cocok.') : $this->error('Jumlah baris tidak cocok. Periksa tabel bertanda BEDA.');

        return $cocok;
    }

    private function emailSintetis(string $username): string
    {
        return mb_substr($username.'@akun.local', 0, 100);
    }
}
