<?php

namespace App\Services\Supervisi;

use App\Models\Supervisi\Supervisi;
use App\Models\User;

/**
 * Siapa boleh apa di modul Supervisi. Sengaja bukan Policy: `Gate::before` meloloskan admin untuk semua ability,
 * padahal penilaian milik guru yang disupervisi baru boleh ia lihat setelah SELESAI.
 *
 * - Kelola (admin, KEPSEK): jadwal, rekap, melihat dan mengisi semua lembar, membuka kunci.
 * - Supervisor yang ditugaskan: melihat dan mengisi lembar yang ditugaskan kepadanya.
 * - Guru yang disupervisi: melihat jadwalnya; penilaian baru terlihat setelah SELESAI.
 */
class AksesSupervisi
{
    public function kelola(User $user): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->peran === 'KEPSEK');
    }

    public function lihat(User $user, Supervisi $supervisi): bool
    {
        return $this->isi($user, $supervisi) || $this->guruPemilik($user, $supervisi);
    }

    /** Mengisi lembar penilaian (status tetap diperiksa terpisah). */
    public function isi(User $user, Supervisi $supervisi): bool
    {
        return $this->kelola($user) || $supervisi->supervisorId === $user->id;
    }

    /** Skor dan catatan supervisor: penilai selalu, guru yang disupervisi hanya setelah SELESAI. */
    public function lihatPenilaian(User $user, Supervisi $supervisi): bool
    {
        return $this->isi($user, $supervisi) || ($this->guruPemilik($user, $supervisi) && $supervisi->selesai());
    }

    public function guruPemilik(User $user, Supervisi $supervisi): bool
    {
        return $supervisi->guruId === $user->id;
    }

    public function punyaTugas(User $user): bool
    {
        return Supervisi::where('supervisorId', $user->id)->exists();
    }

    /** Menu sidebar Supervisi untuk pengguna ini. @return array{kelola: bool, tugas: bool, disupervisi: bool} */
    public function menu(User $user): array
    {
        return [
            'kelola' => $this->kelola($user),
            'tugas' => $user->peran === 'KEPSEK' || $this->punyaTugas($user),
            'disupervisi' => $user->peran === 'GURU',
        ];
    }
}
