<?php

namespace Tests\Feature;

use Database\Seeders\ClassroomSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductionDataSeeder;
use Illuminate\Database\Seeder;
use Tests\TestCase;

/** Urutan db:seed saja yang diuji; seeder aslinya diganti penghitung (ProductionDataSeeder memakai TRUNCATE yang merusak transaksi tes). */
class DatabaseSeederTest extends TestCase
{
    /** @var list<string> */
    private array $dipanggil = [];

    private function gantiSeeder(): void
    {
        $catat = function (string $nama) {
            return new class($nama, $this->dipanggil) extends Seeder
            {
                public function __construct(private string $nama, private array &$log) {}

                public function run(): void
                {
                    $this->log[] = $this->nama;
                }
            };
        };

        $this->app->bind(ProductionDataSeeder::class, fn () => $catat('produksi'));
        $this->app->bind(ClassroomSeeder::class, fn () => $catat('classroom'));
    }

    public function test_database_kosong_memuat_data_produksi_dulu_baru_classroom(): void
    {
        $this->gantiSeeder();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['produksi', 'classroom'], $this->dipanggil);
    }

    public function test_database_berisi_users_melewati_data_produksi(): void
    {
        $this->gantiSeeder();
        $this->admin();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['classroom'], $this->dipanggil);
    }
}
