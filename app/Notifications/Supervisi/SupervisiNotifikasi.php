<?php

namespace App\Notifications\Supervisi;

use App\Models\Supervisi\Supervisi;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi modul Supervisi (dijadwalkan, diubah, selesai, sudah dibaca guru). Disimpan lewat channel database yang
 * sama dengan SPPD (`User::notifications()` -> `sppd_notifications`); NotifikasiTerpusat membedakannya dari `type`.
 */
class SupervisiNotifikasi extends Notification
{
    use Queueable;

    public function __construct(
        public Supervisi $supervisi,
        public string $pesan,
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
            'supervisi_id' => $this->supervisi->id,
            'status' => $this->supervisi->status,
            'pesan' => $this->pesan,
        ];
    }
}
