<?php

namespace Tests\Support;

/**
 * Pembangun PDF minimal untuk tes impor soal: teks Helvetica per baris dan gambar yang ditaruh berurutan, tanpa
 * pustaka PDF dan tanpa berkas biner di repositori. Jenis gambar meniru yang ditemui di PDF nyata (Word,
 * LibreOffice, Chrome): JPEG utuh, Flate mentah, Flate berprediktor PNG, topeng alfa (SMask) termasuk yang
 * `/Decode [1 0]`, dan palet Indexed 1 bit.
 */
final class PdfBuilder
{
    /** JPEG 40x30 yang valid (dibuat sekali dengan Pillow). */
    private const JPEG_BASE64 = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDABQODxIPDRQSEBIXFRQYHjIhHhwcHj0sLiQySUBMS0dARkVQWnNiUFVtVkVGZIhlbXd7gYKBTmCNl4x9lnN+gXz/2wBDARUXFx4aHjshITt8U0ZTfHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHx8fHz/wAARCAAeACgDASIAAhEBAxEB/8QAFwABAQEBAAAAAAAAAAAAAAAAAAQDAv/EACkQAAAEAggHAQAAAAAAAAAAAAADBBICEQEFFBU1cYOyEyJUVaKj0TH/xAAXAQEBAQEAAAAAAAAAAAAAAAABAAQF/8QAIREAAQQCAAcAAAAAAAAAAAAAAQACESESUTGRobHR4fD/2gAMAwEAAhEDEQA/AOkSMtQUcacfwYCmzpY79GlkQdy9EQJMKrDT3CAdGyTawUAKV9kQdy9EQWRB3L0RCAA4nfbwjIaVa1GWnKJNJP40BrpUsb+ANFeFVfqbgE0mLU7jSJMKrDT3CAbpVqhG6zmMfKfLRTOWeYovpf1HhD8BDgTCZaQJUAC++l/UeEPwL6X9R4Q/Ay7XX0iG7+5orwqr9TcAnVLVCxtoMeycuWiiU8sgE0EC1OIJpf/Z';

    /** @var list<list<array<string, mixed>>> */
    private array $halaman = [[]];

    /** @var array<string, array<string, mixed>> */
    private array $gambar = [];

    public static function baru(): self
    {
        return new self;
    }

    /** Gambar JPEG (aliran DCTDecode utuh). */
    public function jpeg(string $nama): self
    {
        $this->gambar[$nama] = ['jenis' => 'jpeg', 'w' => 40, 'h' => 30];

        return $this;
    }

    /**
     * Gambar RGB Flate. Piksel bervariasi per baris/kolom supaya salah baca terlihat. `$predictor` menulis baris dengan
     * filter PNG "Up" (Predictor 15); `$alfa` menambah topeng: kiri buram, kanan transparan; `$alfaTerbalik` memakai
     * `/Decode [1 0]` pada topeng (nilai 0 = buram) seperti LibreOffice.
     */
    public function flate(string $nama, int $w = 48, int $h = 36, bool $predictor = false, bool $alfa = false, bool $alfaTerbalik = false): self
    {
        $this->gambar[$nama] = ['jenis' => 'flate', 'w' => $w, 'h' => $h, 'predictor' => $predictor, 'alfa' => $alfa, 'terbalik' => $alfaTerbalik];

        return $this;
    }

    /** Gambar 1 bit berpalet dua warna: kolom kiri indeks 0 (merah), kolom kanan indeks 1 (biru). */
    public function berpalet(string $nama, int $w = 32, int $h = 32): self
    {
        $this->gambar[$nama] = ['jenis' => 'palet', 'w' => $w, 'h' => $h];

        return $this;
    }

    /** Piksel RGB yang ditulis flate() di kolom `$x`, baris `$y` (untuk memeriksa hasil dekode). */
    public static function pikselFlate(int $x, int $y): array
    {
        return [($y * 5) % 256, ($x * 3) % 256, 100];
    }

    /** Satu baris teks. `$jeda` menambah jarak sebelum baris (paragraf baru); tanpa jeda baris dirapatkan. */
    public function baris(string $teks, bool $jeda = false): self
    {
        $this->halaman[array_key_last($this->halaman)][] = ['t' => 'baris', 'teks' => $teks, 'jeda' => $jeda];

        return $this;
    }

    public function pasang(string $nama, int $lebar = 120, int $tinggi = 80): self
    {
        $this->halaman[array_key_last($this->halaman)][] = ['t' => 'gambar', 'nama' => $nama, 'lebar' => $lebar, 'tinggi' => $tinggi];

        return $this;
    }

    public function halamanBaru(): self
    {
        $this->halaman[] = [];

        return $this;
    }

