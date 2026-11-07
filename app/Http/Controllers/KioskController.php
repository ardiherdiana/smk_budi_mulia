<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KioskController extends Controller
{
    public function __construct(
        private AttendanceService $attendance,
        private SettingsService $settings,
    ) {}

    public function index()
    {
        return Inertia::render('absensi/kiosk-page', [
            'settings' => $this->settings->getSettings(),
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'qrToken' => ['required', 'string', 'min:1'],
        ]);

        return response()->json($this->attendance->checkinByQrToken($validated['qrToken']));
    }
}
