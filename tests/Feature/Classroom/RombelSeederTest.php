<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Rombel;
use Database\Seeders\RombelSeeder;
use Tests\TestCase;

class RombelSeederTest extends TestCase
{
    public function test_membuat_14_rombel_tanpa_wali_dengan_tingkat_dan_jurusan_dari_nama(): void
    {
        $this->seed(RombelSeeder::class);

        $this->assertSame(14, Rombel::count());
        $this->assertSame(0, Rombel::whereNotNull('waliKelasId')->count());

        $this->assertDatabaseHas('rombel', ['nama' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan' => 'TKJ']);
        $this->assertDatabaseHas('rombel', ['nama' => 'X RPL', 'tingkat' => 'X', 'jurusan' => 'RPL']);
        $this->assertDatabaseHas('rombel', ['nama' => 'XI AKT', 'tingkat' => 'XI', 'jurusan' => 'AKT']);
        $this->assertDatabaseHas('rombel', ['nama' => 'XII TKJ 3', 'tingkat' => 'XII', 'jurusan' => 'TKJ']);
        $this->assertSame([5, 5, 4], [
            Rombel::where('tingkat', 'X')->count(),
            Rombel::where('tingkat', 'XI')->count(),
            Rombel::where('tingkat', 'XII')->count(),
        ]);
    }

    public function test_dijalankan_ulang_tidak_menggandakan_dan_tidak_mengubah_rombel_yang_ada(): void
    {
        $guru = $this->akun('GURU', 'guru1');
        Rombel::create(['nama' => 'X TKJ 1', 'tingkat' => 'X', 'jurusan' => 'TKJ', 'waliKelasId' => $guru->id]);

        $this->seed(RombelSeeder::class);
        $this->seed(RombelSeeder::class);

        $this->assertSame(14, Rombel::count());
        $this->assertSame($guru->id, Rombel::where('nama', 'X TKJ 1')->value('waliKelasId'));
    }
}
