<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Services\HolidayService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class HolidayController extends Controller
{
    public function __construct(private HolidayService $holidays) {}

    public function index()
    {
        return Inertia::render('absensi/admin/libur-page', [
            'list' => fn () => $this->holidays->listHolidays(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tanggal' => ['required', 'string', 'min:1'],
            'keterangan' => ['required', 'string', 'min:1'],
        ]);

        $this->holidays->createHoliday(Carbon::parse($validated['tanggal']), $validated['keterangan']);

        return back()->with('toast', ['type' => 'success', 'message' => 'Hari libur berhasil ditambahkan']);
    }

    public function destroy(Holiday $holiday)
    {
        $this->holidays->deleteHoliday($holiday->id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Hari libur berhasil dihapus']);
    }
}
