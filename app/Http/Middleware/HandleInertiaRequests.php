<?php

namespace App\Http\Middleware;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\TahunAjaran;
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
     * Props yang dibagikan ke semua halaman. Di rute Classroom `$request->user()` adalah profil (`Akun`, lihat
     * RequirePeran), jadi bentuk `auth.user` berbeda di sana: `role` berisi peran Classroom.
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

    /** @return array{user: array<string, mixed>|null} */
    private function auth(Request $request): array
    {
        $user = $request->user();
        $akun = $user instanceof Akun ? $user : null;
        $akun?->loadMissing('siswa.rombel:id,nama', 'staff:id,image');

        return [
            'user' => match (true) {
                $akun !== null => [
                    'id' => $akun->id,
                    'username' => $akun->username,
                    'role' => $akun->role,
                    'name' => $akun->namaTampil(),
                    'fotoUrl' => $akun->fotoUrl(),
                    'siswa' => $akun->siswa ? [
                        'id' => $akun->siswa->id,
                        'nis' => $akun->siswa->nis,
                        'rombel' => $akun->siswa->rombel?->nama,
                    ] : null,
                ],
                $user !== null => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
                default => null,
            },
        ];
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
