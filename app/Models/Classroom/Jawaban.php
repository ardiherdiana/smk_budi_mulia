<?php

namespace App\Models\Classroom;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['percobaanId', 'soalId', 'jawaban', 'skor'])]
class Jawaban extends Model
{
    use HasUlid;

    protected $table = 'jawaban';

    protected function casts(): array
    {
        return [
            'skor' => 'float',
        ];
    }

    public function percobaan(): BelongsTo
    {
        return $this->belongsTo(Percobaan::class, 'percobaanId');
    }

    public function soal(): BelongsTo
    {
        return $this->belongsTo(Soal::class, 'soalId');
    }
}
