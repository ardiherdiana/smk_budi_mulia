<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Kunci utama ULID string + kolom waktu camelCase, sama seperti model inti absensi.
 */
trait HasUlid
{
    public function initializeHasUlid(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    protected static function bootHasUlid(): void
    {
        static::creating(function ($model) {
            $model->id ??= (string) Str::ulid();
        });
    }

    public function getCreatedAtColumn(): string
    {
        return 'createdAt';
    }

    public function getUpdatedAtColumn(): string
    {
        return 'updatedAt';
    }
}
