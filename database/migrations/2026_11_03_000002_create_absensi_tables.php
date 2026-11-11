<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul absensi guru + SPPD (digabung dari proyek `absensi`, 2026-11-03).
 *
 * Skema AKHIR absensi (31 migrasi absensi dipadatkan jadi satu), sama kolom per kolom dengan absensi, kecuali:
 * - tidak ada tabel `users` absensi: akun memakai `users` + `profil_akun` (lihat App\Models\User);
 * - 9 kolom yang menunjuk pengguna bertipe `int` dan merujuk `users.id` (di absensi ULID string);
 * - tabel sisa SPPD yang tidak dipakai (`sppd_sessions`, `sppd_cache*`, `sppd_jobs`, `sppd_job_batches`,
 *   `sppd_failed_jobs`) tidak dibuat: sesi/cache memakai driver file dan antrean `sync`.
 * - notifikasi semua modul satu tabel per akun `notifikasi` (bentuk notifikasi database Laravel, sama dengan
 *   `sppd_notifications` absensi); tabel `notifications` absensi (status dibaca bersama) tidak dibuat lagi.
 * Kolom polimorfik spatie (`model_has_roles.model_id`) dan `notifikasi.notifiable_id` tetap varchar seperti di
 * absensi; isinya id `users` dalam bentuk teks. Data lama dipindah oleh `php artisan gabung:impor-absensi`.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- Absensi ------------------------------------------------------------------------------------------
        Schema::create('settings', function (Blueprint $table) {
            $table->integer('id')->default(1)->primary();
            $table->string('namaSekolah', 191)->default('SMK Budi Mulia Karawang');
            $table->dateTime('updatedAt', 3);
        });

        Schema::create('jadwal_hari', function (Blueprint $table) {
            $table->integer('hari')->primary();
            $table->boolean('aktif')->default(true);
            $table->string('jamMulaiAbsen', 191)->default('07:00');
            $table->string('jamMasuk', 191)->default('07:00');
            $table->string('batasTelat', 191)->default('07:15');
            $table->string('jamPulang', 191)->default('15:30');
            $table->string('jamBriefing', 191)->nullable();
            $table->string('jamSelesaiBriefing', 191)->nullable();
            $table->dateTime('updatedAt', 3);
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->date('tanggal');
            $table->string('keterangan', 191);
            $table->dateTime('createdAt', 3)->useCurrent();

            $table->unique('tanggal', 'holidays_tanggal_key');
        });

        Schema::create('guru', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->integer('userId');
            $table->string('nama', 191);
            $table->string('noHp', 191)->nullable();
            $table->string('mapel', 191)->nullable();
            $table->string('fotoUrl', 191)->nullable();
            $table->string('qrToken', 191);
            $table->boolean('aktif')->default(true);
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique('userId', 'guru_userId_key');
            $table->unique('qrToken', 'guru_qrToken_key');
            $table->foreign('userId', 'guru_userId_fkey')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('attendance', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('guruId', 191);
            $table->date('tanggal');
            $table->dateTime('jamMasuk', 3)->nullable();
            $table->dateTime('jamPulang', 3)->nullable();
            $table->enum('statusMasuk', ['HADIR', 'TELAT', 'ALPA', 'IZIN', 'SAKIT'])->nullable();
            $table->enum('statusPulang', ['HADIR', 'TELAT', 'ALPA', 'IZIN', 'SAKIT'])->nullable();
            $table->string('catatan', 191)->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique(['guruId', 'tanggal'], 'attendance_guruId_tanggal_key');
            $table->foreign('guruId', 'attendance_guruId_fkey')->references('id')->on('guru')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('briefing_attendance', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('guruId', 191);
            $table->date('tanggal');
            $table->dateTime('waktu', 3)->nullable();
            $table->enum('status', ['HADIR', 'TELAT', 'IZIN', 'SAKIT', 'ALPA'])->nullable();
            $table->string('catatan', 191)->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3)->nullable();

            $table->unique(['guruId', 'tanggal'], 'briefing_attendance_guruId_tanggal_key');
            $table->foreign('guruId', 'briefing_attendance_guruId_fkey')->references('id')->on('guru')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('guruId', 191);
            $table->enum('jenis', ['IZIN', 'SAKIT']);
            $table->date('tanggalMulai');
            $table->date('tanggalSelesai');
            $table->text('alasan');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->string('reviewedBy', 191)->nullable();
            $table->dateTime('reviewedAt', 3)->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);
            $table->string('lampiranUrl', 191)->nullable();

            $table->foreign('guruId', 'leave_requests_guruId_fkey')->references('id')->on('guru')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->integer('userId');
            $table->string('endpoint', 500);
            $table->string('p256dh', 255);
            $table->string('auth', 255);
            $table->dateTime('createdAt', 3)->useCurrent();

            $table->foreign('userId', 'push_subscriptions_userId_fkey')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });

        // ---- spatie/laravel-permission (role SPPD) ------------------------------------------------------------
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('guard_name', 191);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('guard_name', 191);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type', 191);
            $table->string('model_id', 191);
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type', 191);
            $table->string('model_id', 191);
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        // ---- SPPD ---------------------------------------------------------------------------------------------
        Schema::create('pengajuan_sppds', function (Blueprint $table) {
            $table->id();
            $table->integer('pemohon_id');
            $table->string('tujuan', 191);
            $table->text('maksud');
            $table->string('alat_angkutan', 40)->nullable();
            $table->string('keterangan', 70)->nullable();
            $table->date('tanggal_berangkat');
            $table->time('jam_berangkat');
            $table->date('tanggal_kembali');
            $table->time('jam_kembali');
            $table->string('status', 191)->default('draft');
            $table->string('undangan_path', 191)->nullable();
            $table->text('catatan_kepsek')->nullable();
            $table->integer('disetujui_oleh')->nullable();
            $table->timestamp('disetujui_at')->nullable();
            $table->timestamp('ditolak_at')->nullable();
            $table->timestamps();

            $table->foreign('pemohon_id', 'pengajuan_sppds_pemohon_id_fkey')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('disetujui_oleh', 'pengajuan_sppds_disetujui_oleh_fkey')->references('id')->on('users');
        });

        Schema::create('sppds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_sppds')->cascadeOnDelete();
            $table->string('nomor_sppd', 191)->unique();
            $table->string('akun_anggaran', 100)->nullable();
            $table->integer('diterbitkan_oleh');
            $table->timestamps();

            $table->foreign('diterbitkan_oleh', 'sppds_diterbitkan_oleh_fkey')->references('id')->on('users');
        });

        Schema::create('pencairans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_sppds')->cascadeOnDelete();
            $table->string('jenis', 191);
            $table->decimal('jumlah', 15, 2);
            $table->date('tanggal');
            $table->integer('dicairkan_oleh');
            $table->string('keterangan', 191)->nullable();
            $table->timestamps();

            $table->foreign('dicairkan_oleh', 'pencairans_dicairkan_oleh_fkey')->references('id')->on('users');
        });

        Schema::create('log_audits', function (Blueprint $table) {
            $table->id();
            $table->string('entitas_terkait', 191);
            $table->unsignedBigInteger('entitas_id');
            $table->integer('user_id')->nullable();
            $table->string('aksi', 191);
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entitas_terkait', 'entitas_id']);
            $table->foreign('user_id', 'log_audits_user_id_fkey')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('pengajuan_pengikut', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('pengajuan_sppds')->cascadeOnDelete();
            $table->integer('user_id');

            $table->unique(['pengajuan_id', 'user_id']);
            $table->foreign('user_id', 'pengajuan_pengikut_user_id_fkey')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('pengaturans', function (Blueprint $table) {
            $table->string('kunci', 191)->primary();
            $table->text('nilai')->nullable();
            $table->timestamps();
        });

        // Notifikasi semua modul (absensi, SPPD, Supervisi), satu baris per penerima. Dihapus 30 hari setelah dibuat
        // (App\Models\Notifikasi::prunable).
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 191);
            $table->string('notifiable_type', 191);
            $table->string('notifiable_id', 191);
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id'], 'notifikasi_notifiable_type_id_index');
        });

        Schema::create('laporan_perjalanans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->unique()->constrained('pengajuan_sppds')->cascadeOnDelete();
            $table->integer('ditulis_oleh');
            $table->text('ringkasan');
            $table->timestamps();

            $table->foreign('ditulis_oleh', 'laporan_perjalanans_ditulis_oleh_fkey')->references('id')->on('users');
        });

        Schema::create('laporan_perjalanan_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporan_perjalanans')->cascadeOnDelete();
            $table->string('path', 191);
            $table->string('nama_asli', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'laporan_perjalanan_fotos', 'laporan_perjalanans', 'notifikasi', 'pengaturans', 'pengajuan_pengikut',
            'log_audits', 'pencairans', 'sppds', 'pengajuan_sppds',
            'role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions',
            'push_subscriptions', 'leave_requests', 'briefing_attendance', 'attendance', 'guru',
            'holidays', 'jadwal_hari', 'settings',
        ] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }
};
