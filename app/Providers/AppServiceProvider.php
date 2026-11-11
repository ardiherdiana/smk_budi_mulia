<?php

namespace App\Providers;

use App\Models\LogAktivitas;
use App\Models\User;
use App\Support\LogAktivitas\Perekam;
use App\Support\Seo;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Satu objek metadata SEO per permintaan: controller mengisi, root view (app.blade.php) membaca.
        $this->app->scoped(Seo::class);

        // Log aktivitas bersama: satu perekam per permintaan (dinyalakan middleware CatatAktivitas).
        $this->app->scoped(Perekam::class);
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceHttps();
        }

        // Tanggal Indonesia di PDF SPPD dan laporan (translatedFormat).
        Carbon::setLocale(config('app.locale'));

        // Modul SPPD: admin (satu untuk semua modul) lolos semua pengecekan Policy tanpa perlu role SPPD.
        // Bisa menerima `Akun` (Gate::forUser di Classroom), jadi tipe diperiksa di dalam.
        Gate::before(fn ($user) => $user instanceof User && $user->isAdmin() ? true : null);

        // Formulir kontak publik: cegah spam/flood email.
        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perDay(20)->by($request->ip()),
        ]);

        // Halaman login: batas kasar per IP (batas per akun ada di LoginController).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // Semua aksi tulis admin.
        RateLimiter::for('admin-writes', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        $this->catatAktivitas();
    }

    /**
     * Log aktivitas bersama (config/log_aktivitas.php): tambah/ubah/hapus lewat Eloquent untuk model yang terdaftar, plus
     * masuk/keluar akun. Gagal masuk dicatat di LoginController. Perekam diam di luar permintaan web.
     */
    private function catatAktivitas(): void
    {
        Event::listen(['eloquent.created: *', 'eloquent.updated: *', 'eloquent.deleted: *'], function (string $event, array $data) {
            $perekam = $this->app->make(Perekam::class);
            if ($perekam->aktif() && ($data[0] ?? null) instanceof Model) {
                $perekam->catatModel(Str::between($event, 'eloquent.', ':'), $data[0]);
            }
        });

        $akun = function (string $aksi, string $kata, ?Authenticatable $user) {
            if ($user !== null) {
                $this->app->make(Perekam::class)->catat($aksi, 'Akun', Perekam::namaPengguna($user).' '.$kata, 'Akun',
                    $user instanceof Model ? $user : null, pengguna: $user);
            }
        };
        Event::listen(Login::class, fn (Login $event) => $akun(LogAktivitas::MASUK, 'masuk', $event->user));
        Event::listen(Logout::class, fn (Logout $event) => $akun(LogAktivitas::KELUAR, 'keluar', $event->user));
    }
}
