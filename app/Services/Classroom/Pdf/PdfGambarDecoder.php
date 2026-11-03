<?php

namespace App\Services\Classroom\Pdf;

use Smalot\PdfParser\PDFObject;
use Smalot\PdfParser\XObject\Image;
use Throwable;

/**
 * Mengubah gambar di dalam PDF menjadi berkas gambar biasa.
 *
 * Smalot tidak punya dekoder gambar: aliran `DCTDecode` dibiarkan sebagai byte JPEG utuh, sedangkan `FlateDecode`
 * sudah dibuka menjadi piksel mentah (tanpa menerapkan predictor). PNG ditulis langsung (tanpa GD) karena GD di
 * sebagian server tidak punya JPEG/WebP. Format lain (JPEG2000, JBIG2, CCITT, CMYK mentah) tidak didukung.
 */
class PdfGambarDecoder
{
    /** Batas piksel agar memori tidak habis (3 byte per piksel, belum termasuk salinan sementara). */
    private const PIKSEL_MAKS = 6_000_000;

    private const PIKSEL_MAKS_ALFA = 2_500_000;

    /** @return array{0: int, 1: int} lebar dan tinggi dalam piksel (0 bila tidak diketahui) */
    public static function ukuranPiksel(Image $gambar): array
    {
        $el = $gambar->getHeader()->getElements();

        return [(int) self::nilai($el['Width'] ?? null), (int) self::nilai($el['Height'] ?? null)];
    }

