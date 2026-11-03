<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabel `jurusan`: program keahlian. */
#[Fillable(['name', 'description', 'icon', 'image', 'video_url', 'color'])]
class Major extends Model
{
    protected $table = 'jurusan';

    const UPDATED_AT = null;

    public function scopeListing(Builder $query): Builder
    {
        return $query; // `image` kini path pendek
    }
}
