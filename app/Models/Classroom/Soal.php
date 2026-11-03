<?php

namespace App\Models\Classroom;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bentuk soal mengikuti format TKA. Format `kunci` dan `jawaban` (jawaban siswa) sama untuk tiap bentuk:
 * PG "2" (indeks pilihan), PGK_MCMA "0,2,4" (indeks yang dipilih), PGK_KATEGORI "1,0,1" (1 = Benar, 0 = Salah,
 * urut sesuai pernyataan; pernyataan yang belum dijawab kosong, mis. "1,,0").
 */
#[Fillable(['postId', 'urut', 'tipe', 'teks', 'gambar', 'kompetensi', 'subKompetensi', 'poin', 'opsi', 'opsiGambar', 'kunci'])]
class Soal extends Model
{
    use HasUlid;

    public const TIPE = ['PG', 'PGK_KATEGORI', 'PGK_MCMA', 'ESSAY'];

    protected $table = 'soal';

    protected function casts(): array
    {
        return [
            'poin' => 'float',
            'opsi' => 'array',
            'gambar' => 'array',
            'opsiGambar' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'postId');
    }

    /** Semua path gambar milik soal ini (teks soal dan pilihan), tanpa duplikat. */
    public function semuaGambar(): array
    {
        return array_values(array_unique(array_filter([...($this->gambar ?? []), ...($this->opsiGambar ?? [])])));
    }

    /** Skor otomatis untuk satu jawaban; PGK dinilai parsial dan tidak pernah di bawah 0. */
    public function skorUntuk(?string $jawaban): float
    {
        $jawaban = trim((string) $jawaban);
        $kunci = $this->kunci === null || $this->kunci === '' ? [] : explode(',', $this->kunci);
        if ($jawaban === '' || $kunci === []) {
            return 0.0;
        }

        $nilai = match ($this->tipe) {
            'PG' => $jawaban === (string) $this->kunci ? 1.0 : 0.0,
            'PGK_MCMA' => $this->nilaiMcma($kunci, array_filter(explode(',', $jawaban), fn ($j) => $j !== '')),
            'PGK_KATEGORI' => $this->nilaiKategori($kunci, explode(',', $jawaban)),
            default => 0.0,
        };

        return round($this->poin * $nilai, 2);
    }

    /** Pilihan benar dikurangi pilihan salah, dibagi jumlah kunci. */
    private function nilaiMcma(array $kunci, array $dipilih): float
    {
        $dipilih = array_unique($dipilih);
        $benar = count(array_intersect($dipilih, $kunci));

        return max(0, $benar - (count($dipilih) - $benar)) / count($kunci);
    }

    /** Pernyataan yang jawabannya sama dengan kunci, dibagi jumlah pernyataan. */
    private function nilaiKategori(array $kunci, array $jawab): float
    {
        $cocok = 0;
        foreach ($kunci as $i => $k) {
            $cocok += (($jawab[$i] ?? '') === $k) ? 1 : 0;
        }

        return $cocok / count($kunci);
    }
}
