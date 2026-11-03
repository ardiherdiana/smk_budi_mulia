<?php

namespace Tests\Feature;

use App\Models\About;
use App\Models\AboutMedia;
use App\Models\Major;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Principal;
use App\Models\Staff;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminContentTest extends TestCase
{
    private function png(string $name = 'foto.png', int $w = 800, int $h = 600): UploadedFile
    {
        return UploadedFile::fake()->image($name, $w, $h);
    }

    /** UploadedFile nyata (bukan fake) supaya MIME dideteksi dari isi berkas, seperti request sungguhan. */
    private function mp4(string $name, int $padding): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'vid');
        file_put_contents($path, pack('N', 24).'ftypmp42'.str_repeat(chr(0), 4).'mp42isom'.str_repeat(chr(1), $padding));

        return new UploadedFile($path, $name, 'video/mp4', null, true);
    }

    public function test_admin_pages_render_for_admin(): void
    {
        $this->actingAsAdmin();

        foreach (['/admin', '/admin/berita', '/admin/tentang-kami', '/admin/jurusan', '/admin/guru-staff', '/admin/kepala-sekolah', '/skl', '/skl/siswa', '/skl/berkas'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_post_crud_with_image_and_gallery(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/berita', [
            'title' => 'Juara Lomba',
            'content' => "Baris 1\nBaris 2 <b>bukan html</b>",
            'type' => 'berita',
            'category' => 'Prestasi',
            'image' => $this->png(),
            'gallery' => [$this->png('g1.png'), $this->png('g2.png')],
        ])->assertSessionHasNoErrors();

        $post = Post::firstOrFail();
        $this->assertSame('Admin', $post->author);
        $this->assertMatchesRegularExpression('#^post-image/[0-9a-z]{26}\.(webp|jpg|png)$#', $post->image);
        Storage::disk('media')->assertExists($post->image);
        $gallery = PostMedia::where('postingan_id', $post->id)->orderBy('id')->get();
        $this->assertCount(2, $gallery);
        $gallery->each(function (PostMedia $media): void {
            $this->assertMatchesRegularExpression('#^post-media/[0-9a-z]{26}\.(webp|jpg|png)$#', $media->file);
            Storage::disk('media')->assertExists($media->file);
        });
        $this->assertSame("Baris 1\nBaris 2 <b>bukan html</b>", $post->content); // disimpan apa adanya (React yang meng-escape)

        $oldCover = $post->image;
        $removed = $gallery->first();
        $kept = $gallery->last();
        $this->put("/admin/berita/{$post->id}", [
            'title' => 'Juara Lomba Nasional', 'content' => 'Isi baru', 'type' => 'berita',
            'remove_gallery' => [$removed->id], 'remove_image' => '1',
        ])->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertSame('Juara Lomba Nasional', $post->title);
        $this->assertNull($post->image);
        $this->assertCount(1, PostMedia::where('postingan_id', $post->id)->get());
        // File fisiknya ikut hilang, bukan hanya barisnya.
        Storage::disk('media')->assertMissing($oldCover);
        Storage::disk('media')->assertMissing($removed->file);
        Storage::disk('media')->assertExists($kept->file);

        $this->delete("/admin/berita/{$post->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseCount('postingan', 0);
        $this->assertDatabaseCount('postingan_media', 0); // FK cascade
        Storage::disk('media')->assertMissing($kept->file);
    }

    public function test_replacing_a_cover_image_deletes_the_previous_file(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/berita', ['title' => 'Ganti', 'content' => 'x', 'type' => 'berita', 'image' => $this->png()]);
        $post = Post::firstOrFail();
        $first = $post->image;

        $this->put("/admin/berita/{$post->id}", ['title' => 'Ganti', 'content' => 'x', 'type' => 'berita', 'image' => $this->png('baru.png')])
            ->assertSessionHasNoErrors();

        $second = $post->fresh()->image;
        $this->assertNotSame($first, $second);
        Storage::disk('media')->assertMissing($first);
        Storage::disk('media')->assertExists($second);
    }

    public function test_uploaded_images_are_reencoded_and_resized(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/berita', ['title' => 'Besar', 'content' => 'x', 'type' => 'berita', 'image' => $this->png('besar.png', 4000, 3000)]);

        [$width, $height] = getimagesizefromstring(Storage::disk('media')->get(Post::firstOrFail()->image));
        $this->assertLessThanOrEqual(1600, max($width, $height));
    }

    public function test_dangerous_uploads_are_rejected(): void
    {
        $this->actingAsAdmin();
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>');
        $php = UploadedFile::fake()->createWithContent('shell.png', '<?php system($_GET["c"]);');
        $html = UploadedFile::fake()->createWithContent('a.jpg', '<html><script>alert(1)</script></html>');

        foreach ([$svg, $php, $html] as $file) {
            $this->post('/admin/berita', ['title' => 'T', 'content' => 'x', 'type' => 'berita', 'image' => $file])->assertSessionHasErrors('image');
            $this->flushSession();
        }

        $this->assertDatabaseCount('postingan', 0);
    }

    public function test_video_url_must_be_youtube_or_direct_video_link(): void
    {
        $this->actingAsAdmin();
        $base = ['title' => 'V', 'content' => 'x', 'type' => 'video'];

        $this->post('/admin/berita', [...$base, 'video_url' => 'javascript:alert(1)'])->assertSessionHasErrors('video_url');
        $this->post('/admin/berita', [...$base, 'video_url' => 'https://evil.example/halaman.html'])->assertSessionHasErrors('video_url');
        $this->flushSession();
        $this->post('/admin/berita', [...$base, 'video_url' => 'https://youtu.be/dQw4w9WgXcQ'])->assertSessionHasNoErrors();
        $this->post('/admin/berita', [...$base, 'video_url' => 'https://cdn.example/klip.mp4'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('postingan', 2);
    }

    public function test_video_file_upload_and_size_limit(): void
    {
        $this->actingAsAdmin();
        config(['school.media.max_video_mb' => 1]);

        $this->post('/admin/berita', ['title' => 'V1', 'content' => 'x', 'type' => 'video', 'video_file' => $this->mp4('besar.mp4', 2 * 1024 * 1024)])
            ->assertSessionHasErrors('video_file');
        $this->flushSession(); // flash error request pertama tidak boleh terbawa

        $this->post('/admin/berita', ['title' => 'V2', 'content' => 'x', 'type' => 'video', 'video_file' => $this->mp4('klip.mp4', 100)])
            ->assertSessionHasNoErrors();

        $path = Post::firstOrFail()->video_file;
        $this->assertMatchesRegularExpression('#^post-video/[0-9a-z]{26}\.mp4$#', $path);
        Storage::disk('media')->assertExists($path);
        $this->assertSame(124, Storage::disk('media')->size($path)); // disimpan utuh, tanpa di-encode ulang
    }

    public function test_video_upload_with_non_video_content_is_rejected(): void
    {
        $this->actingAsAdmin();
        $path = tempnam(sys_get_temp_dir(), 'vid');
        file_put_contents($path, '<?php echo "bukan video";');

        $this->post('/admin/berita', ['title' => 'V', 'content' => 'x', 'type' => 'video', 'video_file' => new UploadedFile($path, 'klip.mp4', 'video/mp4', null, true)])
            ->assertSessionHasErrors('video_file');
        $this->assertDatabaseCount('postingan', 0);
    }

    public function test_about_blocks_can_be_managed_and_hidden(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/tentang-kami', [
            'title' => 'Fasilitas', 'description' => 'Lab komputer', 'icon_type' => 'Building',
            'display_order' => 2, 'is_active' => '1', 'media' => [$this->png()],
        ])->assertSessionHasNoErrors();

        $about = About::firstOrFail();
        $this->assertTrue($about->is_active);
        $this->assertCount(1, AboutMedia::where('tentang_kami_id', $about->id)->get());
        $aboutFile = AboutMedia::where('tentang_kami_id', $about->id)->value('file');
        $this->assertMatchesRegularExpression('#^about-media/[0-9a-z]{26}\.(webp|jpg|png)$#', $aboutFile);
        Storage::disk('media')->assertExists($aboutFile);

        $this->post('/admin/tentang-kami', ['title' => 'x', 'description' => 'x', 'icon_type' => '<script>', 'display_order' => 1, 'is_active' => '1'])
            ->assertSessionHasErrors('icon_type');
        $this->flushSession();

        $this->put("/admin/tentang-kami/{$about->id}", ['title' => 'Fasilitas', 'description' => 'x', 'display_order' => 2, 'is_active' => '0'])
            ->assertSessionHasNoErrors();
        $this->assertFalse($about->fresh()->is_active);

        $this->delete("/admin/tentang-kami/{$about->id}");
        $this->assertDatabaseCount('tentang_kami_media', 0);
        Storage::disk('media')->assertMissing($aboutFile);
    }

    public function test_major_staff_and_principal_crud(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/jurusan', ['name' => 'TKJ', 'description' => 'Jaringan', 'icon' => 'Cpu', 'image' => $this->png()])->assertSessionHasNoErrors();
        $major = Major::firstOrFail();
        $majorImage = $major->image;
        $this->assertMatchesRegularExpression('#^major-image/[0-9a-z]{26}\.(webp|jpg|png)$#', $majorImage);
        Storage::disk('media')->assertExists($majorImage);
        $this->post('/admin/jurusan', ['name' => 'X', 'video_url' => 'https://vimeo.com/1'])->assertSessionHasErrors('video_url');
        $this->flushSession();
        $this->put("/admin/jurusan/{$major->id}", ['name' => 'TKJ Baru', 'description' => 'x'])->assertSessionHasNoErrors();
        $this->assertSame('TKJ Baru', $major->fresh()->name);
        $this->assertSame($majorImage, $major->fresh()->image); // tidak ikut terhapus saat edit tanpa gambar
        Storage::disk('media')->assertExists($majorImage);

        $this->post('/admin/guru-staff', ['name' => 'Bu Guru, S.Pd', 'role' => 'teacher', 'expertise' => 'Matematika'])->assertSessionHasNoErrors();
        $this->post('/admin/guru-staff', ['name' => 'X', 'role' => 'superadmin'])->assertSessionHasErrors('role');
        $this->post('/admin/guru-staff', ['name' => 'X', 'role' => 'teacher', 'email' => 'alamat.email.terlalu.panjang@contoh.com'])->assertSessionHasErrors('email');
        $this->flushSession();
        $staff = Staff::firstOrFail();
        $this->delete("/admin/guru-staff/{$staff->id}");
        $this->assertDatabaseCount('guru_staff', 0);

        $this->post('/admin/kepala-sekolah', ['nama' => 'Pak Kepsek', 'tahun_jabatan' => '2020-2024', 'foto' => $this->png()])->assertSessionHasNoErrors();
        $principal = Principal::firstOrFail();
        $this->assertMatchesRegularExpression('#^principal-photo/[0-9a-z]{26}\.(webp|jpg|png)$#', $principal->foto); // path, bukan base64
        Storage::disk('media')->assertExists($principal->foto);
        $this->delete("/admin/kepala-sekolah/{$principal->id}");
        $this->assertDatabaseCount('principals', 0);
        Storage::disk('media')->assertMissing($principal->foto);
    }

    public function test_mass_assignment_cannot_set_protected_columns(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/berita', ['title' => 'T', 'content' => 'x', 'type' => 'berita', 'id' => 'post_sengaja', 'date' => '1999-01-01'])->assertSessionHasNoErrors();

        $post = Post::firstOrFail();
        $this->assertNotSame('post_sengaja', $post->id);
        $this->assertTrue($post->date->year >= 2020);
    }

    public function test_csrf_protection_is_active_for_all_web_routes(): void
    {
        // Middleware CSRF sengaja dilewati saat unit test, jadi pastikan terpasang di grup `web`.
        $web = collect(app(Kernel::class)->getMiddlewareGroups()['web'])
            ->map(fn ($m) => is_string($m) ? $m : '')->implode(' ');

        $this->assertMatchesRegularExpression('/Forgery|Csrf/i', $web);
    }
}
