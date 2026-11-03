<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Memindahkan foto/video dari database (base64) ke FILE, lalu mengubah kolomnya jadi string path.
 *
 * Urutan WAJIB (dijaga oleh migration): 1) toFiles(): decode tiap blob, tulis file, verifikasi ukurannya,
 * baru ganti isi kolom jadi path; 2) narrow(): ubah tipe kolom ke VARCHAR(500) HANYA bila semua isi sudah path.
 * Dengan begitu tidak ada data yang terpotong. Semuanya idempoten: aman dijalankan ulang.
 *
 * Kebalikannya (rollback): widen() lalu toBlobs() menanam file kembali sebagai data-URI.
 */
class MediaMigrator
{
    /**
     * `raw` = format lama tanpa awalan data-URI (principals.foto). `widen` = tipe asli kolom di produksi.
     *
     * @var list<array{table: string, column: string, kind: string, widen: string, raw?: bool}>
     */
    public const TARGETS = [
        ['table' => 'postingan', 'column' => 'image', 'kind' => 'post-image', 'widen' => 'LONGTEXT'],
        ['table' => 'postingan', 'column' => 'video_file', 'kind' => 'post-video', 'widen' => 'LONGTEXT'],
        ['table' => 'postingan_media', 'column' => 'file', 'kind' => 'post-media', 'widen' => 'LONGBLOB'],
        ['table' => 'tentang_kami_media', 'column' => 'file', 'kind' => 'about-media', 'widen' => 'LONGTEXT'],
        ['table' => 'jurusan', 'column' => 'image', 'kind' => 'major-image', 'widen' => 'LONGTEXT'],
        ['table' => 'guru_staff', 'column' => 'image', 'kind' => 'staff-image', 'widen' => 'LONGTEXT'],
        ['table' => 'principals', 'column' => 'foto', 'kind' => 'principal-photo', 'widen' => 'TEXT', 'raw' => true],
    ];

    public const PATH_COLUMN = 'VARCHAR(500) NULL DEFAULT NULL';

    /** Batas byte tipe kolom asli. LONGTEXT/LONGBLOB (4 GB) tidak perlu dicek. */
    private const WIDENED_LIMITS = ['TEXT' => 65535];

    /** @param list<array{table: string, column: string, kind: string, widen: string, raw?: bool}> $targets */
    public function __construct(private MediaService $media, private array $targets = self::TARGETS) {}

    /**
     * Blob -> file. Mengembalikan laporan per kolom.
     *
     * @return list<array{target: string, converted: int, already: int, emptied: int, rejected: int, bytes: int}>
     */
    public function toFiles(bool $dryRun = false): array
    {
        @ini_set('memory_limit', '1G');
        $report = [];

        foreach ($this->targets as $target) {
            ['table' => $table, 'column' => $column, 'kind' => $kind] = $target;
            $row = ['target' => "{$table}.{$column}", 'converted' => 0, 'already' => 0, 'emptied' => 0, 'rejected' => 0, 'bytes' => 0];

            $ids = DB::table($table)->whereNotNull($column)->orderBy('id')->pluck('id');
            foreach ($ids as $id) {
                $value = DB::table($table)->where('id', $id)->value($column);

                if ($value === null) {
                    continue;
                }
                if ($value === '') {
                    $row['emptied']++;
                    $dryRun || DB::table($table)->where('id', $id)->update([$column => null]);

                    continue;
                }
                if ($this->media->isPath($value)) {
                    $row['already']++;

                    continue;
                }

                $decoded = $this->media->decode($value);
                if ($decoded === null) {
                    // Bukan gambar/video yang sah (mis. HTML/SVG/PHP yang diselipkan). Jangan hilang diam-diam:
                    // simpan mentahnya di area privat, lalu kosongkan kolomnya.
                    $row['rejected']++;
                    if (! $dryRun) {
                        Storage::disk('local')->put("media-rejected/{$table}-{$id}-{$column}.txt", (string) $value);
                        DB::table($table)->where('id', $id)->update([$column => null]);
                    }

                    continue;
                }

                $row['converted']++;
                $row['bytes'] += strlen($decoded['bytes']);
                if ($dryRun) {
                    continue;
                }

                $name = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $id).'-'.substr(sha1($decoded['bytes']), 0, 8);
                $path = $this->media->storeBytes($decoded['bytes'], $decoded['mime'], $kind, $name);
                DB::table($table)->where('id', $id)->update([$column => $path]);
            }

            $report[] = $row;
        }

