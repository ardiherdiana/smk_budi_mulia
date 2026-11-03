<?php

namespace Tests\Feature\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Jawaban;
use App\Models\Classroom\Komentar;
use App\Models\Classroom\Lampiran;
use App\Models\Classroom\Pengumpulan;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Soal;
use App\Models\Classroom\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Jelajah menyeluruh modul Classroom: membuka SEMUA halaman GET untuk tiap peran, memastikan tidak ada 404/500, dan
 * memindai kode frontend supaya setiap URL yang dirakit di sana benar-benar cocok dengan rute terdaftar.
 */
class JelajahHalamanTest extends TestCase
{
    /** @var array<string, string> nama parameter rute => id contoh */
    private array $id = [];

    private Akun $admin;

    private Akun $kepsek;

    private Akun $guru;

    private Akun $guruLain;

    private Akun $siswa;

    private Akun $siswaLain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->akun('ADMIN', 'adm');
        $this->kepsek = $this->akun('KEPSEK', 'kepsek1');
        $this->guru = $this->akun('GURU', 'guru1');
        $this->guruLain = $this->akun('GURU', 'guru2');

        $rombel = $this->rombel('X TKJ 1');
        $rombel->update(['waliKelasId' => $this->guru->id]);
        $this->siswa = $this->siswa($rombel, 'siswa01');
        $this->siswaLain = $this->siswa($this->rombel('XI RPL'), 'siswa02');

        $kelas = $this->kelas($this->guru, $rombel);

        $tugas = $this->buatPost($kelas, $this->guru, 'TUGAS', ['poinMaks' => 100, 'deadline' => now()->addDays(3), 'isi' => 'Kerjakan']);
        $this->buatPost($kelas, $this->guru, 'PENGUMUMAN', ['isi' => 'Halo']);
        $this->buatPost($kelas, $this->guru, 'MATERI', ['isi' => 'Bab 1']);
        $ujian = $this->buatPost($kelas, $this->guru, 'UJIAN', [
            'durasiMenit' => 30, 'mulaiAt' => now()->subDay(), 'selesaiAt' => now()->addDay(), 'tampilkanNilai' => true,
        ]);
        $soal = Soal::create(['postId' => $ujian->id, 'urut' => 1, 'tipe' => 'PG', 'teks' => '1+1?', 'poin' => 10, 'opsi' => ['1', '2'], 'kunci' => '1']);
        Soal::create(['postId' => $ujian->id, 'urut' => 2, 'tipe' => 'ESSAY', 'teks' => 'Jelaskan', 'poin' => 10]);

        $pengumpulan = Pengumpulan::create([
            'postId' => $tugas->id, 'siswaId' => $this->siswa->siswa->id, 'teks' => 'jawaban', 'status' => 'DIKUMPULKAN', 'submittedAt' => now(),
        ]);
        $percobaan = Percobaan::create([
            'postId' => $ujian->id, 'siswaId' => $this->siswa->siswa->id, 'mulaiAt' => now(), 'batasAt' => now()->addMinutes(30), 'status' => 'BERJALAN',
        ]);
        $jawaban = Jawaban::create(['percobaanId' => $percobaan->id, 'soalId' => $soal->id, 'jawaban' => '1']);
        $komentar = Komentar::create(['postId' => $tugas->id, 'userId' => $this->siswa->id, 'isi' => 'Siap']);

        Storage::fake('local');
        Storage::disk('local')->put('lampiran/contoh.pdf', '%PDF-1.4 contoh');
        $lampiran = Lampiran::create(['postId' => $tugas->id, 'tipe' => 'FILE', 'nama' => 'contoh.pdf', 'path' => 'lampiran/contoh.pdf', 'mime' => 'application/pdf', 'ukuran' => 15]);

