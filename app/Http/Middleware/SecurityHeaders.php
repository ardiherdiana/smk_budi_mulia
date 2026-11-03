<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk semua respons web. CSP membatasi sumber script ke
 * origin sendiri (tanpa inline script), hanya mengizinkan embed YouTube &
 * OpenStreetMap; gambar/video berasal dari /media (origin sendiri) atau data:/blob:.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nonce per-request: hanya <script> inline milik kita (tema) & tag Vite yang boleh jalan.
        $nonce = base64_encode(random_bytes(16));
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Content-Security-Policy', $this->csp($nonce));

        // Staging / subdomain uji / lokal: jangan pernah diindeks (di produksi tidak ada header ini).
        if (! Seo::indexable()) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function csp(string $nonce): string
    {
        // Saat `npm run dev` (hanya di environment lokal), Vite menyajikan script/HMR dari origin
        // terpisah. Di Windows alamatnya sering `http://[::1]:5173`, dan browser menolak literal
        // IPv6 di CSP, jadi pakai sumber berbasis skema (`http:`/`ws:`). Di produksi cabang ini
        // tidak pernah aktif, sekalipun ada file `public/hot` sisa.
        $dev = app()->isLocal() && file_exists(public_path('hot'));
        $vite = $dev ? 'http:' : null;
        $viteWs = $dev ? 'ws:' : null;

        $script = ["'self'", "'nonce-{$nonce}'", $vite];
        $style = ["'self'", "'unsafe-inline'", $vite];
        $font = ["'self'", 'data:', $vite];
        $connect = ["'self'", $vite, $viteWs];

        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', array_filter($script)),
            'style-src '.implode(' ', array_filter($style)),
            "img-src 'self' data: blob: https://i.ytimg.com",
            "media-src 'self' blob:",
            'font-src '.implode(' ', array_filter($font)),
            'connect-src '.implode(' ', array_filter($connect)),
            'frame-src https://www.youtube-nocookie.com https://www.youtube.com https://www.openstreetmap.org',
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        return implode('; ', $directives);
    }
}
