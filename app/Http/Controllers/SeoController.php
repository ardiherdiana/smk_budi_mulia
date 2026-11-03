<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\MediaService;
use App\Support\Seo;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    /**
     * Di luar produksi (staging, subdomain uji) semuanya ditutup supaya tidak ikut terindeks.
     * Alamat sitemap diambil dari APP_URL, jadi otomatis benar begitu domain final dipasang.
     */
    public function robots(): Response
    {
        $lines = Seo::indexable()
            ? [
                'User-agent: *',
                'Allow: /',
                'Disallow: /admin',
                'Disallow: /skl',
                'Disallow: /portal',
                'Disallow: /akun',
                '',
                'Sitemap: '.Seo::absolute('/sitemap.xml'),
            ]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** Beranda + setiap berita/video (dengan gambar sampulnya). */
    public function sitemap(): Response
    {
        $posts = Post::query()->select(['id', 'title', 'date', 'image'])->orderByDesc('date')->get();
        $media = app(MediaService::class);

        $entries = [$this->entry(Seo::absolute('/'), $posts->first()?->date, [Seo::absolute('/images/gedung-sekolah.webp')])];
        foreach ($posts as $post) {
            $cover = $media->url($post->image);
            $entries[] = $this->entry(Seo::absolute($post->path()), $post->date, $cover ? [Seo::absolute($cover)] : []);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n"
            .implode('', $entries)
            .'</urlset>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /** @param  list<string>  $images */
    private function entry(string $loc, ?\DateTimeInterface $lastmod, array $images): string
    {
        $xml = '  <url><loc>'.$this->xml($loc).'</loc>';
        if ($lastmod) {
            $xml .= '<lastmod>'.$lastmod->format('c').'</lastmod>';
        }
        foreach ($images as $image) {
            $xml .= '<image:image><image:loc>'.$this->xml($image).'</image:loc></image:image>';
        }

        return $xml."</url>\n";
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
