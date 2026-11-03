<?php

namespace App\Models\Classroom;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['postId', 'siswaId', 'mulaiAt', 'batasAt', 'selesaiAt', 'skorOtomatis', 'skorManual', 'status'])]
class Percobaan extends Model
{
    use HasUlid;

    protected $table = 'percobaan';

    protected function casts(): array
    {
        return [
            'mulaiAt' => 'datetime',
            'batasAt' => 'datetime',
            'selesaiAt' => 'datetime',
            'skorOtomatis' => 'float',
            'skorManual' => 'float',
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

    public function jawaban(): HasMany
    {
        return $this->hasMany(Jawaban::class, 'percobaanId');
    }

    public function skorTotal(): float
    {
        return round($this->skorOtomatis + ($this->skorManual ?? 0), 2);
    }
}
