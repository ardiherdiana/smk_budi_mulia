<?php

namespace App\Models\Classroom;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['rombelId', 'mapelId', 'guruId', 'tahunAjaranId', 'deskripsi', 'diarsipkan'])]
class Kelas extends Model
{
    use HasUlid;

    protected $table = 'kelas';

    protected function casts(): array
    {
        return [
            'diarsipkan' => 'boolean',
            'guruId' => 'integer',
        ];
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'rombelId');
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class, 'mapelId');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'guruId');
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahunAjaranId');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'kelasId');
    }

    /** Batasi ke ruang kelas milik `$user`: guru pengajar, siswa di rombelnya; admin dan kepsek melihat semua. */
    public function scopeUntukPengguna(Builder $query, Akun $user): void
    {
        match ($user->role) {
            'GURU' => $query->where('guruId', $user->id),
            'SISWA' => $query->where('rombelId', $user->siswa?->rombelId ?? '-'),
            default => null,
        };
    }

    /** Apakah `$user` boleh membuka ruang kelas ini (guru pengajar, admin, kepsek, atau siswa di rombelnya). */
    public function dapatDiakses(Akun $user): bool
    {
        return match ($user->role) {
            'ADMIN', 'KEPSEK' => true,
            'GURU' => $this->guruId === $user->id,
            'SISWA' => $user->siswa?->rombelId === $this->rombelId,
            default => false,
        };
    }

    /** Apakah `$user` boleh mengubah isi ruang kelas ini (guru pengajar atau admin). */
    public function dapatDikelola(Akun $user): bool
    {
        return $user->role === 'ADMIN' || ($user->role === 'GURU' && $this->guruId === $user->id);
    }
}
