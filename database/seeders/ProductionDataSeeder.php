<?php

namespace Database\Seeders;

use App\Services\MediaMigrator;
use App\Services\MediaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengosongkan lalu memuat ulang 9 tabel produksi dari `database/seeders/data/production.php` (gitignored, berisi
 * data asli): tabel => ['kolom' => [...], 'baris' => [[...], ...]]. Tidak membaca dump SQL.
 * Skema dibuat oleh migration 2026_09_21_000001_create_production_schema.
 *
 *   php artisan migrate
 *   php artisan db:seed --class=ProductionDataSeeder
 *
 * Isi berkas itu berasal dari dump phpMyAdmin produksi (21 Sep 2026) yang base64-nya sudah diubah jadi file, dengan dua
 * pengecualian. Tiga kolom lama `users.password_plain`, `nama_file`, `path_file` tidak ditulis (sandi teks biasa tidak
 * boleh ada di kode). Tujuh baris sampah peninggalan penyerang/uji dibuang: postingan `$(curl ...)` dan `Test`,
 * `tentang_kami` "HACKET BY NEURA SEC" dan "test ttg" beserta media web shell-nya, guru_staff `test`, dan unggahan
 * `alfa.php`. Akun admin lama `gudang@system.internal` juga dibuang (3 berkasnya jadi tanpa pemilik, `user_id` NULL),
 * sehingga satu-satunya admin adalah admin@gmail.com. Foto/video berupa path ke `public/media`; berkas gambarnya TIDAK ada di kode, jadi salin folder
 * `public/media` dari mesin sumber. Path yang berkasnya belum ada dilaporkan sebagai peringatan.
 *
 * Aman dijalankan berulang (selalu kosongkan dulu).
 *
 * Perhatian: `users` ikut dikosongkan, termasuk baris pendamping akun Classroom (`<username>@akun.local`). Pakai pada
 * database yang baru dimigrasi, bukan pada database yang sudah berisi data Classroom.
 */
class ProductionDataSeeder extends Seeder
{
    /** Baris per pernyataan INSERT. */
    private const CHUNK = 100;

    /** @param  string|null  $berkas  Jalur berkas data; null = database/seeders/data/production.php. */
    public function __construct(private ?string $berkas = null) {}

    public function run(): void
    {
        $path = $this->berkas ?? database_path('seeders/data/production.php');
        abort_unless(is_file($path), 500, "Data produksi tidak ditemukan: {$path}");

        /** @var array<string, array{kolom: list<string>, baris: list<list<mixed>>}> $data */
        $data = require $path;

        Schema::disableForeignKeyConstraints();
        try {
            // Urutan induk -> anak mengikuti isi berkas.
            foreach ($data as $table => $isi) {
                $this->kosongkan($table);

                foreach (array_chunk($isi['baris'], self::CHUNK) as $baris) {
                    DB::table($table)->insert(array_map(fn (array $b) => array_combine($isi['kolom'], $b), $baris));
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->command?->info('Data produksi dimuat dari '.basename($path).'.');
        foreach ($data as $table => $isi) {
            $this->command?->line(sprintf('  %-20s %d baris', $table, count($isi['baris'])));
        }

        $hilang = $this->mediaHilang($data);
        if ($hilang !== []) {
            $this->command?->warn(sprintf(
                '%d berkas media belum ada di public/media (mis. %s). Salin folder public/media dari mesin sumber.',
                count($hilang),
                $hilang[0],
            ));
        }
    }

    /** Dipisah supaya tes bisa menggantinya (TRUNCATE memaksa commit dan merusak transaksi RefreshDatabase). */
    protected function kosongkan(string $table): void
    {
        DB::table($table)->truncate();
    }

    /**
     * Path media di data yang berkasnya tidak ada di disk `media`.
     *
     * @param  array<string, array{kolom: list<string>, baris: list<list<mixed>>}>  $data
     * @return list<string>
     */
    private function mediaHilang(array $data): array
    {
        $media = app(MediaService::class);
        $hilang = [];

        foreach (MediaMigrator::TARGETS as ['table' => $table, 'column' => $kolom]) {
            $indeks = array_search($kolom, $data[$table]['kolom'] ?? [], true);
            if ($indeks === false) {
                continue;
            }

            foreach ($data[$table]['baris'] as $baris) {
                $path = $baris[$indeks];
                if ($media->isPath($path) && ! $media->disk()->exists($path)) {
                    $hilang[] = $path;
                }
            }
        }

        return array_values(array_unique($hilang));
    }
}
