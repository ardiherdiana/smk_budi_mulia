<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['userId', 'nama', 'noHp', 'mapel', 'fotoUrl', 'aktif', 'qrToken'])]
class Guru extends Model
{
    use HasFactory;

    protected $table = 'guru';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected static function booted(): void
    {
        static::creating(function (Guru $guru) {
            $guru->id ??= (string) Str::ulid();
            $guru->qrToken ??= bin2hex(random_bytes(16));
        });
    }

    protected function casts(): array
    {
        return [
            'userId' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * Foto yang ditampilkan di absensi: foto orangnya di website (guru: `guru_staff`, kepala sekolah: `principals`),
     * sama dengan Data Guru Classroom. Foto absen lama (`fotoUrl`) hanya dipakai bila di website belum ada fotonya.
     */
    public function fotoTampil(): ?string
    {
        return $this->user?->akun?->fotoUrl() ?? $this->fotoUrl;
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'guruId');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'guruId');
    }

    public function briefingAttendances(): HasMany
    {
        return $this->hasMany(BriefingAttendance::class, 'guruId');
    }
}
