<?php

use App\Http\Controllers\Supervisi\DaftarController;
use App\Http\Controllers\Supervisi\DashboardController;
use App\Http\Controllers\Supervisi\JadwalController;
use App\Http\Controllers\Supervisi\LembarController;
use App\Http\Controllers\Supervisi\RekapController;
use App\Http\Middleware\BagikanMenuSupervisi;
use Illuminate\Support\Facades\Route;

/*
| Modul Supervisi Guru (supervisi akademik). Dimuat dari routes/web.php di dalam grup `auth` + `no-store`.
| Gerbang modul `staf:ADMIN,KEPSEK,GURU`; hak per aksi (kelola, supervisor, guru pemilik) diperiksa AksesSupervisi
| di controller. STAF/TU, siswa, dan akun portal tidak bisa membuka rute ini.
*/

Route::prefix('supervisi')->middleware(['staf:ADMIN,KEPSEK,GURU', BagikanMenuSupervisi::class])->group(function () {
    Route::redirect('/', '/supervisi/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('supervisi.dashboard');
    Route::get('/saya', [DaftarController::class, 'saya'])->name('supervisi.saya');
    Route::get('/tugas', [DaftarController::class, 'tugas'])->name('supervisi.tugas');

    // Kepala sekolah dan admin.
    Route::get('/jadwal', [JadwalController::class, 'index'])->name('supervisi.jadwal');
    Route::post('/jadwal', [JadwalController::class, 'store']);
    Route::patch('/jadwal/{supervisi}', [JadwalController::class, 'update'])->whereUlid('supervisi');
    Route::post('/jadwal/{supervisi}/batal', [JadwalController::class, 'batal'])->whereUlid('supervisi');
    Route::delete('/jadwal/{supervisi}', [JadwalController::class, 'destroy'])->whereUlid('supervisi');

    Route::get('/rekap', [RekapController::class, 'index'])->name('supervisi.rekap');
    Route::get('/rekap/export', [RekapController::class, 'export']);

    // Lembar Instrumen Observasi Pembelajaran Mendalam: penilai mengisi, guru membaca hasilnya setelah selesai.
    Route::get('/{supervisi}', [LembarController::class, 'show'])->whereUlid('supervisi')->name('supervisi.lembar');
    Route::put('/{supervisi}/lembar', [LembarController::class, 'simpan'])->whereUlid('supervisi');
    Route::post('/{supervisi}/selesai', [LembarController::class, 'selesai'])->whereUlid('supervisi');
    Route::post('/{supervisi}/buka-kunci', [LembarController::class, 'bukaKunci'])->whereUlid('supervisi');
    Route::get('/{supervisi}/lembar.pdf', [LembarController::class, 'pdf'])->whereUlid('supervisi');
});
