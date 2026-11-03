<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Data Classroom: admin tunggal, tahun ajaran aktif, guru, mata pelajaran, rombel, lalu siswa
 * (php artisan db:seed --class=ClassroomSeeder). Semuanya ditulis di kode, tidak membaca Excel. Aman dijalankan ulang.
 * Jalankan ProductionDataSeeder lebih dulu bila perlu; ia mengosongkan `users`, termasuk akun Classroom.
 */
class ClassroomSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            TahunAjaranSeeder::class,
            GuruSeeder::class,
            MapelSeeder::class,
            RombelSeeder::class,
            SiswaSeeder::class,
        ]);
    }
}
