<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Support\Seo;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SeoTest extends TestCase
{
    private const BASE = 'https://smkbudimulia.test';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => self::BASE, 'school.seo.indexable' => true]);
    }

    private function makePost(array $attributes = []): Post
    {
        return Post::create([
            'title' => 'Juara Lomba Nasional',
            'content' => "Tim robotik meraih juara pertama.\nSelamat!",
            'type' => 'berita',
            'category' => 'Prestasi',
            'author' => 'Admin',
            'date' => '2026-03-01 10:00:00',
            ...$attributes,
        ]);
    }

    private function xpath(TestResponse $response): \DOMXPath
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$response->getContent());
        libxml_clear_errors();

        return new \DOMXPath($dom);
    }

    private function attr(\DOMXPath $xp, string $query): ?string
    {
        $node = $xp->query($query)->item(0);

        return $node?->nodeValue;
    }

    /** @return list<array<string, mixed>> semua node dari <script type="application/ld+json"> */
    private function jsonLd(TestResponse $response): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $blocks);

        return array_map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $blocks[1]);
    }

    /** @return array<string, array<string, mixed>> node @graph berdasarkan @type */
    private function graph(TestResponse $response): array
    {
        $nodes = collect($this->jsonLd($response))->flatMap(fn ($doc) => $doc['@graph'] ?? [$doc]);

        return $nodes->keyBy(fn ($node) => is_array($node['@type']) ? $node['@type'][0] : $node['@type'])->all();
    }

    // ---- Beranda ---------------------------------------------------------------

    public function test_home_page_has_server_rendered_meta_for_crawlers(): void
    {
        $response = $this->get('/')->assertOk();
        $xp = $this->xpath($response);

        $this->assertSame(config('school.seo.title'), $this->attr($xp, '//title'));
        $this->assertSame(config('school.seo.description'), $this->attr($xp, '//meta[@name="description"]/@content'));
        $this->assertStringStartsWith('index, follow', $this->attr($xp, '//meta[@name="robots"]/@content'));
        $this->assertSame(self::BASE.'/', $this->attr($xp, '//link[@rel="canonical"]/@href'));

        $this->assertSame('website', $this->attr($xp, '//meta[@property="og:type"]/@content'));
        $this->assertSame('id_ID', $this->attr($xp, '//meta[@property="og:locale"]/@content'));
        $this->assertSame(self::BASE.'/', $this->attr($xp, '//meta[@property="og:url"]/@content'));
        $this->assertSame(self::BASE.'/og-default.jpg', $this->attr($xp, '//meta[@property="og:image"]/@content'));
        $this->assertSame('1200', $this->attr($xp, '//meta[@property="og:image:width"]/@content'));
        $this->assertSame('summary_large_image', $this->attr($xp, '//meta[@name="twitter:card"]/@content'));
        $this->assertSame('/images/gedung-sekolah.webp', $this->attr($xp, '//link[@rel="preload"]/@href'));

        // Tepat satu <title> dan satu description (React tidak boleh menambah duplikat).
        $this->assertSame(1, $xp->query('//title')->length);
        $this->assertSame(1, $xp->query('//meta[@name="description"]')->length);
        $this->assertStringContainsString('<noscript>', $response->getContent());
    }

    public function test_home_page_describes_the_school_with_structured_data(): void
    {
        $graph = $this->graph($this->get('/')->assertOk());

        $this->assertSame('SMK Budi Mulia Karawang', $graph['WebSite']['name']);
        $this->assertSame(self::BASE.'/#school', $graph['WebSite']['publisher']['@id']);

        $school = $graph['HighSchool'];
        $this->assertSame(self::BASE.'/#school', $school['@id']);
        $this->assertSame('SMK Budi Mulia Karawang', $school['name']);
        $this->assertSame('+62-267-865-8659', $school['telephone']);
        $this->assertSame('41361', $school['address']['postalCode']);
        $this->assertSame('ID', $school['address']['addressCountry']);
        $this->assertSame(-6.336754, $school['geo']['latitude']);
        $this->assertSame(self::BASE.'/logo_smk.png', $school['logo']);
    }

    public function test_search_filter_and_paging_variants_are_not_indexed_and_keep_the_home_canonical(): void
    {
        foreach (['/?q=robotik', '/?category=Prestasi', '/?type=video', '/?page=2'] as $url) {
            $xp = $this->xpath($this->get($url)->assertOk());

            $this->assertSame('noindex, follow', $this->attr($xp, '//meta[@name="robots"]/@content'), $url);
            $this->assertSame(self::BASE.'/', $this->attr($xp, '//link[@rel="canonical"]/@href'), $url);
        }
    }

    public function test_canonical_and_og_urls_ignore_a_spoofed_host_header(): void
    {
        $spoofed = ['HTTP_HOST' => 'evil.example', 'SERVER_NAME' => 'evil.example'];

        $home = $this->withServerVariables($spoofed)->get('/');
        $xp = $this->xpath($home);

        $this->assertSame(self::BASE.'/', $this->attr($xp, '//link[@rel="canonical"]/@href'));
        $this->assertSame(self::BASE.'/og-default.jpg', $this->attr($xp, '//meta[@property="og:image"]/@content'));
        $this->assertStringNotContainsString('evil.example', $home->getContent());
        $this->assertStringNotContainsString('evil.example', $this->withServerVariables($spoofed)->get('/sitemap.xml')->getContent());
        $this->assertStringNotContainsString('evil.example', $this->withServerVariables($spoofed)->get('/robots.txt')->getContent());
    }

    // ---- Berita ----------------------------------------------------------------

    public function test_post_page_has_its_own_meta_share_card_and_article_data(): void
    {
        $post = $this->makePost(['image' => 'post-image/01abc.jpg', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);

        $response = $this->get($post->path())->assertOk();
        $xp = $this->xpath($response);
        $url = self::BASE.$post->path();

        $this->assertSame('Juara Lomba Nasional - SMK Budi Mulia', $this->attr($xp, '//title'));
        $this->assertSame('Juara Lomba Nasional', $this->attr($xp, '//meta[@property="og:title"]/@content'));
        $this->assertSame('article', $this->attr($xp, '//meta[@property="og:type"]/@content'));
        $this->assertSame($url, $this->attr($xp, '//link[@rel="canonical"]/@href'));
        $this->assertSame(self::BASE.'/media/post-image/01abc.jpg', $this->attr($xp, '//meta[@property="og:image"]/@content'));
        $this->assertSame('Prestasi', $this->attr($xp, '//meta[@property="article:section"]/@content'));
        $this->assertStringStartsWith('2026-03-01T10:00:00', $this->attr($xp, '//meta[@property="article:published_time"]/@content'));
        $this->assertStringStartsWith('Tim robotik meraih juara pertama.', $this->attr($xp, '//meta[@name="description"]/@content'));
        $this->assertStringStartsWith('index, follow', $this->attr($xp, '//meta[@name="robots"]/@content'));
        $this->assertNull($this->attr($xp, '//link[@rel="preload"]/@href')); // preload hanya untuk hero beranda

        $graph = $this->graph($response);
        $this->assertSame($url.'#article', $graph['NewsArticle']['@id']);
        $this->assertSame('Juara Lomba Nasional', $graph['NewsArticle']['headline']);
        $this->assertSame(self::BASE.'/media/post-image/01abc.jpg', $graph['NewsArticle']['image'][0]);
        $this->assertSame(self::BASE.'/#school', $graph['NewsArticle']['publisher']['@id']);
        $this->assertCount(3, $graph['BreadcrumbList']['itemListElement']);
        $this->assertSame($url, $graph['BreadcrumbList']['itemListElement'][2]['item']);
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $graph['VideoObject']['embedUrl']);
    }

    public function test_post_without_a_photo_falls_back_to_the_default_share_image_or_youtube_thumbnail(): void
    {
        $plain = $this->makePost(['title' => 'Tanpa Foto']);
        $video = $this->makePost(['title' => 'Video Saja', 'type' => 'video', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ']);

        $this->assertSame(self::BASE.'/og-default.jpg', $this->attr($this->xpath($this->get($plain->path())), '//meta[@property="og:image"]/@content'));
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $this->attr($this->xpath($this->get($video->path())), '//meta[@property="og:image"]/@content'));
    }

    public function test_uploaded_video_gets_a_content_url(): void
    {
        $post = $this->makePost(['title' => 'Video Unggahan', 'type' => 'video', 'video_file' => 'post-video/01abc.mp4']);

        $video = $this->graph($this->get($post->path()))['VideoObject'];

        $this->assertSame(self::BASE.'/media/post-video/01abc.mp4', $video['contentUrl']);
        $this->assertSame(self::BASE.'/og-default.jpg', $video['thumbnailUrl'][0]);
    }

    public function test_titles_with_markup_cannot_break_out_of_meta_or_json_ld(): void
    {
        $post = $this->makePost(['title' => '"><script>alert(1)</script></script><img src=x onerror=alert(2)> & <b>', 'content' => '</script><script>alert(3)</script>']);

        $response = $this->get($post->path())->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('<img src=x', $html);

        // JSON-LD tetap JSON valid dan judulnya kembali utuh setelah di-decode.
        $article = $this->graph($response)['NewsArticle'];
        $this->assertStringContainsString('<script>alert(1)</script>', $article['headline']);
        // Tepat 1 blok ld+json dan tidak ada </script> nyasar di dalamnya.
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);
        $this->assertCount(1, $blocks[1]);
        $this->assertStringNotContainsString('</script', $blocks[1][0]);
    }

    public function test_old_id_links_and_stale_slugs_redirect_permanently_to_the_canonical_url(): void
    {
        $post = $this->makePost();

        $this->get('/berita/'.$post->id)->assertStatus(301)->assertRedirect(self::BASE.$post->path());
        $this->get('/berita/judul-yang-lama-'.$post->id)->assertStatus(301)->assertRedirect(self::BASE.$post->path());
        $this->get('/berita/'.$post->id.'?type=video')->assertStatus(301)->assertRedirect(self::BASE.$post->path().'?type=video');

        // Host palsu tidak boleh memengaruhi tujuan pengalihan.
        $spoofed = $this->withServerVariables(['HTTP_HOST' => 'evil.example', 'SERVER_NAME' => 'evil.example'])->get('/berita/'.$post->id);
        $this->assertSame(self::BASE.$post->path(), $spoofed->headers->get('location'));
        $this->get($post->path())->assertOk();
        $this->get('/berita/post_tidak_ada')->assertNotFound();
        $this->get('/berita/judul-apa-saja-post_tidak_ada')->assertNotFound();
    }

    public function test_old_site_addresses_redirect_permanently_to_their_new_section(): void
    {
        $expected = [
            '/profil/tentang-kami' => '/#profil',
            '/profil/visi-misi' => '/#profil',
            '/profil/sejarah-kepsek' => '/#kepemimpinan',
            '/profil/guru-staff' => '/#guru',
            '/jurusan' => '/#jurusan',
            '/pendaftaran' => '/#pendaftaran',
            '/kontak' => '/#kontak',
            '/postingan' => '/#berita',
        ];

        foreach ($expected as $old => $new) {
            $this->get($old)->assertStatus(301)->assertRedirect(self::BASE.$new);
        }
    }

    public function test_old_post_addresses_go_straight_to_the_canonical_post_url_in_one_hop(): void
    {
        $post = $this->makePost();

        $this->get('/postingan/'.$post->id)->assertStatus(301)->assertRedirect(self::BASE.$post->path());
        $this->get('/postingan/detail/'.$post->id)->assertStatus(301)->assertRedirect(self::BASE.$post->path());
        // Berita yang sudah dihapus: jangan 404, arahkan ke daftar berita.
        $this->get('/postingan/post_sudah_dihapus')->assertStatus(301)->assertRedirect(self::BASE.'/#berita');
        $this->get('/postingan/detail/post_sudah_dihapus')->assertStatus(301)->assertRedirect(self::BASE.'/#berita');
    }

    public function test_contact_form_post_still_works_next_to_the_legacy_get_redirect(): void
    {
        $this->post('/kontak', [])->assertSessionHasErrors(); // rute POST tidak tertimpa rute GET lama
    }

    public function test_post_paths_are_readable_short_and_stable(): void
    {
        $plain = $this->makePost();
        $this->assertSame('/berita/juara-lomba-nasional-'.$plain->id, $plain->path());

        $symbols = $this->makePost(['title' => '$(curl x)'.' !!!']);
        $this->assertSame('/berita/curl-x-'.$symbols->id, $symbols->path());

        $onlySymbols = $this->makePost(['title' => '???']);
        $this->assertSame('/berita/'.$onlySymbols->id, $onlySymbols->path());

        $long = $this->makePost(['title' => str_repeat('kegiatan sekolah ', 14)]);
        $this->assertLessThanOrEqual(70 + strlen($long->id) + strlen('/berita/-'), strlen($long->path()));
        $this->assertStringEndsWith('-'.$long->id, $long->path());
        $this->assertStringNotContainsString('--', $long->path());

        $this->assertSame('post_123', Post::idFromSegment('judul-post_123'));
        $this->assertSame('post_123', Post::idFromSegment('post_123'));
        $this->assertSame('post_01hxyz', Post::idFromSegment('a-b-c-post_01hxyz'));
    }

    public function test_landing_props_carry_the_canonical_post_url(): void
    {
        $post = $this->makePost();

        $props = $this->get('/')->viewData('page')['props'];

        $this->assertSame($post->path(), $props['posts']['data'][0]['url']);
        $this->assertSame(config('school.seo.title'), $props['school']['seo_title']);
    }

    // ---- robots.txt & sitemap.xml -----------------------------------------------

    public function test_robots_txt_points_crawlers_to_the_sitemap_and_hides_private_areas(): void
    {
        $response = $this->get('/robots.txt')->assertOk();

        $this->assertStringStartsWith('text/plain', $response->headers->get('content-type'));
        $body = $response->getContent();
        $this->assertStringContainsString("Allow: /\n", $body);
        $this->assertStringContainsString("Disallow: /admin\n", $body);
        $this->assertStringContainsString("Disallow: /portal\n", $body);
        $this->assertStringContainsString("Disallow: /skl\n", $body);
        $this->assertStringContainsString('Sitemap: '.self::BASE.'/sitemap.xml', $body);
        $this->assertStringNotContainsString("Disallow: /\n", $body);
    }

    public function test_sitemap_lists_home_and_every_post_with_valid_xml(): void
    {
        $a = $this->makePost(['title' => 'Berita A', 'image' => 'post-image/a.jpg', 'date' => '2026-03-01 10:00:00']);
        $b = $this->makePost(['title' => 'Kunjungan & "Industri"', 'date' => '2026-04-02 08:30:00']);

        $response = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringStartsWith('application/xml', $response->headers->get('content-type'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'sitemap harus XML valid');
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->registerXPathNamespace('i', 'http://www.google.com/schemas/sitemap-image/1.1');

        $locs = array_map('strval', $xml->xpath('//s:url/s:loc'));
        $this->assertSame([self::BASE.'/', self::BASE.$b->path(), self::BASE.$a->path()], $locs); // terbaru dulu
        $this->assertSame(self::BASE.'/media/post-image/a.jpg', (string) $xml->xpath('//s:url[s:loc="'.self::BASE.$a->path().'"]/i:image/i:loc')[0]);
        $this->assertStringStartsWith('2026-04-02T08:30:00', (string) $xml->xpath('//s:url[1]/s:lastmod')[0]); // beranda = berita terbaru
    }

    public function test_environments_that_are_not_production_are_closed_to_search_engines(): void
    {
        config(['school.seo.indexable' => null]); // otomatis: APP_ENV=testing bukan produksi

        $this->assertFalse(Seo::indexable());
        $this->assertSame("User-agent: *\nDisallow: /\n", $this->get('/robots.txt')->getContent());

        $home = $this->get('/')->assertHeader('x-robots-tag', 'noindex, nofollow');
        $this->assertSame('noindex, nofollow', $this->attr($this->xpath($home), '//meta[@name="robots"]/@content'));

        config(['school.seo.indexable' => false]);
        $this->assertFalse(Seo::indexable());
    }

    public function test_production_environment_is_indexable_by_default(): void
    {
        config(['school.seo.indexable' => null]);
        $this->app['env'] = 'production';

        $this->assertTrue(Seo::indexable());
    }

    // ---- Area privat --------------------------------------------------------------

    public function test_login_portal_and_admin_are_never_indexed(): void
    {
        $login = $this->get('/login')->assertOk()->assertHeader('x-robots-tag', 'noindex, nofollow');
        $xp = $this->xpath($login);
        $this->assertSame('noindex, nofollow', $this->attr($xp, '//meta[@name="robots"]/@content'));
        $this->assertNull($this->attr($xp, '//link[@rel="canonical"]/@href'));
        $this->assertNull($this->attr($xp, '//meta[@property="og:title"]/@content'));

        $this->actingAsAdmin();
        $admin = $this->get('/admin')->assertOk()->assertHeader('x-robots-tag', 'noindex, nofollow');
        $this->assertSame('noindex, nofollow', $this->attr($this->xpath($admin), '//meta[@name="robots"]/@content'));
    }

    public function test_static_robots_file_does_not_shadow_the_dynamic_route(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));
        $this->assertFileExists(public_path('og-default.jpg'));
        [$width, $height] = getimagesize(public_path('og-default.jpg'));
        $this->assertSame([1200, 630], [$width, $height]);
        $this->assertLessThan(300 * 1024, filesize(public_path('og-default.jpg'))); // batas aman pratinjau WhatsApp
    }
}
