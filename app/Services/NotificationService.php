<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\Absensi\AbsensiNotifikasi;
use Illuminate\Support\Facades\Notification;

/**
 * Mengirim notifikasi absensi (absen masuk, pulang, pengajuan izin) ke tiap admin dan kepala sekolah aktif, di tabel
 * bersama `notifikasi`. Menampilkan dan menandai dibaca ada di NotifikasiTerpusat (lonceng topbar Menu Utama).
 */
class NotificationService
{
    public function create(string $type, string $judul, string $pesan, ?string $guruId = null): void
    {
        $penerima = User::penerimaNotifikasiAbsensi()->get();
        if ($penerima->isNotEmpty()) {
            Notification::send($penerima, new AbsensiNotifikasi($type, $judul, $pesan, $guruId));
        }
    }
}
