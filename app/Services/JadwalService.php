<?php

namespace App\Services;

use App\Models\JadwalHari;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class JadwalService
{
    const DISPLAY_ORDER = [1, 2, 3, 4, 5, 6, 0];

    private static function defaultAktifForHari(int $hari): bool
    {
        return $hari !== 0 && $hari !== 6;
    }

    private static function ensureJadwalHari(int $hari): JadwalHari
    {
        return JadwalHari::firstOrCreate(
            ['hari' => $hari],
            [
                'aktif' => self::defaultAktifForHari($hari),
                'jamMulaiAbsen' => '07:00',
                'jamMasuk' => '07:00',
                'batasTelat' => '07:15',
                'jamPulang' => '15:30',
            ]
        );
    }

    /** @return Collection<int, JadwalHari> in Senin..Minggu order */
    public function listJadwal(): Collection
    {
        $byHari = JadwalHari::all()->keyBy('hari');

        return collect(self::DISPLAY_ORDER)->map(
            fn (int $hari) => $byHari->get($hari) ?? self::ensureJadwalHari($hari)
        );
    }

    public function getJadwalHari(int $hari): JadwalHari
    {
        return JadwalHari::find($hari) ?? self::ensureJadwalHari($hari);
    }

    public function getJadwalForDate(Carbon $date): JadwalHari
    {
        return $this->getJadwalHari($date->dayOfWeek);
    }

    /** @return Collection<int, JadwalHari> keyed by `hari` */
    public function getJadwalMap(): Collection
    {
        $byHari = JadwalHari::all()->keyBy('hari');

        foreach (self::DISPLAY_ORDER as $hari) {
            if (! $byHari->has($hari)) {
                $byHari->put($hari, self::ensureJadwalHari($hari));
            }
        }

        return $byHari;
    }

    public function updateJadwal(int $hari, array $input): JadwalHari
    {
        $jadwal = self::ensureJadwalHari($hari);
        $jadwal->update($input);

        return $jadwal;
    }
}
