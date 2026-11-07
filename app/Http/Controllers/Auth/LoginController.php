<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Classroom\Akun;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LoginController extends Controller
{
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

        // "Ingat saya" hanya untuk staf (PWA absensi di HP guru). Siswa tidak: komputer lab dipakai bergantian.
        Auth::login($user, remember: $user->isStaf());
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
     * Satu kolom "Email / Username". Berisi "@": akun situs/portal (email + password, alur lama). Selain itu: username
     * profil sekolah (guru, kepsek, admin, pegawai SPPD, siswa), sandinya di `users.password`. Sejak absensi digabung
     * tidak ada lagi verifikasi ke basis data lain. Profil nonaktif ditolak, begitu juga guru/kepsek yang dinonaktifkan
     * di Data Guru absensi (`guru.aktif`), sama seperti login absensi dulu.
     */
    private function autentikasi(string $identitas, string $password): ?User
    {
        if (str_contains($identitas, '@')) {
            $user = User::where('email', $identitas)->first();

            return $user && $this->passwordMatches($user, $password) ? $user : null;
        }

        $akun = Akun::with('pengguna.guru')->where('username', $identitas)->first();
        $user = $akun?->pengguna;

        if (! $akun || ! $akun->is_active || ! $user || ! Hash::check($password, (string) $user->password)) {
            return null;
        }

        if (in_array($akun->role, ['GURU', 'KEPSEK'], true) && $user->guru && ! $user->guru->aktif) {
            return null;
        }

        return $user;
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
