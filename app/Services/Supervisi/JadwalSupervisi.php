<?php

namespace App\Services\Supervisi;

use App\Models\Classroom\Akun;
use App\Models\Supervisi\Supervisi;
use App\Models\Supervisi\SupervisiSkor;
use App\Models\User;
use App\Notifications\Supervisi\SupervisiNotifikasi;
use Illuminate\Support\Facades\DB;

/** Membuat/mengubah jadwal supervisi dan menyiapkan 14 baris skor dari butir instrumen di config. */
class JadwalSupervisi
{
    /** @param  array<string, mixed>  $data  sudah divalidasi */
    public function buat(array $data, User $oleh): Supervisi
    {
        $supervisi = DB::transaction(function () use ($data, $oleh) {
            $supervisi = Supervisi::create($data + [
                'dibuatOlehId' => Akun::whereKey($oleh->id)->exists() ? $oleh->id : null,
                'status' => Supervisi::DIJADWALKAN,
            ]);
            $this->siapkanSkor($supervisi);

            return $supervisi;
        });

        $this->kabari($supervisi, $oleh, 'dijadwalkan');

        return $supervisi;
    }

    /** @param  array<string, mixed>  $data  sudah divalidasi */
    public function ubah(Supervisi $supervisi, array $data, User $oleh): Supervisi
    {
        $supervisi->fill($data);
        $berubah = $supervisi->isDirty(['tanggal', 'supervisorId', 'guruId']);
        $supervisi->save();

        if ($berubah) {
            $this->kabari($supervisi, $oleh, 'diubah');
        }

        return $supervisi;
    }

    /**
     * Butir instrumen dari config, urut sesuai dokumen. @return list<array{kategori: string, uraian: string}>
     */
    public static function butir(): array
    {
        $daftar = [];
        foreach (config('supervisi.instrumen') as $kategori => $uraian) {
            foreach ($uraian as $teks) {
                $daftar[] = ['kategori' => $kategori, 'uraian' => $teks];
            }
        }

        return $daftar;
    }

    /**
     * Samakan baris skor dengan butir di config, selama lembar masih DIJADWALKAN dan belum ada skor atau catatan butir
     * yang diisi. Begitu penilaian dimulai, butirnya dibekukan (salinan uraian) supaya isian tidak hilang.
     */
    public function siapkanSkor(Supervisi $supervisi): void
    {
        if (! $supervisi->bisaDiisi()) {
            return;
        }

        $ada = $supervisi->skor()->get();
        $sudahDiisi = $ada->contains(fn (SupervisiSkor $s) => $s->skor !== null || filled($s->catatan));
        if ($sudahDiisi) {
            return;
        }

        $butir = self::butir();
        $tandaAda = $ada->map(fn (SupervisiSkor $s) => $s->kategori.'|'.$s->uraian)->values()->all();
        $tandaButir = array_map(fn (array $b) => $b['kategori'].'|'.$b['uraian'], $butir);
        if ($tandaAda === $tandaButir) {
            return;
        }

        DB::transaction(function () use ($supervisi, $butir) {
            $supervisi->skor()->delete();
            foreach ($butir as $i => $b) {
                SupervisiSkor::create($b + ['supervisiId' => $supervisi->id, 'urutan' => $i + 1]);
            }
        });
        $supervisi->unsetRelation('skor');
    }

    private function kabari(Supervisi $supervisi, User $oleh, string $peristiwa): void
    {
        $supervisi->loadMissing(['guru', 'supervisor']);
        $tanggal = $supervisi->tanggal->locale('id')->translatedFormat('l, d F Y');
        $kata = $peristiwa === 'dijadwalkan' ? 'dijadwalkan' : 'diubah menjadi';

        $guru = User::find($supervisi->guruId);
        if ($guru && $guru->id !== $oleh->id) {
            $guru->notify(new SupervisiNotifikasi($supervisi, "Supervisi Anda {$kata} {$tanggal} oleh {$supervisi->supervisor?->namaTampil()}."));
        }

        $supervisor = User::find($supervisi->supervisorId);
        if ($supervisor && $supervisor->id !== $oleh->id) {
            $supervisor->notify(new SupervisiNotifikasi($supervisi, "Anda ditugaskan menyupervisi {$supervisi->guru?->namaTampil()} pada {$tanggal}."));
        }
    }
}
