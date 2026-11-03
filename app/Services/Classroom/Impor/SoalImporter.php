<?php

namespace App\Services\Classroom\Impor;

use App\Services\Classroom\Docx\DocxSoalReader;
use App\Services\Classroom\Pdf\PdfGambarDecoder;
use App\Services\Classroom\Pdf\PdfSoalReader;
use App\Services\Classroom\SoalGambarService;
use App\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Impor soal dari dokumen Word (.docx) atau PDF: baca, pecah jadi draf soal, lalu simpan gambar yang dipakai ke media
 * sementara (`soal-tmp/`). Tidak menulis tabel `soal` dan tidak menyimpan dokumennya; guru memeriksa draf di editor
 * lalu menyimpannya.
 */
class SoalImporter
{
    /** Batas jumlah berkas gambar per impor supaya satu dokumen tidak memenuhi disk. */
    private const MAKS_GAMBAR = 120;

    public function __construct(
        private PdfSoalReader $pembacaPdf,
        private DocxSoalReader $pembacaDocx,
        private SoalParser $parser,
        private PdfGambarDecoder $dekoder,
        private MediaService $media,
        private SoalGambarService $gambar,
    ) {}

    /**
     * @return array{soal: list<array<string, mixed>>, peringatan: list<string>}
     *
     * @throws DokumenTidakDapatDibaca
     */
    public function impor(string $path): array
    {
        $this->gambar->sapuSementara();

        $jenis = $this->jenis($path);
        $bacaan = $jenis === 'pdf' ? $this->pembacaPdf->baca($path) : $this->pembacaDocx->baca($path);
        if (! $bacaan->adaTeks()) {
            throw new DokumenTidakDapatDibaca(
                $jenis === 'pdf'
                    ? 'PDF ini tidak berisi teks yang bisa dibaca (kemungkinan hasil scan atau foto). Unggah PDF hasil ketik dari Word atau Google Docs, atau langsung .docx.'
                    : 'Dokumen Word ini tidak berisi teks.'
            );
        }

        $hasil = $this->parser->parse($bacaan);
        $peringatan = [...$bacaan->peringatan, ...$hasil['peringatan']];

        /** @var array<int, array<string, mixed>> $sumber */
        $sumber = [];
        foreach ($bacaan->blok as $b) {
            if ($b['jenis'] === 'gambar') {
                $sumber[$b['id']] = $b;
            }
        }

        $tersimpan = [];
        $perIsi = [];
        $jumlahBerkas = 0;

        $simpan = function (int $id, int $nomor) use (&$tersimpan, &$perIsi, &$jumlahBerkas, &$peringatan, $sumber): ?array {
            if (array_key_exists($id, $tersimpan)) {
                return $tersimpan[$id];
            }

            // Gambar Word sudah berupa berkas gambar; gambar PDF harus dibangun dari aliran pikselnya lebih dulu.
            $blok = $sumber[$id];
            $dekode = isset($blok['bytes']) ? ['bytes' => $blok['bytes'], 'mime' => $blok['mime']] : $this->dekoder->dekode($blok['objek']);
            if (isset($dekode['galat'])) {
                $peringatan[] = "Soal {$nomor}: satu gambar dilewati ({$dekode['galat']}), tambahkan manual di editor.";

                return $tersimpan[$id] = null;
            }

            // Gambar yang sama (isi identik) dipakai bersama, termasuk saat dokumen menyimpannya sebagai objek terpisah.
            $sidik = sha1($dekode['bytes']);
            if (isset($perIsi[$sidik])) {
                return $tersimpan[$id] = $perIsi[$sidik];
            }
            if ($jumlahBerkas >= self::MAKS_GAMBAR) {
                $peringatan[] = 'Terlalu banyak gambar di dokumen; sebagian dilewati, tambahkan manual di editor.';

                return $tersimpan[$id] = null;
            }

            $item = $this->simpanSementara($dekode['bytes'], $dekode['mime']);
            if ($item === null) {
                $peringatan[] = "Soal {$nomor}: satu gambar dilewati (gambar tidak valid), tambahkan manual di editor.";
            } else {
                $jumlahBerkas++;
            }

            return $tersimpan[$id] = $perIsi[$sidik] = $item;
        };

        $soal = [];
        foreach ($hasil['soal'] as $draf) {
            $nomor = $draf['nomor'];
            $draf['gambar'] = array_values(array_filter(array_map(fn (int $id) => $simpan($id, $nomor), $draf['gambar'])));
            $draf['opsiGambar'] = array_map(fn (?int $id) => $id === null ? null : $simpan($id, $nomor), $draf['opsiGambar']);
            $soal[] = $draf;
        }

        return ['soal' => $soal, 'peringatan' => array_slice(array_values(array_unique($peringatan)), 0, 40)];
    }

    /**
     * Jenis berkas dari byte awalnya (bukan nama atau ekstensi, yang bisa dipalsukan).
     *
     * @return 'pdf'|'docx'
     *
     * @throws DokumenTidakDapatDibaca
     */
    private function jenis(string $path): string
    {
        $kepala = (string) file_get_contents($path, false, null, 0, 1024);

        if (str_contains($kepala, '%PDF-')) {
            return 'pdf';
        }
        if (str_starts_with($kepala, "PK\x03\x04")) {
            if (DocxSoalReader::adalahDocx($path)) {
                return 'docx';
            }

            throw new DokumenTidakDapatDibaca('Berkas ini bukan dokumen Word (.docx). Unggah .docx atau PDF.');
        }
        if (str_starts_with($kepala, "\xD0\xCF\x11\xE0")) {
            throw new DokumenTidakDapatDibaca('Format .doc lama tidak didukung. Buka di Word, pilih Simpan Sebagai, lalu simpan sebagai .docx.');
        }

        throw new DokumenTidakDapatDibaca('Format berkas tidak dikenali. Unggah .docx atau PDF.');
    }

    /**
     * Simpan satu gambar lewat MediaService (finfo, batas piksel, buang metadata, ubah ukuran) sebagai berkas sementara.
     *
     * @return array{path: string, url: string}|null
     */
    private function simpanSementara(string $bytes, string $mime): ?array
    {
        $ekstensi = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? 'bin';
        $tmp = tempnam(sys_get_temp_dir(), 'soal');
        if ($tmp === false) {
            return null;
        }

        try {
            file_put_contents($tmp, $bytes);
            $path = $this->media->storeImage(new UploadedFile($tmp, "gambar.{$ekstensi}", $mime, null, true), SoalGambarService::SEMENTARA, 'gambar');

            return $this->gambar->item($path);
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }
}
