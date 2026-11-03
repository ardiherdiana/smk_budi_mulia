<?php

namespace App\Services\Classroom\Impor;

/**
 * Isi PDF menurut alur baca: baris teks dan gambar berselang-seling.
 *
 * Baris: `['jenis' => 'baris', 'teks' => string, 'paragraf' => bool, 'halaman' => int]`; `paragraf` true bila baris
 * membuka paragraf baru. Gambar: `['jenis' => 'gambar', 'id' => int, 'halaman' => int, 'objek' => Image]`.
 */
final class BacaanDokumen
{
    /**
     * @param  list<array<string, mixed>>  $blok
     * @param  list<string>  $peringatan
     */
    public function __construct(
        public readonly array $blok,
        public readonly int $jumlahHalaman,
        public readonly array $peringatan = [],
    ) {}

    public function adaTeks(): bool
    {
        foreach ($this->blok as $b) {
            if ($b['jenis'] === 'baris' && trim($b['teks']) !== '') {
                return true;
            }
        }

        return false;
    }
}
