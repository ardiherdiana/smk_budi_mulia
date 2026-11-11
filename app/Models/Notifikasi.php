<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * Notifikasi semua modul (absensi, SPPD, Supervisi) dalam satu tabel `notifikasi`, satu baris per penerima, bentuk
 * notifikasi database Laravel. Modul dibedakan dari `type` (kelas notifikasi); lonceng di topbar membacanya lewat
 * App\Services\NotifikasiTerpusat. Dihapus tepat 30 hari setelah dibuat, sudah dibaca atau belum.
 */
class Notifikasi extends DatabaseNotification
{
    use MassPrunable;

    public const MASA_SIMPAN_HARI = 30;

    protected $table = 'notifikasi';

    /** Dihapus `model:prune`, yang dijalankan routes/console.php tiap menit. */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', Carbon::now()->subDays(self::MASA_SIMPAN_HARI));
    }
}
