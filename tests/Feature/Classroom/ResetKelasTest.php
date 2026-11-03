<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Jawaban;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Komentar;
use App\Models\Classroom\Lampiran;
use App\Models\Classroom\Mapel;
use App\Models\Classroom\Pengumpulan;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\Siswa;
use App\Models\Classroom\Soal;
use App\Models\Classroom\TahunAjaran;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResetKelasTest extends TestCase
{
    /** Satu kelas lengkap dengan forum, tugas, ujian, nilai, dan satu berkas lampiran fisik. */
    private function isiData(): array
    {
        $guru = $this->akun('GURU', 'guru1');
        $rombel = $this->rombel();
        $siswa = $this->siswa($rombel, 'siswa01');
        $kelas = $this->kelas($guru, $rombel);

        $tugas = $this->buatPost($kelas, $guru, 'TUGAS');
        $ujian = $this->buatPost($kelas, $guru, 'UJIAN', ['durasiMenit' => 30]);
        Komentar::create(['postId' => $tugas->id, 'userId' => $siswa->id, 'isi' => 'Siap']);
        $kumpul = Pengumpulan::create([
            'postId' => $tugas->id, 'siswaId' => $siswa->siswa->id, 'teks' => 'jawaban',
            'status' => 'DIKUMPULKAN', 'submittedAt' => now(),
        ]);

        Storage::disk('local')->put('lampiran/tugas.pdf', 'pdf');
        Storage::disk('local')->put('lampiran/hasil.pdf', 'pdf');
        Lampiran::create(['postId' => $tugas->id, 'tipe' => 'FILE', 'nama' => 'tugas.pdf', 'path' => 'lampiran/tugas.pdf']);
        Lampiran::create(['pengumpulanId' => $kumpul->id, 'tipe' => 'FILE', 'nama' => 'hasil.pdf', 'path' => 'lampiran/hasil.pdf']);

        $soal = Soal::create(['postId' => $ujian->id, 'urut' => 1, 'tipe' => 'ESSAY', 'teks' => 'Jelaskan', 'poin' => 5]);
        $percobaan = Percobaan::create([
            'postId' => $ujian->id, 'siswaId' => $siswa->siswa->id,
            'mulaiAt' => now(), 'batasAt' => now()->addMinutes(30), 'status' => 'DINILAI',
        ]);
        Jawaban::create(['percobaanId' => $percobaan->id, 'soalId' => $soal->id, 'jawaban' => 'TCP/IP']);

        return [$guru, $rombel, $siswa, $kelas];
    }

    public function test_dry_run_hanya_menampilkan_jumlah_dan_tidak_menghapus(): void
    {
        $this->isiData();

        $this->artisan('classroom:reset-kelas', ['--dry-run' => true])
            ->expectsOutputToContain('Dry-run selesai')
            ->assertSuccessful();

        $this->assertSame(1, Kelas::count());
        $this->assertSame(2, Post::count());
        $this->assertSame(1, Percobaan::count());
        Storage::disk('local')->assertExists(['lampiran/tugas.pdf', 'lampiran/hasil.pdf']);
    }

    public function test_force_menghapus_forum_tugas_nilai_dan_berkas_tetapi_menyisakan_data_induk(): void
    {
        $this->isiData();

        $this->artisan('classroom:reset-kelas', ['--force' => true])->assertSuccessful();

        foreach ([Kelas::class, Post::class, Komentar::class, Pengumpulan::class, Lampiran::class, Soal::class, Percobaan::class, Jawaban::class] as $model) {
            $this->assertSame(0, $model::count(), "{$model} seharusnya kosong");
        }
        Storage::disk('local')->assertMissing(['lampiran/tugas.pdf', 'lampiran/hasil.pdf']);

        // Data induk tidak boleh ikut hilang.
        $this->assertSame(1, Akun::where('role', 'GURU')->count());
        $this->assertSame(1, Akun::where('role', 'SISWA')->count());
        $this->assertSame(1, Siswa::count());
        $this->assertSame(1, Rombel::count());
        $this->assertSame(1, Mapel::count());
        $this->assertSame(1, TahunAjaran::where('aktif', true)->count());
    }

    public function test_tanpa_konfirmasi_tidak_menghapus(): void
    {
        $this->isiData();

        $this->artisan('classroom:reset-kelas')
            ->expectsConfirmation('Hapus semua data di atas? Tindakan ini tidak bisa dibatalkan', 'no')
            ->expectsOutputToContain('Dibatalkan')
            ->assertSuccessful();

        $this->assertSame(1, Kelas::count());
        Storage::disk('local')->assertExists('lampiran/tugas.pdf');
    }

    public function test_dengan_konfirmasi_ya_menghapus(): void
    {
        $this->isiData();

        $this->artisan('classroom:reset-kelas')
            ->expectsConfirmation('Hapus semua data di atas? Tindakan ini tidak bisa dibatalkan', 'yes')
            ->assertSuccessful();

        $this->assertSame(0, Kelas::count());
    }

    public function test_basis_data_kosong_tidak_error(): void
    {
        $this->artisan('classroom:reset-kelas', ['--force' => true])
            ->expectsOutputToContain('0 ruang kelas')
            ->assertSuccessful();
    }
}
