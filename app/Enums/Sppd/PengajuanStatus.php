<?php

namespace App\Enums\Sppd;

enum PengajuanStatus: string
{
    /** Pemohon sudah mengajukan SPPD dan Kepala Sekolah belum memutuskan. */
    case DiajukanKeKepsek = 'diajukan_ke_kepsek';
    /** Ditolak Kepala Sekolah: pemohon merevisi pengajuan yang sama lalu mengajukannya ulang. */
    case Ditolak = 'ditolak';
    /** Disetujui Kepala Sekolah, tetapi TU belum mengetik nomor suratnya sehingga SPPD belum bisa diunduh. */
    case MenungguNomor = 'menunggu_nomor';
    case SedangDitugaskan = 'sedang_ditugaskan';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::DiajukanKeKepsek => 'Menunggu Persetujuan Kepala Sekolah',
            self::Ditolak => 'Ditolak',
            self::MenungguNomor => 'Menunggu Nomor Surat',
            self::SedangDitugaskan => 'Sedang Ditugaskan',
            self::Selesai => 'Selesai',
        };
    }

    /**
     * @return array<int, self>
     */
    public function nextAllowed(): array
    {
        return match ($this) {
            self::DiajukanKeKepsek => [self::MenungguNomor, self::Ditolak],
            self::Ditolak => [self::DiajukanKeKepsek],
            self::MenungguNomor => [self::SedangDitugaskan],
            self::SedangDitugaskan => [self::Selesai],
            self::Selesai => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->nextAllowed(), true);
    }
}