        $this->id = [
            'kelas' => $kelas->id, 'post' => $tugas->id, 'ujian' => $ujian->id, 'pengumpulan' => $pengumpulan->id,
            'percobaan' => $percobaan->id, 'jawaban' => $jawaban->id, 'soal' => $soal->id, 'komentar' => $komentar->id,
            'lampiran' => $lampiran->id, 'rombel' => $rombel->id, 'mapel' => $kelas->mapelId,
            'siswa' => $this->siswa->siswa->id, 'tahunAjaran' => TahunAjaran::first()->id,
        ];
    }

    /** @return list<array{0: string, 1: string}> [url, komponen Inertia yang diharapkan] */
    private function halamanInertia(): array
    {
        $k = $this->id['kelas'];

        return [
            ['/classroom/kelas', 'classroom/kelas/daftar-page'],
            ['/classroom/kelas?arsip=1', 'classroom/kelas/daftar-page'],
            ["/classroom/kelas/{$k}", 'classroom/kelas/stream-page'],
            ["/classroom/kelas/{$k}/tugas", 'classroom/kelas/tugas-page'],
            ["/classroom/kelas/{$k}/anggota", 'classroom/kelas/anggota-page'],
            ["/classroom/kelas/{$k}/nilai", 'classroom/kelas/nilai-page'],
            ['/classroom/tugas', 'classroom/agenda/tugas-page'],
            ['/classroom/kalender', 'classroom/agenda/kalender-page'],
            ['/classroom/kalender?bulan='.now()->format('Y-m'), 'classroom/agenda/kalender-page'],
            ["/classroom/post/{$this->id['post']}", 'classroom/post/detail-page'],
            ["/classroom/post/{$this->id['ujian']}", 'classroom/post/detail-page'],
            ['/classroom/profil', 'classroom/profil-page'],
        ];
    }

    /** @return list<string> halaman data induk (admin dan kepsek) */
    private function halamanData(): array
    {
        return ['/classroom/rombel', '/classroom/siswa', '/classroom/mapel', '/classroom/tahun-ajaran', '/classroom/data-guru'];
    }

    public function test_semua_halaman_inertia_terbuka_untuk_peran_yang_berhak(): void
    {
        foreach ([$this->admin, $this->kepsek, $this->guru, $this->siswa] as $akun) {
            foreach ($this->halamanInertia() as [$url, $komponen]) {
                $this->actingAs($akun)->get($url)
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page->component($komponen));
            }
        }
    }

    public function test_sidebar_dan_props_bersama_tersedia_di_setiap_halaman(): void
    {
        foreach ([$this->admin, $this->guru, $this->siswa] as $akun) {
            $this->actingAs($akun)->get('/classroom/kelas')->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.role', $akun->role)
                ->where('auth.user.username', $akun->username)
                ->has('kelasSaya'));
        }

        $this->actingAs($this->guru)->get('/classroom/kelas')->assertInertia(fn (Assert $page) => $page->has('kelasSaya', 1));
        $this->actingAs($this->guruLain)->get('/classroom/kelas')->assertInertia(fn (Assert $page) => $page->has('kelasSaya', 0));
    }

    public function test_halaman_data_induk_hanya_admin_dan_kepsek(): void
    {
        foreach ($this->halamanData() as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
            $this->actingAs($this->kepsek)->get($url)->assertOk();
            $this->actingAs($this->guru)->get($url)->assertForbidden();
            $this->actingAs($this->siswa)->get($url)->assertForbidden();
        }
    }

    public function test_pengguna_lain_tidak_bisa_masuk_ke_kelas_orang(): void
    {
        $k = $this->id['kelas'];
        foreach (["/classroom/kelas/{$k}", "/classroom/kelas/{$k}/tugas", "/classroom/kelas/{$k}/anggota", "/classroom/kelas/{$k}/nilai", "/classroom/post/{$this->id['post']}"] as $url) {
            $this->actingAs($this->guruLain)->get($url)->assertForbidden();
            $this->actingAs($this->siswaLain)->get($url)->assertForbidden();
        }
    }

    public function test_halaman_ujian_koreksi_dan_lampiran(): void
    {
        $this->actingAs($this->guru)->get("/classroom/percobaan/{$this->id['percobaan']}")
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('classroom/ujian/koreksi-page'));
        $this->actingAs($this->kepsek)->get("/classroom/percobaan/{$this->id['percobaan']}")->assertForbidden();
        $this->actingAs($this->siswa)->get("/classroom/percobaan/{$this->id['percobaan']}")->assertForbidden();

        $this->actingAs($this->siswa)->get("/classroom/post/{$this->id['ujian']}/kerjakan")
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('classroom/ujian/kerjakan-page'));
        $this->actingAs($this->guru)->get("/classroom/post/{$this->id['ujian']}/kerjakan")->assertForbidden();

        foreach ([$this->admin, $this->guru, $this->siswa] as $akun) {
            $this->actingAs($akun)->get("/classroom/lampiran/{$this->id['lampiran']}")->assertOk();
        }
        $this->actingAs($this->siswaLain)->get("/classroom/lampiran/{$this->id['lampiran']}")->assertForbidden();
    }

    public function test_unduh_rekap_nilai_xlsx(): void
    {
        $url = "/classroom/kelas/{$this->id['kelas']}/nilai/export";

        foreach ([$this->admin, $this->kepsek, $this->guru] as $akun) {
            $this->actingAs($akun)->get($url)->assertOk()
                ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
        $this->actingAs($this->siswa)->get($url)->assertForbidden();
    }

    public function test_setiap_rute_get_classroom_tidak_404_atau_500(): void
    {
        $dilewati = [];
        $dicoba = 0;

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'classroom') || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();
            foreach ($route->parameterNames() as $nama) {
                if (! isset($this->id[$nama])) {
                    $dilewati[] = $uri;

                    continue 2;
                }
                // Editor soal dan ekspor nilai ujian hanya ada untuk ujian, jadi {post} di rute itu diisi postingan bertipe UJIAN.
                $khususUjian = str_ends_with($route->uri(), '/soal') || str_ends_with($route->uri(), '/nilai/export');
                $contoh = ($nama === 'post' && $khususUjian) ? $this->id['ujian'] : $this->id[$nama];
                $uri = str_replace('{'.$nama.'}', $contoh, $uri);
            }

            $status = $this->actingAs($this->admin)->get('/'.$uri)->getStatusCode();
            $this->assertContains($status, [200, 302, 403], "GET /{$uri} => {$status}");
            $dicoba++;
        }

        $this->assertSame([], $dilewati, 'Parameter rute tanpa data contoh: tambahkan di setUp()');
        $this->assertGreaterThan(15, $dicoba);
    }

    public function test_setiap_nama_halaman_inertia_di_php_punya_berkas_react(): void
    {
        $semua = [];
        foreach ($this->berkas(app_path(), 'php') as $file) {
            preg_match_all("/Inertia::render\\('([^']+)'/", (string) file_get_contents($file), $m);
            array_push($semua, ...$m[1]);
        }

        $this->assertNotEmpty($semua);
        foreach (array_unique($semua) as $nama) {
            $this->assertFileExists(resource_path("js/pages/{$nama}.tsx"), "Halaman Inertia '{$nama}' tidak punya berkas React");
        }
    }

    public function test_setiap_url_di_frontend_cocok_dengan_rute_terdaftar(): void
    {
        $router = app('router');
        $rutes = $router->getRoutes();
        $tidakCocok = [];
        $diperiksa = 0;

        foreach ($this->berkas(resource_path('js'), 'tsx', 'ts') as $file) {
            if (str_contains($file, DIRECTORY_SEPARATOR.'ui'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            foreach ($this->urlDariKode((string) file_get_contents($file)) as $url) {
                $diperiksa++;
                if (! $this->cocokRute($rutes, $url)) {
                    $tidakCocok[] = basename($file).": {$url}";
                }
            }
        }

        $this->assertGreaterThan(50, $diperiksa, 'Pemindai tidak menemukan URL; periksa pola');
        $this->assertSame([], array_values(array_unique($tidakCocok)), 'URL frontend tanpa rute');
    }

    public function test_tab_header_kelas_membentuk_url_yang_valid(): void
    {
        $kode = (string) file_get_contents(resource_path('js/components/classroom/kelas-header.tsx'));
        preg_match_all('/path:\s*"([^"]*)"/', $kode, $m);

        $this->assertCount(4, $m[1], 'Harus ada empat tab (Forum, Tugas, Anggota, Nilai)');
        foreach ($m[1] as $path) {
            $url = '/classroom/kelas/'.$this->id['kelas'].$path;
            $this->actingAs($this->guru)->get($url)->assertOk();
        }
    }

    public function test_menu_utama_dan_modul_lain_tetap_terbuka_per_peran(): void
    {
        foreach ([$this->admin, $this->guru, $this->siswa] as $akun) {
            $this->actingAs($akun)->get('/menu-utama')->assertStatus(in_array($akun->role, ['GURU', 'KEPSEK'], true) ? 302 : 200);
        }

        $adminUser = $this->admin->pengguna;
        foreach (['/admin', '/admin/berita', '/admin/tentang-kami', '/admin/jurusan', '/admin/guru-staff', '/admin/kepala-sekolah', '/skl', '/skl/siswa', '/skl/berkas'] as $url) {
            $this->actingAs($adminUser)->get($url)->assertOk();
        }
        $this->actingAs($this->siswa->pengguna)->get('/portal')->assertOk();
    }

    /** @return list<string> */
    private function berkas(string $dir, string ...$ekstensi): array
    {
        $hasil = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (in_array($f->getExtension(), $ekstensi, true)) {
                $hasil[] = $f->getPathname();
            }
        }

        return $hasil;
    }

    /**
     * Ambil literal URL internal dari kode React: "...", '...', dan `...` yang diawali /classroom, /admin, /skl, dst.
     * `${...}` diganti segmen contoh. Hasilnya tanpa query string.
     *
     * @return list<string>
     */
    private function urlDariKode(string $kode): array
    {
        preg_match_all('/(["\'`])(\/(?:classroom|admin|skl|portal|menu-utama|akun|login|logout)(?:[^"\'`\s]*))\1/', $kode, $m);

        $hasil = [];
        foreach ($m[2] as $url) {
            $url = preg_replace('/\$\{[^}]*\}/', 'x', $url);
            $url = explode('?', $url)[0];
            $hasil[] = $url;
        }

        return $hasil;
    }

    private function cocokRute($rutes, string $url): bool
    {
        foreach (['GET', 'POST', 'PATCH', 'PUT', 'DELETE'] as $metode) {
            try {
                $rutes->match(Request::create($url, $metode));

                return true;
            } catch (\Throwable) {
                // coba metode berikutnya
            }
        }

        return false;
    }
}
