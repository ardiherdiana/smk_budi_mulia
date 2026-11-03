<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Mapel;
use App\Models\Classroom\Pengumpulan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\Siswa;
use App\Models\Classroom\TahunAjaran;
use App\Services\Classroom\NilaiService;
use App\Services\Classroom\PostCleaner;
use App\Services\Classroom\PostPresenter;
use App\Services\Classroom\PostPublisher;
use App\Support\Classroom\XlsxExport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class KelasController extends Controller
{
    public function __construct(private PostPresenter $presenter, private NilaiService $nilai, private PostPublisher $publisher) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $arsip = $request->boolean('arsip');
        $aktif = TahunAjaran::aktif();

        $query = Kelas::with(['mapel:id,nama', 'rombel:id,nama', 'guru:id,name,username', 'tahunAjaran'])
            ->addSelect(['jumlahSiswa' => Siswa::selectRaw('count(*)')->whereColumn('siswa.rombelId', 'kelas.rombelId')])
            ->where('diarsipkan', $arsip);

        if (! $arsip && $aktif) {
            $query->where('tahunAjaranId', $aktif->id);
        }

        $query->untukPengguna($user);

        $daftar = $query->orderBy('rombelId')->get()->sortBy(fn (Kelas $k) => $k->rombel->nama.'|'.$k->mapel->nama)->values();
        $ids = $daftar->pluck('id')->all();

        // Penanda di kartu: tugas yang belum dikumpulkan (siswa) atau pengumpulan yang menunggu nilai (guru).
        $penanda = collect();
        if ($ids && $user->isSiswa() && $user->siswa) {
            $penanda = Post::selectRaw('kelasId, count(*) as total')
                ->whereIn('kelasId', $ids)->where('tipe', 'TUGAS')
                ->whereDoesntHave('pengumpulan', fn ($q) => $q->where('siswaId', $user->siswa->id))
                ->groupBy('kelasId')->pluck('total', 'kelasId');
        } elseif ($ids && ! $user->isSiswa()) {
            $penanda = Pengumpulan::selectRaw('classroom_posts.kelasId, count(*) as total')
                ->join('classroom_posts', 'classroom_posts.id', '=', 'pengumpulan.postId')
                ->whereIn('classroom_posts.kelasId', $ids)->where('pengumpulan.status', 'DIKUMPULKAN')
                ->groupBy('classroom_posts.kelasId')->pluck('total', 'kelasId');
        }

        $bisaMembuat = in_array($user->role, ['ADMIN', 'GURU'], true);

        return Inertia::render('classroom/kelas/daftar-page', [
            'kelas' => $daftar->map(fn (Kelas $k) => [
                'id' => $k->id,
                'mapel' => $k->mapel->nama,
                'rombel' => $k->rombel->nama,
                'guru' => $k->guru->namaTampil(),
                'tahunAjaran' => $k->tahunAjaran->label(),
                'jumlahSiswa' => (int) $k->jumlahSiswa,
                'diarsipkan' => $k->diarsipkan,
                'penanda' => (int) ($penanda[$k->id] ?? 0),
            ])->all(),
            'arsip' => $arsip,
            'bisaMembuat' => $bisaMembuat,
            'opsi' => $bisaMembuat ? [
                'rombel' => Rombel::orderBy('nama')->get(['id', 'nama']),
                'mapel' => Mapel::orderBy('nama')->get(['id', 'nama']),
                'guru' => $user->isAdmin()
                    ? Akun::whereIn('role', ['GURU', 'KEPSEK'])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'username'])
                        ->map(fn (Akun $g) => ['id' => $g->id, 'nama' => $g->namaTampil()])->values()
                    : null,
                'tahunAjaran' => $aktif?->label(),
            ] : null,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'rombelId' => ['required', 'string', Rule::exists('rombel', 'id')],
            'mapelId' => ['nullable', 'string', Rule::exists('mapel', 'id')],
            'mapelBaru' => ['nullable', 'string', 'max:100'],
            'guruId' => ['nullable', 'integer', Rule::exists('profil_akun', 'id')->whereIn('role', ['GURU', 'KEPSEK'])],
            'deskripsi' => ['nullable', 'string', 'max:500'],
        ], [
            'rombelId.required' => 'Pilih rombel',
        ]);

        $tahun = TahunAjaran::aktif();
        abort_unless($tahun, 409, 'Belum ada tahun ajaran aktif. Minta admin mengaktifkannya dulu');

        $namaBaru = trim((string) ($validated['mapelBaru'] ?? ''));
        abort_if(empty($validated['mapelId']) && $namaBaru === '', 422, 'Pilih mata pelajaran atau ketik nama mapel baru');
        $mapelId = $namaBaru !== '' && empty($validated['mapelId'])
            ? Mapel::firstOrCreate(['nama' => $namaBaru])->id
            : $validated['mapelId'];

        $guruId = $user->isAdmin() ? ($validated['guruId'] ?? null) : $user->id;
        abort_unless($guruId, 422, 'Pilih guru pengajar');

        abort_if(
            Kelas::where(['rombelId' => $validated['rombelId'], 'mapelId' => $mapelId, 'tahunAjaranId' => $tahun->id])->exists(),
            409,
            'Ruang kelas untuk mapel dan rombel ini sudah ada di tahun ajaran aktif',
        );

        $kelas = Kelas::create([
            'rombelId' => $validated['rombelId'],
            'mapelId' => $mapelId,
            'guruId' => $guruId,
            'tahunAjaranId' => $tahun->id,
            'deskripsi' => $validated['deskripsi'] ?? null,
        ]);

        return redirect("/classroom/kelas/{$kelas->id}")->with('toast', ['type' => 'success', 'message' => 'Ruang kelas berhasil dibuat']);
    }

    public function update(Request $request, Kelas $kelas)
    {
        abort_unless($kelas->dapatDikelola($request->user()), 403, 'Anda tidak memiliki akses');

        $validated = $request->validate(['deskripsi' => ['nullable', 'string', 'max:500']]);
        $kelas->update(['deskripsi' => $validated['deskripsi'] ?? null]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Ruang kelas diperbarui']);
    }

    public function arsip(Request $request, Kelas $kelas)
    {
        abort_unless($kelas->dapatDikelola($request->user()), 403, 'Anda tidak memiliki akses');

        $kelas->update(['diarsipkan' => ! $kelas->diarsipkan]);

        return redirect('/classroom/kelas')->with('toast', [
            'type' => 'success',
            'message' => $kelas->diarsipkan ? 'Ruang kelas diarsipkan' : 'Ruang kelas dipulihkan dari arsip',
        ]);
    }

    public function destroy(Request $request, Kelas $kelas)
    {
        abort_unless($kelas->dapatDikelola($request->user()), 403, 'Anda tidak memiliki akses');

        app(PostCleaner::class)->hapusKelas($kelas);

        return redirect('/classroom/kelas')->with('toast', ['type' => 'success', 'message' => 'Ruang kelas dihapus']);
    }

    /** Stream: pengumuman, materi, tugas, dan ujian terbaru di satu ruang kelas. */
    public function show(Request $request, Kelas $kelas)
    {
        $user = $request->user();
        abort_unless($kelas->dapatDiakses($user), 403, 'Anda tidak memiliki akses ke ruang kelas ini');

        $posts = Post::with('kelas')->where('kelasId', $kelas->id)->orderByDesc('createdAt')->limit(50)->get();

        return Inertia::render('classroom/kelas/stream-page', [
            'kelas' => $this->presenter->kelas($kelas, $user),
            'posts' => $this->presenter->daftar($posts, $user),
            'mendatang' => $this->mendatang($kelas, $user),
            // Pilihan tujuan untuk pembuat postingan: ruang kelas lain milik guru yang sama (tingkat dan jurusan dari rombelnya).
            'tujuan' => $kelas->dapatDikelola($user) && ! $kelas->diarsipkan
                ? $this->publisher->tujuan($kelas)->map(fn (Kelas $k) => [
                    'id' => $k->id,
                    'mapel' => $k->mapel->nama,
                    'rombel' => $k->rombel->nama,
                    'tingkat' => $k->rombel->tingkat,
                    'jurusan' => $k->rombel->jurusan,
                ])->all()
                : [],
        ]);
    }

    /** Semua tugas dan ujian di ruang kelas ini, terbaru dulu. */
    public function tugas(Request $request, Kelas $kelas)
    {
        $user = $request->user();
        abort_unless($kelas->dapatDiakses($user), 403, 'Anda tidak memiliki akses ke ruang kelas ini');

        $posts = Post::with('kelas')->where('kelasId', $kelas->id)->whereIn('tipe', ['TUGAS', 'UJIAN', 'MATERI'])
            ->orderByDesc('createdAt')->get();

        return Inertia::render('classroom/kelas/tugas-page', [
            'kelas' => $this->presenter->kelas($kelas, $user),
            'posts' => $this->presenter->daftar($posts, $user, denganKomentar: false),
        ]);
    }

    public function anggota(Request $request, Kelas $kelas)
    {
        $user = $request->user();
        abort_unless($kelas->dapatDiakses($user), 403, 'Anda tidak memiliki akses ke ruang kelas ini');

        $kelas->loadMissing('rombel.waliKelas:id,name,username');

        return Inertia::render('classroom/kelas/anggota-page', [
            'kelas' => $this->presenter->kelas($kelas, $user),
            'guru' => $kelas->guru->namaTampil(),
            'waliKelas' => $kelas->rombel->waliKelas?->namaTampil(),
            // NIS hanya untuk staf; siswa melihat sesama anggota hanya sebagai nama.
            'siswa' => Siswa::where('rombelId', $kelas->rombelId)->orderBy('nama')->get()
                ->map(fn (Siswa $s) => ['id' => $s->id, 'nama' => $s->nama, 'nis' => $user->isSiswa() ? null : $s->nis])->all(),
        ]);
    }

    public function nilai(Request $request, Kelas $kelas)
    {
        $user = $request->user();
        abort_unless($kelas->dapatDiakses($user), 403, 'Anda tidak memiliki akses ke ruang kelas ini');

        if ($user->isSiswa()) {
            $matriks = $this->nilai->matriks($kelas);
            $saya = collect($matriks['baris'])->firstWhere('siswaId', $user->siswa?->id);

            return Inertia::render('classroom/kelas/nilai-page', [
                'kelas' => $this->presenter->kelas($kelas, $user),
                'kolom' => $matriks['kolom'],
                'baris' => $saya ? [$saya] : [],
                'saya' => true,
            ]);
        }

        return Inertia::render('classroom/kelas/nilai-page', [
            'kelas' => $this->presenter->kelas($kelas, $user),
            ...$this->nilai->matriks($kelas),
            'saya' => false,
        ]);
    }

    public function exportNilai(Request $request, Kelas $kelas)
    {
        $user = $request->user();
        abort_if($user->isSiswa() || ! $kelas->dapatDiakses($user), 403, 'Anda tidak memiliki akses');

        $matriks = $this->nilai->matriks($kelas);
        $kelas->loadMissing('mapel', 'rombel', 'tahunAjaran');

        $headers = ['No', 'NIS', 'Nama', ...array_map(fn ($k) => $k['judul'].' (/'.rtrim(rtrim(number_format($k['maks'], 2, '.', ''), '0'), '.').')', $matriks['kolom']), 'Rata-rata (%)'];
        $rows = [];
        foreach ($matriks['baris'] as $i => $b) {
            $rows[] = [
                $i + 1, $b['nis'] ?? '', $b['nama'],
                ...array_map(fn ($k) => $b['nilai'][$k['id']] ?? '', $matriks['kolom']),
                $b['rata'] ?? '',
            ];
        }

        return XlsxExport::download(
            'nilai-'.Str::slug($kelas->rombel->nama.' '.$kelas->mapel->nama).'.xlsx',
            config('classroom.nama_sekolah'),
            "Rekap Nilai {$kelas->mapel->nama} - {$kelas->rombel->nama}",
            'Tahun ajaran '.$kelas->tahunAjaran->label(),
            $headers,
            $rows,
        );
    }

    /**
     * Tugas dan ujian yang masih terbuka untuk siswa ini (belum dikumpulkan / belum dikerjakan), terdekat dulu.
     *
     * @return list<array{id: string, tipe: string, judul: ?string, batas: ?string}>
     */
    private function mendatang(Kelas $kelas, Akun $user): array
    {
        if (! $user->isSiswa() || ! $user->siswa) {
            return [];
        }

        $siswaId = $user->siswa->id;

        $tugas = Post::where('kelasId', $kelas->id)->where('tipe', 'TUGAS')
            ->where(fn ($q) => $q->whereNull('deadline')->orWhere('deadline', '>=', now()))
            ->whereDoesntHave('pengumpulan', fn ($q) => $q->where('siswaId', $siswaId))
            ->get();

        $ujian = Post::where('kelasId', $kelas->id)->where('tipe', 'UJIAN')
            ->where(fn ($q) => $q->whereNull('selesaiAt')->orWhere('selesaiAt', '>=', now()))
            ->whereDoesntHave('percobaan', fn ($q) => $q->where('siswaId', $siswaId)->where('status', '!=', 'BERJALAN'))
            ->get();

        return $tugas->concat($ujian)
            ->map(fn (Post $p) => [
                'id' => $p->id,
                'tipe' => $p->tipe,
                'judul' => $p->judul,
                'batas' => PostPresenter::iso($p->tipe === 'TUGAS' ? $p->deadline : $p->selesaiAt),
            ])
            ->sortBy(fn ($p) => $p['batas'] ?? '9999')
            ->take(5)->values()->all();
    }
}
