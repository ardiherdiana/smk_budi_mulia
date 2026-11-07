<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['guruId', 'jenis', 'tanggalMulai', 'tanggalSelesai', 'alasan', 'lampiranUrl', 'status', 'reviewedBy', 'reviewedAt'])]
class LeaveRequest extends Model
{
    protected $table = 'leave_requests';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected static function booted(): void
    {
        static::creating(function (LeaveRequest $leave) {
            $leave->id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'tanggalMulai' => 'date',
            'tanggalSelesai' => 'date',
            'reviewedAt' => 'datetime',
        ];
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'guruId');
    }
}
