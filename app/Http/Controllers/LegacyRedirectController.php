<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;

/**
 * Situs lama punya 6 halaman terpisah (React Router). Alamat itu mungkin sudah terindeks Google atau
 * tersebar di WhatsApp; tanpa ini semuanya jadi 404 begitu situs baru dipasang. Semua dialihkan permanen
 * (301) ke bagian yang sepadan di halaman satu-halaman yang baru.
 */
class LegacyRedirectController extends Controller
{
    /** Alamat lama => id bagian di landing page. */
    public const SECTIONS = [
        'profil/tentang-kami' => 'profil',
        'profil/visi-misi' => 'profil',
        'profil/sejarah-kepsek' => 'kepemimpinan',
        'profil/guru-staff' => 'guru',
        'jurusan' => 'jurusan',
        'pendaftaran' => 'pendaftaran',
        'kontak' => 'kontak',
        'postingan' => 'berita',
    ];

    public function section(string $section): RedirectResponse
    {
        return $this->to('/#'.$section);
    }

    /** `/postingan/{id}` dan `/postingan/detail/{id}` langsung ke URL kanonik berita (satu lompatan). */
    public function post(string $id): RedirectResponse
    {
        $post = Post::query()->find(Post::idFromSegment($id));

        return $this->to($post ? $post->path() : '/#berita');
    }

    // Berdasarkan APP_URL (bukan header Host), sama seperti canonical.
    private function to(string $path): RedirectResponse
    {
        return redirect()->to(Seo::absolute($path), 301);
    }
}
