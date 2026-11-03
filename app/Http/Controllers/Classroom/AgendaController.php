<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Pengumpulan;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\TahunAjaran;
use App\Services\Classroom\PostPresenter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/** Tugas dan ujian lintas ruang kelas: Daftar tugas dan Kalender. */
class AgendaController extends Controller
{
    public function tugas(Request $request)
    {
        $user = $request->user();
        $posts = $this->postsAgenda($user)->get();

        return Inertia::render('classroom/agenda/tugas-page', [
            'items' => $this->items($posts, $user),
        ]);
    }

    public function kalender(Request $request)
    {
        $user = $request->user();
        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']], ['bulan.date_format' => 'Format bulan tidak valid']);

        $awal = Carbon::createFromFormat('Y-m-d', ($request->query('bulan') ?: now()->format('Y-m')).'-01')->startOfDay();
        $akhir = $awal->copy()->endOfMonth();

        $posts = $this->postsAgenda($user)
            ->where(function (Builder $q) use ($awal, $akhir) {
                $q->whereBetween('deadline', [$awal, $akhir])
                    ->orWhereBetween('selesaiAt', [$awal, $akhir])
                    ->orWhere(fn (Builder $w) => $w->whereNull('selesaiAt')->whereBetween('mulaiAt', [$awal, $akhir]));
            })
            ->get();

        return Inertia::render('classroom/agenda/kalender-page', [
            'bulan' => $awal->format('Y-m'),
            'items' => $this->items($posts, $user),
        ]);
    }

    /** Tugas dan ujian dari ruang kelas aktif milik pengguna (tidak diarsipkan, tahun ajaran aktif). */
    private function postsAgenda(Akun $user): Builder
    {
        $kelas = Kelas::untukPengguna($user)->where('diarsipkan', false);
        if ($aktif = TahunAjaran::aktif()) {
            $kelas->where('tahunAjaranId', $aktif->id);
        }

        return Post::with(['kelas:id,mapelId,rombelId', 'kelas.mapel:id,nama', 'kelas.rombel:id,nama'])
            ->whereIn('tipe', ['TUGAS', 'UJIAN'])
            ->whereIn('kelasId', $kelas->select('kelas.id'));
    }

    /**
     * Siswa: status miliknya sendiri. Guru/admin/kepsek: jumlah yang menunggu dinilai.
     *
     * @param  Collection<int, Post>  $posts
     * @return list<array<string, mixed>>
     */
    private function items(Collection $posts, Akun $user): array
    {
        $idTugas = $posts->where('tipe', 'TUGAS')->pluck('id')->all();
        $idUjian = $posts->where('tipe', 'UJIAN')->pluck('id')->all();
        $siswaId = $user->isSiswa() ? $user->siswa?->id : null;

        $pengumpulanSaya = collect();
        $percobaanSaya = collect();
        $menungguTugas = collect();
        $menungguUjian = collect();

        if ($user->isSiswa()) {
            if ($siswaId) {
                $pengumpulanSaya = Pengumpulan::whereIn('postId', $idTugas)->where('siswaId', $siswaId)->pluck('status', 'postId');
                $percobaanSaya = Percobaan::whereIn('postId', $idUjian)->where('siswaId', $siswaId)->pluck('status', 'postId');
            }
        } else {
            $menungguTugas = Pengumpulan::selectRaw('postId, count(*) as total')
                ->whereIn('postId', $idTugas)->where('status', 'DIKUMPULKAN')->groupBy('postId')->pluck('total', 'postId');
            $menungguUjian = Percobaan::selectRaw('postId, count(*) as total')
                ->whereIn('postId', $idUjian)->where('status', 'SELESAI')->groupBy('postId')->pluck('total', 'postId');
        }

        return $posts->map(function (Post $post) use ($user, $pengumpulanSaya, $percobaanSaya, $menungguTugas, $menungguUjian) {
            $tugas = $post->tipe === 'TUGAS';
            $tenggat = $tugas ? $post->deadline : ($post->selesaiAt ?? $post->mulaiAt);

            $item = [
                'id' => $post->id,
                'judul' => $post->judul,
                'tipe' => $post->tipe,
                'tenggat' => PostPresenter::iso($tenggat),
                'diposting' => PostPresenter::iso($post->createdAt),
                'kelasId' => $post->kelasId,
                'mapel' => $post->kelas->mapel->nama,
                'rombel' => $post->kelas->rombel->nama,
            ];

            if ($user->isSiswa()) {
                // Ujian: BERJALAN dianggap belum selesai; SELESAI = sudah dikumpulkan, menunggu koreksi.
                $item['status'] = match ($tugas ? $pengumpulanSaya->get($post->id) : $percobaanSaya->get($post->id)) {
                    'DINILAI' => 'DINILAI',
                    'DIKUMPULKAN', 'SELESAI' => 'DIKUMPULKAN',
                    default => 'BELUM',
                };
            } else {
                $item['menunggu'] = (int) (($tugas ? $menungguTugas : $menungguUjian)->get($post->id) ?? 0);
            }

            return $item;
        })->values()->all();
    }
}
