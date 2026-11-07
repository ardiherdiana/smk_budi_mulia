<?php

use App\Enums\Sppd\RoleName as SppdRoleName;
use App\Http\Controllers\Admin\GuruController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\JadwalController;
use App\Http\Controllers\Admin\RekapController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BriefingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Guru\QrController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\Sppd\AuditLogController as SppdAuditLogController;
use App\Http\Controllers\Sppd\DashboardController as SppdDashboardController;
use App\Http\Controllers\Sppd\LaporanController as SppdLaporanController;
use App\Http\Controllers\Sppd\PegawaiController as SppdPegawaiController;
use App\Http\Controllers\Sppd\PengajuanSppdController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

/*
| Modul absensi guru + SPPD (digabung dari proyek absensi, 2026-11-03). Dimuat dari routes/web.php di dalam grup
| `auth` + `no-store`. URL sama persis dengan absensi lama (bookmark dan tautan notifikasi push tetap jalan); hanya
| `/`, `/login`, `/logout`, dan `/menu-utama` yang memakai versi landing.
|
| `staf:` (RequireStaf) memeriksa peran profil (ADMIN|KEPSEK|GURU|STAF) tanpa mengganti `$request->user()`. Siswa dan
| akun portal tanpa profil tidak pernah bisa membuka rute di berkas ini, termasuk SPPD.
*/

// Foto guru, lampiran izin, surat undangan, dan foto laporan perjalanan: hanya untuk staf.
Route::get('/uploads/{path}', [UploadController::class, 'show'])->where('path', '.*')->middleware('staf');

// Kunci publik VAPID memang bukan rahasia; sama seperti absensi lama, bisa dibaca tanpa login.
Route::get('/push/vapid-public-key', [PushController::class, 'vapidPublicKey'])->withoutMiddleware('auth');

Route::middleware('staf:ADMIN,GURU,KEPSEK')->group(function () {
    Route::get('/absen/dashboard', [DashboardController::class, 'index']);

    Route::post('/push/subscribe', [PushController::class, 'subscribe']);
    Route::post('/push/unsubscribe', [PushController::class, 'unsubscribe']);
});

Route::middleware('staf:ADMIN')->group(function () {
    Route::get('/kiosk', [KioskController::class, 'index']);
    Route::post('/kiosk/scan-qr', [KioskController::class, 'scan']);

    Route::get('/briefing', [BriefingController::class, 'index']);
    Route::post('/briefing/scan', [BriefingController::class, 'scan']);
    Route::post('/briefing/manual', [BriefingController::class, 'manualUpsert']);
    Route::get('/briefing/rekap', [BriefingController::class, 'rekap']);
    Route::get('/briefing/rekap/export', [BriefingController::class, 'export']);
});

Route::middleware('staf:ADMIN,KEPSEK')->group(function () {
    Route::get('/data-guru', [GuruController::class, 'index']);
    Route::post('/data-guru', [GuruController::class, 'store']);
    Route::get('/data-guru/{guru}/detail', [GuruController::class, 'detail']);
    Route::patch('/data-guru/{guru}', [GuruController::class, 'update']);
    Route::delete('/data-guru/{guru}', [GuruController::class, 'destroy']);
    Route::post('/data-guru/{guru}/foto', [GuruController::class, 'uploadFoto']);
    Route::post('/data-guru/{guru}/qr/regenerate', [GuruController::class, 'regenerateQr']);

    Route::get('/rekap', [RekapController::class, 'index']);
    Route::get('/rekap/export', [RekapController::class, 'export']);
    Route::post('/rekap/manual', [RekapController::class, 'manualUpsert']);

    Route::get('/persetujuan', [LeaveController::class, 'adminIndex']);
    Route::patch('/persetujuan/{leaveRequest}/review', [LeaveController::class, 'review']);

    Route::get('/hari-libur', [HolidayController::class, 'index']);
    Route::post('/hari-libur', [HolidayController::class, 'store']);
    Route::delete('/hari-libur/{holiday}', [HolidayController::class, 'destroy']);

    // Notifikasi absensi dipusatkan di lonceng topbar Menu Utama (NotifikasiController, routes/web.php).

    Route::get('/pengaturan', [SettingsController::class, 'edit']);
    Route::patch('/pengaturan', [SettingsController::class, 'update']);
    Route::patch('/pengaturan/jadwal/{hari}', [JadwalController::class, 'update']);
});

// Profil (`/profil`, foto, ganti password) sudah jadi modul tersendiri untuk semua akun: lihat routes/web.php.

Route::middleware('staf:GURU,KEPSEK')->group(function () {
    Route::get('/qr', [QrController::class, 'index']);
    Route::get('/riwayat', [AttendanceController::class, 'riwayat']);
    Route::get('/izin', [LeaveController::class, 'index']);
    Route::post('/izin', [LeaveController::class, 'store']);

    Route::get('/school-location', [AttendanceController::class, 'schoolLocation']);
    Route::post('/checkin-web', [AttendanceController::class, 'checkinWeb']);
    Route::get('/attendance/detail/me', [AttendanceController::class, 'detailMe']);
});

