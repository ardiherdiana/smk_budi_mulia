<?php

namespace App\Http\Controllers\Classroom\Admin;

use App\Http\Controllers\Classroom\Controller;
use App\Models\Classroom\Akun;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SiswaController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $rombelId = $request->query('rombelId');

        $siswa = Siswa::with(['user:id,username,is_active', 'rombel:id,nama'])
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('nama', 'like', "%{$q}%")
                    ->orWhere('nis', 'like', "%{$q}%")
                    ->orWhereHas('user', fn ($u) => $u->where('username', 'like', "%{$q}%"));
            }))
            ->when($rombelId, fn ($query) => $query->where('rombelId', $rombelId))
            ->orderBy('nama')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('classroom/admin/siswa-page', [
            'siswa' => $siswa->through(fn (Siswa $s) => [
                'id' => $s->id,
                'nama' => $s->nama,
                'nis' => $s->nis,
                'username' => $s->user->username,
                'aktif' => $s->user->is_active,
                'rombelId' => $s->rombelId,
                'rombel' => $s->rombel->nama,
            ]),
            'rombel' => Rombel::orderBy('nama')->get(['id', 'nama']),
            'filter' => ['q' => $q, 'rombelId' => $rombelId],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('profil_akun', 'username')],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'rombelId' => ['required', 'string', Rule::exists('rombel', 'id')],
            'nis' => ['nullable', 'string', 'max:20', Rule::unique('siswa', 'nis')],
        ], $this->pesan());

        DB::transaction(function () use ($validated) {
            $user = new Akun;
            $user->forceFill([
                'username' => $validated['username'],
                'name' => $validated['nama'],
                'password' => $validated['password'],
                'role' => 'SISWA',
                'is_active' => true,
            ])->save();

            Siswa::create([
                'userId' => $user->id,
                'rombelId' => $validated['rombelId'],
                'nis' => $validated['nis'] ?: null,
                'nama' => $validated['nama'],
            ]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Siswa ditambahkan']);
    }

    public function update(Request $request, Siswa $siswa)
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('profil_akun', 'username')->ignore($siswa->userId)],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
            'rombelId' => ['required', 'string', Rule::exists('rombel', 'id')],
            'nis' => ['nullable', 'string', 'max:20', Rule::unique('siswa', 'nis')->ignore($siswa->id)],
            'aktif' => ['nullable', 'boolean'],
        ], $this->pesan());

        DB::transaction(function () use ($validated, $siswa) {
            $siswa->update([
                'nama' => $validated['nama'],
                'rombelId' => $validated['rombelId'],
                'nis' => $validated['nis'] ?: null,
            ]);

            $user = $siswa->user;
            $user->username = $validated['username'];
            $user->name = $validated['nama'];
            if (array_key_exists('aktif', $validated)) {
                $user->is_active = (bool) $validated['aktif'];
            }
            if (! empty($validated['password'])) {
                $user->password = $validated['password'];
            }
            $user->save();
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Data siswa diperbarui']);
    }

    public function destroy(Siswa $siswa)
    {
        // Menghapus akun ikut menghapus siswa, pengumpulan tugas, dan percobaan ujiannya (cascade).
        $siswa->user->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Siswa dihapus']);
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
            'rombelId.required' => 'Pilih rombel',
            'nis.unique' => 'NIS sudah dipakai siswa lain',
        ];
    }
}
