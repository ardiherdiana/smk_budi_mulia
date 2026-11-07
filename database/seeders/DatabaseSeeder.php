<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan db:seed` mengisi semuanya: konten landing page dari data produksi (berita, jurusan, guru dan staf beserta
 * fotonya, dst.) lalu data Classroom (admin, tahun ajaran, guru, mapel, rombel, siswa).
 *
 * ProductionDataSeeder mengosongkan `users`, termasuk akun Classroom, jadi ia hanya dijalankan di database yang
 * `users`-nya masih kosong (database baru sesudah `migrate`). Pada db:seed berikutnya ia dilewati dan hanya Classroom
 * yang diisi ulang (aman). Untuk memuat ulang data produksi dengan sengaja:
 *
 *   php artisan db:seed --class=ProductionDataSeeder
 *
 * Foto/video berupa berkas di `public/media`; salin foldernya bila memindahkan proyek ke mesin lain.
 * Role SPPD (pemohon, kepala_sekolah, tu, bendahara) dibuat lebih dulu oleh RoleSeeder; aman dijalankan ulang.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if (DB::table('users')->doesntExist()) {
            $this->call(ProductionDataSeeder::class);
        } else {
            $this->command?->warn('Data produksi dilewati karena tabel users sudah berisi (memuat ulang akan menghapus akun Classroom). Untuk memuat ulang: php artisan db:seed --class=ProductionDataSeeder');
        }

        $this->call(ClassroomSeeder::class);
    }
}
