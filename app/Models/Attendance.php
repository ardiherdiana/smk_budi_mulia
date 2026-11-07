<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['guruId', 'tanggal', 'jamMasuk', 'jamPulang', 'statusMasuk', 'statusPulang', 'catatan'])]
class Attendance extends Model
{
    protected $table = 'attendance';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected static function booted(): void
    {
        static::creating(function (Attendance $attendance) {
            $attendance->id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jamMasuk' => 'datetime',
            'jamPulang' => 'datetime',
        ];
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'guruId');
    }
}
