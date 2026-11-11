<?php

namespace App\Http\Middleware;

use App\Support\LogAktivitas\Perekam;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyalakan log aktivitas (Perekam) selama menangani permintaan web, lalu mematikannya lagi sesudahnya. Di luar
 * permintaan web (seeder, impor, perintah konsol, penjadwal) perubahan data tidak dicatat.
 */
class CatatAktivitas
{
    public function __construct(private Perekam $perekam) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->perekam->aktifkan();

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->perekam->nonaktifkan();
    }
}
