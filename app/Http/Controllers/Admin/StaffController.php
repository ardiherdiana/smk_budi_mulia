<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Services\ContentPresenter;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StaffController extends Controller
{
    public function __construct(private MediaService $media, private ContentPresenter $presenter) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $role = $request->query('role');

        $staff = Staff::listing()->ordered()
            ->when(in_array($role, Staff::ROLES, true), fn ($q) => $q->where('role', $role))
            ->when($search, fn ($q) => $q->where('name', 'like', '%'.addcslashes($search, '\\%_').'%'))
            ->get();

        return Inertia::render('admin/guru-page', [
            'staff' => $staff->map($this->presenter->staff(...))->all(),
            'filters' => ['q' => $search, 'role' => in_array($role, Staff::ROLES, true) ? $role : 'all'],
        ]);
    }

    public function store(Request $request)
    {
        $staff = new Staff($this->validated($request));
        $this->applyImage($staff, $request);
        $staff->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'Data guru & staf berhasil ditambahkan']);
    }

    public function update(Request $request, Staff $staff)
    {
        $staff->fill($this->validated($request));
        $obsolete = $this->applyImage($staff, $request);
        $staff->save();
        $this->media->delete($obsolete); // file lama dihapus setelah data baru tersimpan

        return back()->with('toast', ['type' => 'success', 'message' => 'Data guru & staf berhasil diperbarui']);
    }

    public function destroy(Staff $staff)
    {
        $this->media->delete($staff->image);
        $staff->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Data guru & staf berhasil dihapus']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Skema produksi: kolom `email` hanya varchar(20).
            'email' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'in:'.implode(',', Staff::ROLES)],
            'type' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:255'],
            'expertise' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.config('school.media.max_image_kb')],
            'remove_image' => ['nullable', 'boolean'],
        ], [
            'email.max' => 'Kolom ini maksimal 20 karakter (batasan skema database).',
        ]);

        unset($data['image'], $data['remove_image']);

        return $data;
    }

    /** @return string|null path lama yang harus dihapus setelah model tersimpan */
    private function applyImage(Staff $staff, Request $request): ?string
    {
        $obsolete = null;

        if ($request->hasFile('image')) {
            $obsolete = $staff->image;
            $staff->image = $this->media->storeImage($request->file('image'), 'staff-image');
        } elseif ($request->boolean('remove_image')) {
            $obsolete = $staff->image;
            $staff->image = null;
        }

        return $obsolete;
    }
}
