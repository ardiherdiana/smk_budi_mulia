<?php

namespace App\Services;

use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Mengisi `Seo` untuk halaman publik. Semua nilai berasal dari config/database (bukan dari header
 * permintaan), dan tidak ada angka atau klaim yang dikarang: hanya data yang memang dimiliki sekolah.
 */
class SeoBuilder
{
    public function __construct(private Seo $seo) {}

    public function home(Request $request): Seo
    {
        $seo = $this->seo;
        $seo->title = (string) config('school.seo.title');
        $seo->description = (string) config('school.seo.description');
        $seo->canonical = Seo::absolute('/');
        $seo->follow = true;

        // Hasil pencarian/filter/halaman berikutnya adalah variasi dari beranda yang sama:
        // jangan diindeks sendiri-sendiri (tautan beritanya tetap diikuti dan ada di sitemap).
        $seo->index = ! $this->isListingVariant($request);

        $this->defaultImage($seo);
        $seo->preloadImage = '/images/gedung-sekolah.webp';
        $seo->jsonLd = [['@context' => 'https://schema.org', '@graph' => [$this->website(), $this->school()]]];

        return $seo;
    }

    /** @param  array<string, mixed>  $post  hasil ContentPresenter::postDetail */
    public function post(array $post, string $path): Seo
    {
        $seo = $this->seo;
        $short = (string) config('school.short_name');
        $title = trim((string) $post['title']);

        $seo->title = "{$title} - {$short}";
        $seo->ogTitle = $title;
        $seo->description = $post['excerpt'] !== ''
            ? (string) $post['excerpt']
            : 'Berita dan kegiatan terbaru dari '.config('school.name').'.';
        $seo->canonical = Seo::absolute($path);
        $seo->index = true;
        $seo->follow = true;
        $seo->type = 'article';
        $seo->publishedTime = $post['date'];
        $seo->section = $post['category'];

        $images = $this->postImages($post);
        if ($images !== []) {
            $seo->image = $images[0];
            $seo->imageAlt = $title;
        } else {
            $this->defaultImage($seo);
        }

        $seo->jsonLd = [['@context' => 'https://schema.org', '@graph' => array_values(array_filter([
            $this->article($post, $seo, $images),
            $this->breadcrumbs($title, $seo->canonical),
            $this->video($post, $seo, $images),
        ]))]];

        return $seo;
    }

    private function isListingVariant(Request $request): bool
    {
        return trim((string) $request->query('q', '')) !== ''
            || (string) $request->query('category', '') !== ''
            || in_array($request->query('type'), ['berita', 'video'], true)
            || (int) $request->query('page', 1) > 1;
    }

    private function defaultImage(Seo $seo): void
    {
        $seo->image = Seo::absolute((string) config('school.seo.image'));
        $seo->imageAlt = (string) config('school.seo.image_alt');
        $seo->imageWidth = 1200;
        $seo->imageHeight = 630;
    }

    /**
     * Gambar berita (absolut): sampul, foto galeri, lalu thumbnail YouTube. Pemakaian pertama jadi og:image.
     *
     * @param  array<string, mixed>  $post
     * @return list<string>
     */
    private function postImages(array $post): array
    {
        $images = [];
        if ($post['cover_url']) {
            $images[] = $post['cover_url'];
        }
        foreach (array_slice($post['gallery'], 0, 4) as $item) {
            $images[] = $item['url'];
        }
        if (($post['video']['kind'] ?? null) === 'youtube') {
            $images[] = 'https://i.ytimg.com/vi/'.$post['video']['id'].'/hqdefault.jpg';
        }

        return array_values(array_unique(array_map(Seo::absolute(...), $images)));
    }

    /** @return array<string, mixed> */
    private function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => Seo::absolute('/').'#website',
            'url' => Seo::absolute('/'),
            'name' => config('school.name'),
            'inLanguage' => 'id-ID',
            'publisher' => ['@id' => $this->schoolId()],
        ];
    }

    /** @return array<string, mixed> */
    private function school(): array
    {
        $address = config('school.address_parts');
        $map = config('school.map');

        return [
            '@type' => 'HighSchool',
            '@id' => $this->schoolId(),
            'name' => config('school.name'),
            'alternateName' => config('school.short_name'),
            'url' => Seo::absolute('/'),
            'description' => config('school.seo.description'),
            'logo' => Seo::absolute('/logo_smk.png'),
            'image' => Seo::absolute((string) config('school.seo.image')),
            'telephone' => config('school.phone_international'),
            'email' => config('school.email'),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $address['street'],
                'addressLocality' => $address['locality'],
                'addressRegion' => $address['region'],
                'postalCode' => $address['postal_code'],
                'addressCountry' => $address['country'],
            ],
            'geo' => ['@type' => 'GeoCoordinates', 'latitude' => $map['lat'], 'longitude' => $map['lng']],
            'hasMap' => sprintf('https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=17/%s/%s', $map['lat'], $map['lng'], $map['lat'], $map['lng']),
            'sameAs' => [config('school.instagram')],
        ];
    }

    private function schoolId(): string
    {
        return Seo::absolute('/').'#school';
    }

    /**
     * @param  array<string, mixed>  $post
     * @param  list<string>  $images
     * @return array<string, mixed>
     */
    private function article(array $post, Seo $seo, array $images): array
    {
        $date = $post['date'] ? Carbon::parse($post['date'])->toIso8601String() : null;

        return array_filter([
            '@type' => 'NewsArticle',
            '@id' => $seo->canonical.'#article',
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $seo->canonical],
            'headline' => Str::limit((string) $post['title'], 110, ''),
            'description' => $seo->description,
            'image' => $images ?: [$seo->image],
            'datePublished' => $date,
            // Tabel `postingan` tidak punya kolom tanggal ubah, jadi hanya tanggal terbit yang diketahui.
            'dateModified' => $date,
            'articleSection' => $post['category'],
            'inLanguage' => 'id-ID',
            // Kolom `author` berisi nama akun admin ("Admin"), bukan penulis; yang bertanggung jawab adalah sekolah.
            'author' => ['@id' => $this->schoolId()],
            'publisher' => ['@id' => $this->schoolId()],
        ]);
    }

    /** @return array<string, mixed> */
    private function breadcrumbs(string $title, string $canonical): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => Seo::absolute('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Berita', 'item' => Seo::absolute('/').'#berita'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $title, 'item' => $canonical],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $post
     * @param  list<string>  $images
     * @return array<string, mixed>|null
     */
    private function video(array $post, Seo $seo, array $images): ?array
    {
        $video = $post['video'] ?? null;
        if (! $video || ! $post['date'] || $video['kind'] === 'file') {
            return null; // tautan file video milik pihak lain tidak kita jamin; hanya YouTube & unggahan sendiri
        }

        $node = [
            '@type' => 'VideoObject',
            'name' => trim((string) $post['title']),
            'description' => $seo->description,
            'thumbnailUrl' => $images ?: [$seo->image],
            'uploadDate' => Carbon::parse($post['date'])->toIso8601String(),
        ];

        if ($video['kind'] === 'youtube') {
            $node['embedUrl'] = 'https://www.youtube-nocookie.com/embed/'.$video['id'];
        } else {
            $node['contentUrl'] = Seo::absolute($video['src']);
        }

        return $node;
    }
}
