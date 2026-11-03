<?php

namespace App\Models\Classroom;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['userId', 'rombelId', 'nis', 'nama'])]
class Siswa extends Model
{
    use HasUlid;

    protected $table = 'siswa';

    public function user(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'userId');
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'rombelId');
    }

    public function pengumpulan(): HasMany
    {
        return $this->hasMany(Pengumpulan::class, 'siswaId');
    }

    public function percobaan(): HasMany
    {
        return $this->hasMany(Percobaan::class, 'siswaId');
    }
}
