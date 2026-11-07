<?php

namespace App\Models\Sppd;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table(name: 'pengaturans', key: 'kunci', keyType: 'string', incrementing: false)]
#[Fillable(['kunci', 'nilai'])]
class Pengaturan extends Model
{
    public static function ambil(string $kunci, ?string $bawaan = null): ?string
    {
        return static::query()->whereKey($kunci)->value('nilai') ?? $bawaan;
    }

    public static function simpan(string $kunci, ?string $nilai): void
    {
        static::query()->updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
    }
}
