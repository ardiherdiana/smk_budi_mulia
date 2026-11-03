<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Foto & video situs disimpan sebagai FILE di disk `media` (`public/media`), dan kolom database hanya
 * berisi path relatifnya (mis. `post-image/01k....jpg`). Web server melayani file itu langsung, lengkap dengan
 * cache dan Range untuk video.
 *
 * Keamanan:
 *  - hanya gambar (jpg/png/webp) dan video (mp4/webm/ogv) yang pernah ditulis; tipe ditentukan dari ISI file
 *    (finfo), bukan dari nama atau header klien;
 *  - unggahan gambar di-decode, di-resize, dan di-encode ulang (membuang EXIF & payload tersembunyi);
 *  - nama file dibuat server (ULID atau id + hash), tidak pernah dari nama unggahan;
 *  - path dari database divalidasi ketat sebelum dijadikan URL atau dihapus (tanpa `..`, tanpa direktori lain).
 */
class MediaService
{
    public const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/ogg'];

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogv',
    ];

    private const PATH_PATTERN = '#^[a-z][a-z-]*/[A-Za-z0-9_-]+\.(?:jpg|png|webp|mp4|webm|ogv)$#';

    public function disk(): Filesystem
    {
        return Storage::disk('media');
    }

    /** Apakah nilai kolom sudah berupa path media yang sah (bukan base64/data-URI lama). */
    public function isPath(?string $value): bool
    {
        return $value !== null && strlen($value) <= 200 && preg_match(self::PATH_PATTERN, $value) === 1;
    }

    /** URL publik sebuah path media, atau null bila kosong/bukan path sah. */
    public function url(?string $path): ?string
    {
        return $this->isPath($path) ? $this->disk()->url($path) : null;
    }

    public function delete(?string $path): void
    {
        if ($this->isPath($path)) {
            $this->disk()->delete($path);
        }
    }

    /** Simpan byte mentah yang sudah divalidasi. Mengembalikan path relatif. */
    public function storeBytes(string $bytes, string $mime, string $kind, ?string $name = null): string
    {
        $extension = self::EXTENSIONS[$mime] ?? throw new RuntimeException("Tipe media tidak diizinkan: {$mime}");
        $path = $this->pathFor($kind, $name, $extension);

        $this->disk()->put($path, $bytes);
        if ($this->disk()->size($path) !== strlen($bytes)) {
            $this->disk()->delete($path);
            throw new RuntimeException("Gagal menulis file media: {$path}");
        }

        return $path;
    }

    /** Gambar unggahan -> file (webp/jpeg/png, sudah di-resize & bersih dari metadata). */
    public function storeImage(UploadedFile $file, string $kind, string $field = 'image'): string
    {
        [$binary, $mime] = $this->encodeImage($file, $field);

        return $this->storeBytes($binary, $mime, $kind);
    }

    public function storeVideo(UploadedFile $file, string $kind, string $field = 'video_file'): string
    {
        $mime = $this->sniffMime($file);
        if (! in_array($mime, self::VIDEO_MIMES, true)) {
            throw ValidationException::withMessages([$field => 'Format video harus MP4, WebM, atau OGG.']);
        }

        $path = $this->pathFor($kind, null, self::EXTENSIONS[$mime]);
        $this->disk()->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }

    /**
     * Decode nilai kolom LAMA (data-URI, base64 mentah, atau biner) ke byte + MIME hasil sniffing.
     * Null bila kosong atau bukan gambar/video yang diizinkan. Dipakai migrator dan seeder.
     *
     * @return array{bytes: string, mime: string}|null
     */
    public function decode(string $stored): ?array
    {
        $stored = trim($stored);
        if ($stored === '') {
            return null;
        }

        if (str_starts_with($stored, 'data:') && ($comma = strpos($stored, ',')) !== false) {
            $header = substr($stored, 0, $comma);
            $payload = substr($stored, $comma + 1);
            $bytes = str_contains($header, ';base64') ? base64_decode($payload, true) : rawurldecode($payload);
        } elseif (preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', substr($stored, 0, 256))) {
            $bytes = base64_decode($stored, true);
        } else {
            $bytes = $stored;
        }

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! in_array($mime, [...self::IMAGE_MIMES, ...self::VIDEO_MIMES], true)) {
            return null;
        }

        return ['bytes' => $bytes, 'mime' => $mime];
    }

    public function extensionFor(string $mime): ?string
    {
        return self::EXTENSIONS[$mime] ?? null;
    }

    private function pathFor(string $kind, ?string $name, string $extension): string
    {
        $name ??= Str::lower((string) Str::ulid());

        return $kind.'/'.$name.'.'.$extension;
    }

    /** @return array{0: string, 1: string} [biner, mime] */
    private function encodeImage(UploadedFile $file, string $field): array
    {
        $path = $file->getRealPath();
        $mime = $this->sniffMime($file);
        if (! in_array($mime, self::IMAGE_MIMES, true)) {
            throw ValidationException::withMessages([$field => 'Format gambar harus JPG, PNG, atau WebP.']);
        }

        $dimensions = @getimagesize($path);
        if ($dimensions === false || $dimensions[0] * $dimensions[1] > 40_000_000) {
            throw ValidationException::withMessages([$field => 'File gambar rusak atau resolusinya terlalu besar.']);
        }

        // GD di beberapa server tidak punya decoder/encoder JPEG/WebP. Bila format ini
        // tidak bisa diproses, simpan berkas asli (MIME sudah diverifikasi dari isinya).
        if (! $this->canProcess($mime)) {
            return [(string) file_get_contents($path), $mime];
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));
        if ($source === false) {
            throw ValidationException::withMessages([$field => 'File gambar rusak atau tidak dapat dibaca.']);
        }

        $source = $this->applyExifOrientation($source, $path, $mime);

        $width = imagesx($source);
        $height = imagesy($source);
        $maxSide = (int) config('school.media.image_max_side', 1600);
        $scale = min(1, $maxSide / max($width, $height));
        $targetW = max(1, (int) round($width * $scale));
        $targetH = max(1, (int) round($height * $scale));

        $hasWebp = (imagetypes() & IMG_WEBP) === IMG_WEBP;
        $hasJpeg = (imagetypes() & IMG_JPG) === IMG_JPG;

        $target = imagecreatetruecolor($targetW, $targetH);
        if ($hasWebp || ! $hasJpeg) {
            // Keluaran WebP/PNG mendukung transparansi.
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        } else {
            // JPEG tidak mendukung alpha: latar putih agar PNG transparan tidak menjadi hitam.
            imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        }
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetW, $targetH, $width, $height);

        ob_start();
        if ($hasWebp) {
            imagewebp($target, null, 82);
            $outMime = 'image/webp';
        } elseif ($hasJpeg) {
            imagejpeg($target, null, 84);
            $outMime = 'image/jpeg';
        } else {
            imagepng($target, null, 7);
            $outMime = 'image/png';
        }
        $binary = (string) ob_get_clean();

        return [$binary, $outMime];
    }

    /** MIME dari ISI berkas (finfo), tidak percaya nama file maupun header Content-Type dari klien. */
    private function sniffMime(UploadedFile $file): string
    {
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
    }

    /** Apakah GD di server ini bisa membaca format tersebut DAN menulis salah satu format keluaran. */
    private function canProcess(string $mime): bool
    {
        $types = imagetypes();

        return match ($mime) {
            'image/jpeg' => ($types & IMG_JPG) === IMG_JPG,
            'image/png' => ($types & IMG_PNG) === IMG_PNG,
            'image/webp' => ($types & IMG_WEBP) === IMG_WEBP,
            default => false,
        };
    }

    private function applyExifOrientation(\GdImage $image, string $path, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $rotated = match ($exif['Orientation'] ?? 1) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated ?: $image;
    }
}
