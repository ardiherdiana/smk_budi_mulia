<?php

namespace App\Http\Middleware;

use App\Models\Classroom\Akun;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang modul Classroom: `peran:ADMIN,GURU` mengizinkan pengguna yang profil Classroom-nya berperan salah satunya.
 * Selama permintaan berlangsung `$request->user()` diganti menjadi profil (`Akun`), supaya kode Classroom membaca
 * username, nama, peran, dan data siswa dari satu objek.
 */
class RequirePeran
{
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $pengguna = $request->user();
        $akun = $pengguna ? Akun::untukPengguna($pengguna) : null;

        if (! $akun || ! $akun->is_active || ! in_array($akun->role, $peran, true)) {
            abort(403, 'Anda tidak memiliki akses');
        }

        $request->setUserResolver(fn () => $akun);

        return $next($request);
    }
}
