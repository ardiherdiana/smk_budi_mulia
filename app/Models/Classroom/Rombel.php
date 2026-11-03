<?php

namespace App\Models\Classroom;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'tingkat', 'jurusan', 'waliKelasId'])]
class Rombel extends Model
{
    use HasUlid;

    protected $table = 'rombel';

    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'waliKelasId');
    }

    public function siswa(): HasMany
    {
        return $this->hasMany(Siswa::class, 'rombelId');
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'rombelId');
    }
}
