<?php

namespace App\Http\Middleware;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\TahunAjaran;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props yang dibagikan ke semua halaman. `auth.user` punya SATU bentuk untuk semua modul (Website, SKL, Classroom,
     * Absensi, SPPD): `role` = peran profil (ADMIN|KEPSEK|GURU|SISWA|STAF) bila ada, `admin` = admin satu untuk semua
     * modul. Di rute Classroom `$request->user()` adalah profil (`Akun`, lihat RequirePeran); keduanya ditangani di sini.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Props ini dihitung saat respons dibuat (lazy), SETELAH middleware `peran` mengganti `$request->user()` dengan
        // profil Classroom. Inertia memanggil share() sebelum route middleware jalan.
        return [
            ...parent::share($request),
            'auth' => fn () => $this->auth($request),
            // Daftar ruang kelas aktif untuk sidebar Classroom.
            'kelasSaya' => function () use ($request) {
                $user = $request->user();

                return $user instanceof Akun ? $this->kelasSaya($user) : [];
            },
            'flash' => [
                'toast' => fn () => $request->session()->get('toast'),
                'credentials' => fn () => $request->session()->get('credentials'),
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /** @return array{user: array<string, mixed>|null, roles: list<string>} */
    private function auth(Request $request): array
    {
        $pengguna = $this->pengguna($request);
        if ($pengguna === null) {
            return ['user' => null, 'roles' => []];
        }

        $akun = $request->user() instanceof Akun ? $request->user() : $pengguna->akun;
        $akun?->loadMissing('siswa.rombel:id,nama', 'staff:id,image');
        $guru = $pengguna->guru()->first(['id', 'userId', 'nama', 'fotoUrl']);

        return [
            'user' => [
                'id' => $pengguna->id,
                'email' => $pengguna->email,
                'username' => $akun?->username,
                'role' => $akun?->role ?? ($pengguna->isAdmin() ? 'ADMIN' : $pengguna->role),
                'admin' => $pengguna->isAdmin(),
                'name' => $akun?->namaTampil(),
                'jabatan' => $akun?->jabatan,
                'is_active' => $akun?->is_active ?? true,
                'fotoUrl' => $akun?->fotoUrl(),
                'siswa' => $akun?->siswa ? [
                    'id' => $akun->siswa->id,
                    'nis' => $akun->siswa->nis,
                    'rombel' => $akun->siswa->rombel?->nama,
                ] : null,
                // Data guru absensi (QR); null untuk akun tanpa baris `guru`. Foto = foto website, foto absen lama bila belum ada.
                'guru' => $guru ? ['id' => $guru->id, 'nama' => $guru->nama, 'fotoUrl' => $akun?->fotoUrl() ?? $guru->fotoUrl] : null,
            ],
            // Role SPPD (spatie/laravel-permission) - terpisah dari `role` profil.
            'roles' => $pengguna->getRoleNames()->values()->all(),
        ];
    }

    /** Akun login sebagai model User, juga di rute Classroom (tempat `$request->user()` diganti `Akun`). */
    private function pengguna(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof Akun ? $user->pengguna : $user;
    }

    /** @return list<array{id: string, mapel: string, rombel: string}> */
    private function kelasSaya(Akun $user): array
    {
        $query = Kelas::with(['mapel:id,nama', 'rombel:id,nama'])->where('diarsipkan', false)->untukPengguna($user);

        if ($aktif = TahunAjaran::aktif()) {
            $query->where('tahunAjaranId', $aktif->id);
        }

        return $query->get()
            ->sortBy(fn (Kelas $k) => $k->mapel->nama.'|'.$k->rombel->nama)
            ->map(fn (Kelas $k) => ['id' => $k->id, 'mapel' => $k->mapel->nama, 'rombel' => $k->rombel->nama])
            ->values()->all();
    }
}
