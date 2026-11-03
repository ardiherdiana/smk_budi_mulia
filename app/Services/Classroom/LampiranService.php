<?php

namespace App\Services\Classroom;

use App\Models\Classroom\Lampiran;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LampiranService
{
    private const DISK = 'local';

    /**
     * Aturan validasi untuk kiriman berkas + tautan; gabungkan ke aturan form yang memakainya.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'berkas' => ['nullable', 'array', 'max:'.config('classroom.max_lampiran')],
            'berkas.*' => ['file', 'mimes:'.implode(',', config('classroom.mimes')), 'max:'.config('classroom.max_upload_kb')],
            'tautan' => ['nullable', 'array', 'max:'.config('classroom.max_lampiran')],
            'tautan.*' => ['string', 'url:http,https', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'berkas.*.mimes' => 'Format berkas tidak didukung',
            'berkas.*.max' => 'Ukuran berkas maksimal '.intdiv((int) config('classroom.max_upload_kb'), 1024).' MB',
            'berkas.*.uploaded' => 'Berkas gagal diunggah, periksa ukurannya (maksimal '.intdiv((int) config('classroom.max_upload_kb'), 1024).' MB)',
            'tautan.*.url' => 'Tautan harus berupa alamat http atau https yang valid',
        ];
    }

    /**
     * Simpan berkas dan tautan sebagai lampiran milik satu post atau satu pengumpulan.
     *
     * @param  'postId'|'pengumpulanId'  $kolom
     * @param  list<UploadedFile>  $berkas
     * @param  list<string>  $tautan
     */
    public function simpan(string $kolom, string $ownerId, array $berkas = [], array $tautan = []): void
    {
        foreach ($berkas as $file) {
            $ekstensi = strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs('lampiran', Str::ulid().($ekstensi !== '' ? ".{$ekstensi}" : ''), self::DISK);

            Lampiran::create([
                $kolom => $ownerId,
                'tipe' => 'FILE',
                'nama' => Str::limit($file->getClientOriginalName(), 200, ''),
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'ukuran' => $file->getSize(),
            ]);
        }

        foreach ($tautan as $url) {
            Lampiran::create([
                $kolom => $ownerId,
                'tipe' => 'LINK',
                'nama' => Str::limit(parse_url($url, PHP_URL_HOST) ?: $url, 200, ''),
                'url' => $url,
            ]);
        }
    }

    /**
     * Salin satu lampiran ke pemilik lain. Berkas fisik digandakan (bukan dibagi) supaya menghapus di satu tempat
     * tidak memutus lampiran di tempat lain. Path berkas baru dikembalikan agar pemanggil bisa membersihkannya bila gagal.
     */
    public function salin(Lampiran $sumber, string $kolom, string $ownerId): ?string
    {
        $path = null;

        if ($sumber->tipe === 'FILE') {
            abort_unless($sumber->path && Storage::disk(self::DISK)->exists($sumber->path), 422, 'Berkas lampiran tidak ditemukan untuk disalin');

            $ekstensi = strtolower(pathinfo($sumber->path, PATHINFO_EXTENSION));
            $path = 'lampiran/'.Str::ulid().($ekstensi !== '' ? ".{$ekstensi}" : '');
            Storage::disk(self::DISK)->copy($sumber->path, $path);
        }

        Lampiran::create([
            $kolom => $ownerId,
            'tipe' => $sumber->tipe,
            'nama' => $sumber->nama,
            'path' => $path,
            'url' => $sumber->url,
            'mime' => $sumber->mime,
            'ukuran' => $sumber->ukuran,
        ]);

        return $path;
    }

    public function hapus(Lampiran $lampiran): void
    {
        if ($lampiran->tipe === 'FILE' && $lampiran->path) {
            Storage::disk(self::DISK)->delete($lampiran->path);
        }
        $lampiran->delete();
    }

    /**
     * Hapus berkas fisik berdasarkan path, untuk membersihkan berkas yatim saat penyimpanan gagal di tengah jalan.
     *
     * @param  list<string>  $daftarPath
     */
    public function hapusPath(array $daftarPath): void
    {
        foreach ($daftarPath as $path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /**
     * Hapus berkas fisik beberapa lampiran sekaligus (barisnya ikut terhapus lewat cascade pemiliknya).
     *
     * @param  iterable<Lampiran>  $daftar
     */
    public function hapusBerkasFisik(iterable $daftar): void
    {
        foreach ($daftar as $lampiran) {
            if ($lampiran->tipe === 'FILE' && $lampiran->path) {
                Storage::disk(self::DISK)->delete($lampiran->path);
            }
        }
    }

    public function kirim(Lampiran $lampiran): StreamedResponse
    {
        abort_unless($lampiran->tipe === 'FILE' && $lampiran->path && Storage::disk(self::DISK)->exists($lampiran->path), 404, 'Berkas tidak ditemukan');

        // Gambar dan PDF tampil di tab; lainnya diunduh. nosniff mencegah browser menafsirkan ulang tipenya.
        $inline = $lampiran->mime && (str_starts_with($lampiran->mime, 'image/') || $lampiran->mime === 'application/pdf');

        return Storage::disk(self::DISK)->response($lampiran->path, $lampiran->nama, [
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.addslashes($lampiran->nama).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
