<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log aktivitas bersama semua modul: tambah/ubah/hapus data dan masuk/keluar/gagal masuk akun. Dihapus 30 hari setelah
 * dibuat (App\Models\LogAktivitas::prunable). Riwayat persetujuan SPPD tetap di `log_audits` dan tidak ikut dihapus.
 * Konvensi Classroom: kolom camelCase. `userId` tanpa FK supaya log tetap terbaca walau akunnya sudah dihapus
 * (`namaPengguna` menyimpan namanya saat itu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->integer('userId')->nullable();
            $table->string('namaPengguna', 191)->nullable();
            $table->string('modul', 30);
            $table->string('aksi', 20);
            $table->string('entitas', 100)->nullable();
            $table->string('entitasTipe', 191)->nullable();
            $table->string('entitasId', 191)->nullable();
            $table->string('deskripsi', 500);
            $table->json('perubahan')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('userAgent', 500)->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();

            $table->index('createdAt', 'log_aktivitas_createdAt_idx');
            $table->index(['modul', 'createdAt'], 'log_aktivitas_modul_createdAt_idx');
            $table->index('userId', 'log_aktivitas_userId_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
    }
};
