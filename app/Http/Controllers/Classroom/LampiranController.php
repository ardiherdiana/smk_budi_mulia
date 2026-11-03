<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Lampiran;
use App\Models\Classroom\Pengumpulan;
use App\Models\Classroom\Post;
use App\Services\Classroom\LampiranService;
use Illuminate\Http\Request;

class LampiranController extends Controller
{
    public function __construct(private LampiranService $lampiran) {}

    /** Unduh/lihat satu berkas. Hak akses mengikuti pemiliknya (materi/tugas guru atau hasil kerja siswa). */
    public function show(Request $request, Lampiran $lampiran)
    {
        abort_unless($this->dapatDilihat($request, $lampiran), 403, 'Anda tidak memiliki akses ke berkas ini');

        return $this->lampiran->kirim($lampiran);
    }

    public function destroy(Request $request, Lampiran $lampiran)
    {
        $user = $request->user();

        if ($lampiran->postId) {
            $post = Post::with('kelas')->findOrFail($lampiran->postId);
            abort_unless($post->kelas->dapatDikelola($user), 403, 'Anda tidak memiliki akses');
        } else {
            // Hasil kerja siswa: hanya pemiliknya, dan hanya selama belum dinilai.
            $pengumpulan = Pengumpulan::findOrFail($lampiran->pengumpulanId);
            abort_unless($user->siswa && $pengumpulan->siswaId === $user->siswa->id, 403, 'Anda tidak memiliki akses');
            abort_if($pengumpulan->status === 'DINILAI', 409, 'Tugas sudah dinilai, tidak bisa diubah');
        }

        $this->lampiran->hapus($lampiran);

        return back();
    }

    private function dapatDilihat(Request $request, Lampiran $lampiran): bool
    {
        $user = $request->user();

        if ($lampiran->postId) {
            $post = Post::with('kelas')->find($lampiran->postId);

            return $post && $post->kelas->dapatDiakses($user);
        }

        $pengumpulan = Pengumpulan::with('post.kelas')->find($lampiran->pengumpulanId);
        if (! $pengumpulan) {
            return false;
        }

        // Hasil kerja siswa tidak terlihat oleh teman sekelasnya.
        return $user->isSiswa()
            ? $user->siswa?->id === $pengumpulan->siswaId
            : $pengumpulan->post->kelas->dapatDiakses($user);
    }
}
