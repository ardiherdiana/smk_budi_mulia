<?php

namespace App\Notifications\Absensi;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi absensi (absen masuk, absen pulang, pengajuan izin) untuk tiap admin dan kepala sekolah. Disimpan di tabel
 * bersama `notifikasi`; status dibacanya per orang. Dikirim oleh App\Services\NotificationService.
 */
class AbsensiNotifikasi extends Notification
{
    use Queueable;

    public const TIPE = ['CHECKIN', 'CHECKOUT', 'LEAVE_REQUEST'];

    public function __construct(
        public string $tipe,
        public string $judul,
        public string $pesan,
        public ?string $guruId = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'tipe' => $this->tipe,
            'judul' => $this->judul,
            'pesan' => $this->pesan,
            'guru_id' => $this->guruId,
            'tanggal' => now()->toDateString(),
        ];
    }
}