// Modul SPPD — Surat Perintah Perjalanan Dinas. Otorisasi role via spatie/laravel-permission
// (pemohon, kepala_sekolah, tu, bendahara), lapis di atas peran staf. Admin lolos semua gate di bawah ini
// (lihat App\Models\User::isAdmin(), Gate::before, dan Sppd\RoleMiddleware).
Route::prefix('sppd')->name('sppd.')->middleware('staf')->group(function () {
    Route::get('/dashboard', SppdDashboardController::class)->name('dashboard');
    // Profil SPPD lama -> modul Profil bersama.
    Route::redirect('/profil', '/profil');

    // Notifikasi SPPD dipusatkan di lonceng topbar Menu Utama (NotifikasiController, routes/web.php).

    Route::get('/audit-log', [SppdAuditLogController::class, 'index'])->name('audit-log.index');

    // Pengajuan SPPD — dapat diakses semua staf, otorisasi detail via Policy.
    Route::get('/pengajuan', [PengajuanSppdController::class, 'index'])->name('pengajuan.index');
    Route::get('/pengajuan/{pengajuan}', [PengajuanSppdController::class, 'show'])->name('pengajuan.show');
    Route::get('/pengajuan/{pengajuan}/sppd.pdf', [PengajuanSppdController::class, 'downloadSppd'])->name('pengajuan.sppd-pdf');

    // Pemohon
    Route::middleware('sppd.role:'.SppdRoleName::Pemohon->value)->group(function () {
        Route::get('/pengajuan-create', [PengajuanSppdController::class, 'create'])->name('pengajuan.create');
        Route::post('/pengajuan', [PengajuanSppdController::class, 'store'])->name('pengajuan.store');
        Route::post('/pengajuan/{pengajuan}/laporan', [PengajuanSppdController::class, 'simpanLaporan'])->name('pengajuan.laporan');

        // Revisi pengajuan yang ditolak Kepala Sekolah. POST (bukan PUT) karena formulirnya multipart.
        Route::get('/pengajuan/{pengajuan}/revisi', [PengajuanSppdController::class, 'edit'])->name('pengajuan.edit');
        Route::post('/pengajuan/{pengajuan}/revisi', [PengajuanSppdController::class, 'revisi'])->name('pengajuan.revisi');
    });

    // Kepala Sekolah
    Route::middleware('sppd.role:'.SppdRoleName::KepalaSekolah->value)->group(function () {
        Route::post('/pengajuan/{pengajuan}/approve', [PengajuanSppdController::class, 'approve'])->name('pengajuan.approve');
        Route::post('/pengajuan/{pengajuan}/reject', [PengajuanSppdController::class, 'reject'])->name('pengajuan.reject');
    });

    // TU atau bendahara: melengkapi akun anggaran SPPD yang sudah dibuat pemohon.
    Route::patch('/pengajuan/{pengajuan}/akun-anggaran', [PengajuanSppdController::class, 'simpanAkunAnggaran'])
        ->middleware('sppd.role:'.SppdRoleName::Tu->value.'|'.SppdRoleName::Bendahara->value)
        ->name('pengajuan.akun-anggaran');

    // TU (Tata Usaha)
    Route::middleware('sppd.role:'.SppdRoleName::Tu->value)->group(function () {
        Route::patch('/pengajuan/{pengajuan}/nomor', [PengajuanSppdController::class, 'simpanNomor'])->name('pengajuan.nomor');
        Route::post('/pengajuan/{pengajuan}/selesai', [PengajuanSppdController::class, 'selesaikan'])->name('pengajuan.selesai');

        Route::get('/pegawai', [SppdPegawaiController::class, 'index'])->name('pegawai.index');
        Route::get('/pegawai/create', [SppdPegawaiController::class, 'create'])->name('pegawai.create');
        Route::post('/pegawai', [SppdPegawaiController::class, 'store'])->name('pegawai.store');
        Route::get('/pegawai/{pegawai}/edit', [SppdPegawaiController::class, 'edit'])->name('pegawai.edit');
        Route::put('/pegawai/{pegawai}', [SppdPegawaiController::class, 'update'])->name('pegawai.update');
        Route::delete('/pegawai/{pegawai}', [SppdPegawaiController::class, 'destroy'])->name('pegawai.destroy');
    });

    // Bendahara
    Route::middleware('sppd.role:'.SppdRoleName::Bendahara->value)->group(function () {
        Route::post('/pengajuan/{pengajuan}/cairkan-lumsum', [PengajuanSppdController::class, 'cairkanLumsum'])->name('pengajuan.cairkan-lumsum');

        Route::get('/laporan', [SppdLaporanController::class, 'index'])->name('laporan.index');
        Route::get('/laporan/export-excel', [SppdLaporanController::class, 'exportExcel'])->name('laporan.export-excel');
        Route::get('/laporan/export-pdf', [SppdLaporanController::class, 'exportPdf'])->name('laporan.export-pdf');
    });
});
