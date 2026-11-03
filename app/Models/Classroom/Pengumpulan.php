<?php

namespace App\Models\Classroom;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['postId', 'siswaId', 'teks', 'status', 'terlambat', 'nilai', 'feedback', 'submittedAt', 'gradedAt'])]
class Pengumpulan extends Model
{
    use HasUlid;

    protected $table = 'pengumpulan';

    protected function casts(): array
    {
        return [
            'terlambat' => 'boolean',
            'nilai' => 'float',
            'submittedAt' => 'datetime',
            'gradedAt' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'postId');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswaId');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(Lampiran::class, 'pengumpulanId');
    }
}
