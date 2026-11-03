<?php

namespace Tests\Unit;

use App\Support\VideoUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VideoUrlTest extends TestCase
{
    #[DataProvider('youtubeUrls')]
    public function test_youtube_ids_are_extracted(string $url): void
    {
        $this->assertSame('dQw4w9WgXcQ', VideoUrl::youtubeId($url));
        $this->assertTrue(VideoUrl::isValid($url));
    }

    public static function youtubeUrls(): array
    {
        return [
            ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            ['https://youtube.com/watch?feature=share&v=dQw4w9WgXcQ'],
            ['https://youtu.be/dQw4w9WgXcQ'],
            ['https://www.youtube.com/embed/dQw4w9WgXcQ'],
            ['https://www.youtube.com/shorts/dQw4w9WgXcQ'],
        ];
    }

    #[DataProvider('rejectedUrls')]
    public function test_non_video_or_dangerous_urls_are_rejected(?string $url): void
    {
        $this->assertFalse(VideoUrl::isValid($url));
        $this->assertNull(VideoUrl::parse($url));
    }

    public static function rejectedUrls(): array
    {
        return [
            [null],
            [''],
            ['javascript:alert(1)'],
            ['data:text/html;base64,PHNjcmlwdD4='],
            ['https://vimeo.com/123'],
            ['https://example.com/halaman.html'],
            ['ftp://example.com/klip.mp4'],
            ['https://www.youtube.com/watch?v=pendek'],
            ['https://youtube.com.evil.example/'],
        ];
    }

    public function test_direct_video_links_are_accepted(): void
    {
        $this->assertSame(['kind' => 'file', 'src' => 'https://cdn.example/klip.MP4?x=1'], VideoUrl::parse('https://cdn.example/klip.MP4?x=1'));
        $this->assertSame(['kind' => 'youtube', 'id' => 'dQw4w9WgXcQ'], VideoUrl::parse('https://youtu.be/dQw4w9WgXcQ'));
    }
}
