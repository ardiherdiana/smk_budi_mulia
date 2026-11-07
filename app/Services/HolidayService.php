<?php

namespace App\Services;

use App\Models\Holiday;
use Illuminate\Support\Carbon;

class HolidayService
{
    public function listHolidays(?Carbon $from = null, ?Carbon $to = null)
    {
        return Holiday::query()
            ->when($from && $to, fn ($q) => $q->whereBetween('tanggal', [$from->toDateString(), $to->toDateString()]))
            ->orderBy('tanggal')
            ->get();
    }

    public function createHoliday(Carbon $tanggal, string $keterangan): Holiday
    {
        $tanggal = $tanggal->copy()->startOfDay();

        $existing = Holiday::whereDate('tanggal', $tanggal->toDateString())->first();
        if ($existing) {
            abort(409, 'Sudah ada hari libur di tanggal ini');
        }

        return Holiday::create([
            'tanggal' => $tanggal,
            'keterangan' => $keterangan,
        ]);
    }

    public function deleteHoliday(string $id): void
    {
        $holiday = Holiday::find($id);
        if (! $holiday) {
            abort(404, 'Hari libur tidak ditemukan');
        }
        $holiday->delete();
    }
}
