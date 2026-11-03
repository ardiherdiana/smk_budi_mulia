<?php

namespace App\Services\Classroom;

use App\Models\Classroom\Soal;
use App\Services\MediaService;

/**
 * Gambar pada soal. Berkas ada di media `soal/` (permanen) atau `soal-tmp/` (sementara: hasil impor dokumen atau unggahan
 * yang belum disimpan bersama soalnya). Satu berkas boleh dipakai banyak soal (wacana bersama, salinan ke ruang kelas
 * lain), jadi berkas hanya dihapus bila tidak ada soal yang masih merujuknya.
 */
class SoalGambarService
{
    public const FINAL = 'soal';

    public const SEMENTARA = 'soal-tmp';

    public function __construct(private MediaService $media) {}

    /** Path yang boleh muncul di kolom gambar soal: media sah di folder `soal/` atau `soal-tmp/`. */
    public function sah(?string $path): bool
    {
        return $this->media->isPath($path)
            && (str_starts_with($path, self::FINAL.'/') || str_starts_with($path, self::SEMENTARA.'/'));
    }

    /** Berkas sementara hanya valid selama masih ada di disk. */
    public function ada(string $path): bool
    {
        return $this->media->disk()->exists($path);
    }

    /** @return array{path: string, url: string}|null untuk editor guru (path dikirim balik saat menyimpan) */
    public function item(?string $path): ?array
    {
        $url = $this->media->url($path);

        return $url === null ? null : ['path' => $path, 'url' => $url];
    }

    /**
     * @param  list<string>|null  $paths
     * @return list<array{path: string, url: string}>
     */
    public function daftarItem(?array $paths): array
    {
        return array_values(array_filter(array_map(fn ($p) => $this->item($p), $paths ?? [])));
    }

    /**
     * Sejajar dengan pilihan: satu entri per pilihan, null bila pilihan tanpa gambar.
     *
     * @param  list<string|null>|null  $paths
     * @return list<array{path: string, url: string}|null>
     */
    public function itemSejajar(?array $paths, int $jumlah): array
    {
        $hasil = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $hasil[] = $this->item($paths[$i] ?? null);
        }

        return $hasil;
    }

    /**
     * URL saja, untuk siswa.
     *
     * @param  list<string>|null  $paths
     * @return list<string>
     */
    public function daftarUrl(?array $paths): array
    {
        return array_map(fn (array $i) => $i['url'], $this->daftarItem($paths));
    }

    /**
     * @param  list<string|null>|null  $paths
     * @return list<string|null>
     */
    public function urlSejajar(?array $paths, int $jumlah): array
    {
        return array_map(fn (?array $i) => $i['url'] ?? null, $this->itemSejajar($paths, $jumlah));
    }

    /**
     * Gambar soal untuk ditampilkan (siswa, koreksi, ringkasan guru): URL saja, tanpa path penyimpanan.
     *
     * @return array{gambar: list<string>, opsiGambar: list<string|null>}
     */
    public function tampil(Soal $soal): array
    {
        return [
            'gambar' => $this->daftarUrl($soal->gambar),
            'opsiGambar' => $this->urlSejajar($soal->opsiGambar, count($soal->opsi ?? [])),
        ];
    }

    /**
     * Gambar soal untuk editor guru: path (dikirim balik saat menyimpan) dan URL (pratinjau).
     *
     * @return array{gambar: list<array{path: string, url: string}>, opsiGambar: list<array{path: string, url: string}|null>}
     */
    public function untukEditor(Soal $soal): array
    {
        return [
            'gambar' => $this->daftarItem($soal->gambar),
            'opsiGambar' => $this->itemSejajar($soal->opsiGambar, count($soal->opsi ?? [])),
        ];
    }

    /**
     * Path permanen yang akan dipakai bila berkas sementara dipindahkan: `soal-tmp/x.png` menjadi `soal/x.png`.
     * Hanya path sementara yang ikut; dipakai untuk menulis path final ke database sebelum berkasnya dipindahkan.
     *
     * @param  list<string>  $paths
     * @return array<string, string>
     */
    public function petaSementara(array $paths): array
    {
        $peta = [];
        foreach (array_unique($paths) as $path) {
            if (str_starts_with($path, self::SEMENTARA.'/') && $this->media->isPath($path)) {
                $peta[$path] = self::FINAL.'/'.basename($path);
            }
        }

        return $peta;
    }

    /**
     * Pindahkan berkas sementara ke folder permanen sesuai peta dari `petaSementara`.
     *
     * @param  array<string, string>  $peta
     */
    public function pindahkanSementara(array $peta): void
    {
        $disk = $this->media->disk();
        foreach ($peta as $lama => $baru) {
            if ($disk->exists($lama)) {
                $disk->move($lama, $baru);
            }
        }
    }

    /**
     * Hapus berkas permanen yang sudah tidak dirujuk soal mana pun.
     *
     * @param  iterable<string|null>  $paths
     */
    public function lepas(iterable $paths): void
    {
        foreach ($paths as $path) {
            if ($path === null || ! str_starts_with($path, self::FINAL.'/') || ! $this->media->isPath($path)) {
                continue;
            }
            if (! $this->dipakai($path)) {
                $this->media->delete($path);
            }
        }
    }

    /** Hapus berkas sementara yang lebih tua dari `$jam` (impor yang tidak pernah disimpan). */
    public function sapuSementara(int $jam = 24): void
    {
        $disk = $this->media->disk();
        $batas = time() - $jam * 3600;

        foreach ($disk->files(self::SEMENTARA) as $berkas) {
            if ($disk->lastModified($berkas) < $batas) {
                $disk->delete($berkas);
            }
        }
    }

    public function dipakai(string $path): bool
    {
        return Soal::query()
            ->whereRaw('JSON_SEARCH(gambar, ?, ?) IS NOT NULL', ['one', $path])
            ->orWhereRaw('JSON_SEARCH(opsiGambar, ?, ?) IS NOT NULL', ['one', $path])
            ->exists();
    }
}
