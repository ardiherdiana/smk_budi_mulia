<?php

namespace App\Http\Controllers\Classroom\Admin;

use App\Http\Controllers\Classroom\Controller;
use App\Models\Classroom\Akun;
use App\Models\Classroom\Rombel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class RombelController extends Controller
{
    public function index()
    {
        return Inertia::render('classroom/admin/rombel-page', [
            'rombel' => Rombel::with('waliKelas:id,name,username')->withCount(['siswa', 'kelas'])->orderBy('nama')->get()
                ->map(fn (Rombel $r) => [
                    'id' => $r->id,
                    'nama' => $r->nama,
                    'tingkat' => $r->tingkat,
                    'jurusan' => $r->jurusan,
                    'waliKelasId' => $r->waliKelasId,
                    'waliKelas' => $r->waliKelas?->namaTampil(),
                    'jumlahSiswa' => $r->siswa_count,
                    'jumlahKelas' => $r->kelas_count,
                ])->all(),
            'guru' => Akun::whereIn('role', ['GURU', 'KEPSEK'])->where('is_active', true)->orderBy('name')->get()
                ->map(fn (Akun $u) => ['id' => $u->id, 'nama' => $u->namaTampil()])->all(),
        ]);
    }

    public function store(Request $request)
    {
        Rombel::create($this->validasi($request));

        return back()->with('toast', ['type' => 'success', 'message' => 'Rombel ditambahkan']);
    }

    public function update(Request $request, Rombel $rombel)
    {
        $rombel->update($this->validasi($request, $rombel));

        return back()->with('toast', ['type' => 'success', 'message' => 'Rombel diperbarui']);
    }

    public function destroy(Rombel $rombel)
    {
        abort_if($rombel->siswa()->exists(), 409, 'Rombel masih memiliki siswa. Pindahkan atau hapus siswanya dulu');

        $rombel->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Rombel dihapus']);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?Rombel $rombel = null): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:50', Rule::unique('rombel', 'nama')->ignore($rombel?->id)],
            'tingkat' => ['required', 'in:X,XI,XII'],
            'jurusan' => ['required', 'string', 'max:20'],
            'waliKelasId' => ['nullable', 'integer', Rule::exists('profil_akun', 'id')->whereIn('role', ['GURU', 'KEPSEK'])],
        ], [
            'nama.required' => 'Nama rombel wajib diisi',
            'nama.unique' => 'Nama rombel sudah ada',
            'tingkat.required' => 'Pilih tingkat',
            'jurusan.required' => 'Jurusan wajib diisi',
        ]);
    }
}
