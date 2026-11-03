<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Komentar;
use App\Models\Classroom\Post;
use Illuminate\Http\Request;

class KomentarController extends Controller
{
    public function store(Request $request, Post $post)
    {
        $post->loadMissing('kelas');
        abort_unless($post->kelas->dapatDiakses($request->user()), 403, 'Anda tidak memiliki akses ke ruang kelas ini');
        abort_if($request->user()->role === 'KEPSEK', 403, 'Kepala sekolah hanya dapat melihat');

        $validated = $request->validate([
            'isi' => ['required', 'string', 'max:2000'],
        ], [
            'isi.required' => 'Komentar tidak boleh kosong',
            'isi.max' => 'Komentar maksimal 2000 karakter',
        ]);

        Komentar::create(['postId' => $post->id, 'userId' => $request->user()->id, 'isi' => trim($validated['isi'])]);

        return back();
    }

    public function destroy(Request $request, Komentar $komentar)
    {
        $komentar->loadMissing('post.kelas');
        $user = $request->user();
        abort_unless($komentar->userId === $user->id || $komentar->post->kelas->dapatDikelola($user), 403, 'Anda tidak memiliki akses');

        $komentar->delete();

        return back();
    }
}
