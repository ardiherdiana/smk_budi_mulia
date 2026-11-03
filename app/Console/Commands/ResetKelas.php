<?php

namespace App\Console\Commands;

use App\Models\Classroom\Jawaban;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Komentar;
use App\Models\Classroom\Lampiran;
use App\Models\Classroom\Pengumpulan;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Soal;
use App\Services\Classroom\PostCleaner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('classroom:reset-kelas {--dry-run : Hanya tampilkan jumlah yang akan dihapus} {--force : Hapus tanpa bertanya}')]
#[Description('Hapus semua ruang kelas beserta forum, tugas, dan nilainya. Akun, rombel, siswa, mapel, dan tahun ajaran tetap')]
class ResetKelas extends Command
{
    public function handle(PostCleaner $cleaner): int
    {
        $berkas = count(Storage::disk('local')->files('lampiran'));

        $this->table(['Data', 'Jumlah'], [
            ['Ruang kelas', Kelas::count()],
            ['Postingan forum (pengumuman, materi, tugas, ujian)', Post::count()],
            ['Komentar', Komentar::count()],
            ['Pengumpulan tugas', Pengumpulan::count()],
            ['Lampiran berkas / tautan', Lampiran::where('tipe', 'FILE')->count().' / '.Lampiran::where('tipe', 'LINK')->count()],
            ['Soal ujian', Soal::count()],
            ['Percobaan ujian (nilai)', Percobaan::count()],
            ['Jawaban ujian', Jawaban::count()],
            ['Berkas di folder lampiran', $berkas],
        ]);
        $this->line('Tetap utuh: akun (admin, guru, siswa), rombel, siswa, mapel, dan tahun ajaran.');

        if ($this->option('dry-run')) {
            $this->info('Dry-run selesai, tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->input->isInteractive()) {
                $this->error('Tanpa terminal interaktif, jalankan dengan --force.');

                return self::FAILURE;
            }
            if (! $this->confirm('Hapus semua data di atas? Tindakan ini tidak bisa dibatalkan', false)) {
                $this->line('Dibatalkan, tidak ada yang dihapus.');

                return self::SUCCESS;
            }
        }

        // PostCleaner menghapus berkas lampiran lebih dulu, lalu barisnya; sisanya ikut terhapus lewat cascade dari kelas.
        $dihapus = 0;
        foreach (Kelas::all() as $kelas) {
            $cleaner->hapusKelas($kelas);
            $dihapus++;
        }

        $this->info("Selesai: {$dihapus} ruang kelas beserta isinya dihapus.");

        $sisa = count(Storage::disk('local')->files('lampiran'));
        if ($sisa > 0) {
            $this->warn("Masih ada {$sisa} berkas di storage/app/private/lampiran yang tidak tercatat di database. Periksa dan hapus manual bila perlu.");
        }

        $this->line('Buat ulang ruang kelas dengan: php artisan db:seed --class=KelasSeeder');

        return self::SUCCESS;
    }
}
