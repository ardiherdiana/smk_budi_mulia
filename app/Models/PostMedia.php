<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Tabel `postingan_media`: galeri tambahan sebuah postingan (`file` = path file di disk `media`). */
#[Fillable(['postingan_id', 'type', 'url', 'file', 'display_order'])]
class PostMedia extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'postingan_media';

    public $timestamps = false;

    public function scopeListing(Builder $query): Builder
    {
        return $query; // `file` kini path pendek
    }

    protected static function booted(): void
    {
        static::creating(function (PostMedia $media) {
            $media->id ??= 'media_'.Str::lower((string) Str::ulid());
        });
    }
}
