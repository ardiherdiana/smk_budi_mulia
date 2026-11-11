<?php

namespace App\Models\Supervisi;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Skor satu butir pada satu lembar supervisi. `kategori` dan `uraian` disalin dari `config('supervisi.instrumen')`
 * saat lembar disiapkan (lihat JadwalSupervisi::siapkanSkor), jadi hasil lama tetap utuh bila teks instrumen berubah.
 */
#[Fillable(['supervisiId', 'kategori', 'uraian', 'urutan', 'skor', 'catatan'])]
class SupervisiSkor extends Model
{
    use HasUlid;

    protected $table = 'supervisi_skor';

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'skor' => 'integer',
        ];
    }

    public function supervisi(): BelongsTo
    {
        return $this->belongsTo(Supervisi::class, 'supervisiId');
    }
}
