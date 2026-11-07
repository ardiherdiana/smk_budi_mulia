<?php

namespace App\Models\Sppd;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pengajuan_id', 'nomor_sppd', 'akun_anggaran', 'diterbitkan_oleh'])]
class Sppd extends Model
{
    use HasFactory;

    /**
     * @return BelongsTo<PengajuanSppd, $this>
     */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanSppd::class, 'pengajuan_id');
    }
}
