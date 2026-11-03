<?php

namespace App\Services\Classroom\Docx;

use App\Services\Classroom\Impor\BacaanDokumen;
use App\Services\Classroom\Impor\DokumenTidakDapatDibaca;
use App\Services\Classroom\Impor\PembersihTeks;
use DOMDocument;
use DOMElement;
use DOMNode;
use ZipArchive;

/**
 * Membaca dokumen Word (.docx) menjadi aliran blok yang sama dengan pembaca PDF (`BacaanDokumen`), sehingga
 * `SoalParser` dipakai ulang. Berbeda dengan PDF, struktur Word eksplisit: paragraf, sel tabel, huruf daftar
 * (A., B., ...), dan gambar tersimpan sebagai data, jadi tidak ada tebakan dari jarak antarbaris.
 *
 * Pemetaan: paragraf -> satu baris; `w:br` -> baris baru; baris tabel -> sel digabung tab ("Kompetensi\tisi");
 * butir daftar -> diberi label dari `numbering.xml` ("A. ", "1. ", "• "); gambar -> blok gambar berisi byte-nya.
 */
class DocxSoalReader
{
    private const MAKS_DOKUMEN = 20 * 1024 * 1024;

    private const MAKS_TOTAL = 60 * 1024 * 1024;

    private const MAKS_GAMBAR_BYTE = 25 * 1024 * 1024;

    private const EMU_PER_PT = 12700;

    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const A = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const WP = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';

    private const M = 'http://schemas.openxmlformats.org/officeDocument/2006/math';

    private const MC = 'http://schemas.openxmlformats.org/markup-compatibility/2006';

    private const V = 'urn:schemas-microsoft-com:vml';

    private const REL = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private ZipArchive $zip;

    /** @var list<array<string, mixed>> */
    private array $blok = [];

    /** @var list<string> */
    private array $peringatan = [];

    /** @var array<string, string> rId => path berkas di dalam zip */
    private array $relasi = [];

    /** @var array<string, array<int, array{fmt: string, teks: string, mulai: int}>> numId => ilvl => tingkat */
    private array $daftar = [];

    /** @var array<string, array{basedOn: ?string, numId: ?string, ilvl: ?string}> */
    private array $gaya = [];

    /**
     * Penghitung butir daftar: numId => ilvl => nomor terakhir. Mengikuti Word: nomor lanjut selama `numId` sama; daftar
     * baru (termasuk "mulai lagi dari A") selalu punya `numId` baru.
     *
     * @var array<string, array<int, int>>
     */
    private array $hitung = [];

    /** @var array<string, string> cache byte gambar menurut path di dalam zip */
    private array $cacheGambar = [];

    private int $kedalamanTabel = 0;

