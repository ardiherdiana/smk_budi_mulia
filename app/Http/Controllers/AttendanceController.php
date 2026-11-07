<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Support\Geofence;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    public function schoolLocation()
    {
        return response()->json([
            'lat' => Geofence::SCHOOL_LAT,
            'lng' => Geofence::SCHOOL_LNG,
            'radiusMeters' => Geofence::SCHOOL_RADIUS_METERS,
        ]);
    }

    public function checkinWeb(Request $request)
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'min:-90', 'max:90'],
            'lng' => ['required', 'numeric', 'min:-180', 'max:180'],
        ]);

        return response()->json($this->attendance->checkinWeb($request->user()->id, (float) $validated['lat'], (float) $validated['lng']));
    }

    public function riwayat(Request $request)
    {
        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Akun ini bukan akun guru');

        $from = $request->query('from') ?: Carbon::now()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: Carbon::today()->toDateString();

        return Inertia::render('absensi/guru/riwayat-page', [
            'rows' => fn () => $this->attendance->myAttendance($guru->id, Carbon::parse($from), Carbon::parse($to)),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function detailMe(Request $request)
    {
        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Akun ini bukan akun guru');

        return response()->json($this->attendance->guruDetail($guru->id));
    }
}
