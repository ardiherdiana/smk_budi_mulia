<?php

namespace App\Models\Sppd;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['pengajuan_id', 'ditulis_oleh', 'ringkasan'])]
class LaporanPerjalanan extends Model
{
    protected $table = 'laporan_perjalanans';

    /**
     * @return BelongsTo<PengajuanSppd, $this>
     */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanSppd::class, 'pengajuan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditulis_oleh');
    }

    /**
     * @return HasMany<LaporanFoto, $this>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(LaporanFoto::class, 'laporan_id')->orderBy('id');
    }
}
