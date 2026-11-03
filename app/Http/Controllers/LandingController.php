<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\LandingService;
use App\Services\SeoBuilder;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __construct(private LandingService $landing, private SeoBuilder $seo) {}

    public function index(Request $request): Response
    {
        $this->seo->home($request);

        return Inertia::render('landing-page', $this->landing->props($request));
    }

    /**
     * Tautan berbagi sebuah postingan: tetap halaman landing yang sama, dialog detail terbuka.
     * Tautan lama (`/berita/{id}`) dan judul yang sudah berubah dialihkan permanen ke URL kanonik.
     */
    public function show(Request $request, string $post): Response|RedirectResponse
    {
        $model = $this->landing->findPost(Post::idFromSegment($post));

        if ('/berita/'.$post !== $model->path()) {
            $query = $request->getQueryString();

            // Berdasarkan APP_URL (bukan header Host permintaan), sama seperti canonical.
            return redirect()->to(Seo::absolute($model->path()).($query ? '?'.$query : ''), 301);
        }

        $props = $this->landing->props($request, $model);
        $this->seo->post($props['activePost'], $model->path());

        return Inertia::render('landing-page', $props);
    }
}
