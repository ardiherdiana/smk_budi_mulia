<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Tabel `tentang_kami_media`: foto/video sebuah blok "Tentang Kami" (`file` = data-URI). */
#[Fillable(['tentang_kami_id', 'type', 'url', 'file', 'display_order'])]
class AboutMedia extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'tentang_kami_media';

    public $timestamps = false;

    public function scopeListing(Builder $query): Builder
    {
        return $query; // `file` kini path pendek
    }

    protected static function booted(): void
    {
        static::creating(function (AboutMedia $media) {
            $media->id ??= 'media_'.Str::lower((string) Str::ulid());
        });
    }
}
