<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Profil Classroom untuk satu baris `users` (tabel produksi yang dikunci): `id` SAMA dengan `users.id`.
        // Username, nama, dan peran Classroom ada di sini; sandi tetap di `users.password`.
        Schema::create('profil_akun', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('username', 100);
            $table->string('name')->nullable();
            $table->enum('role', ['ADMIN', 'KEPSEK', 'GURU', 'SISWA']);
            $table->string('absensi_user_id', 191)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique('username', 'profil_akun_username_key');
            $table->unique('absensi_user_id', 'profil_akun_absensi_key');
            $table->foreign('id', 'profil_akun_id_fkey')->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('nama', 9);
            $table->enum('semester', ['GANJIL', 'GENAP']);
            $table->boolean('aktif')->default(false);
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique(['nama', 'semester'], 'tahun_ajaran_nama_semester_key');
        });

        Schema::create('rombel', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('nama', 50);
            $table->enum('tingkat', ['X', 'XI', 'XII']);
            $table->string('jurusan', 20);
            $table->integer('waliKelasId')->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique('nama', 'rombel_nama_key');
            $table->foreign('waliKelasId', 'rombel_waliKelasId_fkey')
                ->references('id')->on('profil_akun')->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('siswa', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->integer('userId');
            $table->string('rombelId', 191);
            $table->string('nis', 20)->nullable();
            $table->string('nama');
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique('userId', 'siswa_userId_key');
            $table->unique('nis', 'siswa_nis_key');
            $table->foreign('userId', 'siswa_userId_fkey')
                ->references('id')->on('profil_akun')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('rombelId', 'siswa_rombelId_fkey')
                ->references('id')->on('rombel')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('mapel', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('nama', 100);
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique('nama', 'mapel_nama_key');
        });

        // Ruang kelas: satu mapel yang diajar satu guru untuk satu rombel pada satu tahun ajaran. Siswa otomatis
        // terdaftar lewat rombelnya (tidak ada kode gabung).
        Schema::create('kelas', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('rombelId', 191);
            $table->string('mapelId', 191);
            $table->integer('guruId');
            $table->string('tahunAjaranId', 191);
            $table->string('deskripsi', 500)->nullable();
            $table->boolean('diarsipkan')->default(false);
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique(['rombelId', 'mapelId', 'tahunAjaranId'], 'kelas_rombel_mapel_tahun_key');
            $table->foreign('rombelId', 'kelas_rombelId_fkey')
                ->references('id')->on('rombel')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('mapelId', 'kelas_mapelId_fkey')
                ->references('id')->on('mapel')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('guruId', 'kelas_guruId_fkey')
                ->references('id')->on('profil_akun')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('tahunAjaranId', 'kelas_tahunAjaranId_fkey')
                ->references('id')->on('tahun_ajaran')->restrictOnDelete()->cascadeOnUpdate();
        });

        // Satu tabel untuk semua isi stream; kolom khusus tugas/ujian null untuk tipe lain.
        Schema::create('classroom_posts', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('kelasId', 191);
            $table->enum('tipe', ['PENGUMUMAN', 'MATERI', 'TUGAS', 'UJIAN']);
            $table->string('judul')->nullable();
            $table->text('isi')->nullable();
            $table->dateTime('deadline', 3)->nullable();
            $table->unsignedSmallInteger('poinMaks')->nullable();
            $table->boolean('izinTerlambat')->default(true);
            $table->unsignedSmallInteger('durasiMenit')->nullable();
            $table->dateTime('mulaiAt', 3)->nullable();
            $table->dateTime('selesaiAt', 3)->nullable();
            $table->boolean('acakSoal')->default(false);
            $table->boolean('tampilkanNilai')->default(true);
            $table->integer('dibuatOleh');
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->index(['kelasId', 'createdAt'], 'posts_kelasId_createdAt_idx');
            $table->foreign('kelasId', 'posts_kelasId_fkey')
                ->references('id')->on('kelas')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('dibuatOleh', 'posts_dibuatOleh_fkey')
                ->references('id')->on('profil_akun')->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('komentar', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('postId', 191);
            $table->integer('userId');
            $table->text('isi');
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->index(['postId', 'createdAt'], 'komentar_postId_createdAt_idx');
            $table->foreign('postId', 'komentar_postId_fkey')
                ->references('id')->on('classroom_posts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('userId', 'komentar_userId_fkey')
                ->references('id')->on('profil_akun')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('pengumpulan', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('postId', 191);
            $table->string('siswaId', 191);
            $table->text('teks')->nullable();
            $table->enum('status', ['DIKUMPULKAN', 'DINILAI'])->default('DIKUMPULKAN');
            $table->boolean('terlambat')->default(false);
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->dateTime('submittedAt', 3);
            $table->dateTime('gradedAt', 3)->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique(['postId', 'siswaId'], 'pengumpulan_postId_siswaId_key');
            $table->foreign('postId', 'pengumpulan_postId_fkey')
                ->references('id')->on('classroom_posts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('siswaId', 'pengumpulan_siswaId_fkey')
                ->references('id')->on('siswa')->cascadeOnDelete()->cascadeOnUpdate();
        });

        // Berkas atau tautan yang menempel ke satu post (materi/tugas guru) atau satu pengumpulan (hasil kerja siswa).
        Schema::create('lampiran', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('postId', 191)->nullable();
            $table->string('pengumpulanId', 191)->nullable();
            $table->enum('tipe', ['FILE', 'LINK']);
            $table->string('nama');
            $table->string('path', 500)->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('ukuran')->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->foreign('postId', 'lampiran_postId_fkey')
                ->references('id')->on('classroom_posts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('pengumpulanId', 'lampiran_pengumpulanId_fkey')
                ->references('id')->on('pengumpulan')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('soal', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('postId', 191);
            $table->unsignedSmallInteger('urut');
            $table->enum('tipe', ['PG', 'ESSAY']);
            $table->text('teks');
            $table->decimal('poin', 5, 2)->default(1);
            $table->json('opsi')->nullable();
            // Indeks opsi yang benar (0, 1, 2, ...) sebagai string; null untuk essay.
            $table->string('kunci', 10)->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->index(['postId', 'urut'], 'soal_postId_urut_idx');
            $table->foreign('postId', 'soal_postId_fkey')
                ->references('id')->on('classroom_posts')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('percobaan', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('postId', 191);
            $table->string('siswaId', 191);
            $table->dateTime('mulaiAt', 3);
            $table->dateTime('batasAt', 3);
            $table->dateTime('selesaiAt', 3)->nullable();
            $table->decimal('skorOtomatis', 6, 2)->default(0);
            $table->decimal('skorManual', 6, 2)->nullable();
            $table->enum('status', ['BERJALAN', 'SELESAI', 'DINILAI'])->default('BERJALAN');
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique(['postId', 'siswaId'], 'percobaan_postId_siswaId_key');
            $table->foreign('postId', 'percobaan_postId_fkey')
                ->references('id')->on('classroom_posts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('siswaId', 'percobaan_siswaId_fkey')
                ->references('id')->on('siswa')->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('jawaban', function (Blueprint $table) {
            $table->string('id', 191)->primary();
            $table->string('percobaanId', 191);
            $table->string('soalId', 191);
            $table->text('jawaban')->nullable();
            $table->decimal('skor', 5, 2)->nullable();
            $table->dateTime('createdAt', 3)->useCurrent();
            $table->dateTime('updatedAt', 3);

            $table->unique(['percobaanId', 'soalId'], 'jawaban_percobaanId_soalId_key');
            $table->foreign('percobaanId', 'jawaban_percobaanId_fkey')
                ->references('id')->on('percobaan')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('soalId', 'jawaban_soalId_fkey')
                ->references('id')->on('soal')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        foreach (['jawaban', 'percobaan', 'soal', 'lampiran', 'pengumpulan', 'komentar', 'classroom_posts', 'kelas', 'mapel', 'siswa', 'rombel', 'tahun_ajaran', 'profil_akun'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
