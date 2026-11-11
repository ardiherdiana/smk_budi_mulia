<?php

namespace App\Services\Supervisi;

use App\Models\Supervisi\Supervisi;
use App\Models\Supervisi\SupervisiSkor;
use App\Models\User;
use App\Notifications\Supervisi\SupervisiNotifikasi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Isi lembar, hitung skor, selesaikan, dan buka kunci. JUMLAH SCORE = jumlah skor semua butir; TOTAL SCORE =
 * Jumlah Score / (jumlah butir x skor maks) x 100, yaitu Jumlah Score / 56 x 100 untuk 14 butir.
 */
class PenilaianSupervisi
{
    /** Baris 5-7 dokumen: Kelebihan Perencanaan, Hal yang perlu ditingkatkan, Rekomendasi Perbaikan. */
    public const TEKS = ['kelebihan', 'halDitingkatkan', 'rekomendasi'];

    /**
     * @param  array<string, mixed>  $teks  kolom TEKS yang dikirim
     * @param  list<array{id: string, skor: int|null, catatan: string|null}>  $skor
     */
    public function simpan(Supervisi $supervisi, array $teks, array $skor): void
    {
        $this->pastikanBisaDiisi($supervisi);

        DB::transaction(function () use ($supervisi, $teks, $skor) {
            $supervisi->fill(array_intersect_key($teks, array_flip(self::TEKS)))->save();

            $baris = $supervisi->skor()->get()->keyBy('id');
            foreach ($skor as $isian) {
                $row = $baris->get($isian['id']);
                if (! $row) {
                    throw ValidationException::withMessages([
                        'skor' => 'Butir penilaian berubah. Muat ulang halaman lalu isi kembali.',
                    ]);
                }
                $row->fill(['skor' => $isian['skor'] ?? null, 'catatan' => $isian['catatan'] ?? null]);
                if ($row->isDirty()) {
                    $row->save();
                }
            }
        });
        $supervisi->unsetRelation('skor');
    }

    public function selesaikan(Supervisi $supervisi, User $oleh): void
    {
        $this->pastikanBisaDiisi($supervisi);

        $skor = $supervisi->skor()->get();
        if ($skor->isEmpty()) {
            throw ValidationException::withMessages(['skor' => 'Lembar belum punya butir penilaian. Muat ulang halaman.']);
        }
        $kosong = $skor->whereNull('skor')->count();
        if ($kosong > 0) {
            throw ValidationException::withMessages(['skor' => "Masih ada {$kosong} butir yang belum diberi skor."]);
        }

        $supervisi->forceFill($this->hitung($skor) + [
            'status' => Supervisi::SELESAI,
            'selesaiAt' => now(),
        ])->save();

        $guru = User::find($supervisi->guruId);
        if ($guru && $guru->id !== $oleh->id) {
            $tanggal = $supervisi->tanggal->locale('id')->translatedFormat('d F Y');
            $guru->notify(new SupervisiNotifikasi($supervisi, "Hasil supervisi {$tanggal} sudah selesai dan bisa dibaca."));
        }
    }

    /** Kembalikan lembar SELESAI ke DIJADWALKAN untuk dikoreksi; Jumlah dan Total Score dikosongkan. */
    public function bukaKunci(Supervisi $supervisi): void
    {
        abort_unless($supervisi->selesai(), 409, 'Hanya lembar yang sudah selesai yang bisa dibuka kuncinya.');

        $supervisi->forceFill([
            'status' => Supervisi::DIJADWALKAN,
            'jumlahSkor' => null,
            'totalSkor' => null,
            'selesaiAt' => null,
        ])->save();
    }

    /**
     * @param  Collection<int, SupervisiSkor>  $skor
     * @return array{jumlahSkor: int|null, totalSkor: float|null}
     */
    public function hitung(Collection $skor): array
    {
        if ($skor->isEmpty()) {
            return ['jumlahSkor' => null, 'totalSkor' => null];
        }

        $jumlah = (int) $skor->sum('skor');

        return ['jumlahSkor' => $jumlah, 'totalSkor' => self::total($jumlah, $skor->count())];
    }

    /** TOTAL SCORE = Jumlah Score / (jumlah butir x skor maks) x 100. */
    public static function total(int $jumlah, int $jumlahButir): float
    {
        return round($jumlah / ($jumlahButir * (int) config('supervisi.skor_maks', 4)) * 100, 2);
    }

    private function pastikanBisaDiisi(Supervisi $supervisi): void
    {
        abort_unless($supervisi->bisaDiisi(), 409, $supervisi->selesai()
            ? 'Lembar sudah selesai dan terkunci.'
            : 'Supervisi ini sudah dibatalkan.');
    }
}
