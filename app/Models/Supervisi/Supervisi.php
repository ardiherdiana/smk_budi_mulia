<?php

namespace App\Models\Supervisi;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Mapel;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\TahunAjaran;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu jadwal + lembar "Instrumen Observasi Pembelajaran Mendalam" untuk satu guru (Penyusun Perencanaan), diisi
 * supervisor (Pemberi Umpan Balik). Alur: DIJADWALKAN (lembar diisi, bisa disimpan berkali-kali) -> SELESAI (Jumlah
 * dan Total Score dihitung, lembar terkunci, guru bisa membaca hasilnya). DIBATALKAN hanya dari DIJADWALKAN.
 * Kepsek/admin bisa membuka kunci SELESAI kembali ke DIJADWALKAN.
 */
#[Fillable([
    'tahunAjaranId', 'guruId', 'supervisorId', 'dibuatOlehId', 'mapelId', 'rombelId', 'tanggal', 'status',
    'kelebihan', 'halDitingkatkan', 'rekomendasi', 'jumlahSkor', 'totalSkor', 'selesaiAt',
])]
class Supervisi extends Model
{
    use HasUlid;

    public const DIJADWALKAN = 'DIJADWALKAN';

    public const SELESAI = 'SELESAI';

    public const DIBATALKAN = 'DIBATALKAN';

    public const STATUS = [self::DIJADWALKAN, self::SELESAI, self::DIBATALKAN];

    protected $table = 'supervisi';

    protected function casts(): array
    {
        return [
            'guruId' => 'integer',
            'supervisorId' => 'integer',
            'dibuatOlehId' => 'integer',
            'tanggal' => 'date',
            'jumlahSkor' => 'integer',
            'totalSkor' => 'float',
            'selesaiAt' => 'datetime',
        ];
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class, 'tahunAjaranId');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'guruId');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'supervisorId');
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'dibuatOlehId');
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class, 'mapelId');
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'rombelId');
    }

    /** @return HasMany<SupervisiSkor, $this> */
    public function skor(): HasMany
    {
        return $this->hasMany(SupervisiSkor::class, 'supervisiId')->orderBy('urutan');
    }

    public function bisaDiisi(): bool
    {
        return $this->status === self::DIJADWALKAN;
    }

    public function selesai(): bool
    {
        return $this->status === self::SELESAI;
    }
}
