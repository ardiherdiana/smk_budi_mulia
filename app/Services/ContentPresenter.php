<?php

namespace App\Services;

use App\Models\About;
use App\Models\Major;
use App\Models\Post;
use App\Models\Principal;
use App\Models\Staff;
use App\Support\VideoUrl;
use Illuminate\Support\Str;

/**
 * Mengubah model (hasil scopeListing, tanpa blob) menjadi array props untuk
 * Inertia. Blob media tidak pernah ikut - hanya URL `/media/...`.
 */
class ContentPresenter
{
    public function __construct(private MediaService $media) {}

    /** @return array<string, mixed> */
    public function postCard(Post $post): array
    {
        $cover = $this->media->url($post->image);

        return [
            'id' => $post->id,
            'url' => $post->path(),
            'title' => $post->title,
            'excerpt' => Str::limit(trim(preg_replace('/\s+/', ' ', (string) $post->content)), 160),
            'type' => $post->type,
            'category' => $post->category ?: null,
            'author' => $post->author,
            'date' => $post->date?->toIso8601String(),
            'cover_url' => $cover,
            'has_video' => $this->media->isPath($post->video_file) || VideoUrl::isValid($post->video_url),
            'youtube_id' => VideoUrl::youtubeId($post->video_url),
        ];
    }

    /** @return array<string, mixed> */
    public function postDetail(Post $post): array
    {
        $card = $this->postCard($post);

        $gallery = $post->relationLoaded('media')
            ? $post->media->where('type', 'image')->map(fn ($m) => [
                'id' => $m->id,
                'url' => $this->mediaUrl($m->file, $m->url),
            ])->filter(fn ($m) => $m['url'])->values()->all()
            : [];

        $video = null;
        if ($this->media->isPath($post->video_file)) {
            $video = ['kind' => 'upload', 'src' => $this->media->url($post->video_file)];
        } elseif ($parsed = VideoUrl::parse($post->video_url)) {
            $video = $parsed;
        }

        return [
            ...$card,
            'content' => (string) $post->content,
            'gallery' => $gallery,
            'video' => $video,
        ];
    }

    /** @return array<string, mixed> */
    public function major(Major $major): array
    {
        return [
            'id' => $major->id,
            'name' => $major->name,
            'description' => $major->description,
            'icon' => $major->icon,
            'color' => $major->color,
            'image_url' => $this->media->url($major->image),
            'video_url' => $major->video_url,
            'youtube_id' => VideoUrl::youtubeId($major->video_url),
        ];
    }

    /** @return array<string, mixed> */
    public function about(About $about): array
    {
        return [
            'id' => $about->id,
            'title' => $about->title,
            'description' => $about->description,
            'icon_type' => $about->icon_type,
            'display_order' => $about->display_order,
            'is_active' => $about->is_active,
            'media' => $about->media->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'url' => $this->mediaUrl($m->file, $m->url),
            ])->filter(fn ($m) => $m['url'])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function staff(Staff $staff): array
    {
        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $staff->role,
            'type' => $staff->type,
            'subject' => $staff->subject,
            'expertise' => $staff->expertise,
            'position' => $staff->position,
            'image_url' => $this->media->url($staff->image),
        ];
    }

    /** @return array<string, mixed> */
    public function principal(Principal $principal): array
    {
        return [
            'id' => $principal->id,
            'nama' => $principal->nama,
            'tahun_jabatan' => $principal->tahun_jabatan,
            'foto_url' => $this->media->url($principal->foto),
        ];
    }

    /** URL file media; bila kosong, jatuh ke tautan luar (http/https saja) yang mungkin tersimpan di kolom `url`. */
    private function mediaUrl(?string $path, ?string $external): ?string
    {
        if ($url = $this->media->url($path)) {
            return $url;
        }

        return $external && preg_match('#^https?://#i', $external) ? $external : null;
    }
}
