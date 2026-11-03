<?php

namespace Tests;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Mapel;
use App\Models\Classroom\Post;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\Siswa;
use App\Models\Classroom\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Produksi mematikan SKL siswa sementara; tes memakai kondisi menyala kecuali tes khusus SklSiswaNonaktifTest.
        config(['school.skl_siswa_aktif' => true]);

        // Berkas PDF portal & cache media tidak boleh menyentuh storage sungguhan.
        Storage::fake('local');
        // Foto/video situs: jangan menulis ke public/media sungguhan. fake() membuang config asli disk, jadi url diisi ulang.
        Storage::fake('media', ['url' => '/media']);
        $this->withoutVite();
    }

    protected function admin(string $email = 'admin@test.local', string $password = 'Rahasia123'): User
    {
        return User::create(['email' => $email, 'password' => $password, 'role' => 'admin']);
    }

    protected function student(string $email = 'siswa@test.local', string $password = 'Rahasia123'): User
    {
        return User::create(['email' => $email, 'password' => $password, 'role' => 'user']);
    }

    /** Profil Classroom membungkus baris `users`; `actingAs` butuh pengguna terautentikasi, bukan profilnya. */
    public function actingAs($user, $guard = null)
    {
        return parent::actingAs($user instanceof Akun ? $user->pengguna : $user, $guard);
    }

    protected function akun(string $role, string $username, ?string $nama = null): Akun
    {
        $akun = new Akun;
        $akun->forceFill([
            'username' => $username,
            'name' => $nama ?? ucfirst($username),
            'password' => 'rahasia123',
            'role' => $role,
            'is_active' => true,
        ])->save();

        return $akun->refresh();
    }

    protected function rombel(string $nama = 'X TKJ 1', string $tingkat = 'X', string $jurusan = 'TKJ'): Rombel
    {
        return Rombel::create(['nama' => $nama, 'tingkat' => $tingkat, 'jurusan' => $jurusan]);
    }

    protected function siswa(Rombel $rombel, string $username): Akun
    {
        $akun = $this->akun('SISWA', $username);
        Siswa::create(['userId' => $akun->id, 'rombelId' => $rombel->id, 'nama' => $akun->name, 'nis' => '9'.crc32($username)]);

        return $akun->refresh();
    }

    protected function kelas(Akun $guru, Rombel $rombel, string $mapel = 'Jaringan Dasar'): Kelas
    {
        $tahun = TahunAjaran::first() ?? TahunAjaran::create(['nama' => '2026/2027', 'semester' => 'GANJIL', 'aktif' => true]);

        return Kelas::create([
            'rombelId' => $rombel->id,
            'mapelId' => Mapel::firstOrCreate(['nama' => $mapel])->id,
            'guruId' => $guru->id,
            'tahunAjaranId' => $tahun->id,
        ]);
    }

    /** @param  array<string, mixed>  $atribut */
    protected function buatPost(Kelas $kelas, Akun $guru, string $tipe, array $atribut = []): Post
    {
        return Post::create($atribut + [
            'kelasId' => $kelas->id,
            'tipe' => $tipe,
            'judul' => "Contoh {$tipe}",
            'dibuatOleh' => $guru->id,
        ]);
    }

    protected function actingAsAdmin(): User
    {
        return tap($this->admin(), fn (User $user) => $this->actingAs($user));
    }

    protected function actingAsStudent(): User
    {
        return tap($this->student(), fn (User $user) => $this->actingAs($user));
    }
}
