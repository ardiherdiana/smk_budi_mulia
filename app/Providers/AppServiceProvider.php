<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Seo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Satu objek metadata SEO per permintaan: controller mengisi, root view (app.blade.php) membaca.
        $this->app->scoped(Seo::class);
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
    }
}
