<?php

namespace Tests\Support;

use ZipArchive;

/**
 * Pembangun dokumen Word (.docx) minimal untuk tes impor soal: paragraf, butir daftar bernomor (huruf/angka/bullet),
 * tabel, gambar sebaris, dan XML mentah untuk kasus khusus. Dibuat dalam memori sehingga tidak ada berkas biner di repositori.
 */
final class DocxBuilder
{
    private const NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
        .'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
        .'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
        .'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" '
        .'xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" '
        .'xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"';

    /** @var list<string> */
    private array $badan = [];

    /** @var array<string, array{rid: string, bytes: string}> */
    private array $media = [];

    /** @var array<string, string> numId => numFmt */
    private array $daftar = [];

    /** @var array<string, array{numId: string, basedOn: ?string}> */
    private array $gaya = [];

    private int $nomorBerikut = 1;

    private int $ridBerikut = 10;

    private int $idGambar = 1;

    public static function baru(): self
    {
        return new self;
    }

    /** PNG polos berukuran `$w` x `$h` (GD), untuk gambar uji yang bisa dibedakan lewat ukurannya. */
    public static function png(int $w, int $h, int $r = 40, int $g = 120, int $b = 200): string
    {
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, $r, $g, $b));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    public function gambar(string $nama, string $bytes): self
    {
        $this->media[$nama] = ['rid' => 'rId'.$this->ridBerikut++, 'bytes' => $bytes];

        return $this;
    }

    /** Daftar bernomor baru (mulai lagi dari awal): `upperLetter` (A.), `lowerLetter`, `decimal`, atau `bullet`. */
    public function daftar(string $fmt = 'upperLetter'): string
    {
        $id = (string) $this->nomorBerikut++;
        $this->daftar[$id] = $fmt;

        return $id;
    }

    /** Gaya paragraf yang membawa penomoran (mis. "Daftar Alfabet"), opsional turunan gaya lain. */
    public function gayaDaftar(string $idGaya, string $numId, ?string $basedOn = null): self
    {
        $this->gaya[$idGaya] = ['numId' => $numId, 'basedOn' => $basedOn];

        return $this;
    }

    /**
     * Paragraf biasa. "\n" di dalam teks menjadi `w:br`; gambar (nama yang didaftarkan lewat `gambar()`) diletakkan sesudah teks.
     *
     * @param  list<string>  $gambar
     */
    public function p(string $teks = '', array $gambar = [], ?string $gaya = null): self
    {
        $pPr = $gaya ? '<w:pPr><w:pStyle w:val="'.$gaya.'"/></w:pPr>' : '';
        $this->badan[] = '<w:p>'.$pPr.$this->jalankan($teks).$this->gambarXml($gambar).'</w:p>';

        return $this;
    }

    /**
     * Butir daftar bernomor.
     *
     * @param  list<string>  $gambar
     */
    public function butir(string $numId, string $teks = '', array $gambar = [], int $ilvl = 0): self
    {
        $this->badan[] = '<w:p><w:pPr><w:numPr><w:ilvl w:val="'.$ilvl.'"/><w:numId w:val="'.$numId.'"/></w:numPr></w:pPr>'
            .$this->jalankan($teks).$this->gambarXml($gambar).'</w:p>';

        return $this;
    }

    /**
     * Tabel. Tiap sel berupa teks atau daftar teks (beberapa paragraf dalam satu sel).
     *
     * @param  list<list<string|list<string>>>  $baris
     */
    public function tabel(array $baris): self
    {
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/></w:tblPr>';
        foreach ($baris as $sel) {
            $xml .= '<w:tr>';
            foreach ($sel as $isi) {
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="2000" w:type="dxa"/></w:tcPr>';
                foreach ((array) $isi as $paragraf) {
                    $xml .= '<w:p>'.$this->jalankan($paragraf).'</w:p>';
                }
                $xml .= '</w:tc>';
            }
            $xml .= '</w:tr>';
        }
        $this->badan[] = $xml.'</w:tbl>';

        return $this;
    }

    /** Tabel meta soal gaya template TKA (5 baris x 2 sel). */
    public function meta(int $no, string $kompetensi, string $sub, string $bentuk, string|array $kunci = ''): self
    {
        return $this->tabel([['No Soal', (string) $no], ['Kompetensi', $kompetensi], ['Sub Kompetensi', $sub], ['Bentuk Soal', $bentuk], ['Kunci', $kunci]]);
    }

    /** Sisipkan XML mentah di badan dokumen (untuk AlternateContent, teks terhapus, dsb.). */
    public function mentah(string $xml): self
    {
        $this->badan[] = $xml;

        return $this;
    }

    public function isi(): string
    {
        $berkas = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($berkas, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');

        $rels = '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        foreach ($this->media as $nama => $m) {
            $rels .= '<Relationship Id="'.$m['rid'].'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/'.$nama.'"/>';
            $zip->addFromString('word/media/'.$nama, $m['bytes']);
        }
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="'.'http://schemas.openxmlformats.org/package/2006/relationships'.'">'.$rels.'</Relationships>');

        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document '.self::NS.'><w:body>'.implode('', $this->badan).'</w:body></w:document>');
        $zip->addFromString('word/numbering.xml', $this->penomoranXml());
        $zip->addFromString('word/styles.xml', $this->gayaXml());

        $zip->close();
        $bytes = (string) file_get_contents($berkas);
        unlink($berkas);

        return $bytes;
    }

    private function jalankan(string $teks): string
    {
        $xml = '';
        foreach (explode("\n", $teks) as $i => $baris) {
            if ($i > 0) {
                $xml .= '<w:r><w:br/></w:r>';
            }
            if ($baris !== '') {
                $xml .= '<w:r><w:t xml:space="preserve">'.htmlspecialchars($baris, ENT_XML1).'</w:t></w:r>';
            }
        }

        return $xml;
    }

    /** @param list<string> $nama */
    private function gambarXml(array $nama): string
    {
        $xml = '';
        foreach ($nama as $n) {
            $rid = $this->media[$n]['rid'];
            $id = $this->idGambar++;
            $xml .= '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="1905000" cy="1270000"/>'
                .'<wp:docPr id="'.$id.'" name="Gambar '.$id.'"/><a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
                .'<pic:pic><pic:blipFill><a:blip r:embed="'.$rid.'"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';
        }

        return $xml;
    }

    private function penomoranXml(): string
    {
        $abstrak = '';
        $num = '';
        foreach ($this->daftar as $id => $fmt) {
            $teks = $fmt === 'bullet' ? '•' : '%1.';
            $abstrak .= '<w:abstractNum w:abstractNumId="'.$id.'"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="'.$fmt.'"/><w:lvlText w:val="'.$teks.'"/></w:lvl></w:abstractNum>';
            $num .= '<w:num w:numId="'.$id.'"><w:abstractNumId w:val="'.$id.'"/></w:num>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:numbering '.self::NS.'>'.$abstrak.$num.'</w:numbering>';
    }

    private function gayaXml(): string
    {
        $xml = '';
        foreach ($this->gaya as $id => $g) {
            $xml .= '<w:style w:type="paragraph" w:styleId="'.$id.'">'
                .($g['basedOn'] ? '<w:basedOn w:val="'.$g['basedOn'].'"/>' : '')
                .($g['numId'] !== '' ? '<w:pPr><w:numPr><w:numId w:val="'.$g['numId'].'"/></w:numPr></w:pPr>' : '')
                .'</w:style>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles '.self::NS.'>'.$xml.'</w:styles>';
    }
}
