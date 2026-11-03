<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Tabel `tentang_kami`: blok konten profil sekolah (sejarah, fasilitas, prestasi, ...). */
#[Fillable(['title', 'description', 'icon_type', 'display_order', 'is_active'])]
class About extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'tentang_kami';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
            'date' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (About $about) {
            $about->id ??= 'tentang_'.Str::lower((string) Str::ulid());
            $about->date ??= now();
        });
    }

    public function media(): HasMany
    {
        return $this->hasMany(AboutMedia::class, 'tentang_kami_id')->orderBy('display_order');
    }
}
