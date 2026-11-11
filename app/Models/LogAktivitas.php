<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Satu baris log aktivitas bersama semua modul (tambah/ubah/hapus data, masuk/keluar/gagal masuk). Ditulis oleh
 * App\Support\LogAktivitas\Perekam, dibaca halaman Log Aktivitas (admin). Dihapus tepat 30 hari setelah dibuat.
 * Riwayat persetujuan SPPD tidak di sini (tetap permanen di `log_audits`).
 */
#[Fillable([
    'userId', 'namaPengguna', 'modul', 'aksi', 'entitas', 'entitasTipe', 'entitasId', 'deskripsi', 'perubahan', 'ip',
    'userAgent', 'createdAt',
])]
class LogAktivitas extends Model
{
    use MassPrunable;

    public const MASA_SIMPAN_HARI = 30;

    public const TAMBAH = 'tambah';

    public const UBAH = 'ubah';

    public const HAPUS = 'hapus';

    public const MASUK = 'masuk';

    public const KELUAR = 'keluar';

    public const GAGAL_MASUK = 'gagal_masuk';

    public const LAIN = 'lain';

    public const AKSI = [self::TAMBAH, self::UBAH, self::HAPUS, self::MASUK, self::KELUAR, self::GAGAL_MASUK, self::LAIN];

    protected $table = 'log_aktivitas';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'userId' => 'integer',
            'perubahan' => 'array',
            'createdAt' => 'datetime',
        ];
    }

    /** Dihapus `model:prune`, yang dijalankan routes/console.php tiap menit. */
    public function prunable(): Builder
    {
        return static::where('createdAt', '<=', Carbon::now()->subDays(self::MASA_SIMPAN_HARI));
    }
}
