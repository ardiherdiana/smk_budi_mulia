<?php

namespace App\Services;

use App\Models\About;
use App\Models\Major;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Principal;
use App\Models\Staff;
use Illuminate\Http\Request;

/** Merangkai semua props satu-halaman landing page. */
class LandingService
{
    public const POSTS_PER_PAGE = 9;

    public function __construct(private ContentPresenter $presenter) {}

    /** @return array<string, mixed> */
    public function props(Request $request, ?Post $activePost = null): array
    {
        $type = $request->query('type');
        $category = $request->query('category');
        $search = trim((string) $request->query('q', ''));

        $posts = Post::listing()
            ->filter(is_string($type) ? $type : null, is_string($category) ? $category : null, $search ?: null)
            ->orderByDesc('date')
            ->paginate(self::POSTS_PER_PAGE, ['*'], 'page')
            ->withQueryString();

        return [
            'school' => $this->school(),
            'majors' => Major::listing()->orderBy('name')->get()->map($this->presenter->major(...))->all(),
            'abouts' => About::query()
                ->where('is_active', true)
                ->with(['media' => fn ($q) => $q->listing()])
                ->orderBy('display_order')
                ->get(['id', 'title', 'description', 'icon_type', 'display_order', 'is_active'])
                ->map($this->presenter->about(...))->all(),
            'principals' => Principal::listing()->orderByDesc('id')->get()->map($this->presenter->principal(...))->all(),
            'staff' => Staff::listing()->ordered()->get()->map($this->presenter->staff(...))->all(),
            'posts' => [
                'data' => $posts->getCollection()->map($this->presenter->postCard(...))->all(),
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'total' => $posts->total(),
            ],
            'categories' => Post::query()->whereNotNull('category')->where('category', '<>', '')
                ->distinct()->orderBy('category')->pluck('category')
                ->map(fn ($c) => trim($c))->unique()->values()->all(),
            'filters' => ['type' => $type ?: 'all', 'category' => $category ?: null, 'q' => $search],
            'activePost' => $activePost ? $this->presenter->postDetail($this->withMedia($activePost)) : null,
        ];
    }

    /** Memuat ulang satu postingan dengan kolom ringan + galeri (untuk dialog detail). */
    public function findPost(string $id): Post
    {
        return $this->withMedia(Post::listing()->findOrFail($id));
    }

    private function withMedia(Post $post): Post
    {
        return $post->relationLoaded('media')
            ? $post
            : $post->setRelation('media', PostMedia::listing()->where('postingan_id', $post->id)->orderBy('display_order')->get());
    }

    /** @return array<string, mixed> */
    private function school(): array
    {
        return [
            ...collect(config('school'))->only([
                'name', 'short_name', 'tagline', 'description', 'address', 'phone', 'email',
                'instagram', 'map', 'ppdb_url', 'features', 'culture', 'vision', 'missions',
            ])->all(),
            'seo_title' => config('school.seo.title'), // sama dengan <title> beranda dari server (dipakai <Head> saat dialog ditutup)
        ];
    }
}
