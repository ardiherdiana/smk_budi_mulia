<?php

namespace App\Models\Sppd;

use App\Enums\Sppd\JenisPencairan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['pengajuan_id', 'jenis', 'jumlah', 'tanggal', 'dicairkan_oleh', 'keterangan'])]
class Pencairan extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'jenis' => JenisPencairan::class,
            'jumlah' => 'decimal:2',
            'tanggal' => 'date',
        ];
    }

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
    public function pencair(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicairkan_oleh');
    }
}
