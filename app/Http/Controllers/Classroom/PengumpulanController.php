<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Pengumpulan;
use App\Models\Classroom\Post;
use App\Services\Classroom\LampiranService;
use Illuminate\Http\Request;

class PengumpulanController extends Controller
{
    public function __construct(private LampiranService $lampiran) {}

    /** Siswa mengumpulkan (atau memperbarui) hasil tugas. */
    public function store(Request $request, Post $post)
    {
        $user = $request->user();
        $post->loadMissing('kelas');
        abort_unless($post->tipe === 'TUGAS', 404, 'Postingan ini bukan tugas');
        abort_unless($user->siswa && $post->kelas->dapatDiakses($user), 403, 'Anda tidak memiliki akses');
        abort_if($post->kelas->diarsipkan, 409, 'Ruang kelas ini sudah diarsipkan');

        $terlambat = $post->deadline && now()->gt($post->deadline);
        abort_if($terlambat && ! $post->izinTerlambat, 409, 'Batas waktu pengumpulan sudah lewat');

        $ada = Pengumpulan::with('lampiran')->where(['postId' => $post->id, 'siswaId' => $user->siswa->id])->first();
        abort_if($ada?->status === 'DINILAI', 409, 'Tugas sudah dinilai, tidak bisa diubah');

        $validated = $request->validate([
            'teks' => ['nullable', 'string', 'max:10000'],
        ] + LampiranService::rules(), LampiranService::messages());

        $teks = trim((string) ($validated['teks'] ?? ''));
        $adaIsi = $teks !== ''
            || $request->hasFile('berkas')
            || ! empty($validated['tautan'])
            || ($ada && $ada->lampiran->isNotEmpty());
        abort_unless($adaIsi, 422, 'Isi jawaban, lampirkan berkas, atau tambahkan tautan sebelum mengumpulkan');

        $pengumpulan = Pengumpulan::updateOrCreate(
            ['postId' => $post->id, 'siswaId' => $user->siswa->id],
            ['teks' => $teks !== '' ? $teks : null, 'status' => 'DIKUMPULKAN', 'terlambat' => (bool) $terlambat, 'submittedAt' => now()],
        );

        $this->lampiran->simpan('pengumpulanId', $pengumpulan->id, $request->file('berkas', []), $validated['tautan'] ?? []);

        return back()->with('toast', ['type' => 'success', 'message' => $terlambat ? 'Tugas dikumpulkan (terlambat)' : 'Tugas berhasil dikumpulkan']);
    }

    /** Siswa membatalkan pengumpulan selama belum dinilai. */
    public function destroy(Request $request, Post $post)
    {
        $user = $request->user();
        abort_unless($user->siswa, 403, 'Anda tidak memiliki akses');

        $pengumpulan = Pengumpulan::with('lampiran')->where(['postId' => $post->id, 'siswaId' => $user->siswa->id])->firstOrFail();
        abort_if($pengumpulan->status === 'DINILAI', 409, 'Tugas sudah dinilai, tidak bisa dibatalkan');

        $this->lampiran->hapusBerkasFisik($pengumpulan->lampiran);
        $pengumpulan->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Pengumpulan dibatalkan']);
    }

    /** Guru memberi nilai dan umpan balik. */
    public function nilai(Request $request, Pengumpulan $pengumpulan)
    {
        $pengumpulan->loadMissing('post.kelas');
        $post = $pengumpulan->post;
        abort_unless($post->kelas->dapatDikelola($request->user()), 403, 'Anda tidak memiliki akses');

        $maks = $post->poinMaks ?? 100;
        $validated = $request->validate([
            'nilai' => ['required', 'numeric', 'min:0', "max:{$maks}"],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ], [
            'nilai.required' => 'Nilai wajib diisi',
            'nilai.numeric' => 'Nilai harus berupa angka',
            'nilai.max' => "Nilai maksimal {$maks}",
            'nilai.min' => 'Nilai tidak boleh negatif',
        ]);

        $pengumpulan->update([
            'nilai' => $validated['nilai'],
            'feedback' => $validated['feedback'] ?? null,
            'status' => 'DINILAI',
            'gradedAt' => now(),
        ]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Nilai disimpan']);
    }
}
