<?php

namespace App\Console\Commands;

use App\Services\MediaMigrator;
use Illuminate\Console\Command;

class MigrateMediaToFiles extends Command
{
    protected $signature = 'media:migrate-to-files {--dry-run : Hanya tampilkan rencana, tidak menulis apa pun}';

    protected $description = 'Ubah foto/video base64 di database menjadi file di public/media dan isi kolomnya dengan path';

    public function handle(MediaMigrator $migrator): int
    {
        $dry = (bool) $this->option('dry-run');
        $report = $migrator->toFiles($dry);

        $this->table(
            ['Kolom', $dry ? 'Akan dikonversi' : 'Dikonversi', 'Sudah path', 'Kosong', 'Ditolak', 'Ukuran file (MB)'],
            array_map(fn ($r) => [$r['target'], $r['converted'], $r['already'], $r['emptied'], $r['rejected'], number_format($r['bytes'] / 1048576, 1)], $report)
        );

        if (array_sum(array_column($report, 'rejected')) > 0) {
            $this->warn('"Ditolak" = isi bukan gambar/video sah; salinan mentahnya ada di storage/app/private/media-rejected/.');
        }
        $this->line($dry ? 'Dry-run selesai, tidak ada yang diubah.' : 'Selesai. Untuk mengubah tipe kolom: php artisan migrate');

        return self::SUCCESS;
    }
}
