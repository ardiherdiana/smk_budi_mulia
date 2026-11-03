<?php

namespace App\Console\Commands;

use App\Models\PortalFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportPortalUploads extends Command
{
    protected $signature = 'portal:import-uploads {source : Folder uploads sistem lama (portal_download/uploads)}';

    protected $description = 'Salin PDF portal lama ke penyimpanan privat sesuai tabel files (hanya PDF asli yang tercatat di DB)';

    public function handle(): int
    {
        $source = rtrim((string) $this->argument('source'), '/\\');
        if (! is_dir($source)) {
            $this->error("Folder tidak ditemukan: {$source}");

            return self::FAILURE;
        }

        $disk = Storage::disk('local');
        $copied = $missing = $invalid = 0;

        foreach (PortalFile::query()->orderBy('id')->cursor() as $file) {
            $name = basename((string) $file->path_file);
            $from = $source.DIRECTORY_SEPARATOR.$name;

            if (! is_file($from)) {
                $missing++;

                continue;
            }
            if (! str_starts_with((string) file_get_contents($from, false, null, 0, 5), '%PDF-')) {
                $invalid++;
                $this->warn("Bukan PDF, dilewati: {$name}");

                continue;
            }

            $disk->put('uploads/'.$name, fopen($from, 'r'));
            $copied++;
        }

        $this->info("{$copied} PDF disalin, {$missing} berkas hilang di sumber, {$invalid} bukan PDF.");
        $this->comment('File lain di folder sumber (mis. *.php, ALFA_DATA, index.html) sengaja TIDAK disalin.');

        return self::SUCCESS;
    }
}
