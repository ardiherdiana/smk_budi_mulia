<?php

namespace App\Support;

/**
 * Metadata SEO satu halaman: diisi controller (lewat SeoBuilder), dibaca partial
 * `resources/views/partials/seo.blade.php` saat root view dirender.
 *
 * Default-nya `noindex`: halaman yang tidak mengaturnya (login, portal, admin, error) tidak pernah masuk
 * indeks. Hanya halaman publik yang sengaja memanggil `SeoBuilder` yang bisa menjadi `index`.
 */
class Seo
{
    public string $title = '';

    public string $description = '';

    public ?string $canonical = null;

    public bool $index = false;

    public bool $follow = false;

    /** `website` untuk beranda, `article` untuk berita. */
    public string $type = 'website';

    /** Judul untuk kartu berbagi bila berbeda dari <title> (mis. tanpa akhiran nama sekolah). */
    public ?string $ogTitle = null;

    public ?string $image = null;

    public ?string $imageAlt = null;

    public ?int $imageWidth = null;

    public ?int $imageHeight = null;

    public ?string $publishedTime = null;

    public ?string $section = null;

    /** Gambar yang dimuat paling awal di halaman ini (LCP): dipasang <link rel="preload">. */
    public ?string $preloadImage = null;

    /** @var list<array<string, mixed>> */
    public array $jsonLd = [];

    public function __construct()
    {
        $this->title = (string) config('school.name');
        $this->description = (string) config('school.seo.description');
    }

    /** Hanya produksi yang diindeks kecuali dipaksa lewat SEO_INDEXABLE. */
    public static function indexable(): bool
    {
        $forced = config('school.seo.indexable');

        return $forced === null ? app()->isProduction() : filter_var($forced, FILTER_VALIDATE_BOOLEAN);
    }

    /** Nilai meta robots. `max-image-preview:large` mengizinkan gambar besar di hasil pencarian & Discover. */
    public function robots(): string
    {
        if (! $this->index || ! self::indexable()) {
            return $this->follow && self::indexable() ? 'noindex, follow' : 'noindex, nofollow';
        }

        return 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    }

    /**
     * URL absolut di atas APP_URL. Sengaja BUKAN dari header Host permintaan: header itu bisa dipalsukan
     * dan akan meracuni canonical/og:url.
     */
    public static function absolute(string $pathOrUrl): string
    {
        if (preg_match('#^https?://#i', $pathOrUrl)) {
            return $pathOrUrl;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($pathOrUrl, '/');
    }

    /** JSON untuk <script type="application/ld+json">: tanda `<` `>` `&` di-escape agar tidak bisa menutup tag <script>. */
    public function jsonLdString(array $node): string
    {
        return (string) json_encode(
            $node,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
    }
}
