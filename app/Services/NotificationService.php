<?php

namespace App\Services;

use App\Models\Notification;

/**
 * Membuat notifikasi absensi (absen masuk, pulang, pengajuan izin). Menampilkan dan menandai dibaca ada di
 * NotifikasiTerpusat (lonceng topbar Menu Utama).
 */
class NotificationService
{
    public function create(string $type, string $judul, string $pesan, ?string $guruId = null): Notification
    {
        return Notification::create([
            'type' => $type,
            'judul' => $judul,
            'pesan' => $pesan,
            'guruId' => $guruId,
        ]);
    }
}
