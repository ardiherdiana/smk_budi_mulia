<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Classroom\Akun;
use App\Models\User;
use App\Services\Classroom\AbsensiAccountService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function __construct(private AbsensiAccountService $absensi) {}

    public function create()
    {
        return Inertia::render('login-page');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik.",
            ]);
        }

        $user = $this->autentikasi($credentials['email'], $credentials['password']);

        if (! $user) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages(['email' => 'Email/username atau password salah']);
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::clearHistory();

        return redirect()->intended('/menu-utama');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Inertia::clearHistory();

        return redirect('/login');
    }

    /**
     * Satu kolom "Email / Username". Berisi "@": akun situs/portal (email + password, alur lama). Selain itu: akun
     * Classroom. Staf (guru/kepsek/admin dari absensi) diverifikasi ke basis data absensi lalu disalin ke profil
     * Classroom; siswa (NIS) dan admin lokal memakai sandi di tabel users.
     */
    private function autentikasi(string $identitas, string $password): ?User
    {
        if (str_contains($identitas, '@')) {
            $user = User::where('email', $identitas)->first();

            return $user && $this->passwordMatches($user, $password) ? $user : null;
        }

        $akun = Akun::where('username', $identitas)->first();

        if ($akun && $akun->absensi_user_id === null) {
            return $akun->is_active && $akun->password && Hash::check($password, $akun->password) ? $akun->pengguna : null;
        }

        try {
            $row = $this->absensi->cari($identitas);
        } catch (QueryException $e) {
            report($e); // basis data absensi tidak terjangkau: akun staf tidak bisa masuk, akun lain tidak terpengaruh

            return null;
        }

        if (! $row || ! Hash::check($password, $row->password)) {
            return null;
        }

        return $this->absensi->sinkron($row)?->pengguna;
    }

    /**
     * Sistem lama menerima password yang tersimpan MENTAH di kolom `password`
     * (mis. akun admin awal). Baris seperti itu tetap bisa masuk satu kali,
     * lalu langsung di-hash ulang sehingga tidak ada lagi password mentah.
     */
    private function passwordMatches(User $user, string $plain): bool
    {
        $stored = (string) $user->password;

        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$2b$')) {
            return Hash::check($plain, $stored);
        }

        if (! hash_equals($stored, $plain)) {
            return false;
        }

        $user->forceFill(['password' => $plain])->save(); // cast 'hashed' -> bcrypt

        return true;
    }
}
