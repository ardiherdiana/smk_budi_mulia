<?php

namespace Database\Seeders;

use App\Models\Classroom\Akun;
use App\Models\Staff;
use App\Services\Classroom\GuruStaffSync;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Akun guru dari `DAFTAR` di bawah (tidak membaca Excel). Nama dan mapel mengikuti daftar guru sekolah; username dan sandi
 * meneruskan "DAFTAR GURU MAPEL SMKBM.xlsx" supaya login yang sudah dibagikan tetap berlaku. Sandi disimpan sebagai hash
 * bcrypt, bukan teks biasa, karena kredensial tidak boleh ada di berkas terlacak. Guru yang belum punya sandi di Excel
 * (SMKBM028) diberi sandi acak yang tidak diketahui siapa pun; atur lewat Data Guru.
 *
 * Aman dijalankan ulang: akun yang sudah ada hanya diperbarui namanya. Sandi, peran, dan status aktif tidak ditimpa
 * karena admin mengelolanya lewat Data Guru. Mapel tiap guru dipakai KelasSeeder; semua namanya ada di MapelSeeder::DAFTAR.
 */
class GuruSeeder extends Seeder
{
    /** @var list<array{no: int, nama: string, username: string, hash: string, mapel: list<string>}> */
    public const DAFTAR = [
        [
            'no' => 1,
            'nama' => 'Rio Falentino, S.Pd. Gr.',
            'username' => 'SMKBM017',
            'hash' => '$2y$12$LrwB2SSYITGTEgjja1hvluoWw1QzlV2pLJEHBfctXSPeCi3gLvSne',
            'mapel' => [
                'Bahasa Indonesia (Kolaboratif Jurusan)',
                'Bahasa Indonesia (TKA+AN+UTBK+Kebekerjaan)',
            ],
        ],
        [
            'no' => 2,
            'nama' => 'Doni Wijaya, S.H. Gr.',
            'username' => 'SMKBM006',
            'hash' => '$2y$12$UflyRSU1.6J0iBi1p.zVvunBART5gFdrUcfjCaa0ex3U6oXz9RlzW',
            'mapel' => [
                'Pendidikan Pancasila (Penguatan Karakter)',
                'BK/BP/Pembina Karakter',
            ],
        ],
        [
            'no' => 3,
            'nama' => 'Marsono, S.Kom., Gr.',
            'username' => 'SMKBM015',
            'hash' => '$2y$12$X4iZxYvk72rUDTLs8TU2IuuhQY35sCgjQObuwPlajwZB7mtb.mTNa',
            'mapel' => [
                'Produktif TKJ',
            ],
        ],
        [
            'no' => 4,
            'nama' => 'Wulan Endarwati, S.Pd. Gr.',
            'username' => 'SMKBM021',
            'hash' => '$2y$12$aBY1N6XS4iWXCWLkHrcKO.tS03fj62WiBuOOd/bY4fEv0TkakltpO',
            'mapel' => [
                'Bahasa Inggris (TKA+SNBT)',
                'Bhs. Inggris (Kebekerjaan)',
            ],
        ],
        [
            'no' => 5,
            'nama' => 'Sri Arum Wulandari, M.Pd., Gr.',
            'username' => 'SMKBM018',
            'hash' => '$2y$12$FQuOFgigzfhj7vmxHBT1CuDWMPKrhcZV6xfK3Ol2u0qjMmhq0q1WG',
            'mapel' => [
                'Project Kreatif & Kewirausahaan',
                'Penjaskes',
            ],
        ],
        [
            'no' => 6,
            'nama' => 'Defriza, S.Pd. Gr.',
            'username' => 'SMKBM004',
            'hash' => '$2y$12$r4mYFkA.zu53pVSfSPxpUeM024s/TQGnhbHTAnFRiA4HgdoahOR.a',
            'mapel' => [
                'Matematika (Kolaboratif Jurusan)',
                'Matematika (TKA+AN+SNBT+Kebekerjaan)',
                'Ilmu Pengetahuan Alam dan Sosial (IPAS)',
            ],
        ],
        [
            'no' => 7,
            'nama' => 'Ika Candy P., S.Pd. Gr.',
            'username' => 'SMKBM011',
            'hash' => '$2y$12$R7vvcZlE6oO51l55o5/p9eNL.W4Ywqblb3Mmk1iir3QOqHiua0Jqa',
            'mapel' => [
                'Bahasa Inggris (Kolaboratif Jurusan)',
            ],
        ],
        [
            'no' => 8,
            'nama' => 'Bobi Royan H, S.Tr.Kom., Gr.',
            'username' => 'SMKBM003',
            'hash' => '$2y$12$xIAA1yH0mF8sqvQlx/phEeVx1l791omFbC6uD1TnuosgegMVTzFsy',
            'mapel' => [
                'Produktif TKJ',
            ],
        ],
        [
            'no' => 9,
            'nama' => 'Latif Abdul Salam, S.Tr.Kom., Gr.',
            'username' => 'SMKBM013',
            'hash' => '$2y$12$krOe7rpeK6zVC6mXk.X6.OT23ywni/HvFsgONgCtJVIdJ4izqTKBO',
            'mapel' => [
                'Produktif TKJ',
            ],
        ],
        [
            'no' => 10,
            'nama' => 'Feviana Miftahul Nurjanah, S.Pd. Gr.',
            'username' => 'SMKBM009',
            'hash' => '$2y$12$5xSMOoxxXU39F/8gQu7k6eJhiI4hOMXaMAiKilXway2aSIY5HfPNe',
            'mapel' => [
                'Matematika (Kolaboratif Jurusan)',
                'Ilmu Pengetahuan Alam dan Sosial (IPAS)',
                'BK/BP/Pembina Karakter',
            ],
        ],
        [
            'no' => 11,
            'nama' => 'Faradilla Ferhat A.S, S.Kom., S.M., Gr.',
            'username' => 'SMKBM007',
            'hash' => '$2y$12$8JDTFSJBNYHPH.0cFvLswuHczgRyaz477iACexO.dixQW6gWE1TWi',
            'mapel' => [
                'Informatika',
                'Project Kreatif & Kewirausahaan (TKA)',
                'Project Kreatif & Kewirausahaan (Kolaboratif Jurusan)',
                'Mata Pelajaran Pilihan (Informatika Lanjutan)',
            ],
        ],
        [
            'no' => 12,
            'nama' => 'Dina Qoyimah, S.Pd. Gr.',
            'username' => 'SMKBM005',
            'hash' => '$2y$12$eVZ7T7D993k8NM5bPjgam.EBMS0RDYLCNkMaT7zvfktLijEgk4GY2',
            'mapel' => [
                'Produktif AKT',
                'Ilmu Pengetahuan Alam dan Sosial (IPAS)',
            ],
        ],
        [
            'no' => 13,
            'nama' => 'Ridho Robbi, S.Kom., Gr.',
            'username' => 'SMKBM016',
            'hash' => '$2y$12$h1tIC5PfkbFogmvqx2clau.7oB0TX91DsJtTzE47C5l29CcUu1IYG',
            'mapel' => [
                'Produktif TKJ',
                'Mata Pelajaran Pilihan (Informatika Lanjutan)',
            ],
        ],
        [
            'no' => 14,
            'nama' => 'Herianto Saputra, M.Kom., Gr. (Mahasiswa PKL)',
            'username' => 'SMKBM028',
            'hash' => '$2y$12$k6JtJmJQs06vO.d2jv5eNu7xKvEmmTJdJG3baElhNdrNAxfFOT146',
            'mapel' => [
                'Produktif RPL',
                'Produktif TKJ',
                'Mata Pelajaran Pilihan (Informatika Lanjutan)',
            ],
        ],
        [
            'no' => 15,
            'nama' => 'Mochammad Chafid Albiro, S.Pd., Gr.',
            'username' => 'SMKBM014',
            'hash' => '$2y$12$Ybz0ZQBQsnbIIti3cYqOqO73kkcv35SpKNOeODzITFtJtY9Yf0ddO',
            'mapel' => [
                'Penjaskes',
                'Penjaskes (Persiapan Tes Fisik)',
                'Project Kreatif & Kewirausahaan (Kolaboratif Jurusan)',
                'BK/BP/Pembina Karakter',
            ],
        ],
        [
            'no' => 16,
            'nama' => 'Irmawati, S.E., Gr.',
            'username' => 'SMKBM012',
            'hash' => '$2y$12$ysWLGIh2X5LPB1xDe.NYSe4wjkyKceAniRfxhL4G7ur2Yjy9189GG',
            'mapel' => [
                'Produktif AKT',
                'Project Kreatif & Kewirausahaan (Kolaboratif Jurusan)',
            ],
        ],
        [
            'no' => 17,
            'nama' => 'Tommy Ferdian H, S.Kom., Gr.',
            'username' => 'SMKBM020',
            'hash' => '$2y$12$cwde3LxXhKLM0Rh/DBhn/OYghu.eB3zkNw.V8nzGBDktNVl98Lr5O',
            'mapel' => [
                'Produktif RPL',
                'Produktif TKJ',
                'Mata Pelajaran Pilihan (Informatika Lanjutan)',
            ],
        ],
        [
            'no' => 18,
            'nama' => 'Eskiyana Khoiru Maisah, S.Pd.',
            'username' => 'SMKBM002',
            'hash' => '$2y$12$LoyiyHiYlJC7MlVv6T4Afua52E.FvyQ.QhZIZrn4AzTXjm4tX6lKO',
            'mapel' => [
                'Bahasa Indonesia (Kolaboratif Jurusan)',
                'Bahasa Jepang',
            ],
        ],
        [
            'no' => 19,
            'nama' => 'Dennies Maulana Yusuf',
            'username' => 'SMKBM023',
            'hash' => '$2y$12$6n6p7DcrI6ufSiMfq0Jz1uq6JKSbraOmL5CV8wYbRY7yRxSNht9YO',
            'mapel' => [
                'Penjaskes',
            ],
        ],
        [
            'no' => 20,
            'nama' => 'Muhammad Firdaus Al-Husna, S.Kom.',
            'username' => 'SMKBM001',
            'hash' => '$2y$12$UIiaT8dELCop3oKy0ZN04.4pBIzGLaA7wuyYpPHvc5Jpc1CV0D03O',
            'mapel' => [
                'Produktif TKJ',
                'Mata Pelajaran Pilihan (Pembekalan PKL/Informatika)',
                'Produktif RPL',
            ],
        ],
        [
            'no' => 21,
            'nama' => 'Rani Suryani',
            'username' => 'SMKBM022',
            'hash' => '$2y$12$i4Zu0AiuQh9uS8V9ILr.VOBNs.pXlN28RMpF/VS7AJIA0RQUdtmO.',
            'mapel' => [
                'Bahasa Inggris',
            ],
        ],
        [
            'no' => 22,
            'nama' => 'Nita Ginnaty Fau, S.Psi., Gr.',
            'username' => 'SMKBM010',
            'hash' => '$2y$12$wuVhpEZZwVRdMZXmj/hz1eB6T891Cq78hrcnAIOIdSD4W3sZuWKWy',
            'mapel' => [
                'Pendidikan Pancasila (Penguatan Karakter)',
                'BK/BP/Pembina Karakter',
            ],
        ],
        [
            'no' => 23,
            'nama' => 'Muhammad Nur Usman, S.Psi.',
            'username' => 'SMKBM008',
            'hash' => '$2y$12$Be7aIVS2uvs4YVCgU6aA9.CQ4tIUsel.DQtS9497aNqsId63RPpOS',
            'mapel' => [
                'BK/BP/Pembina Karakter',
            ],
        ],
    ];

