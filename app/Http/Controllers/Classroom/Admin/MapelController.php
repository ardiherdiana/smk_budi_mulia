<?php

namespace App\Http\Controllers\Classroom\Admin;

use App\Http\Controllers\Classroom\Controller;
use App\Models\Classroom\Mapel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MapelController extends Controller
{
    public function index()
    {
        return Inertia::render('classroom/admin/mapel-page', [
            'mapel' => Mapel::withCount('kelas')->orderBy('nama')->get()
                ->map(fn (Mapel $m) => ['id' => $m->id, 'nama' => $m->nama, 'jumlahKelas' => $m->kelas_count])->all(),
        ]);
    }

    public function store(Request $request)
    {
        Mapel::create($this->validasi($request));

        return back()->with('toast', ['type' => 'success', 'message' => 'Mata pelajaran ditambahkan']);
    }

    public function update(Request $request, Mapel $mapel)
    {
        $mapel->update($this->validasi($request, $mapel));

        return back()->with('toast', ['type' => 'success', 'message' => 'Mata pelajaran diperbarui']);
    }

    public function destroy(Mapel $mapel)
    {
        abort_if($mapel->kelas()->exists(), 409, 'Mata pelajaran masih dipakai ruang kelas');

        $mapel->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Mata pelajaran dihapus']);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?Mapel $mapel = null): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('mapel', 'nama')->ignore($mapel?->id)],
        ], [
            'nama.required' => 'Nama mata pelajaran wajib diisi',
            'nama.unique' => 'Mata pelajaran sudah ada',
        ]);
    }
}
