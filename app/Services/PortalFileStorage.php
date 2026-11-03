<?php

namespace App\Services;

use App\Models\PortalFile;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Berkas portal (PDF) disimpan di disk PRIVAT (storage/app/private/uploads) -
 * tidak pernah bisa diakses langsung dari web, hanya lewat download terotorisasi.
 * Nama file di disk acak; nama asli hanya disimpan di kolom `nama_file`.
 */
class PortalFileStorage
{
    private const DIRECTORY = 'uploads';

    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    public function store(UploadedFile $upload, ?int $userId): PortalFile
    {
        $name = now()->timestamp.'_'.Str::random(12).'.pdf';
        $this->disk()->putFileAs(self::DIRECTORY, $upload, $name);

        return PortalFile::create([
            'user_id' => $userId,
            'nama_file' => $this->cleanName($upload->getClientOriginalName()),
            'path_file' => self::DIRECTORY.'/'.$name,
        ]);
    }

    /** Path aman di dalam folder uploads (menolak `..`, path absolut, dan direktori lain). */
    public function safePath(PortalFile $file): ?string
    {
        $path = (string) $file->path_file;
        if (! preg_match('#^'.self::DIRECTORY.'/[A-Za-z0-9._ ()\-]+\.pdf$#i', $path) || str_contains($path, '..')) {
            return null;
        }

        return $this->disk()->exists($path) ? $path : null;
    }

    public function delete(PortalFile $file): void
    {
        if ($path = $this->safePath($file)) {
            $this->disk()->delete($path);
        }
        $file->delete();
    }

    /** Hapus semua berkas (baris + file fisik) milik satu akun sebelum akunnya dihapus. */
    public function deleteAllFor(User $user): void
    {
        PortalFile::where('user_id', $user->id)->get()->each(fn (PortalFile $f) => $this->delete($f));
    }

    private function cleanName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '', basename($name)) ?? 'berkas.pdf';

        return Str::limit(trim($name) ?: 'berkas.pdf', 250, '');
    }
}
