<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** SKL untuk siswa bisa dimatikan sementara (config school.skl_siswa_aktif): siswa dialihkan ke menu utama, admin tetap lolos. */
class RequireSklSiswa
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('school.skl_siswa_aktif') && ! $request->user()?->isAdmin()) {
            return redirect()->route('menu');
        }

        return $next($request);
    }
}
