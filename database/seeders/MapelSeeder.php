<?php

namespace Database\Seeders;

use App\Models\Classroom\Mapel;
use Illuminate\Database\Seeder;

/**
 * Daftar mata pelajaran resmi (`DAFTAR`), ditulis langsung di kode sehingga seeder ini jalan tanpa berkas apa pun.
 * Aman dijalankan ulang (firstOrCreate). Mapel tiap guru ada di `GuruSeeder::DAFTAR` dan memakai nama yang sama persis.
 */
class MapelSeeder extends Seeder
{
    /** @var list<string> */
    public const DAFTAR = [
        'Bahasa Indonesia (Kolaboratif Jurusan)',
        'Bahasa Indonesia (TKA+AN+UTBK+Kebekerjaan)',
        'Pendidikan Pancasila (Penguatan Karakter)',
        'BK/BP/Pembina Karakter',
        'Produktif TKJ',
        'Bahasa Inggris (TKA+SNBT)',
        'Bhs. Inggris (Kebekerjaan)',
        'Project Kreatif & Kewirausahaan',
        'Penjaskes',
        'Matematika (Kolaboratif Jurusan)',
        'Matematika (TKA+AN+SNBT+Kebekerjaan)',
        'Ilmu Pengetahuan Alam dan Sosial (IPAS)',
        'Bahasa Inggris (Kolaboratif Jurusan)',
        'Informatika',
        'Project Kreatif & Kewirausahaan (TKA)',
        'Project Kreatif & Kewirausahaan (Kolaboratif Jurusan)',
        'Mata Pelajaran Pilihan (Informatika Lanjutan)',
        'Produktif AKT',
        'Produktif RPL',
        'Penjaskes (Persiapan Tes Fisik)',
        'Bahasa Jepang',
        'Mata Pelajaran Pilihan (Pembekalan PKL/Informatika)',
        'Bahasa Inggris',
    ];

    public function run(): void
    {
        foreach (self::DAFTAR as $nama) {
            Mapel::firstOrCreate(['nama' => $nama]);
        }

        $this->command?->info(sprintf('Seed mapel selesai: %d mata pelajaran.', count(self::DAFTAR)));
    }
}
