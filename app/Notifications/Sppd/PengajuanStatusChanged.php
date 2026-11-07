<?php

namespace App\Notifications\Sppd;

use App\Models\Sppd\PengajuanSppd;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PengajuanStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public PengajuanSppd $pengajuan,
        public string $pesan,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'pengajuan_id' => $this->pengajuan->id,
            'status' => $this->pengajuan->status->value,
            'pesan' => $this->pesan,
        ];
    }
}
