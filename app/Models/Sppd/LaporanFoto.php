<?php

namespace App\Models\Sppd;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['laporan_id', 'path', 'nama_asli'])]
class LaporanFoto extends Model
{
    protected $table = 'laporan_perjalanan_fotos';

    /**
     * @return BelongsTo<LaporanPerjalanan, $this>
     */
    public function laporan(): BelongsTo
    {
        return $this->belongsTo(LaporanPerjalanan::class, 'laporan_id');
    }
}
