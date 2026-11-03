<?php

namespace App\Services\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post;
use App\Models\Classroom\Siswa;
use App\Models\Classroom\Soal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Hasil satu ujian per ruang kelas untuk ekspor nilai. Satu ujian bisa diterbitkan ke beberapa ruang kelas (satu
 * `classroom_posts` per kelas dengan `grupId` sama); tiap kelas dihitung dengan total poinnya sendiri karena soal
 * kelas yang sudah dikerjakan tidak ikut tertimpa saat soal kelas lain diubah.
 */
class NilaiUjianService
{
    public function __construct(private UjianService $ujian) {}

    private function labelStatus(?string $status): string
    {
        return match ($status) {
            null => 'Belum mengerjakan',
            'BERJALAN' => 'Sedang mengerjakan',
            'SELESAI' => 'Perlu koreksi',
            default => 'Dinilai',
        };
    }

    /**
     * Ruang kelas yang boleh diekspor dari ujian ini: kelas ini (pertama) dan kelas lain satu grup yang boleh diakses `$user`
     * (admin, kepsek, atau guru pemilik kelas). Kelas yang diarsipkan tetap ikut karena nilainya masih dibutuhkan.
     *
     * @return Collection<string, Post> kunci = id post
     */
    public function kandidat(Post $post, Akun $user): Collection
    {
        $post->loadMissing('kelas.rombel', 'kelas.mapel', 'kelas.tahunAjaran');

        $saudara = $post->grupId
            ? Post::with('kelas.rombel', 'kelas.mapel', 'kelas.tahunAjaran')
                ->where('grupId', $post->grupId)->where('tipe', 'UJIAN')->where('id', '!=', $post->id)->get()
                ->sortBy(fn (Post $p) => $p->kelas->rombel->nama, SORT_NATURAL | SORT_FLAG_CASE)
            : collect();

        return collect([$post])->merge($saudara)
            ->filter(fn (Post $p) => $p->kelas->dapatDiakses($user))
            ->keyBy('id');
    }

    /**
     * Ringkasan tiap kelas untuk dialog ekspor.
     *
     * @param  Collection<string, Post>  $posts
     * @return list<array<string, mixed>>
     */
    public function ringkasKelas(Collection $posts, Post $utama): array
    {
        $ids = $posts->keys()->all();
        $total = Soal::whereIn('postId', $ids)->selectRaw('postId, SUM(poin) as total')->groupBy('postId')->pluck('total', 'postId');
        $percobaan = $this->muatPercobaan($ids)->groupBy('postId');
        $jumlahSiswa = Siswa::whereIn('rombelId', $posts->map(fn (Post $p) => $p->kelas->rombelId)->unique()->all())
            ->selectRaw('rombelId, COUNT(*) as n')->groupBy('rombelId')->pluck('n', 'rombelId');

        return $posts->map(function (Post $p) use ($utama, $total, $percobaan, $jumlahSiswa) {
            $semua = $percobaan->get($p->id, collect());
            $selesai = $semua->whereIn('status', ['SELESAI', 'DINILAI']);
            $maks = round((float) ($total[$p->id] ?? 0), 2);

            return [
                'postId' => $p->id,
                'kelasId' => $p->kelasId,
                'rombel' => $p->kelas->rombel->nama,
                'mapel' => $p->kelas->mapel->nama,
                'tingkat' => $p->kelas->rombel->tingkat,
                'jurusan' => $p->kelas->rombel->jurusan,
                'utama' => $p->id === $utama->id,
                'jumlahSiswa' => (int) ($jumlahSiswa[$p->kelas->rombelId] ?? 0),
                'selesai' => $selesai->count(),
                'perluKoreksi' => $semua->where('status', 'SELESAI')->count(),
                'rata' => $maks > 0 && $selesai->isNotEmpty() ? round($selesai->avg(fn (Percobaan $x) => $x->skorTotal() / $maks * 100), 1) : null,
            ];
        })->values()->all();
    }

