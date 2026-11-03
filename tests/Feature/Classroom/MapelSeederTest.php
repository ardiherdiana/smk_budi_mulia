<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Mapel;
use Database\Seeders\MapelSeeder;
use Tests\TestCase;

class MapelSeederTest extends TestCase
{
    public function test_daftar_mapel_unik_dan_tanpa_spasi_berlebih(): void
    {
        $this->assertCount(23, MapelSeeder::DAFTAR);
        $this->assertSame(MapelSeeder::DAFTAR, array_values(array_unique(MapelSeeder::DAFTAR)));

        foreach (MapelSeeder::DAFTAR as $nama) {
            $this->assertSame(trim(preg_replace('/\s+/', ' ', $nama)), $nama, "Nama mapel '{$nama}' punya spasi berlebih");
        }
    }

    public function test_seeder_mengisi_semua_mapel_tanpa_berkas_excel_dan_aman_diulang(): void
    {
        $this->seed(MapelSeeder::class);
        $this->seed(MapelSeeder::class);

        $this->assertSame(23, Mapel::count());
        $this->assertEqualsCanonicalizing(MapelSeeder::DAFTAR, Mapel::pluck('nama')->all());
        $this->assertDatabaseHas('mapel', ['nama' => 'Bhs. Inggris (Kebekerjaan)']);
    }
}
