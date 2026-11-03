<?php

namespace App\Console\Commands;

use App\Services\Classroom\AbsensiAccountService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('classroom:sinkron-guru')]
#[Description('Salin akun guru/admin/kepsek dari basis data absensi (baca-saja) ke profil Classroom, tanpa sandi')]
class SinkronAkunGuru extends Command
{
    public function handle(AbsensiAccountService $absensi): int
    {
        $hasil = $absensi->sinkronSemua();

        $this->info("Akun baru: {$hasil['dibuat']}, diperbarui: {$hasil['diperbarui']}.");
        foreach ($hasil['dilewati'] as $username) {
            $this->warn("Dilewati (username bentrok dengan akun Classroom lain): {$username}");
        }

        return self::SUCCESS;
    }
}
