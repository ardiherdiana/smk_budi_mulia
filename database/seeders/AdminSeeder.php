<?php

namespace Database\Seeders;

use App\Models\Classroom\Akun;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Satu admin untuk semua modul (Website, SKL, Classroom): masuk dengan username `admin`.
 *
 * Login tanpa "@" mencari profil Classroom lokal lebih dulu dan baru bertanya ke basis data absensi bila tidak ada.
 * Tanpa profil `admin` lokal, login pertama "admin" malah menyalin admin absensi sebagai profil `admin`, lalu admin
 * produksi (admin@gmail.com) kebagian `admin-1`, sehingga ada dua admin. Seeder ini membuat profil lokalnya lebih dulu.
 *
 * - Ada admin dari data produksi (ProductionDataSeeder): ia diberi profil Classroom ADMIN. Sandinya tidak diubah
 *   (di data produksi `admin123`).
 * - Belum ada (basis data tanpa data produksi): dibuat akun admin baru dengan sandi awal `admin123`.
 *
 * Sandi awal itu hanya untuk pengembangan; ganti lewat halaman Profil sebelum dipakai di produksi.
 * Aman dijalankan ulang: profil yang sudah ada tidak diubah.
 */
class AdminSeeder extends Seeder
{
    public const USERNAME = 'admin';

    public const SANDI_AWAL = 'admin123';

    public function run(): void
    {
        $pengguna = User::where('role', 'admin')->where('email', 'not like', '%@akun.local')->orderBy('id')->first();

        if ($pengguna) {
            $akun = Akun::untukPengguna($pengguna);

            if ($akun?->username !== self::USERNAME) {
                $this->command?->warn(sprintf("Username '%s' sudah dipakai profil lain; admin %s memakai '%s'.", self::USERNAME, $pengguna->email, $akun?->username));
            }
        } elseif (! Akun::where('username', self::USERNAME)->exists()) {
            $akun = new Akun;
            $akun->forceFill([
                'username' => self::USERNAME,
                'name' => 'Admin',
                'role' => 'ADMIN',
                'password' => self::SANDI_AWAL,
                'is_active' => true,
            ])->save();
        }

        $this->command?->info(sprintf("Seed admin selesai: username '%s'.", self::USERNAME));
    }
}
