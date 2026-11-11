<?php

namespace App\Services;

use App\Models\Notifikasi;
use App\Models\User;
use App\Notifications\Absensi\AbsensiNotifikasi;
use App\Notifications\Supervisi\SupervisiNotifikasi;

/**
 * Notifikasi terpusat: daftar untuk lonceng di topbar Menu Utama. Semua modul memakai satu tabel `notifikasi`, satu
 * baris per penerima (App\Models\Notifikasi, lewat `User::notifications()`), jadi tiap akun hanya melihat dan menandai
 * notifikasinya sendiri. Modul dibedakan dari `type`:
 *
 * - `absensi` (AbsensiNotifikasi): absen masuk, absen pulang, dan pengajuan izin; dikirim ke admin dan kepsek.
 * - `supervisi` (SupervisiNotifikasi): jadwal/hasil supervisi guru.
 * - `sppd` (selain dua di atas, PengajuanStatusChanged): perubahan status pengajuan SPPD.
 *
 * Semua notifikasi dihapus otomatis 30 hari setelah dibuat (Notifikasi::prunable).
 */
class NotifikasiTerpusat
{
    public const BATAS = 30;

    /**
     * @return array{items: list<array{id: string, sumber: string, tipe: string, judul: string, pesan: string, waktu: string, dibaca: bool}>, belumDibaca: int}
     */
    public function untuk(User $user): array
    {
        $items = $user->notifications()->limit(self::BATAS)->get()->map(fn (Notifikasi $n) => [
            'id' => $n->id,
            'sumber' => $this->sumber($n),
            'tipe' => $this->tipe($n),
            'judul' => $this->judul($n),
            'pesan' => $n->data['pesan'] ?? 'Ada pembaruan.',
            'waktu' => $n->created_at->toIso8601String(),
            'dibaca' => $n->read_at !== null,
        ]);

        return [
            'items' => $items->values()->all(),
            'belumDibaca' => $user->unreadNotifications()->count(),
        ];
    }

    /** Tandai satu notifikasi milik akun ini dibaca dan kembalikan halaman tujuannya. Null bila tidak ditemukan. */
    public function baca(User $user, string $sumber, string $id): ?string
    {
        $notifikasi = $user->notifications()->where('id', $id)->first();
        if (! $notifikasi || $this->sumber($notifikasi) !== $sumber) {
            return null;
        }

        $notifikasi->markAsRead();

        return match ($sumber) {
            'absensi' => $this->tujuanAbsensi($notifikasi),
            'supervisi' => $this->tujuanSupervisi($notifikasi),
            default => $this->tujuanSppd($notifikasi),
        };
    }

    public function bacaSemua(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    private function sumber(Notifikasi $notifikasi): string
    {
        return match ($notifikasi->type) {
            AbsensiNotifikasi::class => 'absensi',
            SupervisiNotifikasi::class => 'supervisi',
            default => 'sppd',
        };
    }

    private function tipe(Notifikasi $notifikasi): string
    {
        return match ($this->sumber($notifikasi)) {
            'absensi' => $notifikasi->data['tipe'] ?? 'CHECKIN',
            'supervisi' => 'SUPERVISI',
            default => 'SPPD',
        };
    }

    private function judul(Notifikasi $notifikasi): string
    {
        return match ($this->sumber($notifikasi)) {
            'absensi' => $notifikasi->data['judul'] ?? 'Absensi',
            'supervisi' => 'Supervisi Guru',
            default => 'Pengajuan SPPD',
        };
    }

    /** Tidak ada FK ke baris absen/izin tertentu, jadi diarahkan ke halaman yang disaring ke guru dan tanggalnya. */
    private function tujuanAbsensi(Notifikasi $notifikasi): string
    {
        if (($notifikasi->data['tipe'] ?? null) === 'LEAVE_REQUEST') {
            return '/persetujuan?status=all';
        }

        $tanggal = $notifikasi->data['tanggal'] ?? $notifikasi->created_at->toDateString();

        return '/rekap?'.http_build_query(array_filter([
            'from' => $tanggal,
            'to' => $tanggal,
            'guruId' => $notifikasi->data['guru_id'] ?? null,
        ]));
    }

    private function tujuanSupervisi(Notifikasi $notifikasi): string
    {
        $supervisi = $notifikasi->data['supervisi_id'] ?? null;

        return $supervisi ? "/supervisi/{$supervisi}" : '/supervisi/dashboard';
    }

    private function tujuanSppd(Notifikasi $notifikasi): string
    {
        $pengajuan = $notifikasi->data['pengajuan_id'] ?? null;

        return $pengajuan ? "/sppd/pengajuan/{$pengajuan}" : '/sppd/dashboard';
    }
}
