<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gabungan absensi: `profil_akun` jadi profil semua akun sekolah (tabel `users` produksi tetap dikunci).
        // - `jabatan`: jabatan pegawai untuk SPPD (dulu `users.jabatan` absensi).
        // - `remember_token`: "ingat saya" untuk staf; `users` produksi tidak punya kolomnya.
        // - peran `STAF`: pegawai yang hanya memakai SPPD (dulu `users.role` NULL di absensi).
        Schema::table('profil_akun', function (Blueprint $table) {
            $table->string('jabatan')->nullable()->after('name');
            $table->string('remember_token', 100)->nullable()->after('is_active');
        });

        DB::statement("ALTER TABLE `profil_akun` MODIFY COLUMN `role` ENUM('ADMIN', 'KEPSEK', 'GURU', 'SISWA', 'STAF') NOT NULL");
    }

    public function down(): void
    {
        DB::table('profil_akun')->where('role', 'STAF')->update(['role' => 'GURU']);
        DB::statement("ALTER TABLE `profil_akun` MODIFY COLUMN `role` ENUM('ADMIN', 'KEPSEK', 'GURU', 'SISWA') NOT NULL");

        Schema::table('profil_akun', function (Blueprint $table) {
            $table->dropColumn(['jabatan', 'remember_token']);
        });
    }
};
