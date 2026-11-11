<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Supervisi Guru, persis format "INSTRUMEN OBSERVASI PEMBELAJARAN MENDALAM": jadwal + lembar per guru dan skor
 * 14 butir (butirnya dikunci di config/supervisi.php, tidak ada tabel butir). Konvensi tabel Classroom: id ULID string,
 * kolom camelCase, FK pengguna integer ke `profil_akun.id`. Tabel produksi tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        // guru = Penyusun Perencanaan, supervisor = Pemberi Umpan Balik, rombel = Kelas, tanggal = Tanggal Supervisi.
        // kelebihan/halDitingkatkan/rekomendasi = baris 5-7 dokumen; jumlahSkor = JUMLAH SCORE; totalSkor = TOTAL SCORE.
        Schema::create('supervisi', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('tahunAjaranId', 191);
            $table->integer('guruId');
            $table->integer('supervisorId');
            $table->integer('dibuatOlehId')->nullable();
            $table->string('mapelId', 191)->nullable();
            $table->string('rombelId', 191)->nullable();
            $table->date('tanggal');
            $table->enum('status', ['DIJADWALKAN', 'SELESAI', 'DIBATALKAN'])->default('DIJADWALKAN');
            $table->text('kelebihan')->nullable();
            $table->text('halDitingkatkan')->nullable();
            $table->text('rekomendasi')->nullable();
            $table->unsignedSmallInteger('jumlahSkor')->nullable();
            $table->decimal('totalSkor', 5, 2)->nullable();
            $table->dateTime('selesaiAt')->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->index(['tahunAjaranId', 'status'], 'supervisi_tahun_status_idx');
            $table->index('guruId', 'supervisi_guru_idx');
            $table->index('supervisorId', 'supervisi_supervisor_idx');
            $table->foreign('tahunAjaranId', 'supervisi_tahunAjaranId_fkey')
                ->references('id')->on('tahun_ajaran')->restrictOnDelete()->cascadeOnUpdate();
            // RESTRICT: akun yang punya riwayat supervisi ditolak dihapus (User::modulTerpakai), cukup dinonaktifkan.
            $table->foreign('guruId', 'supervisi_guruId_fkey')
                ->references('id')->on('profil_akun')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('supervisorId', 'supervisi_supervisorId_fkey')
                ->references('id')->on('profil_akun')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('dibuatOlehId', 'supervisi_dibuatOlehId_fkey')
                ->references('id')->on('profil_akun')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('mapelId', 'supervisi_mapelId_fkey')
                ->references('id')->on('mapel')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('rombelId', 'supervisi_rombelId_fkey')
                ->references('id')->on('rombel')->nullOnDelete()->cascadeOnUpdate();
        });

        // Skor per butir. `kategori` dan `uraian` disalin dari config saat lembar disiapkan, supaya hasil lama tetap
        // utuh bila teks instrumen di kode berubah.
        Schema::create('supervisi_skor', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('supervisiId', 191);
            $table->string('kategori', 100);
            $table->string('uraian', 500);
            $table->unsignedInteger('urutan')->default(0);
            $table->unsignedTinyInteger('skor')->nullable();
            $table->string('catatan', 500)->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->index(['supervisiId', 'urutan'], 'supervisi_skor_lembar_idx');
            $table->foreign('supervisiId', 'supervisi_skor_supervisiId_fkey')
                ->references('id')->on('supervisi')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisi_skor');
        Schema::dropIfExists('supervisi');
    }
};
