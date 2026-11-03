<?php

namespace Tests\Feature;

use App\Models\About;
use App\Models\Major;
use App\Models\Post;
use App\Models\Principal;
use App\Models\Staff;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    private function makePost(array $attributes = []): Post
    {
        return Post::create([
            'title' => 'Judul '.fake()->unique()->word(),
            'content' => 'Isi berita',
            'type' => 'berita',
            'author' => 'Admin',
            ...$attributes,
        ]);
    }

    public function test_landing_page_renders_all_sections_as_props(): void
    {
        Major::create(['name' => 'RPL', 'description' => 'Rekayasa Perangkat Lunak']);
        About::create(['title' => 'Sejarah', 'description' => 'Berdiri tahun ...', 'display_order' => 1, 'is_active' => true]);
        About::create(['title' => 'Disembunyikan', 'description' => 'x', 'display_order' => 2, 'is_active' => false]);
        Principal::create(['nama' => 'Kepala A', 'tahun_jabatan' => '2020-2024']);
        Staff::create(['name' => 'Guru A', 'role' => 'teacher']);
        $this->makePost();

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('landing-page')
            ->where('school.name', 'SMK Budi Mulia Karawang')
            ->has('school.missions', 8)
            ->has('majors', 1)
            ->has('abouts', 1) // hanya yang is_active
            ->where('abouts.0.title', 'Sejarah')
            ->has('principals', 1)
            ->has('staff', 1)
            ->has('posts.data', 1)
            ->missing('stats') // angka ringkasan tidak ditampilkan: tidak ada sumber yang bisa dipertanggungjawabkan
        );
    }

    public function test_props_contain_media_file_urls_only(): void
    {
        $this->makePost(['image' => 'post-image/01abc.jpg', 'video_file' => 'post-video/01abc.mp4']);
        Major::create(['name' => 'TKJ', 'image' => 'major-image/3-1a2b3c4d.png']);
        // Nilai yang bukan path sah (mis. sisa base64 lama atau path liar) tidak boleh berubah jadi URL.
        $this->makePost(['image' => 'data:image/png;base64,AAAA']);
        $this->makePost(['image' => '../../.env']);

        $json = json_encode($this->get('/')->assertOk()->viewData('page')['props'], JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('"/media/post-image/01abc.jpg"', $json);
        $this->assertStringContainsString('"/media/major-image/3-1a2b3c4d.png"', $json);
        $this->assertStringNotContainsString('base64', $json);
        $this->assertStringNotContainsString('.env', $json);
    }

    public function test_posts_can_be_filtered_searched_and_paginated(): void
    {
        foreach (range(1, 11) as $i) {
            $this->makePost(['title' => "Berita {$i}", 'type' => 'berita', 'category' => 'Akademik']);
        }
        $this->makePost(['title' => 'Video Kegiatan', 'type' => 'video', 'category' => 'Kegiatan']);

        $this->get('/')->assertInertia(fn (Assert $p) => $p->has('posts.data', 9)->where('posts.last_page', 2)->where('posts.total', 12));
        $this->get('/?page=2')->assertInertia(fn (Assert $p) => $p->has('posts.data', 3));
        $this->get('/?type=video')->assertInertia(fn (Assert $p) => $p->has('posts.data', 1)->where('filters.type', 'video'));
        $this->get('/?category=Kegiatan')->assertInertia(fn (Assert $p) => $p->has('posts.data', 1));
        $this->get('/?q=Kegiatan')->assertInertia(fn (Assert $p) => $p->has('posts.data', 1));
        $this->get('/?q=tidak-ada-yang-cocok')->assertInertia(fn (Assert $p) => $p->has('posts.data', 0));
        $this->get('/?type=<script>')->assertOk(); // nilai liar diabaikan, bukan error
    }

    public function test_search_treats_like_wildcards_literally(): void
    {
        $this->makePost(['title' => 'Diskon 100% lulus']);
        $this->makePost(['title' => 'Berita biasa']);

        $this->get('/?q=%25')->assertInertia(fn (Assert $p) => $p->has('posts.data', 1)); // '%' bukan wildcard
    }

    public function test_share_link_opens_the_post_inside_the_same_landing_page(): void
    {
        $post = $this->makePost(['title' => 'Prestasi Siswa', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $this->get($post->path())->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('landing-page')
            ->where('activePost.title', 'Prestasi Siswa')
            ->where('activePost.video.kind', 'youtube')
            ->where('activePost.video.id', 'dQw4w9WgXcQ')
        );

        $this->get('/berita/tidak-ada')->assertNotFound();
    }

    public function test_only_https_youtube_or_direct_video_urls_become_embeds(): void
    {
        $bad = $this->makePost(['video_url' => 'javascript:alert(1)']);
        // (video_file kosong + video_url berbahaya => tidak ada pemutar sama sekali)

        $this->get($bad->path())->assertInertia(fn (Assert $p) => $p->where('activePost.video', null));
    }
}
