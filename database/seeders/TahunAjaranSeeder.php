<?php

namespace Database\Seeders;

use App\Models\Classroom\TahunAjaran;
use Illuminate\Database\Seeder;

/** Tahun ajaran aktif 2026/2027 ganjil: ruang kelas baru hanya bisa dibuat bila ada tahun ajaran aktif. */
class TahunAjaranSeeder extends Seeder
{
    public function run(): void
    {
        TahunAjaran::updateOrCreate(['nama' => '2026/2027', 'semester' => 'GANJIL'], ['aktif' => true]);

        $this->command?->info('Seed tahun ajaran selesai: 2026/2027 GANJIL (aktif).');
    }
}