    /**
     * Apakah berkas ini paket Word (zip dengan `word/document.xml`).
     */
    public static function adalahDocx(string $path): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }
        $ada = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        return $ada;
    }

    /**
     * @throws DokumenTidakDapatDibaca
     */
    public function baca(string $path): BacaanDokumen
    {
        $this->blok = [];
        $this->peringatan = [];
        $this->relasi = [];
        $this->daftar = [];
        $this->gaya = [];
        $this->hitung = [];
        $this->cacheGambar = [];
        $this->kedalamanTabel = 0;

        $this->zip = new ZipArchive;
        if ($this->zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new DokumenTidakDapatDibaca('Dokumen Word tidak dapat dibuka. Pastikan berkas tidak rusak, lalu simpan ulang sebagai .docx.');
        }

        try {
            $this->periksaZip();

            $dokumen = $this->muatXml('word/document.xml');
            $body = $dokumen?->getElementsByTagNameNS(self::W, 'body')->item(0);
            if (! $body instanceof DOMElement) {
                throw new DokumenTidakDapatDibaca('Isi dokumen Word tidak dapat dibaca.');
            }

            $this->bacaRelasi();
            $this->bacaPenomoran();
            $this->bacaGaya();
            $this->telusuri($body);
        } finally {
            $this->zip->close();
        }

        return new BacaanDokumen($this->blok, 1, array_values(array_unique($this->peringatan)));
    }

    // ---------------------------------------------------------------- paket zip dan XML

    private function periksaZip(): void
    {
        if ($this->zip->numFiles > 3000) {
            throw new DokumenTidakDapatDibaca('Dokumen Word ini tidak wajar (terlalu banyak komponen).');
        }

        $total = 0;
        for ($i = 0; $i < $this->zip->numFiles; $i++) {
            $total += (int) ($this->zip->statIndex($i)['size'] ?? 0);
        }
        if ($total > self::MAKS_TOTAL) {
            throw new DokumenTidakDapatDibaca('Dokumen Word terlalu besar setelah dibuka. Kecilkan ukuran gambar di dalamnya, lalu unggah lagi.');
        }

        $dokumen = $this->zip->statName('word/document.xml');
        if ($dokumen === false) {
            throw new DokumenTidakDapatDibaca('Berkas ini bukan dokumen Word (.docx).');
        }
        if ($dokumen['size'] > self::MAKS_DOKUMEN) {
            throw new DokumenTidakDapatDibaca('Teks dokumen Word terlalu besar.');
        }
    }

    private function muatXml(string $nama): ?DOMDocument
    {
        $isi = $this->zip->getFromName($nama);
        if ($isi === false || $isi === '') {
            return null;
        }
        // Dokumen Word tidak memerlukan DOCTYPE; menolaknya menutup jalan entitas eksternal dan ledakan entitas.
        if (stripos($isi, '<!DOCTYPE') !== false || stripos($isi, '<!ENTITY') !== false) {
            throw new DokumenTidakDapatDibaca('Dokumen Word ini tidak aman dibaca.');
        }

        $dom = new DOMDocument;
        $sebelumnya = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($isi, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($sebelumnya);

        if (! $ok) {
            throw new DokumenTidakDapatDibaca('Isi dokumen Word rusak dan tidak dapat dibaca.');
        }

        return $dom;
    }

    private function bacaRelasi(): void
    {
        $dom = $this->muatXml('word/_rels/document.xml.rels');
        if (! $dom) {
            return;
        }

        foreach ($dom->getElementsByTagNameNS(self::REL, 'Relationship') as $rel) {
            if (! $rel instanceof DOMElement || $rel->getAttribute('TargetMode') === 'External') {
                continue;
            }
            $this->relasi[$rel->getAttribute('Id')] = $this->normalisasiPath($rel->getAttribute('Target'));
        }
    }

    /** Target relasi relatif terhadap `word/`; bentuk mutlak ("/word/media/x.png") dan "../" dirapikan. */
    private function normalisasiPath(string $target): string
    {
        $bagian = str_starts_with($target, '/') ? [] : ['word'];
        foreach (explode('/', ltrim($target, '/')) as $b) {
            if ($b === '..') {
                array_pop($bagian);
            } elseif ($b !== '' && $b !== '.') {
                $bagian[] = $b;
            }
        }

        return implode('/', $bagian);
    }

    private function bacaPenomoran(): void
    {
        $dom = $this->muatXml('word/numbering.xml');
        if (! $dom) {
            return;
        }

        $abstrak = [];
        foreach ($dom->getElementsByTagNameNS(self::W, 'abstractNum') as $a) {
            $id = $a->getAttributeNS(self::W, 'abstractNumId');
            foreach ($this->anak($a, self::W, 'lvl') as $lvl) {
                $ilvl = (int) $lvl->getAttributeNS(self::W, 'ilvl');
                $abstrak[$id][$ilvl] = [
                    'fmt' => $this->nilaiW($this->pertama($lvl, self::W, 'numFmt')) ?? 'decimal',
                    'teks' => $this->nilaiW($this->pertama($lvl, self::W, 'lvlText')) ?? '%'.($ilvl + 1).'.',
                    'mulai' => (int) ($this->nilaiW($this->pertama($lvl, self::W, 'start')) ?? 1),
                ];
            }
        }

        foreach ($dom->getElementsByTagNameNS(self::W, 'num') as $num) {
            $numId = $num->getAttributeNS(self::W, 'numId');
            $tingkat = $abstrak[$this->nilaiW($this->pertama($num, self::W, 'abstractNumId')) ?? ''] ?? [];
            foreach ($this->anak($num, self::W, 'lvlOverride') as $o) {
                $mulai = $this->pertama($o, self::W, 'startOverride');
                $ilvl = (int) $o->getAttributeNS(self::W, 'ilvl');
                if ($mulai && isset($tingkat[$ilvl])) {
                    $tingkat[$ilvl]['mulai'] = (int) $mulai->getAttributeNS(self::W, 'val');
                }
            }
            $this->daftar[$numId] = $tingkat;
        }
    }

    private function bacaGaya(): void
    {
        $dom = $this->muatXml('word/styles.xml');
        if (! $dom) {
            return;
        }

        foreach ($dom->getElementsByTagNameNS(self::W, 'style') as $g) {
            $pPr = $this->pertama($g, self::W, 'pPr');
            $numPr = $pPr ? $this->pertama($pPr, self::W, 'numPr') : null;
            $this->gaya[$g->getAttributeNS(self::W, 'styleId')] = [
                'basedOn' => $this->nilaiW($this->pertama($g, self::W, 'basedOn')),
                'numId' => $this->nilaiW($numPr ? $this->pertama($numPr, self::W, 'numId') : null),
                'ilvl' => $this->nilaiW($numPr ? $this->pertama($numPr, self::W, 'ilvl') : null),
            ];
        }
    }

    // ---------------------------------------------------------------- penelusuran isi

    private function telusuri(DOMNode $induk): void
    {
        foreach ($induk->childNodes as $n) {
            if (! $n instanceof DOMElement || $n->namespaceURI !== self::W) {
                continue;
            }
            match ($n->localName) {
                'p' => $this->paragraf($n),
                'tbl' => $this->tabel($n),
                'sdt' => array_map(fn (DOMElement $isi) => $this->telusuri($isi), $this->anak($n, self::W, 'sdtContent')),
                default => null,
            };
        }
    }

    private function tabel(DOMElement $tbl): void
    {
        $this->kedalamanTabel++;

        foreach ($this->anak($tbl, self::W, 'tr') as $tr) {
            $barisSel = [];
            $gambarSel = [];

            foreach ($this->anak($tr, self::W, 'tc') as $tc) {
                $simpan = $this->blok;
                $this->blok = [];
                $this->telusuri($tc);
                $isi = $this->blok;
                $this->blok = $simpan;

                $teks = [];
                foreach ($isi as $b) {
                    if ($b['jenis'] === 'baris') {
                        $teks[] = $b['teks'];
                    } else {
                        $gambarSel[] = $b;
                    }
                }
                $barisSel[] = $teks;
            }

            // Baris tabel menjadi satu baris bertab; paragraf tambahan di dalam sel (mis. sel Kunci berbaris-baris) menyusul.
            $utama = PembersihTeks::bersihkan(implode("\t", array_map(fn (array $t) => $t[0] ?? '', $barisSel)));
            if ($utama !== '') {
                $this->tambahBaris($utama);
            }
            foreach ($barisSel as $teks) {
                foreach (array_slice($teks, 1) as $lanjutan) {
                    $this->tambahBaris($lanjutan);
                }
            }
            array_push($this->blok, ...$gambarSel);
        }

        $this->kedalamanTabel--;
    }

    private function paragraf(DOMElement $p): void
    {
        $label = $this->labelDaftar($p);

        $peristiwa = [];
        $this->kumpulkan($p, $peristiwa);

        $awalan = $label['teks'] ?? '';
        $bullet = $label['bullet'] ?? false;
        $buf = '';

        $tutup = function () use (&$buf, &$awalan): void {
            $teks = PembersihTeks::bersihkan($buf);
            $buf = '';
            if ($teks === '') {
                return;
            }
            $this->tambahBaris($awalan.$teks);
            $awalan = '';
        };

        foreach ($peristiwa as $e) {
            if ($e['t'] === 'teks') {
                $buf .= $e['isi'];
            } elseif ($e['t'] === 'br') {
                $tutup();
            } else {
                $tutup();
                // Butir berhuruf yang hanya berisi gambar tetap menandai pilihannya ("A." lalu gambar).
                if ($awalan !== '' && ! $bullet) {
                    $this->tambahBaris(rtrim($awalan));
                }
                $awalan = '';
                $this->blok[] = $e['gambar'];
            }
        }
        $tutup();

        // Butir kosong di dalam sel tabel (kolom huruf "A.") tetap dipertahankan sebagai penanda baris.
        if ($awalan !== '' && ! $bullet && $this->kedalamanTabel > 0) {
            $this->tambahBaris(rtrim($awalan));
        }
    }

    private function tambahBaris(string $teks): void
    {
        $this->blok[] = ['jenis' => 'baris', 'teks' => $teks, 'paragraf' => true, 'halaman' => 1];
    }

    /**
     * Susun peristiwa isi paragraf berurutan: potongan teks, pemisah baris, dan gambar.
     *
     * @param  list<array<string, mixed>>  $keluar
     */
    private function kumpulkan(DOMNode $induk, array &$keluar): void
    {
        foreach ($induk->childNodes as $n) {
            if (! $n instanceof DOMElement) {
                continue;
            }

            if ($n->namespaceURI === self::MC && $n->localName === 'AlternateContent') {
                // Isi alternatif ("Fallback") adalah salinan Choice untuk aplikasi lama; hanya Choice yang dibaca.
                $pilihan = $this->pertama($n, self::MC, 'Choice');
                if ($pilihan) {
                    $this->kumpulkan($pilihan, $keluar);
                }

                continue;
            }

            if ($n->namespaceURI === self::M && in_array($n->localName, ['oMath', 'oMathPara'], true)) {
                $this->peringatan[] = 'Rumus di dokumen dibaca sebagai teks biasa; periksa kembali.';
                $teks = '';
                foreach ($n->getElementsByTagNameNS(self::M, 't') as $t) {
                    $teks .= $t->textContent;
                }
                $keluar[] = ['t' => 'teks', 'isi' => $teks];

                continue;
            }

            if ($n->namespaceURI !== self::W) {
                continue;
            }

            switch ($n->localName) {
                case 'r':
                case 'ins':
                case 'hyperlink':
                case 'smartTag':
                case 'fldSimple':
                    $this->kumpulkan($n, $keluar);
                    break;
                case 'sdt':
                    foreach ($this->anak($n, self::W, 'sdtContent') as $isi) {
                        $this->kumpulkan($isi, $keluar);
                    }
                    break;
                case 't':
                    $keluar[] = ['t' => 'teks', 'isi' => $n->textContent];
                    break;
                case 'tab':
                    $keluar[] = ['t' => 'teks', 'isi' => ' '];
                    break;
                case 'noBreakHyphen':
                    $keluar[] = ['t' => 'teks', 'isi' => '-'];
                    break;
                case 'br':
                    if ($n->getAttributeNS(self::W, 'type') !== 'page') {
                        $keluar[] = ['t' => 'br'];
                    }
                    break;
                case 'cr':
                    $keluar[] = ['t' => 'br'];
                    break;
                case 'drawing':
                case 'pict':
                case 'object':
                    $this->kumpulkanGambar($n, $keluar);
                    break;
                    // `w:del` (teks yang dihapus pada riwayat perubahan) sengaja tidak dibaca.
            }
        }
    }

    // ---------------------------------------------------------------- gambar

    /**
     * @param  list<array<string, mixed>>  $keluar
     */
    private function kumpulkanGambar(DOMElement $el, array &$keluar): void
    {
        if ($el->getElementsByTagNameNS(self::W, 'txbxContent')->length > 0) {
            $this->peringatan[] = 'Kotak teks di dokumen tidak dibaca; pindahkan isinya ke paragraf biasa.';
        }

        $lebar = null;
        $tinggi = null;
        $ukuran = $el->getElementsByTagNameNS(self::WP, 'extent')->item(0);
        if ($ukuran instanceof DOMElement) {
            $lebar = (float) $ukuran->getAttribute('cx') / self::EMU_PER_PT;
            $tinggi = (float) $ukuran->getAttribute('cy') / self::EMU_PER_PT;
        }

        $idRelasi = [];
        foreach ($el->getElementsByTagNameNS(self::A, 'blip') as $blip) {
            $idRelasi[] = $blip->getAttributeNS(self::R, 'embed');
        }
        foreach ($el->getElementsByTagNameNS(self::V, 'imagedata') as $data) {
            $idRelasi[] = $data->getAttributeNS(self::R, 'id');
        }

        foreach (array_filter($idRelasi) as $rid) {
            $path = $this->relasi[$rid] ?? null;
            if ($path === null) {
                continue;
            }
            // Ikon kecil di tengah teks (panah, simbol) bukan gambar soal.
            if ($lebar !== null && $tinggi !== null && min($lebar, $tinggi) < 18.0) {
                continue;
            }

            $bytes = $this->byteGambar($path);
            $info = $bytes === null ? false : @getimagesizefromstring($bytes);
            $mime = $info === false ? null : image_type_to_mime_type($info[2]);
            if ($bytes === null || ! in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
                $this->peringatan[] = 'Ada gambar yang formatnya tidak didukung (mis. EMF/WMF); sisipkan ulang sebagai PNG atau JPG.';

                continue;
            }

            $keluar[] = ['t' => 'gambar', 'gambar' => [
                'jenis' => 'gambar',
                'id' => crc32($path),
                'bytes' => $bytes,
                'mime' => $mime,
                'halaman' => 1,
                'lebar' => $lebar ?? (float) $info[0],
                'tinggi' => $tinggi ?? (float) $info[1],
            ]];
        }
    }

    private function byteGambar(string $path): ?string
    {
        if (isset($this->cacheGambar[$path])) {
            return $this->cacheGambar[$path];
        }

        $stat = $this->zip->statName($path);
        if ($stat === false || $stat['size'] > self::MAKS_GAMBAR_BYTE) {
            return null;
        }
        $isi = $this->zip->getFromName($path);

        return $isi === false ? null : $this->cacheGambar[$path] = $isi;
    }

    // ---------------------------------------------------------------- daftar bernomor

    /**
     * Label butir daftar untuk sebuah paragraf (dan menaikkan penghitungnya), atau null bila bukan butir daftar.
     *
     * @return array{teks: string, bullet: bool}|null
     */
    private function labelDaftar(DOMElement $p): ?array
    {
        $pPr = $this->pertama($p, self::W, 'pPr');
        $numPr = $pPr ? $this->pertama($pPr, self::W, 'numPr') : null;

        $numId = $this->nilaiW($numPr ? $this->pertama($numPr, self::W, 'numId') : null);
        $ilvl = $this->nilaiW($numPr ? $this->pertama($numPr, self::W, 'ilvl') : null);
        if ($numId === null) {
            [$numId, $ilvlGaya] = $this->numPrDariGaya($this->nilaiW($pPr ? $this->pertama($pPr, self::W, 'pStyle') : null));
            $ilvl ??= $ilvlGaya;
        }

        if ($numId === null || $numId === '0') {
            return null;
        }
        $ilvl = (int) ($ilvl ?? 0);

        $tingkat = $this->daftar[$numId][$ilvl] ?? null;
        if ($tingkat === null || $tingkat['fmt'] === 'none') {
            return null;
        }
        if ($tingkat['fmt'] === 'bullet') {
            return ['teks' => '• ', 'bullet' => true];
        }

        $nomor = ($this->hitung[$numId][$ilvl] ?? $tingkat['mulai'] - 1) + 1;
        $this->hitung[$numId][$ilvl] = $nomor;
        foreach (array_keys($this->hitung[$numId]) as $dalam) {
            if ($dalam > $ilvl) {
                unset($this->hitung[$numId][$dalam]);
            }
        }

        $teks = preg_replace_callback('/%([1-9])/', function (array $m) use ($numId, $ilvl, $nomor) {
            $k = (int) $m[1] - 1;
            $tingkatK = $this->daftar[$numId][$k] ?? null;

            return $this->formatNomor($k === $ilvl ? $nomor : ($this->hitung[$numId][$k] ?? ($tingkatK['mulai'] ?? 1)), $tingkatK['fmt'] ?? 'decimal');
        }, $tingkat['teks']);

        return ['teks' => trim((string) $teks).' ', 'bullet' => false];
    }

    /**
     * Nomor daftar yang ditentukan gaya paragraf (mis. gaya "Daftar Alfabet"), menelusuri `basedOn`.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function numPrDariGaya(?string $id): array
    {
        for ($i = 0; $id !== null && $i < 6; $i++) {
            $g = $this->gaya[$id] ?? null;
            if ($g === null) {
                break;
            }
            if ($g['numId'] !== null) {
                return [$g['numId'], $g['ilvl']];
            }
            $id = $g['basedOn'];
        }

        return [null, null];
    }

    private function formatNomor(int $n, string $fmt): string
    {
        return match ($fmt) {
            'upperLetter' => $this->huruf($n),
            'lowerLetter' => strtolower($this->huruf($n)),
            'upperRoman' => $this->romawi($n),
            'lowerRoman' => strtolower($this->romawi($n)),
            default => (string) $n,
        };
    }

    /** A..Z, lalu AA, BB, ... seperti penomoran Word. */
    private function huruf(int $n): string
    {
        $n = max(1, $n);

        return str_repeat(chr(64 + ($n - 1) % 26 + 1), intdiv($n - 1, 26) + 1);
    }

    private function romawi(int $n): string
    {
        $hasil = '';
        foreach (['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1] as $lambang => $nilai) {
            while ($n >= $nilai) {
                $hasil .= $lambang;
                $n -= $nilai;
            }
        }

        return $hasil;
    }

    // ---------------------------------------------------------------- DOM

    /** @return list<DOMElement> */
    private function anak(DOMNode $induk, string $ns, string $nama): array
    {
        $hasil = [];
        foreach ($induk->childNodes as $n) {
            if ($n instanceof DOMElement && $n->namespaceURI === $ns && $n->localName === $nama) {
                $hasil[] = $n;
            }
        }

        return $hasil;
    }

    private function pertama(DOMNode $induk, string $ns, string $nama): ?DOMElement
    {
        return $this->anak($induk, $ns, $nama)[0] ?? null;
    }

    /** Nilai atribut `w:val` sebuah elemen Word, atau null bila elemen/atributnya tidak ada. */
    private function nilaiW(?DOMElement $el): ?string
    {
        return $el && $el->hasAttributeNS(self::W, 'val') ? $el->getAttributeNS(self::W, 'val') : null;
    }
}
