<?php

namespace App\Services\Sppd;

use App\Enums\Sppd\RoleName;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;

/**
 * Data tampilan SPPD untuk template PDF (resources/views/pdf/sppd.blade.php).
 */
class SppdPdfData
{
    public const SEKOLAH = 'SMK Budi Mulia Karawang';

    /**
     * @return array<string, mixed>
     */
    public function data(PengajuanSppd $pengajuan): array
    {
        $sppd = $pengajuan->sppd;

        return [
            'sppd' => $sppd,
            'pemohon' => $pengajuan->pemohon,
            'namaPejabat' => $this->namaPejabat($pengajuan),

            'tanggalTerbit' => $sppd->created_at->translatedFormat('j F Y'),
            'tanggalBerangkat' => $pengajuan->tanggal_berangkat->translatedFormat('j F Y'),
            'tanggalKembali' => $pengajuan->tanggal_kembali->translatedFormat('j F Y'),
            'jamBerangkat' => str_replace(':', '.', substr((string) $pengajuan->jam_berangkat, 0, 5)),
            'jamKembali' => str_replace(':', '.', substr((string) $pengajuan->jam_kembali, 0, 5)),
            'lamaHari' => (int) $pengajuan->tanggal_berangkat->copy()->startOfDay()
                ->diffInDays($pengajuan->tanggal_kembali->copy()->startOfDay()) + 1,

            'sekolah' => self::SEKOLAH,
            'tujuan' => $pengajuan->tujuan,

            'pengikuts' => $pengajuan->pengikuts,
            'alatAngkutan' => $pengajuan->alat_angkutan,
            'keterangan' => $pengajuan->keterangan,
            'akun' => $sppd->akun_anggaran,
            'lumsum' => $this->lumsum($pengajuan),
        ];
    }

    /**
     * Baris 11 (Lumsum Biaya): total lumsum yang dicairkan bendahara, mis. "Rp500.000". Kosong bila belum ada,
     * supaya bisa ditulis tangan.
     */
    private function lumsum(PengajuanSppd $pengajuan): ?string
    {
        $total = (float) $pengajuan->pencairans->sum('jumlah');

        return $total > 0 ? 'Rp'.number_format($total, 0, ',', '.') : null;
    }

    /**
     * Kuasa Pengguna Anggaran yang namanya dicetak: penyetuju pada SPPD lama (dulu disetujui di aplikasi),
     * selain itu akun aktif berperan Kepala Sekolah. Tanda tangannya diisi pulpen di kertas.
     */
    private function namaPejabat(PengajuanSppd $pengajuan): string
    {
        return $pengajuan->penyetuju?->name
            ?? User::role(RoleName::KepalaSekolah->value)
                ->whereHas('akun', fn ($q) => $q->where('is_active', true))
                ->orderBy('id')
                ->first()?->name
            ?? '..............................';
    }
}
