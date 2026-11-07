<?php

namespace Tests;

use App\Enums\Sppd\PengajuanStatus;
use App\Models\Guru;
use App\Models\JadwalHari;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use App\Services\Sppd\ArsipSppd;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Basis tes modul absensi + SPPD (dibawa dari proyek absensi). Akun dibuat lewat UserFactory: baris `users` + profil
 * `profil_akun` dengan peran GURU (default), ADMIN (`admin()`), KEPSEK (`kepsek()`), atau STAF (`staf()`).
 */
abstract class AbsensiTestCase extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Freezes "now" to a fixed point in time for deterministic attendance-window tests. */
    protected function freezeAt(string $dateTime): Carbon
    {
        $now = Carbon::parse($dateTime);
        Carbon::setTestNow($now);

        return $now;
    }

    /** @param  array<string, mixed>  $attributes */
    protected function setJadwalHari(int $hari, array $attributes = []): JadwalHari
    {
        return JadwalHari::updateOrCreate(['hari' => $hari], array_merge([
            'aktif' => true,
            'jamMulaiAbsen' => '07:00',
            'jamMasuk' => '07:00',
            'batasTelat' => '07:15',
            'jamPulang' => '15:30',
        ], $attributes));
    }

    /** Disk `local` palsu berisi gambar tanda tangan Kepala Sekolah (JPEG 1x1 piksel) yang dibutuhkan arsip SPPD. */
    protected function siapkanTtdKepsek(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(
            ArsipSppd::BERKAS_TTD,
            base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='),
        );
    }

    /**
     * TU mengetik nomor surat SPPD: baru sesudah ini SPPD terbit dan bisa diunduh. Tanpa $nomor, nomornya diturunkan
     * dari id pengajuan supaya beberapa SPPD dalam satu tes tidak kembar. Pengajuan yang masih menunggu Kepala Sekolah
     * dianggap sudah disetujui supaya tes yang tidak menguji persetujuan tidak perlu mengulangnya; alur persetujuan
     * yang sebenarnya diuji di PengajuanSppdWorkflowTest.
     */
    protected function isiNomorSppd(PengajuanSppd $pengajuan, User $tu, ?string $nomor = null): PengajuanSppd
    {
        $nomor ??= sprintf('%03d/SMK-BM/X/%d', $pengajuan->id, now()->year);

        if ($pengajuan->status === PengajuanStatus::DiajukanKeKepsek) {
            $pengajuan->update(['status' => PengajuanStatus::MenungguNomor]);
        }

        $this->actingAs($tu)
            ->patch(route('sppd.pengajuan.nomor', $pengajuan), ['nomor_sppd' => $nomor])
            ->assertSessionHasNoErrors();

        return $pengajuan->fresh(['pemohon', 'penyetuju', 'sppd', 'pengikuts']);
    }

    /** Admin absensi: profil ADMIN (users.role admin). Menggantikan admin landing (tanpa profil) di tes absensi. */
    protected function actingAsAdmin(array $attributes = []): User
    {
        $user = User::factory()->admin()->create($attributes);
        $this->actingAs($user);

        return $user;
    }

    protected function actingAsGuru(array $guruAttributes = [], array $userAttributes = []): Guru
    {
        $guru = Guru::factory()->create([
            'userId' => User::factory()->create($userAttributes),
            ...$guruAttributes,
        ]);
        $this->actingAs($guru->user);

        return $guru;
    }

    protected function actingAsKepsek(array $guruAttributes = []): Guru
    {
        $guru = Guru::factory()->create([
            'userId' => User::factory()->kepsek(),
            ...$guruAttributes,
        ]);
        $this->actingAs($guru->user);

        return $guru;
    }
}
