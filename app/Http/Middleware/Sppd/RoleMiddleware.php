<?php

namespace App\Http\Middleware\Sppd;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware as SpatieRoleMiddleware;

/**
 * Wraps Spatie's role middleware so an absensi `ADMIN` account (App\Models\User::isAdmin())
 * always passes the SPPD module's role gates, without needing any of
 * pemohon/kepala_sekolah/tu/bendahara assigned. Registered as the `sppd.role` alias in
 * bootstrap/app.php — kept separate from absensi's own `role` alias (App\Http\Middleware\RequireRole),
 * which uses a completely different role vocabulary (ADMIN/GURU/KEPSEK).
 */
class RoleMiddleware extends SpatieRoleMiddleware
{
    public function handle(Request $request, Closure $next, $role, ?string $guard = null)
    {
        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        return parent::handle($request, $next, $role, $guard);
    }
}