    /**
     * Guru yang namanya di sini disingkat sehingga tidak cocok otomatis dengan `guru_staff` (yang tampil di #guru):
     * username => nama di website (gelar boleh dihilangkan). Sisanya dicocokkan lewat GuruStaffSync::kunciNama().
     *
     * @var array<string, string>
     */
    private const NAMA_DI_WEBSITE = [
        'SMKBM011' => 'Ika Candi Permata',
        'SMKBM003' => 'Bobi Royan Haryanto',
        'SMKBM007' => 'Faradilla Ferhat Arina Shandy',
        'SMKBM028' => 'Herianto Saputra',
        'SMKBM020' => 'Tommy Ferdian Hadimarta',
        'SMKBM023' => 'Denies Maulana Yusuf',
    ];

    public function run(): void
    {
        $dibuat = 0;
        $diperbarui = 0;
        $ditautkan = 0;
        $sync = app(GuruStaffSync::class);
        $adaStaff = Staff::query()->exists(); // kosong bila data produksi belum dimuat: foto menyusul di db:seed berikutnya

        foreach (self::DAFTAR as $guru) {
            $akun = Akun::where('username', $guru['username'])->first();
            $baru = $akun === null;

            $akun ??= new Akun;
            $akun->username = $guru['username'];
            $akun->name = $guru['nama'];
            if ($baru) {
                $akun->role = 'GURU';
                $akun->is_active = true;
                $akun->password = Str::random(40); // sementara; langsung diganti hash di bawah
            }
            $akun->save();

            if ($baru) {
                // Lewat query builder: cast `hashed` di User akan meng-hash ulang hash ini bila biaya bcrypt-nya
                // berbeda dari konfigurasi (mis. BCRYPT_ROUNDS di tes), dan sandi asli tidak akan cocok lagi.
                DB::table('users')->where('id', $akun->id)->update(['password' => $guru['hash']]);
                $dibuat++;
            } else {
                $diperbarui++;
            }

            if ($adaStaff && $sync->tautkan($akun, self::NAMA_DI_WEBSITE[$guru['username']] ?? null)) {
                $ditautkan++;
            }
        }

        $this->command?->info("Seed guru selesai: {$dibuat} akun baru, {$diperbarui} diperbarui, {$ditautkan} ditautkan ke guru di website.");
    }
}
