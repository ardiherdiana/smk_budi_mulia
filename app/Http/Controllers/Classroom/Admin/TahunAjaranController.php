<?php

namespace App\Http\Controllers\Classroom\Admin;

use App\Http\Controllers\Classroom\Controller;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TahunAjaranController extends Controller
{
    public function index()
    {
        return Inertia::render('classroom/admin/tahun-ajaran-page', [
            'tahunAjaran' => TahunAjaran::orderByDesc('nama')->orderByDesc('semester')->get()
                ->map(fn (TahunAjaran $t) => [
                    'id' => $t->id,
                    'nama' => $t->nama,
                    'semester' => $t->semester,
                    'aktif' => $t->aktif,
                    'jumlahKelas' => Kelas::where('tahunAjaranId', $t->id)->count(),
                ])->all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validasi($request);

        DB::transaction(function () use ($validated) {
            if ($validated['aktif'] ?? false) {
                TahunAjaran::query()->update(['aktif' => false]);
            }
            TahunAjaran::create($validated + ['aktif' => false]);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Tahun ajaran ditambahkan']);
    }

    public function update(Request $request, TahunAjaran $tahunAjaran)
    {
        $validated = $this->validasi($request, $tahunAjaran);

        DB::transaction(function () use ($validated, $tahunAjaran) {
            // Hanya satu tahun ajaran yang aktif; ruang kelas baru selalu dibuat di tahun yang aktif.
            if ($validated['aktif'] ?? false) {
                TahunAjaran::where('id', '!=', $tahunAjaran->id)->update(['aktif' => false]);
            }
            $tahunAjaran->update($validated);
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Tahun ajaran diperbarui']);
    }

    public function destroy(TahunAjaran $tahunAjaran)
    {
        abort_if(Kelas::where('tahunAjaranId', $tahunAjaran->id)->exists(), 409, 'Tahun ajaran masih dipakai ruang kelas');

        $tahunAjaran->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Tahun ajaran dihapus']);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?TahunAjaran $tahun = null): array
    {
        $validated = $request->validate([
            'nama' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', 'in:GANJIL,GENAP', Rule::unique('tahun_ajaran', 'semester')->where('nama', $request->input('nama'))->ignore($tahun?->id)],
            'aktif' => ['nullable', 'boolean'],
        ], [
            'nama.required' => 'Tahun ajaran wajib diisi',
            'nama.regex' => 'Format tahun ajaran harus seperti 2026/2027',
            'semester.unique' => 'Tahun ajaran dan semester ini sudah ada',
        ]);

        if (array_key_exists('aktif', $validated)) {
            $validated['aktif'] = (bool) $validated['aktif'];
        }

        return $validated;
    }
}
