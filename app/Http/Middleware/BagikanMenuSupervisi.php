<?php

namespace App\Http\Middleware;

use App\Services\Supervisi\AksesSupervisi;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Item sidebar modul Supervisi menurut peran (kelola, tugas supervisor, supervisi saya); hanya untuk rute /supervisi. */
class BagikanMenuSupervisi
{
    public function __construct(private AksesSupervisi $akses) {}

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('supervisiMenu', fn () => $this->akses->menu($request->user()));

        return $next($request);
    }
}
