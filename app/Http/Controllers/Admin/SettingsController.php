<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\JadwalService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function __construct(
        private SettingsService $settings,
        private JadwalService $jadwal,
    ) {}

    public function edit()
    {
        return Inertia::render('absensi/admin/pengaturan-page', [
            'settings' => fn () => $this->settings->getSettings(),
            'jadwal' => fn () => $this->jadwal->listJadwal()->values(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'namaSekolah' => ['sometimes', 'string', 'min:1'],
        ]);

        $this->settings->updateSettings($validated);

        return back()->with('toast', ['type' => 'success', 'message' => 'Pengaturan berhasil disimpan']);
    }
}
