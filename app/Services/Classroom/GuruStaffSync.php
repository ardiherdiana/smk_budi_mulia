<?php

namespace App\Services\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Staff;
use App\Services\MediaService;
use Illuminate\Http\UploadedFile;

/**
 * Menyatukan akun guru Classroom (`profil_akun`) dengan orangnya di `guru_staff` (yang tampil di #guru): foto, nama, dan
 * "tampil di website" satu sumber. `guru_staff` tabel produksi yang dikunci, jadi tautannya ada di `profil_akun.guruStaffId`.
 *
 * Hanya peran GURU yang punya baris di website. KEPSEK tampil lewat tabel `principals`, ADMIN dan SISWA tidak tampil.
 */
class GuruStaffSync
{
    /** Gelar yang dibuang saat mencocokkan nama (sudah dipecah per huruf/singkatan: "S.Pd." -> s, pd). */
    private const GELAR = ['s', 'm', 'h', 'e', 'pd', 'kom', 'tr', 'psi', 'sh', 'se', 'si', 'sos', 'ag', 'gr', 'dr', 'drs', 'hj'];

    public function __construct(private MediaService $media) {}

    /** Nama tanpa gelar, kurung, dan tanda baca, huruf kecil: "Rio Falentino, S.Pd. Gr." dan "Rio Falentino, S.Pd, Gr." sama. */
    public static function kunciNama(string $nama): string
    {
        $nama = mb_strtolower(preg_replace('/\([^)]*\)/u', ' ', $nama));
        $kata = preg_split('/[^\p{L}\p{N}]+/u', $nama, -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', array_values(array_diff($kata, self::GELAR)));
    }

    /**
     * Tautkan akun ke baris `guru_staff` yang namanya sama. Bila satu orang punya beberapa baris (mis. wakil kepala dan guru),
     * dipilih yang berperan guru, lalu yang berfoto. Baris yang sudah dipakai akun lain dilewati. Tidak menimpa tautan.
     *
     * @param  string|null  $namaManual  nama persis di `guru_staff` bila nama akunnya disingkat
     */
    public function tautkan(Akun $akun, ?string $namaManual = null): bool
    {
        if ($akun->guruStaffId) {
            return false;
        }

        $kunci = self::kunciNama($namaManual ?? (string) $akun->name);
        $dipakai = Akun::whereNotNull('guruStaffId')->pluck('guruStaffId')->all();

        $pilih = Staff::whereNotIn('id', $dipakai)->get()
            ->filter(fn (Staff $s) => self::kunciNama($s->name) === $kunci)
            ->sort(fn (Staff $a, Staff $b) => [$a->role === 'teacher' ? 0 : 1, $a->image ? 0 : 1, $a->id] <=> [$b->role === 'teacher' ? 0 : 1, $b->image ? 0 : 1, $b->id])
            ->first();

        if (! $pilih) {
            return false;
        }

        $akun->forceFill(['guruStaffId' => $pilih->id])->save();

        return true;
    }

    /** Guru baru dari Classroom: buat barisnya di `guru_staff` (otomatis tampil di #guru) dan tautkan. */
    public function buat(Akun $akun, ?UploadedFile $foto = null): ?Staff
    {
        if ($akun->role !== 'GURU' || $akun->guruStaffId) {
            return null;
        }

        $path = $foto ? $this->media->storeImage($foto, 'staff-image', 'foto') : null;

        try {
            $staff = Staff::create(['name' => (string) $akun->name, 'role' => 'teacher', 'type' => 'teacher', 'image' => $path]);
            $akun->forceFill(['guruStaffId' => $staff->id])->save();
        } catch (\Throwable $e) {
            $this->media->delete($path); // jangan tinggalkan berkas yatim bila datanya gagal tersimpan

            throw $e;
        }

        return $staff;
    }

    /**
     * Setelah akun diubah: nama ikut berubah HANYA bila berubah, foto baru menggantikan yang lama (berkas lama dihapus
     * sesudah data tersimpan), atau foto dihapus. Akun GURU yang belum tertaut dan diberi foto dibuatkan barisnya,
     * karena fotonya butuh tempat.
     */
    public function sinkron(Akun $akun, ?string $namaLama, ?UploadedFile $foto = null, bool $hapusFoto = false): void
    {
        if ($akun->role !== 'GURU') {
            return;
        }

        $staff = $akun->staff;
        if (! $staff) {
            if ($foto) {
                $this->buat($akun, $foto);
            }

            return;
        }

        $namaBerubah = $namaLama !== null && $akun->name !== $namaLama;
        if (! $namaBerubah && ! $foto && ! $hapusFoto) {
            return;
        }

        $usang = null;
        $baru = null;
        if ($namaBerubah) {
            $staff->name = (string) $akun->name;
        }
        if ($foto) {
            $usang = $staff->image;
            $staff->image = $baru = $this->media->storeImage($foto, 'staff-image', 'foto');
        } elseif ($hapusFoto) {
            $usang = $staff->image;
            $staff->image = null;
        }

        try {
            $staff->save();
        } catch (\Throwable $e) {
            $this->media->delete($baru);

            throw $e;
        }

        $this->media->delete($usang);
    }
}
