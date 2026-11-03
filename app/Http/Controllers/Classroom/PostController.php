<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Lampiran;
use App\Models\Classroom\Pengumpulan;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Siswa;
use App\Services\Classroom\LampiranService;
use App\Services\Classroom\NilaiUjianService;
use App\Services\Classroom\PostCleaner;
use App\Services\Classroom\PostPresenter;
use App\Services\Classroom\PostPublisher;
use App\Services\Classroom\SoalGambarService;
use App\Services\Classroom\UjianService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostController extends Controller
{
    public function __construct(
        private PostPresenter $presenter,
        private LampiranService $lampiran,
        private UjianService $ujian,
        private PostPublisher $publisher,
        private SoalGambarService $soalGambar,
        private NilaiUjianService $nilaiUjian,
    ) {}

    public function store(Request $request, Kelas $kelas)
    {
        abort_unless($kelas->dapatDikelola($request->user()), 403, 'Anda tidak memiliki akses');
        abort_if($kelas->diarsipkan, 409, 'Ruang kelas ini sudah diarsipkan');

        $validated = $request->validate(
            ['tipe' => ['required', 'in:PENGUMUMAN,MATERI,TUGAS,UJIAN']]
                + ['kelasTujuan' => ['nullable', 'array', 'max:50'], 'kelasTujuan.*' => ['string']]
                + $this->aturan($request->input('tipe'))
                + LampiranService::rules(),
            $this->pesan(),
        );

        $posts = $this->publisher->terbitkan(
            $kelas,
            $request->user(),
            $validated['tipe'],
            $this->atribut($validated),
            $validated['kelasTujuan'] ?? [],
            $request->file('berkas', []),
            $validated['tautan'] ?? [],
        );
        $post = $posts[0];
        $jumlah = count($posts);

        // Ujian baru perlu soal, jadi langsung buka halaman detailnya. Soal ikut disalin ke kelas lain di grup yang sama.
        if ($post->tipe === 'UJIAN') {
            return redirect("/classroom/post/{$post->id}")->with('toast', [
                'type' => 'success',
                'message' => $jumlah > 1
                    ? "Ujian dibuat di {$jumlah} ruang kelas. Tambahkan soal di bawah, soal ikut disalin ke kelas lain"
                    : 'Ujian dibuat. Tambahkan soal di bawah',
            ]);
        }

        return back()->with('toast', [
            'type' => 'success',
            'message' => $jumlah > 1 ? "Berhasil dipublikasikan ke {$jumlah} ruang kelas" : 'Berhasil dipublikasikan',
        ]);
    }

    public function show(Request $request, Post $post)
    {
        $user = $request->user();
        $post->loadMissing('kelas');
        $kelas = $post->kelas;
        abort_unless($kelas->dapatDiakses($user), 403, 'Anda tidak memiliki akses ke ruang kelas ini');

        $data = [
            'kelas' => $this->presenter->kelas($kelas, $user),
            'post' => $this->presenter->daftar($post->newCollection([$post]), $user)[0],
            'dapatDikelola' => $kelas->dapatDikelola($user),
        ];

        if ($post->tipe === 'TUGAS') {
            $data += $user->isSiswa() ? $this->tugasSiswa($post, $user->siswa) : $this->tugasGuru($post, $kelas);
        } elseif ($post->tipe === 'UJIAN') {
            $data += $user->isSiswa() ? $this->ujianSiswa($post, $user->siswa) : $this->ujianGuru($post, $user);
        }

        return Inertia::render('classroom/post/detail-page', $data);
    }

    public function update(Request $request, Post $post)
    {
        $post->loadMissing('kelas');
        abort_unless($post->kelas->dapatDikelola($request->user()), 403, 'Anda tidak memiliki akses');

        $validated = $request->validate(
            $this->aturan($post->tipe) + LampiranService::rules() + [
                'hapusLampiran' => ['nullable', 'array'],
                'hapusLampiran.*' => ['string'],
                'semuaKelas' => ['nullable', 'boolean'],
            ],
            $this->pesan(),
        );
        $atribut = $this->atribut($validated, $post);

        $post->update($atribut);

        $dihapus = Lampiran::where('postId', $post->id)->whereIn('id', $validated['hapusLampiran'] ?? [])->get();
        foreach ($dihapus as $l) {
            $this->lampiran->hapus($l);
        }

        $sebelum = Lampiran::where('postId', $post->id)->pluck('id')->all();
        $this->lampiran->simpan('postId', $post->id, $request->file('berkas', []), $validated['tautan'] ?? []);

        // Postingan multi-kelas: ubahan boleh diteruskan ke kelas lain di grup yang sama.
        $lainnya = $request->boolean('semuaKelas') && $post->grupId
            ? $this->publisher->terapkanKeSaudara(
                $post,
                $request->user(),
                $atribut,
                $dihapus,
                Lampiran::where('postId', $post->id)->whereNotIn('id', $sebelum)->get(),
            )
            : 0;

        return back()->with('toast', [
            'type' => 'success',
            'message' => $lainnya > 0 ? 'Perubahan disimpan di '.($lainnya + 1).' ruang kelas' : 'Perubahan disimpan',
        ]);
    }

    /** Ganti token ujian (mis. setelah bocor). Seluruh kelas dalam grup yang sama ikut mendapat token baru. */
    public function tokenBaru(Request $request, Post $post)
    {
        $post->loadMissing('kelas');
        abort_unless($post->kelas->dapatDikelola($request->user()), 403, 'Anda tidak memiliki akses');
        abort_unless($post->tipe === 'UJIAN' && $post->tokenAkses !== null, 409, 'Ujian ini tidak memakai token');

        $token = $this->ujian->buatToken();
        $post->update(['tokenAkses' => $token]);
        foreach ($this->publisher->saudaraDapatDikelola($post, $request->user(), tanpaArsip: true) as $saudara) {
            $saudara->update(['tokenAkses' => $token]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Token baru dibuat']);
    }

    public function destroy(Request $request, Post $post, PostCleaner $cleaner)
    {
        $post->loadMissing('kelas');
        abort_unless($post->kelas->dapatDikelola($request->user()), 403, 'Anda tidak memiliki akses');

        $kelasId = $post->kelasId;

        // Postingan multi-kelas: hapus juga salinannya di kelas lain bila diminta.
        $lainnya = $request->boolean('semuaKelas') ? $this->publisher->saudaraDapatDikelola($post, $request->user()) : collect();
        foreach ($lainnya as $saudara) {
            $cleaner->hapusPost($saudara);
        }
        $cleaner->hapusPost($post);

        return redirect("/classroom/kelas/{$kelasId}")->with('toast', [
            'type' => 'success',
            'message' => $lainnya->isNotEmpty() ? 'Postingan dihapus dari '.($lainnya->count() + 1).' ruang kelas' : 'Postingan dihapus',
        ]);
    }

    /** @return array<string, mixed> */
    private function aturan(?string $tipe): array
    {
        return match ($tipe) {
            'PENGUMUMAN' => [
                'isi' => ['required', 'string', 'max:10000'],
            ],
            'MATERI' => [
                'judul' => ['required', 'string', 'max:255'],
                'isi' => ['nullable', 'string', 'max:10000'],
            ],
            'TUGAS' => [
                'judul' => ['required', 'string', 'max:255'],
                'isi' => ['nullable', 'string', 'max:10000'],
                'deadline' => ['nullable', 'date'],
                'poinMaks' => ['nullable', 'integer', 'min:1', 'max:1000'],
                'izinTerlambat' => ['nullable', 'boolean'],
            ],
            'UJIAN' => [
                'judul' => ['required', 'string', 'max:255'],
                'isi' => ['nullable', 'string', 'max:10000'],
                'durasiMenit' => ['required', 'integer', 'min:1', 'max:300'],
                'mulaiAt' => ['nullable', 'date'],
                'selesaiAt' => ['nullable', 'date', 'after:mulaiAt'],
                'acakSoal' => ['nullable', 'boolean'],
                'tampilkanNilai' => ['nullable', 'boolean'],
                'wajibToken' => ['nullable', 'boolean'],
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    private function pesan(): array
    {
        return LampiranService::messages() + [
            'isi.required' => 'Isi pengumuman wajib diisi',
            'judul.required' => 'Judul wajib diisi',
            'durasiMenit.required' => 'Durasi ujian wajib diisi',
            'selesaiAt.after' => 'Waktu selesai harus setelah waktu mulai',
        ];
    }

    /**
     * Hanya kolom milik post (tanpa berkas/tautan), dengan boolean dan bawaan yang rapi.
     *
     * Token ujian: `wajibToken` hidup mempertahankan token yang ada (atau membuat baru bila belum ada), mati mengosongkannya.
     * Atribut yang sama dipakai untuk seluruh grup multi-kelas, jadi semua kelas berbagi satu token.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function atribut(array $validated, ?Post $post = null): array
    {
        $atribut = array_intersect_key($validated, array_flip([
            'judul', 'isi', 'deadline', 'poinMaks', 'durasiMenit', 'mulaiAt', 'selesaiAt',
        ]));

        foreach (['izinTerlambat', 'acakSoal', 'tampilkanNilai'] as $kolom) {
            if (array_key_exists($kolom, $validated)) {
                $atribut[$kolom] = (bool) $validated[$kolom];
            }
        }
        if (array_key_exists('deadline', $validated) && ! array_key_exists('poinMaks', $validated)) {
            $atribut['poinMaks'] = 100;
        }
        if (array_key_exists('wajibToken', $validated)) {
            $atribut['tokenAkses'] = $validated['wajibToken'] ? ($post?->tokenAkses ?? $this->ujian->buatToken()) : null;
        }

        return $atribut;
    }

    /** @return array<string, mixed> */
    private function tugasSiswa(Post $post, ?Siswa $siswa): array
    {
        $kumpul = $siswa ? Pengumpulan::with('lampiran')->where(['postId' => $post->id, 'siswaId' => $siswa->id])->first() : null;

        return [
            'pengumpulanSaya' => $kumpul ? $this->presenter->ringkasPengumpulan($kumpul) + [
                'lampiran' => $kumpul->lampiran->map(fn (Lampiran $l) => $this->presenter->lampiran($l))->values()->all(),
            ] : null,
            'bisaMengumpulkan' => ! $post->deadline || $post->izinTerlambat || now()->lte($post->deadline),
        ];
    }

    /** @return array<string, mixed> */
    private function tugasGuru(Post $post, Kelas $kelas): array
    {
        $kumpul = Pengumpulan::with('lampiran')->where('postId', $post->id)->get()->keyBy('siswaId');

        return [
            'pengumpulan' => Siswa::where('rombelId', $kelas->rombelId)->orderBy('nama')->get()->map(function (Siswa $s) use ($kumpul) {
                $p = $kumpul->get($s->id);

                return [
                    'siswaId' => $s->id,
                    'nama' => $s->nama,
                    'nis' => $s->nis,
                    'pengumpulan' => $p ? $this->presenter->ringkasPengumpulan($p) + [
                        'lampiran' => $p->lampiran->map(fn (Lampiran $l) => $this->presenter->lampiran($l))->values()->all(),
                    ] : null,
                ];
            })->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function ujianSiswa(Post $post, ?Siswa $siswa): array
    {
        $percobaan = $siswa ? Percobaan::where(['postId' => $post->id, 'siswaId' => $siswa->id])->first() : null;
        if ($percobaan) {
            $this->ujian->finalisasiJikaKedaluwarsa($percobaan);
            $percobaan->refresh();
        }

        $total = $this->ujian->totalPoin($post);

        return [
            'jumlahSoal' => $post->soal()->count(),
            'totalPoin' => $total,
            'jendela' => $this->ujian->jendela($post),
            'percobaanSaya' => $percobaan ? [
                'status' => $percobaan->status,
                'selesaiAt' => PostPresenter::iso($percobaan->selesaiAt),
                // Nilai disembunyikan bila guru mematikannya, atau bila essay belum selesai dikoreksi.
                'skor' => $post->tampilkanNilai && $percobaan->status === 'DINILAI' ? $percobaan->skorTotal() : null,
                'menungguKoreksi' => $percobaan->status === 'SELESAI',
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function ujianGuru(Post $post, Akun $user): array
    {
        $soal = $post->soal()->get();
        $percobaan = Percobaan::where('postId', $post->id)->get()->keyBy('siswaId');
        foreach ($percobaan as $p) {
            $this->ujian->finalisasiJikaKedaluwarsa($p);
        }

        return [
            // Ruang kelas yang bisa dipilih di dialog ekspor nilai (kelas ini dan kelas lain satu grup ujian).
            'kelasUjian' => $this->nilaiUjian->ringkasKelas($this->nilaiUjian->kandidat($post, $user), $post),
            'totalPoin' => (float) $soal->sum('poin'),
            'sudahDikerjakan' => $percobaan->isNotEmpty(),
            'soal' => $soal->map(fn ($s) => [
                'id' => $s->id,
                'urut' => $s->urut,
                'tipe' => $s->tipe,
                'teks' => $s->teks,
                'poin' => $s->poin,
                'opsi' => $s->opsi,
                ...$this->soalGambar->tampil($s),
                'kunci' => $s->kunci,
                'kompetensi' => $s->kompetensi,
                'subKompetensi' => $s->subKompetensi,
            ])->values()->all(),
            'hasil' => Siswa::where('rombelId', $post->kelas->rombelId)->orderBy('nama')->get()->map(function (Siswa $s) use ($percobaan) {
                $p = $percobaan->get($s->id)?->fresh();

                return [
                    'siswaId' => $s->id,
                    'nama' => $s->nama,
                    'nis' => $s->nis,
                    'percobaanId' => $p?->id,
                    'status' => $p?->status,
                    'skor' => $p && $p->status !== 'BERJALAN' ? $p->skorTotal() : null,
                ];
            })->values()->all(),
        ];
    }
}
