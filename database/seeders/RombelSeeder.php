<?php

namespace Database\Seeders;

use App\Models\Classroom\Rombel;
use Illuminate\Database\Seeder;

/**
 * Rombel (kelas/rombongan belajar) resmi, ditulis langsung di kode. Tingkat dan jurusan diambil dari namanya
 * ("XI TKJ 2" => XI, TKJ). Wali kelas sengaja belum diisi; admin mengisinya lewat halaman Rombel. Aman dijalankan ulang:
 * rombel yang sudah ada (beserta wali kelasnya) tidak diubah.
 */
class RombelSeeder extends Seeder
{
    /** @var list<string> */
    public const DAFTAR = [
        'X TKJ 1', 'X TKJ 2', 'X TKJ 3', 'X RPL', 'X AKT',
        'XI TKJ 1', 'XI TKJ 2', 'XI TKJ 3', 'XI RPL', 'XI AKT',
        'XII TKJ 1', 'XII TKJ 2', 'XII TKJ 3', 'XII AKT',
    ];

    /** Rombel bernama $nama; dibuat (tanpa wali kelas) bila belum ada. Dipakai juga oleh SiswaSeeder. */
    public static function buat(string $nama): Rombel
    {
        [$tingkat, $jurusan] = explode(' ', $nama);

        return Rombel::firstOrCreate(['nama' => $nama], ['tingkat' => $tingkat, 'jurusan' => $jurusan]);
    }

    public function run(): void
    {
        $dibuat = 0;
        foreach (self::DAFTAR as $nama) {
            $dibuat += self::buat($nama)->wasRecentlyCreated ? 1 : 0;
        }

        $this->command?->info(sprintf('Seed rombel selesai: %d rombel baru dari %d (wali kelas belum diisi).', $dibuat, count(self::DAFTAR)));
    }
}
