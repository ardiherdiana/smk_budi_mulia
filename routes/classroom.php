<?php

use App\Http\Controllers\Classroom\Admin\GuruController;
use App\Http\Controllers\Classroom\Admin\MapelController;
use App\Http\Controllers\Classroom\Admin\RombelController;
use App\Http\Controllers\Classroom\Admin\SiswaController;
use App\Http\Controllers\Classroom\Admin\TahunAjaranController;
use App\Http\Controllers\Classroom\AgendaController;
use App\Http\Controllers\Classroom\KelasController;
use App\Http\Controllers\Classroom\KomentarController;
use App\Http\Controllers\Classroom\KoreksiController;
use App\Http\Controllers\Classroom\LampiranController;
use App\Http\Controllers\Classroom\NilaiUjianController;
use App\Http\Controllers\Classroom\PengumpulanController;
use App\Http\Controllers\Classroom\PostController;
use App\Http\Controllers\Classroom\SoalController;
use App\Http\Controllers\Classroom\UjianController;
use Illuminate\Support\Facades\Route;

/*
| Modul Classroom (ruang kelas, tugas, ujian, nilai). Dimuat dari routes/web.php di dalam grup `auth` + `no-store`.
| `peran:` memeriksa peran profil Classroom (lihat RequirePeran), bukan `users.role`.
*/
Route::prefix('classroom')->group(function () {
    // Semua peran: akses ke ruang kelas dan isinya diperiksa per ruang kelas di controller (Kelas::dapatDiakses).
    Route::middleware('peran:ADMIN,KEPSEK,GURU,SISWA')->group(function () {
        Route::get('/kelas', [KelasController::class, 'index'])->name('classroom.kelas');
        Route::get('/kelas/{kelas}', [KelasController::class, 'show']);
        Route::get('/kelas/{kelas}/tugas', [KelasController::class, 'tugas']);
        Route::get('/kelas/{kelas}/anggota', [KelasController::class, 'anggota']);
        Route::get('/kelas/{kelas}/nilai', [KelasController::class, 'nilai']);

        Route::get('/tugas', [AgendaController::class, 'tugas']);
        Route::get('/kalender', [AgendaController::class, 'kalender']);

        Route::get('/post/{post}', [PostController::class, 'show']);
        Route::post('/post/{post}/komentar', [KomentarController::class, 'store']);
        Route::delete('/komentar/{komentar}', [KomentarController::class, 'destroy']);

        Route::get('/lampiran/{lampiran}', [LampiranController::class, 'show']);
        Route::delete('/lampiran/{lampiran}', [LampiranController::class, 'destroy']);

        // Profil Classroom lama -> modul Profil bersama (routes/web.php).
        Route::redirect('/profil', '/profil');
    });

    // Guru pengajar dan admin: kelola ruang kelas, materi, tugas, ujian, dan penilaian.
    Route::middleware('peran:ADMIN,GURU')->group(function () {
        Route::post('/kelas', [KelasController::class, 'store']);
        Route::patch('/kelas/{kelas}', [KelasController::class, 'update']);
        Route::post('/kelas/{kelas}/arsip', [KelasController::class, 'arsip']);
        Route::delete('/kelas/{kelas}', [KelasController::class, 'destroy']);

        Route::post('/kelas/{kelas}/post', [PostController::class, 'store']);
        // Formulir dengan berkas memakai POST + _method=PATCH (multipart tidak didukung PATCH murni).
        Route::patch('/post/{post}', [PostController::class, 'update']);
        Route::delete('/post/{post}', [PostController::class, 'destroy']);
        Route::post('/post/{post}/token', [PostController::class, 'tokenBaru']);

        Route::patch('/pengumpulan/{pengumpulan}/nilai', [PengumpulanController::class, 'nilai']);

        Route::get('/post/{post}/soal', [SoalController::class, 'edit']);
        Route::put('/post/{post}/soal', [SoalController::class, 'sinkron']);
        // Membaca dokumen cukup berat, jadi dibatasi lebih ketat daripada unggah gambar biasa.
        Route::post('/post/{post}/soal/impor', [SoalController::class, 'impor'])->middleware('throttle:10,1');
        Route::get('/template-soal', [SoalController::class, 'templat']);
        Route::post('/post/{post}/soal/gambar', [SoalController::class, 'unggahGambar'])->middleware('throttle:60,1');

        Route::get('/percobaan/{percobaan}', [KoreksiController::class, 'show']);
        Route::patch('/jawaban/{jawaban}/skor', [KoreksiController::class, 'skor']);
    });

    // Rekap nilai boleh diunduh guru, admin, dan kepsek; siswa ditolak di controller.
    Route::middleware('peran:ADMIN,KEPSEK,GURU')->group(function () {
        Route::get('/kelas/{kelas}/nilai/export', [KelasController::class, 'exportNilai']);
        Route::get('/post/{post}/nilai/export', [NilaiUjianController::class, 'ekspor']);
    });

    Route::middleware('peran:SISWA')->group(function () {
        Route::post('/post/{post}/kumpulkan', [PengumpulanController::class, 'store']);
        Route::delete('/post/{post}/kumpulkan', [PengumpulanController::class, 'destroy']);

        Route::post('/post/{post}/mulai', [UjianController::class, 'mulai']);
        Route::get('/post/{post}/kerjakan', [UjianController::class, 'kerjakan']);
        Route::patch('/percobaan/{percobaan}/jawab', [UjianController::class, 'jawab']);
        Route::post('/percobaan/{percobaan}/selesai', [UjianController::class, 'selesai']);
    });

    // Data induk: admin dan kepsek boleh melihat, hanya admin yang mengubah.
    Route::middleware('peran:ADMIN,KEPSEK')->group(function () {
        Route::get('/rombel', [RombelController::class, 'index']);
        Route::get('/siswa', [SiswaController::class, 'index']);
        Route::get('/mapel', [MapelController::class, 'index']);
        Route::get('/tahun-ajaran', [TahunAjaranController::class, 'index']);
        Route::get('/data-guru', [GuruController::class, 'index']);
    });

    Route::middleware('peran:ADMIN')->group(function () {
        Route::post('/rombel', [RombelController::class, 'store']);
        Route::patch('/rombel/{rombel}', [RombelController::class, 'update']);
        Route::delete('/rombel/{rombel}', [RombelController::class, 'destroy']);

        Route::post('/siswa', [SiswaController::class, 'store']);
        Route::patch('/siswa/{siswa}', [SiswaController::class, 'update']);
        Route::delete('/siswa/{siswa}', [SiswaController::class, 'destroy']);

        Route::post('/mapel', [MapelController::class, 'store']);
        Route::patch('/mapel/{mapel}', [MapelController::class, 'update']);
        Route::delete('/mapel/{mapel}', [MapelController::class, 'destroy']);

        Route::post('/tahun-ajaran', [TahunAjaranController::class, 'store']);
        Route::patch('/tahun-ajaran/{tahunAjaran}', [TahunAjaranController::class, 'update']);
        Route::delete('/tahun-ajaran/{tahunAjaran}', [TahunAjaranController::class, 'destroy']);

        Route::post('/data-guru', [GuruController::class, 'store']);
        Route::patch('/data-guru/{guru}', [GuruController::class, 'update']);
        Route::delete('/data-guru/{guru}', [GuruController::class, 'destroy']);
    });
});
