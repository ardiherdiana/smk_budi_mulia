<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostMedia;
use App\Services\ContentPresenter;
use App\Services\MediaService;
use App\Support\VideoUrl;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PostController extends Controller
{
    public function __construct(private MediaService $media, private ContentPresenter $presenter) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $type = $request->query('type');
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50], true) ? (int) $request->query('per_page') : 10;

        $paginator = Post::listing()
            ->filter(is_string($type) ? $type : null, null, $search ?: null)
            ->orderByDesc('date')
            ->paginate($perPage)
            ->withQueryString();

        $gallery = PostMedia::listing()
            ->whereIn('postingan_id', $paginator->getCollection()->pluck('id'))
            ->orderBy('display_order')->get()->groupBy('postingan_id');

        return Inertia::render('admin/berita-page', [
            'posts' => [
                'data' => $paginator->getCollection()->map(fn (Post $post) => [
                    ...$this->presenter->postCard($post),
                    'content' => (string) $post->content,
                    'video_url' => $post->video_url,
                    'video_file_url' => $this->media->url($post->video_file),
                    'gallery' => ($gallery[$post->id] ?? collect())->map(fn ($m) => [
                        'id' => $m->id,
                        'url' => $this->media->url($m->file) ?? $m->url,
                    ])->values()->all(),
                ])->all(),
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => ['q' => $search, 'type' => is_string($type) ? $type : 'all'],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $post = new Post([
            'title' => $data['title'],
            'content' => $data['content'],
            'type' => $data['type'],
            'category' => $data['category'] ?? null,
            'author' => $data['author'] ?? 'Admin',
            'video_url' => $data['video_url'] ?? null,
        ]);
        $this->applyMedia($post, $request);
        $post->save();
        $this->appendGallery($post, $request);

        return back()->with('toast', ['type' => 'success', 'message' => 'Postingan berhasil dibuat']);
    }

    public function update(Request $request, Post $post)
    {
        $data = $this->validated($request);

        $post->fill([
            'title' => $data['title'],
            'content' => $data['content'],
            'type' => $data['type'],
            'category' => $data['category'] ?? null,
            'author' => $data['author'] ?? $post->author,
            'video_url' => $data['video_url'] ?? null,
        ]);
        $obsolete = $this->applyMedia($post, $request);
        $post->save();
        array_map($this->media->delete(...), $obsolete); // file lama baru dihapus setelah data baru tersimpan

        $removeIds = array_map('strval', $request->input('remove_gallery', []));
        if ($removeIds) {
            PostMedia::where('postingan_id', $post->id)->whereIn('id', $removeIds)->get()
                ->each(function (PostMedia $media) {
                    $this->media->delete($media->file);
                    $media->delete();
                });
        }
        $this->appendGallery($post, $request);

        return back()->with('toast', ['type' => 'success', 'message' => 'Postingan berhasil diperbarui']);
    }

    public function destroy(Post $post)
    {
        PostMedia::where('postingan_id', $post->id)->pluck('file')->each($this->media->delete(...));
        $this->media->delete($post->image);
        $this->media->delete($post->video_file);
        $post->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Postingan berhasil dihapus']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $maxVideoKb = config('school.media.max_video_mb') * 1024;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
            'type' => ['required', 'in:berita,video'],
            'category' => ['nullable', 'string', 'max:100'],
            'author' => ['nullable', 'string', 'max:100'],
            'video_url' => ['nullable', 'string', 'max:500'],
            'image' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.config('school.media.max_image_kb')],
            'video_file' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/ogg', 'max:'.$maxVideoKb],
            'gallery' => ['nullable', 'array', 'max:12'],
            'gallery.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.config('school.media.max_image_kb')],
            'remove_gallery' => ['nullable', 'array'],
            'remove_image' => ['nullable', 'boolean'],
            'remove_video_file' => ['nullable', 'boolean'],
        ], [
            'image.mimetypes' => 'Gambar harus berformat JPG, PNG, atau WebP.',
            'video_file.mimetypes' => 'Video harus berformat MP4, WebM, atau OGG.',
            'video_file.max' => 'Ukuran video maksimal '.config('school.media.max_video_mb').' MB.',
            'image.max' => 'Ukuran gambar maksimal '.(config('school.media.max_image_kb') / 1024).' MB.',
        ]);

        $data['video_url'] = filled($data['video_url'] ?? null) ? trim($data['video_url']) : null;
        if ($data['video_url'] !== null && ! VideoUrl::isValid($data['video_url'])) {
            throw ValidationException::withMessages([
                'video_url' => 'Gunakan link YouTube atau URL video langsung (.mp4, .webm, .ogg).',
            ]);
        }

        return $data;
    }

    /**
     * Simpan file baru ke disk media dan isi kolomnya dengan path.
     *
     * @return list<string> path lama yang harus dihapus SETELAH model tersimpan
     */
    private function applyMedia(Post $post, Request $request): array
    {
        $obsolete = [];

        if ($request->hasFile('image')) {
            $obsolete[] = $post->image;
            $post->image = $this->media->storeImage($request->file('image'), 'post-image');
        } elseif ($request->boolean('remove_image')) {
            $obsolete[] = $post->image;
            $post->image = null;
        }

        if ($request->hasFile('video_file')) {
            $obsolete[] = $post->video_file;
            $post->video_file = $this->media->storeVideo($request->file('video_file'), 'post-video');
        } elseif ($request->boolean('remove_video_file')) {
            $obsolete[] = $post->video_file;
            $post->video_file = null;
        }

        return array_values(array_filter($obsolete));
    }

    private function appendGallery(Post $post, Request $request): void
    {
        $files = $request->file('gallery', []);
        if (! $files) {
            return;
        }

        $order = (int) PostMedia::where('postingan_id', $post->id)->max('display_order') + 1;
        foreach ($files as $file) {
            PostMedia::create([
                'postingan_id' => $post->id,
                'type' => 'image',
                'file' => $this->media->storeImage($file, 'post-media', 'gallery'),
                'display_order' => $order++,
            ]);
        }
    }
}
