<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['guruId', 'tanggal', 'waktu', 'status', 'catatan'])]
class BriefingAttendance extends Model
{
    protected $table = 'briefing_attendance';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected static function booted(): void
    {
        static::creating(function (BriefingAttendance $briefing) {
            $briefing->id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'waktu' => 'datetime',
        ];
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'guruId');
    }
}
