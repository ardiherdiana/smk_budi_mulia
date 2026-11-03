<?php

namespace App\Models\Classroom;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kelasId', 'grupId', 'tipe', 'judul', 'isi', 'deadline', 'poinMaks', 'izinTerlambat',
    'durasiMenit', 'mulaiAt', 'selesaiAt', 'acakSoal', 'tampilkanNilai', 'tokenAkses', 'dibuatOleh',
])]
class Post extends Model
{
    use HasUlid;

    protected $table = 'classroom_posts';

    /** Token ujian hanya boleh keluar lewat PostPresenter (untuk non-siswa), tidak lewat serialisasi model. */
    protected $hidden = ['tokenAkses'];

    protected function casts(): array
    {
        return [
            'deadline' => 'datetime',
            'mulaiAt' => 'datetime',
            'selesaiAt' => 'datetime',
            'izinTerlambat' => 'boolean',
            'acakSoal' => 'boolean',
            'tampilkanNilai' => 'boolean',
        ];
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelasId');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'dibuatOleh');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(Lampiran::class, 'postId');
    }

    public function komentar(): HasMany
    {
        return $this->hasMany(Komentar::class, 'postId');
    }

    public function pengumpulan(): HasMany
    {
        return $this->hasMany(Pengumpulan::class, 'postId');
    }

    public function soal(): HasMany
    {
        return $this->hasMany(Soal::class, 'postId')->orderBy('urut');
    }

    public function percobaan(): HasMany
    {
        return $this->hasMany(Percobaan::class, 'postId');
    }

    public function isTugas(): bool
    {
        return $this->tipe === 'TUGAS';
    }

    public function isUjian(): bool
    {
        return $this->tipe === 'UJIAN';
    }
}
