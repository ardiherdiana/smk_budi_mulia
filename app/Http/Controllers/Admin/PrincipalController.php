<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Principal;
use App\Services\ContentPresenter;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PrincipalController extends Controller
{
    public function __construct(private MediaService $media, private ContentPresenter $presenter) {}

    public function index()
    {
        return Inertia::render('admin/kepsek-page', [
            'principals' => Principal::listing()->orderByDesc('id')->get()->map($this->presenter->principal(...))->all(),
        ]);
    }

    public function store(Request $request)
    {
        $principal = new Principal($this->validated($request));
        $this->applyPhoto($principal, $request);
        $principal->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'Data kepala sekolah berhasil ditambahkan']);
    }

    public function update(Request $request, Principal $principal)
    {
        $principal->fill($this->validated($request));
        $obsolete = $this->applyPhoto($principal, $request);
        $principal->save();
        $this->media->delete($obsolete); // file lama dihapus setelah data baru tersimpan

        return back()->with('toast', ['type' => 'success', 'message' => 'Data kepala sekolah berhasil diperbarui']);
    }

    public function destroy(Principal $principal)
    {
        $this->media->delete($principal->foto);
        $principal->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Data kepala sekolah berhasil dihapus']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tahun_jabatan' => ['required', 'string', 'max:50'],
            'foto' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.config('school.media.max_image_kb')],
            'remove_foto' => ['nullable', 'boolean'],
        ]);

        unset($data['foto'], $data['remove_foto']);

        return $data;
    }

    /** @return string|null path lama yang harus dihapus setelah model tersimpan */
    private function applyPhoto(Principal $principal, Request $request): ?string
    {
        $obsolete = null;

        if ($request->hasFile('foto')) {
            $obsolete = $principal->foto;
            $principal->foto = $this->media->storeImage($request->file('foto'), 'principal-photo', 'foto');
        } elseif ($request->boolean('remove_foto')) {
            $obsolete = $principal->foto;
            $principal->foto = null;
        }

        return $obsolete;
    }
}
