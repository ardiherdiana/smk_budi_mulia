<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tabel `files`: berkas (mis. SKL) milik satu siswa, disimpan di disk privat. */
#[Fillable(['user_id', 'nama_file', 'path_file'])]
class PortalFile extends Model
{
    protected $table = 'files';

    const CREATED_AT = 'uploaded_at';

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['uploaded_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * "Gudang": berkas yang belum dibagikan. Baris lama memakai akun
     * gudang@system.internal, berkas baru memakai user_id NULL (kolom nullable
     * di skema produksi).
     */
    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('user_id')
                ->orWhereIn('user_id', User::query()->where('email', 'gudang@system.internal')->select('id'));
        });
    }
}
