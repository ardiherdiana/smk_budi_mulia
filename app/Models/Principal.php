<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabel `principals`: riwayat kepala sekolah (`foto` = path file di disk `media`). */
#[Fillable(['nama', 'tahun_jabatan', 'foto'])]
class Principal extends Model
{
    protected $table = 'principals';

    const UPDATED_AT = null;

    public function scopeListing(Builder $query): Builder
    {
        return $query; // `foto` kini path pendek
    }
}
