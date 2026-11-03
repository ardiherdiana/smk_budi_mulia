<?php

namespace App\Support;

/** Memilah link video: YouTube (di-embed via youtube-nocookie) atau file video langsung (http/https). */
class VideoUrl
{
    private const YOUTUBE = '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~';

    private const DIRECT = '~^https?://[^\s]+\.(?:mp4|webm|ogg)(?:\?[^\s]*)?$~i';

    public static function isValid(?string $url): bool
    {
        return $url !== null && $url !== '' && (self::youtubeId($url) !== null || (bool) preg_match(self::DIRECT, $url));
    }

    public static function youtubeId(?string $url): ?string
    {
        if ($url && preg_match(self::YOUTUBE, $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /** @return array{kind: 'youtube', id: string}|array{kind: 'file', src: string}|null */
    public static function parse(?string $url): ?array
    {
        if ($id = self::youtubeId($url)) {
            return ['kind' => 'youtube', 'id' => $id];
        }

        if ($url && preg_match(self::DIRECT, $url)) {
            return ['kind' => 'file', 'src' => $url];
        }

        return null;
    }
}
