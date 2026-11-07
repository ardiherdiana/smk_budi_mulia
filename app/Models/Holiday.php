<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['tanggal', 'keterangan'])]
class Holiday extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (Holiday $holiday) {
            $holiday->id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }
}
