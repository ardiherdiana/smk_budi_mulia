<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang modul absensi dan SPPD: `staf:GURU,KEPSEK` mengizinkan pengguna yang peran profilnya (`User::peran`) salah
 * satunya; tanpa argumen = semua staf (ADMIN, KEPSEK, GURU, STAF). Admin (`users.role` admin, termasuk admin lama
 * tanpa profil) lolos bila ADMIN ada di daftar, sama seperti `role:` absensi dulu. Siswa, akun portal tanpa profil, dan
 * profil nonaktif selalu ditolak. Beda dengan `peran:` milik Classroom, middleware ini TIDAK mengganti
 * `$request->user()`: kode absensi bekerja dengan model User.
 */
class RequireStaf
{
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $user = $request->user();
        $peran = $peran ?: ['ADMIN', 'KEPSEK', 'GURU', 'STAF'];

        $cocok = $user && (in_array($user->peran, $peran, true) || ($user->isAdmin() && in_array('ADMIN', $peran, true)));
        $boleh = $cocok && $user->is_active;
        if (! $boleh) {
            abort(403, 'Anda tidak memiliki akses');
        }

        return $next($request);
    }
}
