<?php

namespace App\Http\Controllers\Classroom\Admin;

use App\Http\Controllers\Classroom\Controller;
use App\Models\Classroom\Akun;
use App\Models\Classroom\Post;
use App\Services\Classroom\GuruStaffSync;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Akun staf (guru/kepsek/admin). Admin menambah, mengubah, dan menghapus guru langsung di sini; sandi disimpan lokal.
 * Guru (peran GURU) otomatis punya baris di `guru_staff`, jadi tampil di #guru di website beserta fotonya (GuruStaffSync).
 */
class GuruController extends Controller
{
    public function __construct(private GuruStaffSync $website) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $staf = Akun::whereIn('role', ['GURU', 'KEPSEK', 'ADMIN'])
            ->with('staff:id,image')
            ->withCount(['kelasDiajar', 'rombelWali'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%")))
            ->orderByRaw("field(role, 'ADMIN', 'KEPSEK', 'GURU')")
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('classroom/admin/guru-page', [
            'guru' => $staf->through(fn (Akun $u) => [
                'id' => $u->id,
                'nama' => $u->namaTampil(),
                'username' => $u->username,
                'role' => $u->role,
                'aktif' => $u->is_active,
                'jumlahKelas' => $u->kelas_diajar_count,
                'waliKelas' => $u->rombel_wali_count > 0,
                'fotoUrl' => $u->fotoUrl(),
                'tampilDiWebsite' => $u->guruStaffId !== null,
            ]),
            'filter' => ['q' => $q],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('profil_akun', 'username')],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'role' => ['required', Rule::in(['GURU', 'KEPSEK'])],
            'foto' => $this->aturanFoto(),
        ], $this->pesan());

        $akun = new Akun;
        $akun->forceFill([
            'username' => $validated['username'],
            'name' => $validated['nama'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'is_active' => true,
        ])->save();
        $this->website->buat($akun, $request->file('foto'));

        return back()->with('toast', ['type' => 'success', 'message' => 'Akun ditambahkan']);
    }

    public function update(Request $request, Akun $guru)
    {
        abort_unless($guru->isStaf(), 404);

        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('profil_akun', 'username')->ignore($guru->id)],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'role' => ['nullable', Rule::in(['GURU', 'KEPSEK'])],
            'aktif' => ['nullable', 'boolean'],
            'foto' => $this->aturanFoto(),
            'hapusFoto' => ['nullable', 'boolean'],
        ], $this->pesan());

        $aktif = array_key_exists('aktif', $validated) ? (bool) $validated['aktif'] : $guru->is_active;
        abort_if($guru->is($request->user()) && ! $aktif, 409, 'Akun yang sedang dipakai tidak bisa dinonaktifkan');

        $namaLama = $guru->name;
        $guru->username = $validated['username'];
        $guru->name = $validated['nama'];
        $guru->is_active = $aktif;
        if (! $guru->isAdmin() && ! empty($validated['role'])) {
            $guru->role = $validated['role'];
        }
        if (! empty($validated['password'])) {
            $guru->password = $validated['password'];
            // Akun bekas sinkron absensi diverifikasi ke absensi; sandi baru hanya berlaku bila tautannya dilepas.
            $guru->absensi_user_id = null;
        }
        $guru->save();
        $this->website->sinkron($guru, $namaLama, $request->file('foto'), $request->boolean('hapusFoto'));

        return back()->with('toast', ['type' => 'success', 'message' => 'Akun diperbarui']);
    }

    public function destroy(Akun $guru)
    {
        abort_unless($guru->isStaf(), 404);
        abort_if($guru->isAdmin(), 409, 'Akun admin tidak bisa dihapus');
        abort_if($guru->kelasDiajar()->exists(), 409, 'Guru masih mengajar ruang kelas. Hapus atau pindahkan ruang kelasnya dulu');
        abort_if(Post::where('dibuatOleh', $guru->id)->exists(), 409, 'Guru ini masih tercatat sebagai pembuat postingan');

        // Wali kelas di rombel otomatis dikosongkan (FK nullOnDelete); baris `users`-nya ikut terhapus oleh model.
        // Orangnya di `guru_staff` sengaja dibiarkan: tetap tampil di #guru (hapus lewat Admin > Guru & Staf).
        $guru->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Akun dihapus']);
    }

    /** @return list<string> */
    private function aturanFoto(): array
    {
        return ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.config('school.media.max_image_kb')];
    }

    /** @return array<string, string> */
    private function pesan(): array
    {
        return [
            'nama.required' => 'Nama wajib diisi',
            'username.required' => 'Username wajib diisi',
            'username.unique' => 'Username sudah dipakai',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, garis bawah, dan strip',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 6 karakter',
            'role.required' => 'Pilih peran',
        ];
    }
}
