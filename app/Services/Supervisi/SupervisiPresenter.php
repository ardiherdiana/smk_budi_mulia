<?php

namespace App\Services\Supervisi;

use App\Models\Classroom\Akun;
use App\Models\Classroom\TahunAjaran;
use App\Models\Supervisi\Supervisi;
use App\Models\Supervisi\SupervisiSkor;

/** Supervisi -> array untuk props Inertia. Penilaian hanya disertakan bila pemanggil boleh melihatnya. */
class SupervisiPresenter
{
    public const RELASI = ['guru:id,name,username,role', 'supervisor:id,name,username,role', 'mapel:id,nama', 'rombel:id,nama', 'tahunAjaran'];

    /** @return array<string, mixed> */
    public static function ringkas(Supervisi $s): array
    {
        return [
            'id' => $s->id,
            'tanggal' => $s->tanggal->toDateString(),
            'status' => $s->status,
            'tahunAjaranId' => $s->tahunAjaranId,
            'tahunAjaran' => $s->tahunAjaran?->label(),
            'guru' => self::orang($s->guru),
            'supervisor' => self::orang($s->supervisor),
            'mapelId' => $s->mapelId,
            'mapel' => $s->mapel?->nama,
            'rombelId' => $s->rombelId,
            'rombel' => $s->rombel?->nama,
            'jumlahSkor' => $s->selesai() ? $s->jumlahSkor : null,
            'totalSkor' => $s->selesai() ? $s->totalSkor : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function lembar(Supervisi $s, bool $denganPenilaian): array
    {
        $data = self::ringkas($s) + [
            'selesaiAt' => $s->selesaiAt?->toIso8601String(),
            'penilaian' => null,
        ];

        if ($denganPenilaian) {
            $data['penilaian'] = [
                'kelebihan' => $s->kelebihan,
                'halDitingkatkan' => $s->halDitingkatkan,
                'rekomendasi' => $s->rekomendasi,
                'skor' => $s->skor->map(fn (SupervisiSkor $k) => [
                    'id' => $k->id,
                    'kategori' => $k->kategori,
                    'uraian' => $k->uraian,
                    'skor' => $k->skor,
                    'catatan' => $k->catatan,
                ])->values()->all(),
            ];
        }

        return $data;
    }

    /** @return array{id: int, nama: string}|null */
    public static function orang(?Akun $akun): ?array
    {
        return $akun ? ['id' => $akun->id, 'nama' => $akun->namaTampil()] : null;
    }

    /** @return list<array{id: string, label: string, aktif: bool}> */
    public static function daftarTahunAjaran(): array
    {
        return TahunAjaran::orderByDesc('nama')->orderByDesc('semester')->get()
            ->map(fn (TahunAjaran $t) => ['id' => $t->id, 'label' => $t->label(), 'aktif' => $t->aktif])
            ->values()->all();
    }

    /** Tahun ajaran dari query `tahunAjaranId`, jatuh ke yang aktif, lalu ke yang terbaru. */
    public static function tahunAjaranTerpilih(?string $id): ?TahunAjaran
    {
        return ($id ? TahunAjaran::find($id) : null)
            ?? TahunAjaran::aktif()
            ?? TahunAjaran::orderByDesc('nama')->orderByDesc('semester')->first();
    }

    /** @return array<string, string> label status untuk UI */
    public static function labelStatus(): array
    {
        return [
            Supervisi::DIJADWALKAN => 'Dijadwalkan',
            Supervisi::SELESAI => 'Selesai',
            Supervisi::DIBATALKAN => 'Dibatalkan',
        ];
    }
}
