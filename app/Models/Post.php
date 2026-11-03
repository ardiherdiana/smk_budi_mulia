<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Tabel `postingan`. Kolom `image` & `video_file` berisi PATH file di disk `media`
 * (mis. `post-image/01k....webp`), bukan base64. Ubah lewat MediaService, jangan tulis manual.
 */
#[Fillable(['title', 'content', 'image', 'type', 'category', 'author', 'date', 'video_url', 'video_file'])]
class Post extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'postingan';

    public $timestamps = false;

    protected function casts(): array
    {
        return ['date' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            $post->id ??= 'post_'.Str::lower((string) Str::ulid());
            $post->date ??= now();
        });
    }

    /**
     * Tautan publik: `/berita/{slug-judul}-{id}`. Judul membuat URL terbaca dan berisi kata kunci;
     * id di ujung tetap satu-satunya kunci pencarian, jadi judul boleh berubah tanpa memutus tautan lama.
     */
    public function path(): string
    {
        $slug = Str::slug((string) $this->title);
        if (strlen($slug) > 70) {
            $slug = substr($slug, 0, 70);
            $slug = str_contains($slug, '-') ? substr($slug, 0, (int) strrpos($slug, '-')) : $slug;
        }

        return '/berita/'.($slug === '' ? '' : $slug.'-').$this->id;
    }

    /** Id postingan dari segmen URL: `judul-post_123` atau `post_123` (tautan lama). */
    public static function idFromSegment(string $segment): string
    {
        return preg_match('/(post_[A-Za-z0-9]+)$/', $segment, $m) === 1 ? $m[1] : $segment;
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class, 'postingan_id')->orderBy('display_order');
    }

    /** Kolom `image` & `video_file` kini path file pendek (bukan base64), jadi aman diambil semuanya. */
    public function scopeListing(Builder $query): Builder
    {
        return $query;
    }

    public function scopeFilter(Builder $query, ?string $type, ?string $category, ?string $search): Builder
    {
        return $query
            ->when(in_array($type, ['berita', 'video'], true), fn ($q) => $q->where('type', $type))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($search, function ($q) use ($search) {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('content', 'like', $like));
            });
    }
}