        return $report;
    }

    /** Jumlah baris yang isinya BUKAN path sah (base64 lama, dll). Harus 0 sebelum narrow(). */
    public function nonPathRows(): int
    {
        $bad = 0;
        foreach ($this->targets as ['table' => $table, 'column' => $column]) {
            $bad += DB::table($table)->whereNotNull($column)->whereRaw("LENGTH(`{$column}`) > 200")->count();

            foreach (DB::table($table)->whereNotNull($column)->whereRaw("LENGTH(`{$column}`) <= 200")->pluck($column) as $value) {
                if (! $this->media->isPath((string) $value)) {
                    $bad++;
                }
            }
        }

        return $bad;
    }

    /** Ubah tipe kolom jadi VARCHAR(500). Menolak bila masih ada isi yang bukan path (mencegah data terpotong). */
    public function narrow(): void
    {
        if (($bad = $this->nonPathRows()) > 0) {
            throw new RuntimeException("Masih ada {$bad} isi kolom media yang belum jadi file. Jalankan: php artisan media:migrate-to-files");
        }

        foreach ($this->targets as ['table' => $table, 'column' => $column]) {
            DB::statement('ALTER TABLE `'.$table.'` MODIFY `'.$column.'` '.self::PATH_COLUMN);
        }
    }

    /** Kembalikan tipe kolom ke tipe asli produksi (LONGTEXT/LONGBLOB/TEXT). */
    public function widen(): void
    {
        foreach ($this->targets as ['table' => $table, 'column' => $column, 'widen' => $type]) {
            DB::statement('ALTER TABLE `'.$table.'` MODIFY `'.$column.'` '.$type.' NULL DEFAULT NULL');
        }
    }

    /**
     * File -> base64 lagi (untuk rollback). Kolom harus sudah di-widen(). File yang hilang dikosongkan
     * dan dilaporkan.
     *
     * @return list<array{target: string, embedded: int, missing: int, oversize: int}>
     */
    public function toBlobs(): array
    {
        @ini_set('memory_limit', '1G');
        $report = [];

        foreach ($this->targets as $target) {
            ['table' => $table, 'column' => $column] = $target;
            $row = ['target' => "{$table}.{$column}", 'embedded' => 0, 'missing' => 0, 'oversize' => 0];
            $limit = self::WIDENED_LIMITS[$target['widen']] ?? PHP_INT_MAX;

            foreach (DB::table($table)->whereNotNull($column)->orderBy('id')->pluck($column, 'id') as $id => $value) {
                if (! $this->media->isPath((string) $value)) {
                    continue;
                }

                if (! $this->media->disk()->exists($value)) {
                    $row['missing']++;
                    DB::table($table)->where('id', $id)->update([$column => null]);

                    continue;
                }

                $bytes = $this->media->disk()->get($value);
                $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
                $encoded = base64_encode($bytes);
                $raw = $target['raw'] ?? false;
                if ($raw && strlen($encoded) > $limit) {
                    // Foto yang dulu terpotong tepat di batas TEXT (65535) kembali muat bila padding `=` dibuang.
                    $encoded = rtrim($encoded, '=');
                }
                $value = $raw ? $encoded : "data:{$mime};base64,{$encoded}";

                if (strlen($value) > $limit) {
                    $row['oversize']++;
                    DB::table($table)->where('id', $id)->update([$column => null]);

                    continue;
                }

                DB::table($table)->where('id', $id)->update([$column => $value]);
                $row['embedded']++;
            }

            $report[] = $row;
        }

        return $report;
    }
}