    /** Stensil 1-bit (penutup huruf/garis), bukan gambar yang bisa ditampilkan sendiri. */
    public static function adalahStensil(Image $gambar): bool
    {
        $el = $gambar->getHeader()->getElements();

        return filter_var(self::nilai($el['ImageMask'] ?? null), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{bytes: string, mime: string}|array{galat: string}
     */
    public function dekode(Image $gambar): array
    {
        try {
            return $this->proses($gambar);
        } catch (Throwable $e) {
            return ['galat' => 'berkas gambar rusak'];
        }
    }

    /** @return array{bytes: string, mime: string}|array{galat: string} */
    private function proses(Image $gambar): array
    {
        $el = $gambar->getHeader()->getElements();
        $filter = self::daftarFilter($el['Filter'] ?? null);

        foreach (['JPXDecode' => 'JPEG2000', 'JBIG2Decode' => 'JBIG2', 'CCITTFaxDecode' => 'faks hitam-putih'] as $nama => $label) {
            if (in_array($nama, $filter, true)) {
                return ['galat' => "format {$label} tidak didukung"];
            }
        }

        $data = $gambar->getContent();

        if (in_array('DCTDecode', $filter, true)) {
            $info = str_starts_with($data, "\xFF\xD8") ? @getimagesizefromstring($data) : false;

            return $info !== false && $info[2] === IMAGETYPE_JPEG ? ['bytes' => $data, 'mime' => 'image/jpeg'] : ['galat' => 'berkas JPEG rusak'];
        }

        [$lebar, $tinggi] = self::ukuranPiksel($gambar);
        if ($lebar <= 0 || $tinggi <= 0) {
            return ['galat' => 'ukuran gambar tidak diketahui'];
        }
        if ($lebar * $tinggi > self::PIKSEL_MAKS) {
            return ['galat' => 'gambar terlalu besar'];
        }

        $bpc = (int) (self::nilai($el['BitsPerComponent'] ?? null) ?: 8);
        $ruang = self::ruangWarna($el['ColorSpace'] ?? null);
        $kedalamanSah = match ($ruang['jenis'] ?? null) {
            'rgb' => [8, 16],
            'indexed' => [1, 2, 4, 8],
            default => [1, 2, 4, 8, 16],
        };
        if ($ruang === null || ! in_array($bpc, $kedalamanSah, true)) {
            return ['galat' => 'ruang warna tidak didukung'];
        }

        $baris = intdiv($lebar * $ruang['komponen'] * $bpc + 7, 8);
        $raw = $this->susunBaris($data, $baris, $tinggi);
        if ($raw === null) {
            return ['galat' => 'data gambar tidak cocok dengan ukurannya'];
        }

        // Transparansi (SMask): pakai latar putih supaya tampil sama di tema terang dan gelap.
        $topeng = $el['SMask'] ?? null;
        if ($topeng instanceof Image && $bpc === 8 && in_array($ruang['jenis'], ['rgb', 'gray'], true) && $lebar * $tinggi <= self::PIKSEL_MAKS_ALFA) {
            $komposit = $this->komposit($raw, $baris, $lebar, $tinggi, $ruang['komponen'], $topeng);
            if ($komposit !== null) {
                $raw = ['predicted' => false, 'data' => $komposit];
            }
        }

        $skanline = $raw['predicted'] ? $raw['data'] : $this->tambahByteFilter($raw['data'], $baris, $tinggi);
        $tipeWarna = ['gray' => 0, 'rgb' => 2, 'indexed' => 3][$ruang['jenis']];

        return ['bytes' => self::png($lebar, $tinggi, $bpc, $tipeWarna, $skanline, $ruang['palet'] ?? null), 'mime' => 'image/png'];
    }

    /**
     * Kenali apakah data sudah berformat scanline PNG (predictor: satu byte filter di depan tiap baris) atau piksel
     * mentah, dari panjangnya. Mengembalikan null bila panjang tidak cocok dengan keduanya.
     *
     * @return array{predicted: bool, data: string}|null
     */
    private function susunBaris(string $data, int $baris, int $tinggi): ?array
    {
        $panjang = strlen($data);
        if ($panjang === ($baris + 1) * $tinggi) {
            return ['predicted' => true, 'data' => $data];
        }
        if ($panjang >= $baris * $tinggi) {
            return ['predicted' => false, 'data' => substr($data, 0, $baris * $tinggi)];
        }

        return null;
    }

    private function tambahByteFilter(string $mentah, int $baris, int $tinggi): string
    {
        $hasil = '';
        for ($y = 0; $y < $tinggi; $y++) {
            $hasil .= "\0".substr($mentah, $y * $baris, $baris);
        }

        return $hasil;
    }

    /**
     * Gabungkan piksel 8-bit dengan topeng alfa di atas latar putih. Mengembalikan baris mentah (tanpa byte filter)
     * atau null bila topeng tidak bisa dipakai (gambar tetap ditampilkan tanpa transparansi).
     *
     * @param  array{predicted: bool, data: string}  $raw
     */
    private function komposit(array $raw, int $baris, int $lebar, int $tinggi, int $komponen, Image $topeng): ?string
    {
        [$lebarM, $tinggiM] = self::ukuranPiksel($topeng);
        $elM = $topeng->getHeader()->getElements();
        $bpcM = (int) (self::nilai($elM['BitsPerComponent'] ?? null) ?: 8);
        if ($lebarM <= 0 || $tinggiM <= 0 || $bpcM !== 8 || $lebarM * $tinggiM > self::PIKSEL_MAKS_ALFA) {
            return null;
        }

        $barisM = $lebarM;
        $dataM = $this->susunBaris($topeng->getContent(), $barisM, $tinggiM);
        if ($dataM === null) {
            return null;
        }
        $alfa = $dataM['predicted'] ? self::lepasFilter($dataM['data'], $barisM, $tinggiM, 1) : $dataM['data'];
        $piksel = $raw['predicted'] ? self::lepasFilter($raw['data'], $baris, $tinggi, $komponen) : $raw['data'];

        // `/Decode [1 0]` pada topeng membalik artinya (nilai 0 = buram penuh); sebagian penghasil PDF memakainya.
        $balik = self::decodeTerbalik($elM);
        if (trim($alfa, $balik ? "\0" : "\xFF") === '') {
            return $piksel;
        }

        $hasil = '';
        for ($y = 0; $y < $tinggi; $y++) {
            $c = unpack('C*', substr($piksel, $y * $baris, $baris));
            $a = unpack('C*', substr($alfa, intdiv($y * $tinggiM, $tinggi) * $barisM, $barisM));
            $keluar = [];
            $n = 0;
            for ($x = 0; $x < $lebar; $x++) {
                $nilaiA = $a[intdiv($x * $lebarM, $lebar) + 1];
                if ($balik) {
                    $nilaiA = 255 - $nilaiA;
                }
                for ($k = 0; $k < $komponen; $k++) {
                    $keluar[$n] = intdiv($c[$n + 1] * $nilaiA + 255 * (255 - $nilaiA), 255);
                    $n++;
                }
            }
            $hasil .= pack('C*', ...$keluar);
        }

        return $hasil;
    }

    /**
     * Balikkan filter PNG (None/Sub/Up/Average/Paeth) pada data berprediktor; hasilnya baris mentah tanpa byte filter.
     */
    private static function lepasFilter(string $data, int $baris, int $tinggi, int $bpp): string
    {
        $hasil = '';
        $sebelum = array_fill(1, $baris, 0);
        $langkah = $baris + 1;

        for ($y = 0; $y < $tinggi; $y++) {
            $jenis = ord($data[$y * $langkah]);
            $kini = unpack('C*', substr($data, $y * $langkah + 1, $baris));

            for ($i = 1; $i <= $baris; $i++) {
                $kiri = $i > $bpp ? $kini[$i - $bpp] : 0;
                $atas = $sebelum[$i];
                $kiriAtas = $i > $bpp ? $sebelum[$i - $bpp] : 0;

                $kini[$i] = ($kini[$i] + match ($jenis) {
                    1 => $kiri,
                    2 => $atas,
                    3 => intdiv($kiri + $atas, 2),
                    4 => self::paeth($kiri, $atas, $kiriAtas),
                    default => 0,
                }) & 0xFF;
            }

            $hasil .= pack('C*', ...$kini);
            $sebelum = $kini;
        }

        return $hasil;
    }

    /** @param array<string, mixed> $elemen header sebuah gambar */
    private static function decodeTerbalik(array $elemen): bool
    {
        $decode = self::nilai($elemen['Decode'] ?? null);
        if (! is_array($decode) || count($decode) < 2) {
            return false;
        }

        return (float) self::nilai($decode[0]) === 1.0 && (float) self::nilai($decode[1]) === 0.0;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        return $pa <= $pb && $pa <= $pc ? $a : ($pb <= $pc ? $b : $c);
    }

    private static function png(int $lebar, int $tinggi, int $kedalaman, int $tipeWarna, string $skanline, ?string $palet): string
    {
        $potong = fn (string $jenis, string $isi): string => pack('N', strlen($isi)).$jenis.$isi.pack('N', crc32($jenis.$isi));

        $png = "\x89PNG\r\n\x1a\n".$potong('IHDR', pack('NNCCCCC', $lebar, $tinggi, $kedalaman, $tipeWarna, 0, 0, 0));
        if ($palet !== null) {
            $png .= $potong('PLTE', $palet);
        }

        return $png.$potong('IDAT', gzcompress($skanline, 6)).$potong('IEND', '');
    }

    /**
     * @return array{jenis: 'gray'|'rgb'|'indexed', komponen: int, palet?: string}|null
     */
    private static function ruangWarna(mixed $cs): ?array
    {
        $isi = $cs instanceof PDFObject ? self::isiObjek($cs) : self::nilai($cs);

        if (is_string($isi)) {
            return match ($isi) {
                'DeviceRGB', 'CalRGB', 'RGB' => ['jenis' => 'rgb', 'komponen' => 3],
                'DeviceGray', 'CalGray', 'G' => ['jenis' => 'gray', 'komponen' => 1],
                default => null,
            };
        }
        if (! is_array($isi) || $isi === []) {
            return null;
        }

        $nama = (string) self::nilai($isi[0]);
        switch ($nama) {
            case 'CalRGB':
                return ['jenis' => 'rgb', 'komponen' => 3];
            case 'CalGray':
                return ['jenis' => 'gray', 'komponen' => 1];
            case 'ICCBased':
                $profil = $isi[1] ?? null;
                $n = $profil instanceof PDFObject ? (int) self::nilai($profil->getHeader()->getElements()['N'] ?? null) : 0;

                return match ($n) {
                    1 => ['jenis' => 'gray', 'komponen' => 1],
                    3 => ['jenis' => 'rgb', 'komponen' => 3],
                    default => null,
                };
            case 'Indexed':
            case 'I':
                $dasar = self::ruangWarna($isi[1] ?? null);
                $tertinggi = (int) self::nilai($isi[2] ?? null);
                $tabel = $isi[3] ?? null;
                $bytes = $tabel instanceof PDFObject ? $tabel->getContent() : (string) self::nilai($tabel);
                if ($dasar === null || ! in_array($dasar['jenis'], ['rgb', 'gray'], true) || $tertinggi < 0 || $tertinggi > 255) {
                    return null;
                }
                $entri = $tertinggi + 1;
                $bytes = str_pad(substr($bytes, 0, $entri * $dasar['komponen']), $entri * $dasar['komponen'], "\0");
                if ($dasar['jenis'] === 'gray') {
                    $bytes = implode('', array_map(fn (string $g) => $g.$g.$g, str_split($bytes)));
                }

                return ['jenis' => 'indexed', 'komponen' => 1, 'palet' => $bytes];
            default:
                return null;
        }
    }

    /**
     * Ruang warna yang berupa rujukan objek (`/ColorSpace 9 0 R`): larik atau nama ada di isi objek itu. Smalot menaruh
     * elemen larik tak bernama di header objek.
     *
     * @return string|list<mixed>|null
     */
    private static function isiObjek(PDFObject $objek): string|array|null
    {
        $isi = $objek->getContent();
        if (is_string($isi) && trim($isi, "/ \r\n") !== '') {
            return trim($isi, "/ \r\n");
        }

        $elemen = array_values($objek->getHeader()->getElements());

        return $elemen === [] ? null : $elemen;
    }

    /** @return list<string> */
    private static function daftarFilter(mixed $filter): array
    {
        $isi = self::nilai($filter);
        if ($isi === null) {
            return [];
        }

        return is_array($isi) ? array_map(fn ($f) => (string) self::nilai($f), $isi) : [(string) $isi];
    }

    /** Isi sebuah elemen smalot (nama, angka, larik) sebagai nilai PHP biasa. */
    private static function nilai(mixed $elemen): mixed
    {
        if ($elemen === null || is_scalar($elemen) || is_array($elemen)) {
            return $elemen;
        }
        if (! method_exists($elemen, 'getContent') || $elemen instanceof PDFObject) {
            return null;
        }

        return $elemen->getContent();
    }
}
