<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Kelas;
use App\Models\Classroom\Mapel;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\TahunAjaran;
use Database\Seeders\KelasSeeder;
use Database\Seeders\SiswaSeeder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class KelasSeederTest extends TestCase
{
    /**
     * @param  list<string>  $mapel
     * @return array{nama: string, username: string, mapel: list<string>}
     */
    private function guru(int $no, string $nama, array $mapel): array
    {
        return ['nama' => $nama, 'username' => sprintf('SMKBM%03d', $no), 'mapel' => $mapel];
    }

    public function test_nama_wali_dicocokkan_ke_daftar_guru(): void
    {
        $guru = [
            $this->guru(17, 'Rio Falentino, S.Kom', ['Produktif TKJ']),
            $this->guru(13, 'Latif A S, S.Tr.Kom', ['Produktif TKJ']),
            $this->guru(11, 'Ika Candy P, S.Pd', ['Informatika']),
            $this->guru(9, 'Feviana Miftahul N, S.Pd', ['Produktif RPL']),
            $this->guru(14, 'M. Chafid A, S.Kom', ['Produktif TKJ']),
            $this->guru(5, 'Dina Qoyimah, S.E', ['Produktif AKT']),
            $this->guru(21, 'Dina Rahma', ['Ilmu Pengetahuan Alam dan Sosial (IPAS)']),
        ];

        $cari = fn (string $wali) => KelasSeeder::cariGuru($wali, $guru)['username'] ?? null;

        $this->assertSame('SMKBM017', $cari('Rio Falentino, S.Pd., Gr.'), 'nama inti sama, gelar diabaikan');
        $this->assertSame('SMKBM013', $cari('Latif Abdul Salam'), 'inisial dicocokkan dengan nama lengkap');
        $this->assertSame('SMKBM011', $cari('Ika Candi Permata, M.Pd'), 'salah eja satu huruf');
        $this->assertSame('SMKBM009', $cari('Feviana Miftahul Nurjanah'), 'nama belakang disingkat');
        $this->assertSame('SMKBM014', $cari('Mochammad Chafid Albiro'), 'nama depan disingkat');
        $this->assertSame('SMKBM005', $cari('Dina Qoyimah'));
        $this->assertSame('SMKBM007', KelasSeeder::cariGuru('Faradilla Ferhat Arina Shandy, S.Kom., S.M., Gr.', [
            $this->guru(7, 'Faradilla Ferhat A.S, S.Kom., S.M., Gr.', ['Informatika']),
            $this->guru(8, 'Feviana Miftahul Nurjanah, S.Pd. Gr.', ['Informatika']),
        ])['username'] ?? null, 'inisial bertitik "A.S" dibaca sebagai dua kata');
        $this->assertNull($cari('Nama Tidak Dikenal'));
        $this->assertNull($cari('Dina'), 'dua guru cocok sama baiknya, jangan menebak');
        $this->assertNull($cari(''));
    }

    public function test_kelas_dibuat_dari_mapel_wali_dan_wali_kelas_rombel_terisi(): void
    {
        $tahun = TahunAjaran::create(['nama' => '2026/2027', 'semester' => 'GANJIL', 'aktif' => true]);
        $akunA = $this->akun('GURU', 'SMKBM013');
        $akunB = $this->akun('GURU', 'SMKBM019');
        $rombelA = $this->rombel('X TJKT 1', 'X', 'TJKT');
        $rombelB = $this->rombel('XI RPL', 'XI', 'RPL');

        $guru = [
            $this->guru(13, 'Latif A S', ['Produktif TKJ', 'Mata Pelajaran Pilihan (Pembekalan PKL/Informatika)']),
            $this->guru(19, 'Teti Tresnawati, M.Pd', []),
        ];
        $wali = ['X TJKT 1' => 'Latif Abdul Salam', 'XI RPL' => 'Teti Tresnawati, M.Pd', 'XII AKL' => 'Siapa Saja'];

        $hasil = KelasSeeder::bangun($guru, $wali, $tahun);

        $this->assertSame(2, Kelas::count());
        $this->assertSame(2, Kelas::where(['rombelId' => $rombelA->id, 'guruId' => $akunA->id, 'tahunAjaranId' => $tahun->id])->count());
        $this->assertSame($akunA->id, $rombelA->fresh()->waliKelasId);
        $this->assertNull($rombelB->fresh()->waliKelasId);

        $this->assertSame(2, $hasil[0]['dibuat']);
        $this->assertSame('', $hasil[0]['catatan']);
        $this->assertSame('wali kelas tidak punya mapel', $hasil[1]['catatan']);
        $this->assertSame('rombel belum ada (jalankan SiswaSeeder)', $hasil[2]['catatan']);
        $this->assertNotContains($akunB->id, Kelas::pluck('guruId')->all());
    }

    public function test_dijalankan_ulang_tidak_menggandakan_dan_tidak_menimpa_guru_lain(): void
    {
        $tahun = TahunAjaran::create(['nama' => '2026/2027', 'semester' => 'GANJIL', 'aktif' => true]);
        $wali = $this->akun('GURU', 'SMKBM013');
        $lain = $this->akun('GURU', 'SMKBM020');
        $rombel = $this->rombel('X TJKT 1', 'X', 'TJKT');
        $guru = [$this->guru(13, 'Latif A S', ['Produktif TKJ', 'Informatika'])];
        $dataWali = ['X TJKT 1' => 'Latif Abdul Salam'];

        // Produktif TKJ di rombel ini sudah diajar guru lain: tidak boleh diambil alih.
        $sudahAda = $this->kelas($lain, $rombel, 'Produktif TKJ');

        $pertama = KelasSeeder::bangun($guru, $dataWali, $tahun);
        $kedua = KelasSeeder::bangun($guru, $dataWali, $tahun);

        $this->assertSame(2, Kelas::count());
        $this->assertSame($lain->id, $sudahAda->fresh()->guruId);
        $this->assertSame(1, $pertama[0]['dibuat']);
        $this->assertSame(1, $pertama[0]['ada']);
        $this->assertSame(0, $kedua[0]['dibuat']);
        $this->assertSame(2, $kedua[0]['ada']);
        $this->assertSame($wali->id, Kelas::where('mapelId', Mapel::where('nama', 'Informatika')->value('id'))->value('guruId'));
    }

    public function test_rombel_tanpa_akun_guru_dilewati(): void
    {
        $tahun = TahunAjaran::create(['nama' => '2026/2027', 'semester' => 'GANJIL', 'aktif' => true]);
        $this->rombel('X TJKT 1', 'X', 'TJKT');

        $hasil = KelasSeeder::bangun([$this->guru(13, 'Latif A S', ['Produktif TKJ'])], ['X TJKT 1' => 'Latif Abdul Salam'], $tahun);

        $this->assertSame('akun guru belum ada (jalankan GuruSeeder)', $hasil[0]['catatan']);
        $this->assertSame(0, Kelas::count());
    }

    public function test_seeder_menolak_berjalan_tanpa_tahun_ajaran_aktif(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Belum ada tahun ajaran aktif');

        (new KelasSeeder)->run();
    }

    public function test_baca_wali_kelas_dari_tanda_tangan_tiap_blok(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Dua blok meniru berkas asli: label "Wali Kelas" di kolom berbeda, nama wali empat baris di bawahnya, kepala sekolah di kolom B.
        $sheet->setCellValue('A1', 'DAFTAR ABSENSI SISWA KELAS X TJKT 1');
        $sheet->setCellValue('A4', 1);
        $sheet->setCellValue('B4', 'ADAM SATU');
        $sheet->setCellValue('B6', 'Mengetahui,');
        $sheet->setCellValue('B7', 'Kepala SMK Budi Mulia');
        $sheet->setCellValue('X7', 'Wali Kelas');
        $sheet->setCellValue('B11', 'Teti Tresnawati, M.Pd');
        $sheet->setCellValue('X11', 'Rio  Falentino, S.Pd., Gr.');

        $sheet->setCellValue('A13', 'DAFTAR ABSENSI SISWA KELAS  XI   RPL');
        $sheet->setCellValue('A16', 1);
        $sheet->setCellValue('B16', 'BUDI DUA');
        $sheet->setCellValue('B18', 'Mengetahui,');
        $sheet->setCellValue('B19', 'Kepala SMK Budi Mulia');
        $sheet->setCellValue('T19', 'wali kelas');
        $sheet->setCellValue('B23', 'Teti Tresnawati, M.Pd');
        $sheet->setCellValue('T23', 'Feviana Miftahul Nurjanah, S.Pd.');

        // Blok tanpa tanda tangan tidak menghasilkan entri.
        $sheet->setCellValue('A25', 'DAFTAR ABSENSI SISWA KELAS X AKL');
        $sheet->setCellValue('A28', 1);
        $sheet->setCellValue('B28', 'CITRA TIGA');

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'wali-'.uniqid().'.xlsx';
        try {
            (new Xlsx($spreadsheet))->save($path);

            $this->assertSame(
                ['X TJKT 1' => 'Rio Falentino, S.Pd., Gr.', 'XI RPL' => 'Feviana Miftahul Nurjanah, S.Pd.'],
                SiswaSeeder::bacaWaliKelas($path),
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_baca_wali_kelas_gagal_jelas_bila_berkas_tidak_ada(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Berkas data siswa tidak ditemukan');

        SiswaSeeder::bacaWaliKelas(sys_get_temp_dir().DIRECTORY_SEPARATOR.'tidak-ada-'.uniqid().'.xlsx');
    }
}
