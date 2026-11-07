<?php

namespace App\Services\Sppd;

use App\Models\Sppd\PengajuanSppd;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Arsip SPPD: salinan PDF beku yang dibuat saat TU menandai perjalanan dinas selesai, lengkap dengan gambar tanda
 * tangan Kepala Sekolah. Berkas disimpan di disk `local` (privat); unduhannya hanya lewat PengajuanSppdController::downloadSppd
 * supaya kebijakan `view` tetap berlaku (rute /uploads/{path} tidak memeriksa policy).
 */
class ArsipSppd
{
    /** Gambar tanda tangan Kepala Sekolah di disk `local`; ganti isinya bila Kepala Sekolah berganti. */
    public const BERKAS_TTD = 'ttd-kepsek.jpeg';

    public function ttdTersedia(): bool
    {
        return Storage::disk('local')->exists(self::BERKAS_TTD);
    }

    public function ada(PengajuanSppd $pengajuan): bool
    {
        return Storage::disk('local')->exists($this->jalur($pengajuan));
    }

    /**
     * Render dan simpan arsip. Data yang tercetak (nama pejabat, akun anggaran, tanggal) dibekukan pada saat ini.
     */
    public function simpan(PengajuanSppd $pengajuan): void
    {
        $pengajuan->load(['pemohon', 'penyetuju', 'sppd', 'pengikuts']);

        $pdf = Pdf::loadView('pdf.sppd', [
            'pengajuan' => $pengajuan,
            // Dompdf membaca berkas lokal dengan "/" sebagai pemisah, juga di Windows.
            'ttdKepsek' => str_replace('\\', '/', Storage::disk('local')->path(self::BERKAS_TTD)),
        ])->setPaper('a4', 'landscape')->output();

        Storage::disk('local')->put($this->jalur($pengajuan), $pdf);
    }

    public function unduh(PengajuanSppd $pengajuan, string $namaBerkas): StreamedResponse
    {
        return Storage::disk('local')->download($this->jalur($pengajuan), $namaBerkas, ['Content-Type' => 'application/pdf']);
    }

    public function hapus(PengajuanSppd $pengajuan): void
    {
        Storage::disk('local')->delete($this->jalur($pengajuan));
    }

    private function jalur(PengajuanSppd $pengajuan): string
    {
        return "arsip-sppd/{$pengajuan->id}.pdf";
    }
}
