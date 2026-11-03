<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\About;
use App\Models\AboutMedia;
use App\Services\ContentPresenter;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AboutController extends Controller
{
    /** Tanpa ikon generik (sparkle/bintang): daftar ini harus sama dengan `icon-map.ts` di frontend. */
    public const ICONS = ['Building', 'Users', 'Award', 'History', 'BookOpen', 'Trophy', 'Heart', 'Target', 'Lightbulb', 'Handshake'];

    public function __construct(private MediaService $media, private ContentPresenter $presenter) {}

    public function index()
    {
        $items = About::query()
            ->with(['media' => fn ($q) => $q->listing()])
            ->orderBy('display_order')
            ->get(['id', 'title', 'description', 'icon_type', 'display_order', 'is_active']);

        return Inertia::render('admin/tentang-page', [
            'items' => $items->map($this->presenter->about(...))->all(),
            'icons' => self::ICONS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $about = About::create($data);
        $this->appendMedia($about, $request);

        return back()->with('toast', ['type' => 'success', 'message' => 'Data tentang kami berhasil dibuat']);
    }

    public function update(Request $request, About $about)
    {
        $about->update($this->validated($request));

        $removeIds = array_map('strval', $request->input('remove_media', []));
        if ($removeIds) {
            AboutMedia::where('tentang_kami_id', $about->id)->whereIn('id', $removeIds)->get()
                ->each(function (AboutMedia $media) {
                    $this->media->delete($media->file);
                    $media->delete();
                });
        }
        $this->appendMedia($about, $request);

        return back()->with('toast', ['type' => 'success', 'message' => 'Data tentang kami berhasil diperbarui']);
    }

    public function destroy(About $about)
    {
        AboutMedia::where('tentang_kami_id', $about->id)->pluck('file')->each($this->media->delete(...));
        $about->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Data tentang kami berhasil dihapus']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'icon_type' => ['nullable', 'in:'.implode(',', self::ICONS)],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
            'media' => ['nullable', 'array', 'max:12'],
            'media.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.config('school.media.max_image_kb')],
            'remove_media' => ['nullable', 'array'],
        ]);
    }

    private function appendMedia(About $about, Request $request): void
    {
        $files = $request->file('media', []);
        if (! $files) {
            return;
        }

        $order = (int) AboutMedia::where('tentang_kami_id', $about->id)->max('display_order') + 1;
        foreach ($files as $file) {
            AboutMedia::create([
                'tentang_kami_id' => $about->id,
                'type' => 'image',
                'file' => $this->media->storeImage($file, 'about-media', 'media'),
                'display_order' => $order++,
            ]);
        }
    }
}
