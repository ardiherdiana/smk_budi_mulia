<?php

namespace App\Models\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Models\Classroom\Akun;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'pemohon_id', 'tujuan', 'maksud', 'alat_angkutan', 'keterangan',
    'tanggal_berangkat', 'jam_berangkat', 'tanggal_kembali', 'jam_kembali',
    'status', 'undangan_path',
    'catatan_kepsek', 'disetujui_oleh', 'disetujui_at',
    'ditolak_at',
])]
class PengajuanSppd extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            // Id pengguna (`users.id` int); dibandingkan dengan === di Policy.
            'pemohon_id' => 'integer',
            'disetujui_oleh' => 'integer',
            'status' => PengajuanStatus::class,
            'tanggal_berangkat' => 'date',
            'tanggal_kembali' => 'date',
            'disetujui_at' => 'datetime',
            'ditolak_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pemohon_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /**
     * @return HasOne<Sppd, $this>
     */
    public function sppd(): HasOne
    {
        return $this->hasOne(Sppd::class, 'pengajuan_id');
    }

    /**
     * Laporan perjalanan (rangkuman hasil dinas beserta foto dokumentasi) yang diisi pemohon.
     *
     * @return HasOne<LaporanPerjalanan, $this>
     */
    public function laporan(): HasOne
    {
        return $this->hasOne(LaporanPerjalanan::class, 'pengajuan_id');
    }

    /**
     * Pegawai yang ikut dalam perjalanan dinas (kolom 8 SPPD).
     *
     * @return BelongsToMany<User, $this>
     */
    public function pengikuts(): BelongsToMany
    {
        // Urut nama; nama ada di profil (`profil_akun`), bukan di `users`.
        return $this->belongsToMany(User::class, 'pengajuan_pengikut', 'pengajuan_id', 'user_id')
            ->orderBy(Akun::select('name')->whereColumn('profil_akun.id', 'users.id'));
    }

    /**
     * @return HasMany<Pencairan, $this>
     */
    public function pencairans(): HasMany
    {
        return $this->hasMany(Pencairan::class, 'pengajuan_id');
    }
}
