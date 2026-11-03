<?php

namespace Database\Seeders;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Siswa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Siswa dari `database/seeders/data/siswa.php` (tidak membaca Excel): 380 siswa dengan [nama rombel, nama, username 2627NNNN,
 * hash bcrypt sandi]. Datanya berasal dari kredensial-siswa.xlsx yang dipakai produksi, jadi username dan sandi yang sudah
 * dibagikan tetap berlaku; sandi hanya disimpan sebagai hash. Nama rombel memakai nama baru (RombelSeeder::DAFTAR).
 *
 * Aman dijalankan ulang: siswa yang sudah ada hanya diperbarui namanya. Sandi dan rombel (yang bisa diubah lewat halaman
 * Siswa atau ganti sandi sendiri) tidak ditimpa.
 */
class SiswaSeeder extends Seeder
{
    /** Hanya dipakai bacaWaliKelas() untuk KelasSeeder, bukan untuk seed siswa. */
    private const BERKAS_DATA = 'private/data-siswa.xlsx';

    /** Judul tiap blok rombel di berkas: "DAFTAR ABSENSI SISWA KELAS <nama rombel>". */
    private const PENANDA = 'DAFTAR ABSENSI SISWA KELAS';

    /** @return list<array{0: string, 1: string, 2: string, 3: string}> [nama rombel, nama siswa, username, hash sandi] */
    public static function daftar(): array
    {
        return require __DIR__.'/data/siswa.php';
    }

    public function run(): void
    {
        // Sandi sementara tiap akun baru langsung diganti hash di bawah, jadi hash-nya tidak perlu mahal: pada biaya bcrypt 12,
        // 380 akun memakan sekitar 2,5 menit hanya untuk sandi yang langsung dibuang.
        $hasher = Hash::driver('bcrypt');
        $hasher->setRounds(4);

        try {
            [$dibuat, $diperbarui, $jumlahRombel] = $this->isi();
        } finally {
            $hasher->setRounds((int) config('hashing.bcrypt.rounds', 12));
        }

        $this->command?->info(sprintf('Seed siswa selesai: %d akun baru, %d diperbarui, %d rombel.', $dibuat, $diperbarui, $jumlahRombel));
    }

    /** @return array{0: int, 1: int, 2: int} [akun baru, akun diperbarui, jumlah rombel] */
    private function isi(): array
    {
        $rombelPerNama = [];
        $dibuat = 0;
        $diperbarui = 0;

        foreach (self::daftar() as [$namaRombel, $nama, $username, $hash]) {
            $rombel = $rombelPerNama[$namaRombel] ??= RombelSeeder::buat($namaRombel);

            $akun = Akun::where('username', $username)->first();
            $baru = $akun === null;

            $akun ??= new Akun;
            $akun->username = $username;
            $akun->name = $nama;
            if ($baru) {
                $akun->role = 'SISWA';
                $akun->is_active = true;
                $akun->password = Str::random(40); // sementara; langsung diganti hash di bawah
            }
            $akun->save();

            if ($baru) {
                // Lewat query builder: cast `hashed` di User akan meng-hash ulang hash ini bila biaya bcrypt-nya
                // berbeda dari konfigurasi (mis. BCRYPT_ROUNDS di tes), dan sandi asli tidak akan cocok lagi.
                DB::table('users')->where('id', $akun->id)->update(['password' => $hash]);
                $dibuat++;
            } else {
                $diperbarui++;
            }

            $siswa = Siswa::firstOrNew(['userId' => $akun->id]);
            if (! $siswa->exists) {
                $siswa->rombelId = $rombel->id;
                $siswa->nis = null;
            }
            $siswa->nama = $nama;
            $siswa->save();
        }

        return [$dibuat, $diperbarui, count($rombelPerNama)];
    }

    /**
     * Wali kelas tiap rombel dari tanda tangan di akhir blok: label "Wali Kelas" ada di satu kolom, dan namanya di kolom
     * yang sama beberapa baris di bawahnya (ada jarak untuk tanda tangan). Nama dikembalikan apa adanya, lengkap dengan gelar.
     * Dipakai KelasSeeder; membaca storage/app/private/data-siswa.xlsx (salinan "DATA SISWA SMKBM 2627.xlsx"), yang bukan
     * lagi sumber seed siswa. Kunci hasilnya nama rombel di berkas itu (mis. "X TJKT 1"), bukan nama baru di RombelSeeder.
     *
     * @return array<string, string> [NAMA ROMBEL (huruf besar) => nama wali kelas]
     */
    public static function bacaWaliKelas(?string $path = null): array
    {
        $path ??= storage_path('app/'.self::BERKAS_DATA);
        if (! is_file($path)) {
            throw new \RuntimeException("Berkas data siswa tidak ditemukan: {$path}");
        }

        // Kunci kolom berupa huruf (A, B, ...) dan kunci baris berupa nomor baris sebenarnya.
        $baris = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, true);
        $hasil = [];
        $rombel = null;
        foreach ($baris as $no => $sel) {
            $a = trim((string) ($sel['A'] ?? ''));
            if (stripos($a, self::PENANDA) === 0) {
                $rombel = self::namaRombel($a);

                continue;
            }
            if ($rombel === null || isset($hasil[$rombel])) {
                continue;
            }

            foreach ($sel as $kolom => $isi) {
                if (strcasecmp(trim((string) $isi), 'Wali Kelas') !== 0) {
                    continue;
                }
                for ($i = 1; $i <= 8; $i++) {
                    $nama = trim((string) ($baris[$no + $i][$kolom] ?? ''));
                    if ($nama !== '') {
                        $hasil[$rombel] = preg_replace('/\s+/', ' ', $nama);
                        break;
                    }
                }
                break;
            }
        }

        return $hasil;
    }

    private static function namaRombel(string $judul): string
    {
        return mb_strtoupper(preg_replace('/\s+/', ' ', trim(substr($judul, strlen(self::PENANDA)))));
    }
}
