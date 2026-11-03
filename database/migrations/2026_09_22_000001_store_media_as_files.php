<?php

use App\Services\MediaMigrator;
use Illuminate\Database\Migrations\Migration;

/**
 * Foto & video berhenti disimpan sebagai base64 di database.
 *
 * up():   1) decode setiap blob jadi file di disk `media` (public/media) dan ganti isi kolom jadi path,
 *         2) baru ubah tipe 7 kolom (postingan.image/video_file, postingan_media.file,
 *            tentang_kami_media.file, jurusan.image, guru_staff.image, principals.foto) jadi VARCHAR(500).
 *         Bila ada satu saja isi yang gagal jadi file, migration berhenti SEBELUM mengubah tipe kolom,
 *         jadi tidak ada data yang terpotong. Aman diulang.
 * down(): kembalikan tipe kolom asli, lalu tanam isi file kembali sebagai base64 (file di public/media tidak dihapus).
 *
 * Sebelum menjalankan di produksi: backup database (mysqldump).
 */
return new class extends Migration
{
    public function up(): void
    {
        $migrator = app(MediaMigrator::class);

        $migrator->toFiles();
        $migrator->narrow();
    }

    public function down(): void
    {
        $migrator = app(MediaMigrator::class);

        $migrator->widen();
        $migrator->toBlobs();
    }
};
