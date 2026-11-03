<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalFile;
use App\Services\PortalFileStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalController extends Controller
{
    public function __construct(private PortalFileStorage $storage) {}

    public function index(Request $request)
    {
        $files = PortalFile::where('user_id', $request->user()->id)
            ->orderByDesc('uploaded_at')->orderByDesc('id')->get();

        return Inertia::render('portal-page', [
            'files' => $files->map(fn (PortalFile $f) => [
                'id' => $f->id,
                'nama_file' => $f->nama_file,
                'uploaded_at' => $f->uploaded_at?->toIso8601String(),
                'available' => $this->storage->safePath($f) !== null,
                'download_url' => route('portal.download', $f),
            ])->all(),
        ]);
    }

    public function download(PortalFile $file): StreamedResponse
    {
        Gate::authorize('download', $file);

        $path = $this->storage->safePath($file);
        abort_unless($path, 404, 'Berkas tidak ditemukan');

        return $this->storage->disk()->download($path, $file->nama_file, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
