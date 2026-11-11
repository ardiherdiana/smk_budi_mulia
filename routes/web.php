<?php

use App\Http\Controllers\Admin\AboutController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MajorController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PrincipalController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\LogAktivitasController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\Skl\DashboardController as SklDashboardController;
use App\Http\Controllers\Skl\PortalFileController;
use App\Http\Controllers\Skl\PortalUserController;
use Illuminate\Support\Facades\Route;

// ---- SEO ---------------------------------------------------------------------
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');

// ---- Alamat situs lama (301 ke padanannya di halaman satu-halaman) ------------------------
foreach (LegacyRedirectController::SECTIONS as $legacy => $section) {
    Route::get($legacy, [LegacyRedirectController::class, 'section'])->defaults('section', $section);
}
Route::get('/postingan/detail/{id}', [LegacyRedirectController::class, 'post'])->where('id', '[A-Za-z0-9_-]+');
Route::get('/postingan/{id}', [LegacyRedirectController::class, 'post'])->where('id', '[A-Za-z0-9_-]+');

// ---- Publik: landing page satu halaman -------------------------------------
Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/berita/{post}', [LandingController::class, 'show'])->where('post', '[A-Za-z0-9_-]+')->name('berita.show');
Route::post('/kontak', [ContactController::class, 'store'])->middleware('throttle:contact')->name('kontak.store');

// ---- Auth --------------------------------------------------------------------
Route::middleware(['guest', 'no-store'])->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth');

Route::middleware(['auth', 'no-store'])->group(function () {
    // ---- Menu utama (pemilih modul: Website, SKL, Classroom, Absensi, SPPD, Supervisi, Data Guru, Profil) ----
    Route::get('/menu-utama', [MenuController::class, 'index'])->name('menu');

    // ---- Notifikasi terpusat (lonceng di topbar Menu Utama; daftarnya dikirim MenuController) ----
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
    Route::post('/notifikasi/{sumber}/{id}/baca', [NotifikasiController::class, 'baca'])
        ->whereIn('sumber', ['absensi', 'sppd', 'supervisi'])->name('notifikasi.baca');

    // ---- Profil: satu untuk semua akun dan semua modul ---------------------------
    Route::get('/profil', [ProfilController::class, 'edit'])->name('profil');
    Route::patch('/profil/password', [ProfilController::class, 'gantiPassword'])->name('profil.password');
    Route::post('/profil/foto', [ProfilController::class, 'unggahFoto'])->name('profil.foto');

    // ---- Log aktivitas bersama semua modul (admin saja, data 30 hari terakhir) ----
    Route::get('/log-aktivitas', [LogAktivitasController::class, 'index'])->middleware('role:admin')->name('log-aktivitas');

    // ---- Classroom ----------------------------------------------------------
    require __DIR__.'/classroom.php';

    // ---- Absensi guru + SPPD (URL lama absensi, middleware `staf:`) -------------
    require __DIR__.'/absensi.php';

    // ---- Supervisi guru (supervisi akademik, middleware `staf:ADMIN,KEPSEK,GURU`) ----
    require __DIR__.'/supervisi.php';

    // ---- Portal siswa -------------------------------------------------------
    // Dimatikan sementara lewat config('school.skl_siswa_aktif'): siswa dialihkan ke menu utama.
    Route::middleware('skl-siswa')->group(function () {
        Route::get('/portal', [PortalController::class, 'index'])->middleware('role:user')->name('portal');
        Route::get('/portal/berkas/{file}', [PortalController::class, 'download'])->name('portal.download');
    });

    // ---- Panel admin --------------------------------------------------------
    Route::prefix('admin')->middleware(['role:admin', 'throttle:admin-writes'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('admin.dashboard');

        Route::get('/berita', [PostController::class, 'index']);
        Route::post('/berita', [PostController::class, 'store']);
        Route::put('/berita/{post}', [PostController::class, 'update']);
        Route::delete('/berita/{post}', [PostController::class, 'destroy']);

        Route::get('/tentang-kami', [AboutController::class, 'index']);
        Route::post('/tentang-kami', [AboutController::class, 'store']);
        Route::put('/tentang-kami/{about}', [AboutController::class, 'update']);
        Route::delete('/tentang-kami/{about}', [AboutController::class, 'destroy']);

        Route::get('/jurusan', [MajorController::class, 'index']);
        Route::post('/jurusan', [MajorController::class, 'store']);
        Route::put('/jurusan/{major}', [MajorController::class, 'update']);
        Route::delete('/jurusan/{major}', [MajorController::class, 'destroy']);

        Route::get('/guru-staff', [StaffController::class, 'index']);
        Route::post('/guru-staff', [StaffController::class, 'store']);
        Route::put('/guru-staff/{staff}', [StaffController::class, 'update']);
        Route::delete('/guru-staff/{staff}', [StaffController::class, 'destroy']);

        Route::get('/kepala-sekolah', [PrincipalController::class, 'index']);
        Route::post('/kepala-sekolah', [PrincipalController::class, 'store']);
        Route::put('/kepala-sekolah/{principal}', [PrincipalController::class, 'update']);
        Route::delete('/kepala-sekolah/{principal}', [PrincipalController::class, 'destroy']);
    });

    // ---- Modul SKL (admin): akun siswa dan berkas SKL, terpisah dari panel Website ------
    Route::prefix('skl')->middleware(['role:admin', 'throttle:admin-writes'])->group(function () {
        Route::get('/', [SklDashboardController::class, 'index'])->name('skl.dashboard');

        Route::get('/siswa', [PortalUserController::class, 'index']);
        Route::post('/siswa', [PortalUserController::class, 'store']);
        Route::post('/siswa/impor', [PortalUserController::class, 'import']);
        Route::get('/siswa/ekspor', [PortalUserController::class, 'export']);
        Route::patch('/siswa/password', [PortalUserController::class, 'bulkPassword']);
        Route::patch('/siswa/{user}/password', [PortalUserController::class, 'updatePassword']);
        Route::delete('/siswa/{user}', [PortalUserController::class, 'destroy']);

        Route::get('/berkas', [PortalFileController::class, 'index']);
        Route::post('/berkas', [PortalFileController::class, 'store']);
        Route::patch('/berkas/bagikan', [PortalFileController::class, 'assign']);
        Route::delete('/berkas/{file}', [PortalFileController::class, 'destroy']);
    });
});
