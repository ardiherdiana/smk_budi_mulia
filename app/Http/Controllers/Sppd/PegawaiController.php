<?php

namespace App\Http\Controllers\Sppd;

use App\Enums\Sppd\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sppd\StorePegawaiRequest;
use App\Http\Requests\Sppd\UpdatePegawaiRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akun pegawai untuk SPPD, dikelola TU. Sejak absensi digabung ke situs sekolah, `users` juga berisi siswa dan akun
 * portal; halaman ini hanya menampilkan dan mengubah akun staf (KEPSEK, GURU, STAF). Akun admin hanya boleh diubah oleh
 * admin, dan hapus ditolak bila akun masih punya data di Absensi atau Classroom.
 */
class PegawaiController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('sppd/pegawai/index', [
            'items' => User::query()
                ->with('roles', 'akun')
                ->whereHas('akun', fn ($q) => $q->whereIn('role', User::PERAN_STAF))
                ->orderByDesc('id')
                ->paginate($this->perPage($request)),
            'roles' => array_map(fn (RoleName $r) => ['value' => $r->value, 'label' => $r->label()], RoleName::cases()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('sppd/pegawai/create', [
            'roles' => array_map(fn (RoleName $r) => ['value' => $r->value, 'label' => $r->label()], RoleName::cases()),
        ]);
    }

    public function store(StorePegawaiRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'jabatan' => $data['jabatan'] ?? null,
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($data['roles']);

        return to_route('sppd.pegawai.index')->with('success', 'Akun pegawai berhasil dibuat.');
    }

    public function edit(Request $request, User $pegawai): Response
    {
        $this->pastikanBolehDikelola($request, $pegawai);

        return Inertia::render('sppd/pegawai/edit', [
            'pegawai' => $pegawai->load('roles'),
            'roles' => array_map(fn (RoleName $r) => ['value' => $r->value, 'label' => $r->label()], RoleName::cases()),
        ]);
    }

    public function update(UpdatePegawaiRequest $request, User $pegawai): RedirectResponse
    {
        $this->pastikanBolehDikelola($request, $pegawai);

        $data = $request->validated();

        $pegawai->update([
            'name' => $data['name'],
            'jabatan' => $data['jabatan'] ?? null,
            'username' => $data['username'],
            'is_active' => $data['is_active'],
        ]);

        $pegawai->syncRoles($data['roles']);

        return to_route('sppd.pegawai.index')->with('success', 'Akun pegawai berhasil diperbarui.');
    }

    public function destroy(Request $request, User $pegawai): RedirectResponse
    {
        $this->pastikanBolehDikelola($request, $pegawai);

        if ($pegawai->id === auth()->id()) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $pegawai->cegahHapusLintasModul('SPPD');
        $pegawai->delete();

        return back()->with('success', 'Akun pegawai dihapus.');
    }

    /** Hanya akun staf; akun admin hanya boleh dikelola admin. Siswa dan akun portal tidak terlihat (404). */
    private function pastikanBolehDikelola(Request $request, User $pegawai): void
    {
        abort_unless($pegawai->isStaf(), 404);
        abort_if($pegawai->isAdmin() && ! $request->user()->isAdmin(), 403, 'Akun admin hanya bisa diubah oleh admin.');
    }
}
