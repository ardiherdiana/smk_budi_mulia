<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Soal;
use App\Models\LogAktivitas;
use App\Services\Classroom\Impor\DokumenTidakDapatDibaca;
use App\Services\Classroom\Impor\SoalImporter;
use App\Services\Classroom\PostPublisher;
use App\Services\Classroom\SoalGambarService;
use App\Services\MediaService;
use App\Support\Classroom\PembagiPoin;
use App\Support\LogAktivitas\Perekam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SoalController extends Controller
{
    public function __construct(private PostPublisher $publisher, private SoalGambarService $gambar, private Perekam $perekam) {}

    /** Editor satu halaman: seluruh soal ujian diedit sekaligus. */
    public function edit(Request $request, Post $post)
    {
        $this->izinkan($request, $post, bolehTerkunci: true);

        $post->loadMissing('kelas.mapel', 'kelas.rombel');

        $saudara = $this->publisher->saudara($post);
        $sudahDikerjakan = Percobaan::whereIn('postId', $saudara->pluck('id'))->pluck('postId')->unique()->all();
        $soal = $post->soal()->get();

        return Inertia::render('classroom/ujian/editor-soal-page', [
            'post' => ['id' => $post->id, 'judul' => $post->judul, 'kelasId' => $post->kelasId],
            'kelas' => ['id' => $post->kelas->id, 'mapel' => $post->kelas->mapel->nama, 'rombel' => $post->kelas->rombel->nama],
            // Ujian multi-kelas: soal yang disimpan di sini ikut disalin ke kelas lain yang belum dikerjakan siswa.
            'kelasLain' => $saudara->map(fn (Post $s) => [
                'rombel' => $s->kelas->rombel->nama,
                'terkunci' => in_array($s->id, $sudahDikerjakan, true),
            ])->values()->all(),
            'terkunci' => $this->sudahDikerjakan($post),
            // Ujian baru (atau yang poinnya memang bagi rata) dibuka dalam mode bagi rata total 100; poin manual dibiarkan apa adanya.
            'bagiRata' => $soal->isEmpty() || PembagiPoin::sama($soal->pluck('poin')),
            'soal' => $soal->map(fn (Soal $s) => [
                'id' => $s->id,
                'tipe' => $s->tipe,
                'teks' => $s->teks,
                'kompetensi' => $s->kompetensi,
                'subKompetensi' => $s->subKompetensi,
                'poin' => $s->poin,
                'opsi' => $s->opsi,
                'kunci' => $s->kunci,
                ...$this->gambar->untukEditor($s),
            ])->values()->all(),
        ]);
    }

    /**
     * Baca dokumen soal (.docx atau PDF) dan kembalikan draf soal (beserta gambarnya di media sementara) tanpa
     * menyimpan apa pun ke tabel soal. Guru memeriksa draf di editor lalu menyimpannya lewat `sinkron`. Jenis berkas
     * ditentukan dari isinya oleh `SoalImporter`, bukan dari ekstensi.
     */
    public function impor(Request $request, Post $post, SoalImporter $importer)
    {
        $this->izinkan($request, $post);

        $maksMb = intdiv((int) config('classroom.max_impor_kb'), 1024);
        $request->validate(
            ['berkas' => ['required', 'file', 'extensions:docx,pdf', 'max:'.config('classroom.max_impor_kb')]],
            [
                'berkas.required' => 'Pilih berkas .docx atau PDF',
                'berkas.extensions' => 'Berkas harus berformat .docx atau PDF',
                'berkas.max' => "Ukuran berkas maksimal {$maksMb} MB",
                'berkas.uploaded' => "Berkas gagal diunggah, periksa ukurannya (maksimal {$maksMb} MB)",
            ]
        );

        // Membaca dokumen besar dengan banyak gambar bisa lebih lama dari batas bawaan.
        set_time_limit(120);

        try {
            $hasil = $importer->impor($request->file('berkas')->getRealPath());
        } catch (DokumenTidakDapatDibaca $e) {
            throw ValidationException::withMessages(['berkas' => $e->getMessage()]);
        }

        return response()->json($hasil);
    }

    /** Template Word soal ujian yang dibaca langsung oleh `impor`. */
    public function templat()
    {
        return response()->download(resource_path('templates/template-soal.docx'), 'Template Soal Ujian.docx');
    }

    /** Unggah satu gambar untuk soal atau pilihan; disimpan sementara sampai guru menyimpan soalnya. */
    public function unggahGambar(Request $request, Post $post, MediaService $media)
    {
        $this->izinkan($request, $post);

        $request->validate(
            ['gambar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120']],
            [
                'gambar.required' => 'Pilih gambar',
                'gambar.mimes' => 'Gambar harus berformat JPG, PNG, atau WebP',
                'gambar.max' => 'Ukuran gambar maksimal 5 MB',
                'gambar.uploaded' => 'Gambar gagal diunggah, periksa ukurannya (maksimal 5 MB)',
            ]
        );

        $this->gambar->sapuSementara();
        $path = $media->storeImage($request->file('gambar'), SoalGambarService::SEMENTARA, 'gambar');

        return response()->json($this->gambar->item($path));
    }

    /**
     * Simpan seluruh daftar soal sekaligus: yang punya id diperbarui, yang baru dibuat, yang tidak dikirim dihapus,
     * dan nomor urut mengikuti urutan kiriman.
     */
    public function sinkron(Request $request, Post $post)
    {
        $this->izinkan($request, $post);

        // Mode bagi rata: poin dihitung server (total 100) dan kiriman poin per soal diabaikan.
        $bagiRata = $request->boolean('bagiRata');

        $request->validate([
            'bagiRata' => ['sometimes', 'boolean'],
            'soal' => ['present', 'array', 'max:200'],
            'soal.*.id' => ['nullable', 'string'],
            'soal.*.tipe' => ['required', Rule::in(Soal::TIPE)],
            'soal.*.teks' => ['required', 'string', 'max:10000'],
            'soal.*.gambar' => ['nullable', 'array', 'max:'.config('classroom.max_gambar_soal')],
            'soal.*.gambar.*' => ['nullable', 'string', 'max:200'],
            'soal.*.kompetensi' => ['nullable', 'string', 'max:2000'],
            'soal.*.subKompetensi' => ['nullable', 'string', 'max:2000'],
            'soal.*.poin' => $bagiRata ? ['nullable'] : ['required', 'numeric', 'min:0.5', 'max:100'],
            'soal.*.opsi' => ['nullable', 'array', 'max:10'],
            'soal.*.opsi.*' => ['nullable', 'string', 'max:1000'],
            'soal.*.opsiGambar' => ['nullable', 'array', 'max:10'],
            'soal.*.opsiGambar.*' => ['nullable', 'string', 'max:200'],
            'soal.*.kunci' => ['nullable', 'string', 'max:50'],
        ], [
            'soal.*.teks.required' => 'Teks soal wajib diisi',
            'soal.*.poin.required' => 'Poin wajib diisi',
            'soal.*.poin.min' => 'Poin minimal 0,5',
            'soal.*.gambar.max' => 'Gambar pada soal maksimal :max',
        ]);

        $ada = $post->soal()->get()->keyBy('id');
        // Gambar yang boleh dirujuk selain unggahan sementara: yang sudah dipakai soal ujian ini.
        $pathLama = $ada->flatMap(fn (Soal $s) => $s->semuaGambar())->unique()->values()->all();

        // Rapikan semua soal dulu dan kumpulkan kesalahannya (kunci galat memuat nomor soal) sebelum menyentuh DB.
        $bersih = [];
        $galat = [];
        foreach ((array) $request->input('soal') as $i => $item) {
            try {
                $bersih[$i] = $this->rapikan($item, $pathLama);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $kolom => $pesan) {
                    $galat["soal.{$i}.{$kolom}"] = $pesan[0];
                }
            }
        }
        if ($galat) {
            throw ValidationException::withMessages($galat);
        }

        if ($bagiRata) {
            $poin = PembagiPoin::bagiRata(count($bersih));
            foreach (array_keys($bersih) as $urutan => $i) {
                $bersih[$i]['poin'] = $poin[$urutan];
            }
        }

        // Berkas sementara yang dipakai soal akan dipindah ke folder permanen; path finalnya ditulis ke database.
        $dipakai = collect($bersih)->flatMap(fn (array $d) => [...($d['gambar'] ?? []), ...($d['opsiGambar'] ?? [])])->filter()->unique()->values()->all();
        $peta = $this->gambar->petaSementara($dipakai);
        if ($peta) {
            foreach ($bersih as $i => $d) {
                $bersih[$i]['gambar'] = $d['gambar'] === null ? null : array_map(fn (string $p) => $peta[$p] ?? $p, $d['gambar']);
                $bersih[$i]['opsiGambar'] = $d['opsiGambar'] === null ? null : array_map(fn (?string $p) => $p === null ? null : ($peta[$p] ?? $p), $d['opsiGambar']);
            }
        }

        DB::transaction(function () use ($post, $bersih, $request, $ada) {
            $dipakai = [];

            foreach ($bersih as $i => $data) {
                $data['urut'] = $i + 1;
                $id = $request->input("soal.{$i}.id");
                if ($id && $ada->has($id)) {
                    $ada[$id]->update($data);
                    $dipakai[] = $id;
                } else {
                    $dipakai[] = $post->soal()->create($data)->id;
                }
            }

            $post->soal()->whereNotIn('id', $dipakai)->delete();
        });

        // Soal tidak didaftarkan di log per baris (satu simpan bisa puluhan soal); dicatat satu baris di sini.
        $this->perekam->catat(
            LogAktivitas::UBAH,
            'Classroom',
            Perekam::namaPengguna($request->user()).' menyimpan '.count($bersih).' soal ujian "'.$post->judul.'"',
            'Soal ujian',
            $post,
        );

        $this->gambar->pindahkanSementara($peta);
        $this->gambar->lepas($pathLama);

        $pesan = 'Soal disimpan';
        if ($post->grupId) {
            $hasil = $this->publisher->sinkronkanSoal($post, $request->user());
            $pesan .= $hasil['disalin'] > 0 ? " dan disalin ke {$hasil['disalin']} ruang kelas lain" : '';
            $pesan .= $hasil['dilewati'] > 0 ? " ({$hasil['dilewati']} dilewati karena sudah dikerjakan siswa)" : '';
        }

        return redirect("/classroom/post/{$post->id}")->with('toast', ['type' => 'success', 'message' => $pesan]);
    }

    private function izinkan(Request $request, Post $post, bool $bolehTerkunci = false): void
    {
        $post->loadMissing('kelas');
        abort_unless($post->kelas->dapatDikelola($request->user()), 403, 'Anda tidak memiliki akses');
        abort_unless($post->tipe === 'UJIAN', 404, 'Postingan ini bukan ujian');
        // Mengubah soal setelah ada yang mengerjakan membuat nilai yang sudah keluar tidak sesuai lagi.
        abort_if(! $bolehTerkunci && $this->sudahDikerjakan($post), 409, 'Soal tidak bisa diubah karena sudah ada siswa yang mengerjakan');
    }

    private function sudahDikerjakan(Post $post): bool
    {
        return Percobaan::where('postId', $post->id)->exists();
    }

    /**
     * Susun data soal siap simpan. Pilihan kosong dibuang (kecuali yang punya gambar) dan kunci dipetakan ulang ke daftar
     * yang sudah bersih.
     *
     * @param  array<string, mixed>  $item
     * @param  list<string>  $pathLama  gambar yang sudah dipakai soal ujian ini
     * @return array<string, mixed>
     */
    private function rapikan(array $item, array $pathLama): array
    {
        $gambar = $this->rapikanGambar((array) ($item['gambar'] ?? []), $pathLama, 'gambar');

        $dasar = [
            'tipe' => $item['tipe'],
            'teks' => $item['teks'],
            'gambar' => $gambar === [] ? null : $gambar,
            'kompetensi' => $this->teksOpsional($item['kompetensi'] ?? null),
            'subKompetensi' => $this->teksOpsional($item['subKompetensi'] ?? null),
            'poin' => $item['poin'] ?? 0,
        ];

        if ($item['tipe'] === 'ESSAY') {
            return $dasar + ['opsi' => null, 'opsiGambar' => null, 'kunci' => null];
        }

        $mentah = array_values($item['opsi'] ?? []);
        $kunciMentah = explode(',', (string) ($item['kunci'] ?? ''));

        if ($item['tipe'] === 'PGK_KATEGORI') {
            // Kunci per pernyataan ("1,0,1"), jadi posisi kosong tetap dibawa supaya urutannya sejajar dengan pernyataan.
            $opsi = [];
            $kunci = [];
            foreach ($mentah as $i => $teks) {
                if (trim((string) $teks) === '') {
                    continue;
                }
                $opsi[] = trim((string) $teks);
                $kunci[] = $kunciMentah[$i] ?? '';
            }
            if (count($opsi) < 2) {
                throw ValidationException::withMessages(['opsi' => 'Isi minimal 2 pernyataan']);
            }
            if (array_diff($kunci, ['0', '1'])) {
                throw ValidationException::withMessages(['kunci' => 'Tentukan Benar atau Salah untuk setiap pernyataan']);
            }

            return $dasar + ['opsi' => $opsi, 'opsiGambar' => null, 'kunci' => implode(',', $kunci)];
        }

        $gambarMentah = array_values((array) ($item['opsiGambar'] ?? []));
        $peta = [];
        $opsi = [];
        $opsiGambar = [];
        foreach ($mentah as $i => $teks) {
            $path = $this->rapikanGambar([$gambarMentah[$i] ?? null], $pathLama, 'opsiGambar')[0] ?? null;
            // Pilihan boleh hanya berupa gambar.
            if (trim((string) $teks) !== '' || $path !== null) {
                $peta[$i] = count($opsi);
                $opsi[] = trim((string) $teks);
                $opsiGambar[] = $path;
            }
        }
        if (count($opsi) < 2) {
            throw ValidationException::withMessages(['opsi' => 'Isi minimal 2 pilihan jawaban']);
        }

        $kunci = [];
        foreach ($kunciMentah as $k) {
            if (ctype_digit($k) && isset($peta[(int) $k])) {
                $kunci[] = $peta[(int) $k];
            }
        }
        $kunci = array_values(array_unique($kunci));
        sort($kunci);

        if ($item['tipe'] === 'PG' && count($kunci) !== 1) {
            throw ValidationException::withMessages(['kunci' => 'Pilih satu jawaban yang benar']);
        }
        if ($item['tipe'] === 'PGK_MCMA' && $kunci === []) {
            throw ValidationException::withMessages(['kunci' => 'Pilih minimal satu jawaban yang benar']);
        }

        return $dasar + [
            'opsi' => $opsi,
            'opsiGambar' => array_filter($opsiGambar) === [] ? null : $opsiGambar,
            'kunci' => implode(',', $kunci),
        ];
    }

    /**
     * Pastikan setiap path gambar sah: unggahan sementara yang berkasnya masih ada, atau gambar yang memang sudah
     * dipakai soal ujian ini. Path lain (milik ujian lain, sembarang string) ditolak.
     *
     * @param  array<int, mixed>  $paths
     * @param  list<string>  $pathLama
     * @return list<string>
     */
    private function rapikanGambar(array $paths, array $pathLama, string $kolom): array
    {
        $hasil = [];
        foreach ($paths as $path) {
            $path = trim((string) $path);
            if ($path === '') {
                continue;
            }
            if (! $this->gambar->sah($path)) {
                throw ValidationException::withMessages([$kolom => 'Gambar tidak valid, unggah ulang']);
            }
            $dikenal = str_starts_with($path, SoalGambarService::SEMENTARA.'/') ? $this->gambar->ada($path) : in_array($path, $pathLama, true);
            if (! $dikenal) {
                throw ValidationException::withMessages([$kolom => 'Gambar sudah tidak tersedia, unggah ulang']);
            }
            $hasil[] = $path;
        }

        return array_values(array_unique($hasil));
    }

    private function teksOpsional(mixed $nilai): ?string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }
}