    public function isi(): string
    {
        $objek = [];
        $berikut = 4;
        $alokasi = function (string $badan) use (&$objek, &$berikut): int {
            $objek[$berikut] = $badan;

            return $berikut++;
        };

        $xobject = [];
        foreach ($this->gambar as $nama => $g) {
            [$utama, $topeng] = $this->objekGambar($g);
            if ($topeng !== null) {
                $utama = str_replace('{SMASK}', (string) $alokasi($topeng), $utama);
            }
            $xobject[$nama] = $alokasi($utama);
        }

        $sumber = '<< /Font << /F1 3 0 R >> /XObject << '.implode(' ', array_map(fn ($nama, $no) => '/'.$nama." {$no} 0 R", array_keys($xobject), $xobject)).' >> >>';

        $kids = [];
        foreach ($this->halaman as $isi) {
            $konten = $alokasi($this->aliran('', $this->konten($isi)));
            $kids[] = $alokasi("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources {$sumber} /Contents {$konten} 0 R >>");
        }

        $objek[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objek[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn ($k) => "{$k} 0 R", $kids)).'] /Count '.count($kids).' >>';
        $objek[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        ksort($objek);

        $pdf = "%PDF-1.5\n";
        $offset = [];
        foreach ($objek as $no => $badan) {
            $offset[$no] = strlen($pdf);
            $pdf .= "{$no} 0 obj\n{$badan}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objek) + 1)."\n0000000000 65535 f \n";
        foreach ($offset as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf."trailer\n<< /Size ".(count($objek) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    /** @param list<array<string, mixed>> $isi */
    private function konten(array $isi): string
    {
        $y = 800.0;
        $perintah = '';
        foreach ($isi as $item) {
            if ($item['t'] === 'baris') {
                $y -= $item['jeda'] ? 26 : 15;
                $teks = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $item['teks']);
                $perintah .= sprintf("BT /F1 11 Tf 1 0 0 1 60 %.2f Tm (%s) Tj ET\n", $y, $teks);
            } else {
                $y -= $item['tinggi'] + 8;
                $perintah .= sprintf("q %d 0 0 %d 60 %.2f cm /%s Do Q\n", $item['lebar'], $item['tinggi'], $y, $item['nama']);
            }
        }

        return $perintah;
    }

    /**
     * @param  array<string, mixed>  $g
     * @return array{0: string, 1: string|null} badan objek gambar dan (bila ada) badan objek topeng
     */
    private function objekGambar(array $g): array
    {
        $dasar = '/Type /XObject /Subtype /Image /Width '.$g['w'].' /Height '.$g['h'];

        if ($g['jenis'] === 'jpeg') {
            return [$this->aliran($dasar.' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode', base64_decode(self::JPEG_BASE64)), null];
        }

        if ($g['jenis'] === 'palet') {
            $barisByte = intdiv($g['w'] + 7, 8);
            $baris = str_repeat(chr(0x00), intdiv($barisByte, 2)).str_repeat(chr(0xFF), $barisByte - intdiv($barisByte, 2));

            return [$this->aliran(
                $dasar.' /ColorSpace [/Indexed /DeviceRGB 1 <FF00000000FF>] /BitsPerComponent 1 /Filter /FlateDecode',
                gzcompress(str_repeat($baris, $g['h']))
            ), null];
        }

        $mentah = [];
        for ($y = 0; $y < $g['h']; $y++) {
            $baris = '';
            for ($x = 0; $x < $g['w']; $x++) {
                $baris .= chr(self::pikselFlate($x, $y)[0]).chr(self::pikselFlate($x, $y)[1]).chr(self::pikselFlate($x, $y)[2]);
            }
            $mentah[] = $baris;
        }

        $parameter = '';
        if ($g['predictor']) {
            $data = '';
            $sebelum = str_repeat("\0", $g['w'] * 3);
            foreach ($mentah as $baris) {
                $data .= "\x02";
                for ($i = 0, $n = strlen($baris); $i < $n; $i++) {
                    $data .= chr((ord($baris[$i]) - ord($sebelum[$i]) + 256) % 256);
                }
                $sebelum = $baris;
            }
            $parameter = ' /DecodeParms << /Predictor 15 /Colors 3 /BitsPerComponent 8 /Columns '.$g['w'].' >>';
        } else {
            $data = implode('', $mentah);
        }

        $topeng = null;
        $rujukan = '';
        if ($g['alfa']) {
            $buram = $g['terbalik'] ? "\x00" : "\xFF";
            $bening = $g['terbalik'] ? "\xFF" : "\x00";
            $bar = str_repeat($buram, intdiv($g['w'], 2)).str_repeat($bening, $g['w'] - intdiv($g['w'], 2));
            $topeng = $this->aliran(
                '/Type /XObject /Subtype /Image /Width '.$g['w'].' /Height '.$g['h'].' /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode'.($g['terbalik'] ? ' /Decode [1 0]' : ''),
                gzcompress(str_repeat($bar, $g['h']))
            );
            $rujukan = ' /SMask {SMASK} 0 R';
        }

        return [$this->aliran($dasar.' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode'.$parameter.$rujukan, gzcompress($data)), $topeng];
    }

    private function aliran(string $kamus, string $data): string
    {
        return '<< '.$kamus.' /Length '.strlen($data).' >>'."\nstream\n".$data."\nendstream";
    }
}
