<?php

namespace App\Services\Classroom;

use App\Models\Classroom\Akun;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

/**
 * Jembatan BACA-SAJA ke basis data absensi (`absensi`): sumber username, sandi, peran, dan nama guru.
 * Satu-satunya hal yang ditulis adalah salinan akun (tanpa sandi) di profil Classroom.
 */
class AbsensiAccountService
{
    private const ROLES = ['ADMIN', 'KEPSEK', 'GURU'];

    /**
     * Cari akun staf berdasarkan username, lengkap dengan hash sandinya. Null bila tidak ada, tidak punya peran
     * absensi (staf yang hanya memakai SPPD), atau dinonaktifkan di absensi.
     */
    public function cari(string $username): ?stdClass
    {
        $row = $this->query()->where('users.username', $username)->first();

        return $row && $this->layak($row) ? $row : null;
    }

    /**
     * Semua akun staf yang layak masuk Classroom.
     *
     * @return Collection<int, stdClass>
     */
    public function semua()
    {
        return $this->query()->get()->filter(fn (stdClass $row) => $this->layak($row))->values();
    }

    /**
     * Salin satu akun absensi ke profil Classroom (`profil_akun` + baris `users`), dikaitkan lewat `absensi_user_id`.
     * Sandi tidak disalin: sandi guru selalu diverifikasi ke absensi. Mengembalikan null bila username-nya sudah
     * dipakai akun Classroom lain (mis. siswa).
     */
    public function sinkron(stdClass $row): ?Akun
    {
        $akun = Akun::where('absensi_user_id', (string) $row->id)->first() ?? new Akun;

        $bentrok = Akun::where('username', $row->username)
            ->when($akun->exists, fn ($q) => $q->where('id', '!=', $akun->id))
            ->exists();
        if ($bentrok) {
            return null;
        }

        $akun->forceFill([
            'absensi_user_id' => (string) $row->id,
            'username' => $row->username,
            'name' => $row->guru_nama ?: ($row->name ?: null),
            'role' => $row->role,
            'is_active' => true,
        ]);
        $akun->save();

        User::whereKey($akun->id)->update(['role' => $row->role === 'ADMIN' ? 'admin' : 'user']);

        return $akun;
    }

    /**
     * @return array{dibuat: int, diperbarui: int, dilewati: list<string>}
     */
    public function sinkronSemua(): array
    {
        $hasil = ['dibuat' => 0, 'diperbarui' => 0, 'dilewati' => []];

        foreach ($this->semua() as $row) {
            $ada = Akun::where('absensi_user_id', (string) $row->id)->exists();
            if ($this->sinkron($row)) {
                $hasil[$ada ? 'diperbarui' : 'dibuat']++;
            } else {
                $hasil['dilewati'][] = $row->username;
            }
        }

        return $hasil;
    }

    /**
     * Cocokkan nama guru (mis. dari daftar wali kelas) ke akun staf. Gelar diabaikan; toleran terhadap singkatan
     * ("Feviana Miftahul N") dan salah eja kecil ("Candi" vs "Candy").
     */
    public function cocokkanNama(string $nama): ?Akun
    {
        $target = $this->normalisasi($nama);
        $terbaik = null;
        $skorTerbaik = 0.0;

        foreach (Akun::whereIn('role', self::ROLES)->get() as $user) {
            $calon = $this->normalisasi((string) $user->name);
            if ($calon === '') {
                continue;
            }

            if ($calon === $target) {
                return $user;
            }

            $skor = 0.0;
            if (str_starts_with($target, $calon) || str_starts_with($calon, $target)) {
                $skor = 90.0;
            } else {
                similar_text($target, $calon, $skor);
            }

            if ($skor > $skorTerbaik) {
                $skorTerbaik = $skor;
                $terbaik = $user;
            }
        }

        return $skorTerbaik >= 85.0 ? $terbaik : null;
    }

    private function normalisasi(string $nama): string
    {
        // Gelar selalu setelah koma pertama ("Defriza, S.Pd. Gr.").
        $inti = Str::before($nama, ',');

        return Str::of($inti)->lower()->replaceMatches('/[^a-z ]/', '')->squish()->toString();
    }

    private function layak(stdClass $row): bool
    {
        return in_array($row->role, self::ROLES, true)
            && (bool) $row->is_active
            && ($row->guru_aktif === null || (bool) $row->guru_aktif);
    }

    private function query()
    {
        return DB::connection('absensi')
            ->table('users')
            ->leftJoin('guru', 'guru.userId', '=', 'users.id')
            ->select([
                'users.id', 'users.username', 'users.name', 'users.password', 'users.role', 'users.is_active',
                'guru.nama as guru_nama', 'guru.aktif as guru_aktif',
            ]);
    }
}
