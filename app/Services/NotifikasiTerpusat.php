<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Sppd\SppdNotification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Notifikasi terpusat: satu daftar untuk lonceng di topbar Menu Utama, gabungan dua sumber.
 *
 * - `absensi`: absen masuk, absen pulang, dan pengajuan izin (tabel `notifications`). Status dibacanya bersama untuk
 *   semua penerima, dan hanya admin serta kepsek yang menerimanya (sama seperti halaman notifikasi absensi dulu).
 *   Dihapus otomatis 7 hari setelah dibuat (Notification::prunable).
 * - `sppd`: perubahan status pengajuan SPPD, per akun (tabel `sppd_notifications`).
 */
class NotifikasiTerpusat
{
    public const BATAS = 30;

    public function bolehAbsensi(User $user): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->peran === 'KEPSEK');
    }

    public function bolehSppd(User $user): bool
    {
        return $user->is_active && $user->isStaf();
    }

    /**
     * @return array{items: list<array{id: string, sumber: string, tipe: string, judul: string, pesan: string, waktu: string, dibaca: bool}>, belumDibaca: int}
     */
    public function untuk(User $user): array
    {
        $items = new Collection;
        $belumDibaca = 0;

        if ($this->bolehAbsensi($user)) {
            $items = $items->concat(
                Notification::orderByDesc('createdAt')->limit(self::BATAS)->get()->map(fn (Notification $n) => [
                    'id' => $n->id,
                    'sumber' => 'absensi',
                    'tipe' => $n->type,
                    'judul' => $n->judul,
                    'pesan' => $n->pesan,
                    'waktu' => $n->createdAt->toIso8601String(),
                    'dibaca' => (bool) $n->isRead,
                ])
            );
            $belumDibaca += Notification::where('isRead', false)->count();
        }

        if ($this->bolehSppd($user)) {
            $items = $items->concat(
                $user->notifications()->limit(self::BATAS)->get()->map(fn (SppdNotification $n) => [
                    'id' => $n->id,
                    'sumber' => 'sppd',
                    'tipe' => 'SPPD',
                    'judul' => 'Pengajuan SPPD',
                    'pesan' => $n->data['pesan'] ?? 'Ada pembaruan pada pengajuan SPPD.',
                    'waktu' => $n->created_at->toIso8601String(),
                    'dibaca' => $n->read_at !== null,
                ])
            );
            $belumDibaca += $user->unreadNotifications()->count();
        }

        return [
            'items' => $items->sortByDesc(fn (array $n) => strtotime($n['waktu']))->take(self::BATAS)->values()->all(),
            'belumDibaca' => $belumDibaca,
        ];
    }

    /** Tandai satu notifikasi dibaca dan kembalikan halaman tujuannya. Null bila tidak ditemukan atau bukan haknya. */
    public function baca(User $user, string $sumber, string $id): ?string
    {
        if ($sumber === 'absensi' && $this->bolehAbsensi($user)) {
            $notifikasi = Notification::find($id);
            $notifikasi?->update(['isRead' => true]);

            return $notifikasi ? $this->tujuanAbsensi($notifikasi) : null;
        }

        if ($sumber === 'sppd' && $this->bolehSppd($user)) {
            $notifikasi = $user->notifications()->where('id', $id)->first();
            $notifikasi?->markAsRead();

            return $notifikasi ? $this->tujuanSppd($notifikasi) : null;
        }

        return null;
    }

    public function bacaSemua(User $user): void
    {
        if ($this->bolehAbsensi($user)) {
            Notification::where('isRead', false)->update(['isRead' => true]);
        }

        if ($this->bolehSppd($user)) {
            $user->unreadNotifications()->update(['read_at' => now()]);
        }
    }

    /** Tidak ada FK ke baris absen/izin tertentu, jadi diarahkan ke halaman yang disaring ke guru dan tanggalnya. */
    private function tujuanAbsensi(Notification $notifikasi): string
    {
        if ($notifikasi->type === 'LEAVE_REQUEST') {
            return '/persetujuan?status=all';
        }

        $tanggal = $notifikasi->createdAt->toDateString();

        return '/rekap?'.http_build_query(array_filter([
            'from' => $tanggal,
            'to' => $tanggal,
            'guruId' => $notifikasi->guruId,
        ]));
    }

    private function tujuanSppd(SppdNotification $notifikasi): string
    {
        $pengajuan = $notifikasi->data['pengajuan_id'] ?? null;

        return $pengajuan ? "/sppd/pengajuan/{$pengajuan}" : '/sppd/dashboard';
    }
}
