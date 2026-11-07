<?php

namespace App\Services\Sppd;

use App\Enums\Sppd\JenisPencairan;
use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Models\Sppd\LaporanPerjalanan;
use App\Models\Sppd\LogAudit;
use App\Models\Sppd\Pencairan;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use App\Notifications\Sppd\PengajuanStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PengajuanWorkflowService
{
    public function __construct(private readonly ArsipSppd $arsip) {}

    /**
     * Pemohon mengajukan SPPD ke Kepala Sekolah: pengajuan dan pengikut disimpan tanpa nomor surat. Nomor baru diminta
     * ke TU sesudah Kepala Sekolah menyetujui.
     *
     * @param  array<string, mixed>  $data  Kolom PengajuanSppd (tanpa pemohon_id dan status).
     * @param  array<int, string>  $pengikutIds
     */
    public function buat(User $pemohon, array $data, array $pengikutIds = []): PengajuanSppd
    {
        $pengajuan = DB::transaction(function () use ($pemohon, $data, $pengikutIds): PengajuanSppd {
            $pengajuan = PengajuanSppd::create($data + [
                'pemohon_id' => $pemohon->id,
                'status' => PengajuanStatus::DiajukanKeKepsek,
            ]);

            $pengajuan->pengikuts()->sync($pengikutIds);

            $this->log($pengajuan, $pemohon, 'SPPD diajukan ke Kepala Sekolah.');

            return $pengajuan;
        });

        $this->notifyRole(RoleName::KepalaSekolah, $pengajuan, 'Pengajuan SPPD baru menunggu persetujuan Anda.');

        return $pengajuan->fresh();
    }

    /**
     * Kepala Sekolah menyetujui: pengajuan lanjut ke TU untuk diberi nomor surat. Persetujuan hanya mencatat penyetuju
     * dan waktunya; kolom tanda tangan di PDF tetap kosong untuk pulpen.
     *
     * @throws RuntimeException
     */
    public function setujui(PengajuanSppd $pengajuan, User $kepsek, ?string $catatan): PengajuanSppd
    {
        $this->guardTransition($pengajuan, PengajuanStatus::MenungguNomor);

        DB::transaction(function () use ($pengajuan, $kepsek, $catatan): void {
            $pengajuan->update([
                'disetujui_oleh' => $kepsek->id,
                'disetujui_at' => now(),
                'catatan_kepsek' => $catatan === null || trim($catatan) === '' ? null : trim($catatan),
            ]);

            $this->transition($pengajuan, PengajuanStatus::MenungguNomor, $kepsek, 'Pengajuan disetujui Kepala Sekolah, menunggu nomor surat dari TU.');
        });

        $this->notifyRole(RoleName::Tu, $pengajuan, 'Pengajuan SPPD disetujui, silakan isi nomor surat.');
        $this->notifyUser($pengajuan->pemohon, $pengajuan, 'Pengajuan SPPD Anda disetujui Kepala Sekolah. Menunggu TU mengisi nomor surat.');

        return $pengajuan->fresh();
    }

    /**
     * Kepala Sekolah menolak dengan alasan. Pengajuan yang sama dibuka lagi untuk direvisi pemohon, sehingga nomor
     * surat tidak pernah diminta untuk pengajuan yang belum disetujui.
     *
     * @throws RuntimeException
     */
    public function tolak(PengajuanSppd $pengajuan, User $kepsek, string $catatan): PengajuanSppd
    {
        $this->guardTransition($pengajuan, PengajuanStatus::Ditolak);

        DB::transaction(function () use ($pengajuan, $kepsek, $catatan): void {
            $pengajuan->update([
                'catatan_kepsek' => $catatan,
                'ditolak_at' => now(),
            ]);

            $this->transition($pengajuan, PengajuanStatus::Ditolak, $kepsek, "Pengajuan ditolak: {$catatan}");
        });

        $this->notifyUser($pengajuan->pemohon, $pengajuan, 'Pengajuan SPPD Anda ditolak Kepala Sekolah. Silakan revisi lalu ajukan ulang.');

        return $pengajuan->fresh();
    }

    /**
     * Pemohon memperbaiki pengajuan yang ditolak lalu mengajukannya ulang ke Kepala Sekolah. Alasan penolakan
     * sebelumnya tetap terbaca di riwayat audit; kolom catatan dikosongkan agar hanya memuat keputusan terbaru.
     *
     * @param  array<string, mixed>  $data  Kolom PengajuanSppd yang diperbarui (`undangan_path` hanya bila berkas diganti).
     * @param  array<int, string>  $pengikutIds
     *
     * @throws RuntimeException
     */
    public function revisi(PengajuanSppd $pengajuan, User $pemohon, array $data, array $pengikutIds = []): PengajuanSppd
    {
        $this->guardTransition($pengajuan, PengajuanStatus::DiajukanKeKepsek);

        $undanganLama = isset($data['undangan_path']) ? $pengajuan->undangan_path : null;

        DB::transaction(function () use ($pengajuan, $pemohon, $data, $pengikutIds): void {
            $pengajuan->update($data + ['catatan_kepsek' => null, 'ditolak_at' => null]);

            $pengajuan->pengikuts()->sync($pengikutIds);

            $this->transition($pengajuan, PengajuanStatus::DiajukanKeKepsek, $pemohon, 'Pengajuan direvisi dan diajukan ulang ke Kepala Sekolah.');
        });

        if ($undanganLama) {
            Storage::disk('public')->delete($undanganLama);
        }

        $this->notifyRole(RoleName::KepalaSekolah, $pengajuan, 'Pengajuan SPPD yang direvisi menunggu persetujuan Anda.');

        return $pengajuan->fresh();
    }

    /**
     * TU mengetik nomor surat SPPD secara manual. Pertama kali: SPPD diterbitkan (baris `sppds` dibuat, status
     * berpindah ke sedang ditugaskan) dan pemohon boleh mengunduhnya. Sesudahnya, selama belum selesai, TU masih
     * bisa memperbaiki nomor yang salah ketik.
     */
    public function isiNomor(PengajuanSppd $pengajuan, User $tu, string $nomor): PengajuanSppd
    {
        $nomor = trim($nomor);

        $nomorLama = DB::transaction(function () use ($pengajuan, $tu, $nomor): ?string {
            $sppd = $pengajuan->sppd;

            if ($sppd) {
                $nomorLama = $sppd->nomor_sppd;
                $sppd->update(['nomor_sppd' => $nomor]);

                $this->log($pengajuan, $tu, "Nomor SPPD diubah dari {$nomorLama} menjadi {$nomor}.");

                return $nomorLama;
            }

            $pengajuan->sppd()->create(['nomor_sppd' => $nomor, 'diterbitkan_oleh' => $tu->id]);
            $this->transition($pengajuan, PengajuanStatus::SedangDitugaskan, $tu, "Nomor SPPD diisi TU: {$nomor}.");

            return null;
        });

        if ($nomorLama === null) {
            $this->notifyUser($pengajuan->pemohon, $pengajuan, "SPPD Anda sudah bernomor {$nomor} dan siap diunduh.");
            $this->notifyRole(RoleName::Bendahara, $pengajuan, "SPPD {$nomor} terbit, siap dicairkan lumsumnya (jika ada).");
        }

        return $pengajuan->fresh();
    }

    public function isiAkunAnggaran(PengajuanSppd $pengajuan, User $pengisi, ?string $akunAnggaran): void
    {
        $akunAnggaran = $akunAnggaran === null || trim($akunAnggaran) === '' ? null : trim($akunAnggaran);

        $pengajuan->sppd->update(['akun_anggaran' => $akunAnggaran]);

        $this->log($pengajuan, $pengisi, $akunAnggaran === null
            ? 'Akun anggaran dikosongkan.'
            : "Akun anggaran diisi: {$akunAnggaran}");
    }

    /**
     * Pemohon mengisi atau memperbarui laporan perjalanan: rangkuman hasil dinas dan foto dokumentasi.
     * Foto lama yang ada di $hapusFotoIds dibuang (berkasnya ikut dihapus); $fotoBaru adalah daftar [path, nama asli]
     * berkas yang sudah tersimpan di disk.
     *
     * @param  array<int, int|string>  $hapusFotoIds
     * @param  array<int, array{path: string, nama_asli: ?string}>  $fotoBaru
     */
    public function simpanLaporan(PengajuanSppd $pengajuan, User $pemohon, string $ringkasan, array $hapusFotoIds, array $fotoBaru): LaporanPerjalanan
    {
        $baru = ! $pengajuan->laporan()->exists();

        $laporan = DB::transaction(function () use ($pengajuan, $pemohon, $ringkasan, $hapusFotoIds, $fotoBaru): LaporanPerjalanan {
            $laporan = $pengajuan->laporan()->updateOrCreate([], [
                'ditulis_oleh' => $pemohon->id,
                'ringkasan' => $ringkasan,
            ]);

            $dibuang = $laporan->fotos()->whereIn('id', $hapusFotoIds)->get();
            $dibuang->each->delete();
            Storage::disk('public')->delete($dibuang->pluck('path')->all());

            foreach ($fotoBaru as $foto) {
                $laporan->fotos()->create($foto);
            }

            return $laporan;
        });

        $this->log($pengajuan, $pemohon, ($baru ? 'Laporan perjalanan diisi' : 'Laporan perjalanan diperbarui').' oleh pemohon ('.$laporan->fotos()->count().' foto dokumentasi).');

        if ($baru) {
            $this->notifyRole(RoleName::Tu, $pengajuan, 'Pemohon telah mengisi laporan perjalanan dinas.');
        }

        return $laporan;
    }

    public function cairkanLumsum(PengajuanSppd $pengajuan, User $bendahara, float $jumlah, ?string $keterangan): Pencairan
    {
        $pencairan = $pengajuan->pencairans()->create([
            'jenis' => JenisPencairan::Lumsum,
            'jumlah' => $jumlah,
            'tanggal' => now()->toDateString(),
            'dicairkan_oleh' => $bendahara->id,
            'keterangan' => $keterangan,
        ]);

        $this->log($pengajuan, $bendahara, 'Pencairan '.JenisPencairan::Lumsum->label().' sebesar Rp'.number_format($jumlah, 0, ',', '.'));

        $this->notifyUser($pengajuan->pemohon, $pengajuan, 'Lumsum perjalanan dinas Anda telah dicairkan.');

        return $pencairan;
    }

    /**
     * TU menandai perjalanan dinas selesai: SPPD diarsipkan sebagai salinan PDF beku yang sudah berisi tanda tangan
     * Kepala Sekolah. Tanpa berkas tanda tangan arsip tidak dibuat dan status tidak berubah.
     *
     * @throws RuntimeException
     */
    public function selesaikan(PengajuanSppd $pengajuan, User $tu): PengajuanSppd
    {
        $this->guardTransition($pengajuan, PengajuanStatus::Selesai);

        if (! $this->arsip->ttdTersedia()) {
            throw new RuntimeException('Berkas tanda tangan Kepala Sekolah tidak ditemukan, arsip belum bisa dibuat. Hubungi admin.');
        }

        $this->arsip->simpan($pengajuan);

        try {
            $this->transition($pengajuan, PengajuanStatus::Selesai, $tu, 'Perjalanan dinas selesai & SPPD bertanda tangan diarsipkan oleh TU.');
        } catch (\Throwable $e) {
            $this->arsip->hapus($pengajuan);

            throw $e;
        }

        $this->notifyUser($pengajuan->pemohon, $pengajuan, 'Perjalanan dinas Anda selesai & telah diarsipkan.');

        return $pengajuan->fresh();
    }

    private function guardTransition(PengajuanSppd $pengajuan, PengajuanStatus $target): void
    {
        if (! $pengajuan->status->canTransitionTo($target)) {
            throw new RuntimeException("Status tidak dapat berpindah dari {$pengajuan->status->label()} ke {$target->label()}.");
        }
    }

    private function transition(PengajuanSppd $pengajuan, PengajuanStatus $target, User $actor, string $keterangan): void
    {
        $this->guardTransition($pengajuan, $target);

        $pengajuan->update(['status' => $target]);

        $this->log($pengajuan, $actor, $keterangan);
    }

    private function log(PengajuanSppd $pengajuan, User $actor, string $keterangan): void
    {
        LogAudit::create([
            'entitas_terkait' => PengajuanSppd::class,
            'entitas_id' => $pengajuan->id,
            'user_id' => $actor->id,
            'aksi' => $keterangan,
            'keterangan' => $keterangan,
        ]);
    }

    private function notifyRole(RoleName $role, PengajuanSppd $pengajuan, string $pesan): void
    {
        User::role($role->value)->get()->each(
            fn (User $user) => $user->notify(new PengajuanStatusChanged($pengajuan, $pesan))
        );
    }

    private function notifyUser(User $user, PengajuanSppd $pengajuan, string $pesan): void
    {
        $user->notify(new PengajuanStatusChanged($pengajuan, $pesan));
    }
}
