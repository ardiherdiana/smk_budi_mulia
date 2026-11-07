<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['hari', 'aktif', 'jamMulaiAbsen', 'jamMasuk', 'batasTelat', 'jamPulang', 'jamBriefing', 'jamSelesaiBriefing'])]
class JadwalHari extends Model
{
    protected $table = 'jadwal_hari';

    protected $primaryKey = 'hari';

    public $incrementing = false;

    protected $keyType = 'int';

    const CREATED_AT = null;

    const UPDATED_AT = 'updatedAt';

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }
}