    /**
     * Lembar-lembar Excel: "Rekap" lalu satu lembar per kelas, siap diberikan ke `XlsxExport::downloadSheets`.
     *
     * @param  Collection<string, Post>  $posts  kelas yang dipilih, sudah berurutan
     * @return array{nama: string, sheets: list<array<string, mixed>>}
     */
    public function lembar(Collection $posts, bool $perSoal, bool $termasukBelum): array
    {
        $judul = $posts->first()->judul ?? 'Ujian';
        $kelas = $posts->map(fn (Post $p) => $this->hitungKelas($p, $perSoal, $termasukBelum))->values();

        $sheets = [$this->lembarRekap($judul, $kelas)];
        foreach ($kelas as $k) {
            $sheets[] = $this->lembarKelas($judul, $k, $perSoal);
        }

        $nama = 'nilai-ujian-'.Str::slug($judul).($kelas->count() === 1 ? '-'.Str::slug($kelas[0]['rombel']) : '-'.$kelas->count().'-kelas').'.xlsx';

        return ['nama' => $nama, 'sheets' => $sheets];
    }

    /**
     * Hasil satu kelas: baris tiap siswa dan statistik.
     *
     * @return array<string, mixed>
     */
    private function hitungKelas(Post $post, bool $perSoal, bool $termasukBelum): array
    {
        $soal = $post->soal()->get();
        $total = round((float) $soal->sum('poin'), 2);
        $siswa = Siswa::where('rombelId', $post->kelas->rombelId)->orderBy('nama')->get();
        $percobaan = $this->muatPercobaan([$post->id], $perSoal)->keyBy('siswaId');

        $baris = [];
        $nilaiSelesai = [];
        // `selesai` hanya yang sudah dinilai penuh; `perlu` (esai belum dikoreksi) dihitung terpisah lalu digabung di hasil.
        $hitung = ['selesai' => 0, 'perlu' => 0, 'sedang' => 0, 'belum' => 0];

        foreach ($siswa as $s) {
            /** @var Percobaan|null $p */
            $p = $percobaan->get($s->id);
            $status = $p?->status;
            $hitung[match ($status) {
                null => 'belum',
                'BERJALAN' => 'sedang',
                'SELESAI' => 'perlu',
                default => 'selesai',
            }]++;

            if ($p === null && ! $termasukBelum) {
                continue;
            }

            $selesai = $p !== null && $status !== 'BERJALAN';
            $skor = $selesai ? $p->skorTotal() : null;
            $nilai = $selesai && $total > 0 ? round($skor / $total * 100, 2) : null;
            if ($nilai !== null) {
                $nilaiSelesai[] = $nilai;
            }

            $perSoalSkor = [];
            if ($perSoal) {
                $jawaban = $selesai ? $p->jawaban->keyBy('soalId') : collect();
                foreach ($soal as $item) {
                    // Esai yang belum dinilai (skor null) dikosongkan, bukan ditulis 0.
                    $perSoalSkor[] = $selesai ? ($jawaban->get($item->id)?->skor ?? ($jawaban->has($item->id) ? '' : 0)) : '';
                }
            }

            $baris[] = [
                'nis' => $s->nis ?? '',
                'nama' => $s->nama,
                'status' => $this->labelStatus($status),
                'skor' => $skor ?? '',
                'nilai' => $nilai ?? '',
                'soal' => $perSoalSkor,
                'mulai' => $p?->mulaiAt?->format('d/m/Y H:i') ?? '',
                'selesai' => $selesai && $p->selesaiAt ? $p->selesaiAt->format('d/m/Y H:i') : '',
                'lama' => $selesai && $p->selesaiAt ? (int) ceil($p->mulaiAt->diffInSeconds($p->selesaiAt) / 60) : '',
            ];
        }

        return [
            'rombel' => $post->kelas->rombel->nama,
            'mapel' => $post->kelas->mapel->nama,
            'tahun' => $post->kelas->tahunAjaran->label(),
            'total' => $total,
            'soal' => $soal,
            'baris' => $baris,
            'jumlahSiswa' => $siswa->count(),
            'selesai' => $hitung['selesai'] + $hitung['perlu'],
            'perlu' => $hitung['perlu'],
            'sedang' => $hitung['sedang'],
            'belum' => $hitung['belum'],
            'nilai' => $nilaiSelesai,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $kelas */
    private function lembarRekap(string $judul, Collection $kelas): array
    {
        $rows = [];
        foreach ($kelas as $i => $k) {
            $rows[] = [$i + 1, $k['rombel'], $k['mapel'], $k['jumlahSiswa'], $k['selesai'], $k['perlu'], $k['sedang'], $k['belum'], ...$this->statistik($k['nilai'])];
        }

        if ($kelas->count() > 1) {
            $semua = $kelas->flatMap(fn (array $k) => $k['nilai'])->all();
            $rows[] = ['', 'Semua kelas', '', $kelas->sum('jumlahSiswa'), $kelas->sum('selesai'), $kelas->sum('perlu'), $kelas->sum('sedang'), $kelas->sum('belum'), ...$this->statistik($semua)];
        }

        return [
            'nama' => 'Rekap',
            'judul' => "Rekap Nilai Ujian {$judul}",
            'subjudul' => $kelas->count().' kelas · nilai dalam skala 0-100, rata-rata dihitung dari siswa yang sudah selesai',
            'headers' => ['No', 'Kelas', 'Mata Pelajaran', 'Jumlah Siswa', 'Sudah Mengerjakan', 'Perlu Koreksi', 'Sedang Mengerjakan', 'Belum Mengerjakan', 'Rata-rata', 'Tertinggi', 'Terendah'],
            'rows' => $rows,
            'format' => [9 => '0.00', 10 => '0.00', 11 => '0.00'],
            'tebal' => $kelas->count() > 1 ? [count($rows) - 1] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $k
     * @return array<string, mixed>
     */
    private function lembarKelas(string $judul, array $k, bool $perSoal): array
    {
        $headers = ['No', 'NIS', 'Nama', 'Status', 'Skor (/'.$this->angka($k['total']).')', 'Nilai (0-100)'];
        $format = [5 => '0.00', 6 => '0.00'];
        if ($perSoal) {
            foreach ($k['soal'] as $i => $s) {
                $headers[] = ($i + 1).' (/'.$this->angka($s->poin).')';
                $format[7 + $i] = '0.00';
            }
        }
        array_push($headers, 'Mulai', 'Selesai', 'Lama (menit)');

        $rows = [];
        foreach ($k['baris'] as $i => $b) {
            $rows[] = [$i + 1, $b['nis'], $b['nama'], $b['status'], $b['skor'], $b['nilai'], ...$b['soal'], $b['mulai'], $b['selesai'], $b['lama']];
        }

        return [
            'nama' => $k['rombel'],
            'judul' => "Nilai Ujian {$judul} - {$k['rombel']}",
            'subjudul' => "{$k['mapel']} · Tahun ajaran {$k['tahun']}",
            'headers' => $headers,
            'rows' => $rows,
            'format' => $format,
            'teks' => [2],
        ];
    }

    /**
     * Rata-rata, tertinggi, dan terendah; kosong bila belum ada yang selesai.
     *
     * @param  list<float>  $nilai
     * @return array{0: float|string, 1: float|string, 2: float|string}
     */
    private function statistik(array $nilai): array
    {
        if ($nilai === []) {
            return ['', '', ''];
        }

        return [round(array_sum($nilai) / count($nilai), 2), max($nilai), min($nilai)];
    }

    /**
     * Percobaan ujian-ujian ini; yang waktunya sudah habis diselesaikan dulu supaya skornya ikut terhitung.
     *
     * @param  list<string>  $ids
     * @return Collection<int, Percobaan>
     */
    private function muatPercobaan(array $ids, bool $denganJawaban = false): Collection
    {
        $percobaan = Percobaan::whereIn('postId', $ids)->get();
        foreach ($percobaan as $p) {
            $this->ujian->finalisasiJikaKedaluwarsa($p);
        }

        // Jawaban dimuat sesudah penyelesaian otomatis, karena itulah yang membuat baris jawaban untuk tiap soal.
        return $denganJawaban ? $percobaan->load('jawaban') : $percobaan;
    }

    private function angka(float|int|string $nilai): string
    {
        return rtrim(rtrim(number_format((float) $nilai, 2, '.', ''), '0'), '.');
    }
}
