<?php

namespace App\Policies\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;

class PengajuanSppdPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        if ($user->hasAnyRole([RoleName::KepalaSekolah->value, RoleName::Tu->value, RoleName::Bendahara->value])) {
            return true;
        }

        return $user->id === $pengajuanSppd->pemohon_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Pemohon->value);
    }

    /**
     * Setuju atau tolak: hanya Kepala Sekolah, dan hanya selama pengajuan menunggu keputusannya.
     */
    public function approve(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        return $user->hasRole(RoleName::KepalaSekolah->value) && $pengajuanSppd->status === PengajuanStatus::DiajukanKeKepsek;
    }

    /**
     * Pemohon memperbaiki pengajuannya sendiri yang ditolak Kepala Sekolah, lalu mengajukannya ulang.
     */
    public function revisi(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        return $user->id === $pengajuanSppd->pemohon_id && $pengajuanSppd->status === PengajuanStatus::Ditolak;
    }

    /**
     * TU mengetik nomor surat secara manual setelah Kepala Sekolah menyetujui. Sesudah terisi masih bisa diperbaiki
     * sampai SPPD diarsipkan; selesai berarti salinan PDF sudah beku, jadi nomornya tidak boleh berubah lagi.
     */
    public function isiNomor(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        return $user->hasRole(RoleName::Tu->value)
            && in_array($pengajuanSppd->status, [PengajuanStatus::MenungguNomor, PengajuanStatus::SedangDitugaskan], true);
    }

    /**
     * Akun anggaran (baris 10 SPPD) dilengkapi TU atau bendahara selama perjalanan berlangsung; pemohon tidak
     * mengisinya karena biasanya tidak tahu kode akun. Setelah selesai, SPPD sudah diarsipkan sebagai salinan beku,
     * jadi isinya tidak boleh berubah lagi.
     */
    public function isiAkunAnggaran(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        return $user->hasAnyRole([RoleName::Tu->value, RoleName::Bendahara->value])
            && $pengajuanSppd->sppd()->exists()
            && $pengajuanSppd->status === PengajuanStatus::SedangDitugaskan;
    }

    /**
     * Laporan tetap bisa diisi dan diubah setelah TU menandai perjalanan selesai: pemohon sering baru sempat
     * menulisnya sesudah kembali, dan arsip tidak mengunci laporan.
     */
    public function isiLaporan(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        return $user->id === $pengajuanSppd->pemohon_id
            && in_array($pengajuanSppd->status, [PengajuanStatus::SedangDitugaskan, PengajuanStatus::Selesai], true);
    }

    public function cairkanLumsum(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        return $user->hasRole(RoleName::Bendahara->value) && $pengajuanSppd->status === PengajuanStatus::SedangDitugaskan;
    }

    public function selesaikan(User $user, PengajuanSppd $pengajuanSppd): bool
    {
        return $user->hasRole(RoleName::Tu->value) && $pengajuanSppd->status === PengajuanStatus::SedangDitugaskan;
    }
}
