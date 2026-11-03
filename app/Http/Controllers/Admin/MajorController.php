<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Major;
use App\Services\ContentPresenter;
use App\Services\MediaService;
use App\Support\VideoUrl;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class MajorController extends Controller
{
    public const ICONS = ['Briefcase', 'Code', 'Cpu', 'Wrench', 'Calculator', 'Palette', 'Camera', 'Cog', 'Network', 'Monitor', 'Database', 'Store', 'Wallet', 'Cable'];

    public function __construct(private MediaService $media, private ContentPresenter $presenter) {}

    public function index()
    {
        return Inertia::render('admin/jurusan-page', [
            'majors' => Major::listing()->orderBy('name')->get()->map($this->presenter->major(...))->all(),
            'icons' => self::ICONS,
        ]);
    }

    public function store(Request $request)
    {
        $major = new Major($this->validated($request));
        $this->applyImage($major, $request);
        $major->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'Jurusan berhasil ditambahkan']);
    }

    public function update(Request $request, Major $major)
    {
        $major->fill($this->validated($request));
        $obsolete = $this->applyImage($major, $request);
        $major->save();
        $this->media->delete($obsolete); // file lama dihapus setelah data baru tersimpan

        return back()->with('toast', ['type' => 'success', 'message' => 'Jurusan berhasil diperbarui']);
    }

    public function destroy(Major $major)
    {
        $this->media->delete($major->image);
        $major->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Jurusan berhasil dihapus']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'icon' => ['nullable', 'in:'.implode(',', self::ICONS)],
            'video_url' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.config('school.media.max_image_kb')],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        $data['video_url'] = filled($data['video_url'] ?? null) ? trim($data['video_url']) : null;
        if ($data['video_url'] !== null && VideoUrl::youtubeId($data['video_url']) === null) {
            throw ValidationException::withMessages(['video_url' => 'Gunakan link video YouTube yang valid.']);
        }

        unset($data['image'], $data['remove_image']);

        return $data;
    }

    /** @return string|null path lama yang harus dihapus setelah model tersimpan */
    private function applyImage(Major $major, Request $request): ?string
    {
        $obsolete = null;

        if ($request->hasFile('image')) {
            $obsolete = $major->image;
            $major->image = $this->media->storeImage($request->file('image'), 'major-image');
        } elseif ($request->boolean('remove_image')) {
            $obsolete = $major->image;
            $major->image = null;
        }

        return $obsolete;
    }
}
