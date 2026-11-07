<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Services\GuruService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QrController extends Controller
{
    public function __construct(private GuruService $guruService) {}

    public function index(Request $request)
    {
        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Akun ini bukan akun guru');

        return Inertia::render('absensi/guru/qr-page', ['guru' => $this->guruService->find($guru->id)]);
    }
}
