<?php

namespace Tests\Feature;

use App\Services\MediaService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MediaAndSecurityTest extends TestCase
{
    public function test_valid_media_paths_become_relative_public_urls(): void
    {
        $media = app(MediaService::class);

        $this->assertSame('/media/post-image/01abc.jpg', $media->url('post-image/01abc.jpg'));
        $this->assertSame('/media/post-video/post_1775-3fa9c1d2.mp4', $media->url('post-video/post_1775-3fa9c1d2.mp4'));
        $this->assertNull($media->url(null));
        $this->assertNull($media->url(''));
    }

    /** @return array<string, array{string}> */
    public static function unsafeValues(): array
    {
        return [
            'traversal' => ['../../.env'],
            'traversal in kind' => ['post-image/../../.env.jpg'],
            'absolute' => ['/etc/passwd'],
            'php extension' => ['post-image/shell.php'],
            'double extension' => ['post-image/shell.php.jpg.php'],
            'svg' => ['post-image/x.svg'],
            'html' => ['post-image/x.html'],
            'backslash' => ['post-image\\x.jpg'],
            'no directory' => ['x.jpg'],
            'scheme' => ['https://evil.example/x.jpg'],
            'legacy base64' => ['data:image/png;base64,AAAA'],
            'null byte' => ["post-image/x.jpg\0.php"],
        ];
    }

    #[DataProvider('unsafeValues')]
    public function test_unsafe_values_never_become_urls_or_get_deleted(string $value): void
    {
        $media = app(MediaService::class);
        Storage::disk('media')->put('post-image/keep.jpg', 'x');

        $this->assertNull($media->url($value));
        $media->delete($value);

        Storage::disk('media')->assertExists('post-image/keep.jpg');
    }

    public function test_store_and_delete_round_trip_with_real_bytes(): void
    {
        $media = app(MediaService::class);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $path = $media->storeBytes($png, 'image/png', 'post-image');

        $this->assertMatchesRegularExpression('#^post-image/[0-9a-z]{26}\.png$#', $path);
        Storage::disk('media')->assertExists($path);
        $this->assertSame($png, Storage::disk('media')->get($path));

        $media->delete($path);
        Storage::disk('media')->assertMissing($path);
    }

    public function test_only_image_and_video_mimes_can_be_stored(): void
    {
        $this->expectException(\RuntimeException::class);

        app(MediaService::class)->storeBytes('<?php echo 1;', 'text/html', 'post-image');
    }

    public function test_upload_directory_blocks_script_execution(): void
    {
        $htaccess = (string) file_get_contents(public_path('media/.htaccess'));

        $this->assertStringContainsString('php_flag engine off', $htaccess);
        $this->assertStringContainsString('Require all denied', $htaccess);
        $this->assertStringContainsString('-Indexes', $htaccess);
        $this->assertStringContainsString('jpe?g|png|webp|mp4|webm|ogv', $htaccess);
    }

    public function test_security_headers_and_csp_nonce_are_sent_with_matching_inline_script(): void
    {
        $response = $this->get('/')->assertOk();

        $csp = $response->headers->get('content-security-policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", explode('style-src', $csp)[0]); // script-src tanpa unsafe-inline
        $this->assertStringNotContainsString('unsafe-eval', $csp);

        preg_match("/script-src[^;]*'nonce-([^']+)'/", $csp, $header);
        preg_match('/<script nonce="([^"]+)">/', $response->getContent(), $inline);
        $this->assertNotEmpty($header[1] ?? null);
        $this->assertSame($header[1], $inline[1] ?? null);

        $response->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('x-frame-options', 'SAMEORIGIN')
            ->assertHeader('referrer-policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', $response->headers->get('permissions-policy'));
    }

    public function test_nonce_changes_on_every_request(): void
    {
        preg_match("/nonce-([^']+)'/", $this->get('/')->headers->get('content-security-policy'), $a);
        preg_match("/nonce-([^']+)'/", $this->get('/')->headers->get('content-security-policy'), $b);

        $this->assertNotSame($a[1], $b[1]);
    }

    public function test_authenticated_pages_are_not_cacheable(): void
    {
        $this->actingAsAdmin();

        $cache = $this->get('/admin')->headers->get('cache-control');

        $this->assertStringContainsString('no-store', $cache);
    }

    public function test_hsts_is_only_sent_over_https(): void
    {
        $this->get('/')->assertHeaderMissing('strict-transport-security');
        $this->get('https://localhost/')->assertHeader('strict-transport-security');
    }

    public function test_sensitive_files_are_not_web_accessible(): void
    {
        $this->assertFileDoesNotExist(public_path('.env'));
        $this->assertFileDoesNotExist(public_path('storage'));
        $this->assertFalse(File::exists(public_path('uploads')));
    }
}
