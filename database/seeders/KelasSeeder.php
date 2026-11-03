<?php

namespace Database\Seeders;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Mapel;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\TahunAjaran;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Ruang kelas (kelas) dari aturan wali kelas: tiap rombel mendapat satu ruang kelas untuk setiap mapel milik wali kelasnya.
 * Berkas yang ada tidak memuat jadwal mengajar, jadi satu-satunya kaitan guru dengan rombel adalah nama wali kelas di
 * tanda tangan tiap blok data-siswa.xlsx (dicocokkan ke GuruSeeder::DAFTAR). Hasilnya perkiraan; guru lain dan koreksi
 * ditambahkan lewat "Buat Ruang Kelas". Isi `rombel.waliKelasId` bila masih kosong.
 *
 * Sengaja TIDAK dipanggil dari ClassroomSeeder, supaya `db:seed` tidak menghidupkan lagi kelas yang sengaja dihapus guru.
 * Jalankan: php artisan db:seed --class=KelasSeeder. Aman dijalankan ulang (firstOrCreate; kelas milik guru lain tidak ditimpa).
 */
class KelasSeeder extends Seeder
{
    /**
     * Cadangan bila pencocokan nama otomatis meleset: nama wali ternormalisasi (lihat normalisasi()) => username guru.
     *
     * @var array<string, string>
     */
    private const ALIAS_WALI = [];

    public function run(): void
    {
        $tahun = TahunAjaran::aktif();
        if (! $tahun) {
            throw new \RuntimeException('Belum ada tahun ajaran aktif. Jalankan dulu: php artisan db:seed --class=TahunAjaranSeeder');
        }

        $hasil = self::bangun(GuruSeeder::DAFTAR, SiswaSeeder::bacaWaliKelas(), $tahun);

        $this->command?->table(
            ['Rombel', 'Wali kelas (di berkas siswa)', 'Guru', 'Kelas baru', 'Sudah ada', 'Catatan'],
            array_map(fn (array $r) => [$r['rombel'], $r['wali'], $r['username'] ?? '-', $r['dibuat'], $r['ada'], $r['catatan']], $hasil),
        );

        $dibuat = array_sum(array_column($hasil, 'dibuat'));
        $dilewati = count(array_filter($hasil, fn (array $r) => $r['catatan'] !== ''));
        $this->command?->info("Seed kelas selesai: {$dibuat} ruang kelas baru dari ".count($hasil)." rombel ({$dilewati} dilewati).");
    }

    /**
     * @param  list<array{nama: string, username: string, mapel: list<string>}>  $guru
     * @param  array<string, string>  $waliPerRombel  [NAMA ROMBEL => nama wali]
     * @return list<array{rombel: string, wali: string, username: ?string, dibuat: int, ada: int, catatan: string}>
     */
    public static function bangun(array $guru, array $waliPerRombel, TahunAjaran $tahun): array
    {
        $hasil = [];

        foreach ($waliPerRombel as $namaRombel => $namaWali) {
            $baris = ['rombel' => $namaRombel, 'wali' => $namaWali, 'username' => null, 'dibuat' => 0, 'ada' => 0, 'catatan' => ''];

            $rombel = Rombel::where('nama', $namaRombel)->first();
            $dataGuru = self::cariGuru($namaWali, $guru);
            $akun = $dataGuru ? Akun::where('username', $dataGuru['username'])->where('role', 'GURU')->first() : null;
            $mapel = $dataGuru['mapel'] ?? [];

            $baris['catatan'] = match (true) {
                $rombel === null => 'rombel belum ada (jalankan SiswaSeeder)',
                $dataGuru === null => 'wali kelas tidak ketemu di daftar guru',
                $akun === null => 'akun guru belum ada (jalankan GuruSeeder)',
                $mapel === [] => 'wali kelas tidak punya mapel',
                default => '',
            };
            $baris['username'] = $dataGuru['username'] ?? null;

            if ($baris['catatan'] === '') {
                foreach ($mapel as $namaMapel) {
                    $kelas = Kelas::firstOrCreate(
                        ['rombelId' => $rombel->id, 'mapelId' => Mapel::firstOrCreate(['nama' => $namaMapel])->id, 'tahunAjaranId' => $tahun->id],
                        ['guruId' => $akun->id],
                    );
                    $kelas->wasRecentlyCreated ? $baris['dibuat']++ : $baris['ada']++;
                }

                if ($rombel->waliKelasId === null) {
                    $rombel->update(['waliKelasId' => $akun->id]);
                }
            }

            $hasil[] = $baris;
        }

        return $hasil;
    }

    /**
     * Guru yang namanya paling cocok dengan nama wali kelas. Berurutan: alias manual, nama inti sama, nama saling mirip kata
     * per kata (singkatan "Latif A S" ~ "Latif Abdul Salam", salah eja "Candi" ~ "Candy") asal hanya satu yang cocok, lalu
     * kemiripan teks >= 85% seperti AbsensiAccountService::cocokkanNama.
     *
     * @param  list<array{nama: string, username: string, mapel: list<string>}>  $guru
     * @return array{nama: string, username: string, mapel: list<string>}|null
     */
    public static function cariGuru(string $namaWali, array $guru): ?array
    {
        $target = self::normalisasi($namaWali);
        if ($target === '') {
            return null;
        }

        if ($username = self::ALIAS_WALI[$target] ?? null) {
            foreach ($guru as $g) {
                if ($g['username'] === $username) {
                    return $g;
                }
            }
        }

        $kandidat = [];
        foreach ($guru as $g) {
            if (($nama = self::normalisasi($g['nama'])) !== '') {
                $kandidat[] = [$g, $nama];
            }
        }

        foreach ($kandidat as [$g, $nama]) {
            if ($nama === $target) {
                return $g;
            }
        }

        $mirip = array_values(array_filter($kandidat, fn (array $k) => self::namaMirip($target, $k[1])));
        if (count($mirip) === 1) {
            return $mirip[0][0];
        }

        $terbaik = null;
        $skorTerbaik = 0.0;
        foreach ($kandidat as [$g, $nama]) {
            similar_text($target, $nama, $skor);
            if ($skor > $skorTerbaik) {
                $skorTerbaik = $skor;
                $terbaik = $g;
            }
        }

        return $skorTerbaik >= 85.0 ? $terbaik : null;
    }

    /** Gelar selalu setelah koma pertama ("Defriza, S.Pd. Gr."); sisanya huruf kecil, tanda baca menjadi pemisah kata ("A.S" -> "a s"). */
    public static function normalisasi(string $nama): string
    {
        return Str::of(Str::before($nama, ','))->lower()->replaceMatches('/[^a-z ]/', ' ')->squish()->toString();
    }

    /** Kata pertama sampai kata terakhir dari nama yang lebih pendek harus mirip dengan kata di posisi yang sama pada nama yang lebih panjang. */
    private static function namaMirip(string $a, string $b): bool
    {
        $kataA = explode(' ', $a);
        $kataB = explode(' ', $b);
        if (count($kataA) > count($kataB)) {
            [$kataA, $kataB] = [$kataB, $kataA];
        }

        foreach ($kataA as $i => $kata) {
            if (! self::kataMirip($kata, $kataB[$i])) {
                return false;
            }
        }

        return true;
    }

    private static function kataMirip(string $x, string $y): bool
    {
        if (str_starts_with($x, $y) || str_starts_with($y, $x)) {
            return true;
        }

        return strlen($x) >= 4 && strlen($y) >= 4 && levenshtein($x, $y) <= 1;
    }
}
