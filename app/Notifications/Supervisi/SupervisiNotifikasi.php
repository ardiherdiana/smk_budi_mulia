<?php

namespace App\Notifications\Supervisi;

use App\Models\Supervisi\Supervisi;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi modul Supervisi (dijadwalkan, diubah, dibatalkan, selesai). Disimpan di tabel bersama `notifikasi`
 * (`User::notifications()`); NotifikasiTerpusat membedakannya dari `type`.
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
