<?php

namespace App\Services\Classroom\Pdf;

use App\Services\Classroom\Impor\BacaanDokumen;
use App\Services\Classroom\Impor\DokumenTidakDapatDibaca;
use App\Services\Classroom\Impor\PembersihTeks;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser;
use Smalot\PdfParser\XObject\Form;
use Smalot\PdfParser\XObject\Image;
use Throwable;

/**
 * Membaca PDF hasil ketik menjadi urutan baris teks dan gambar menurut alur baca.
 *
 * Smalot memberi daftar operator halaman berurutan (`extractRawData`) dan teks yang sudah didekode font
 * (`getTextArray`, satu entri per operator tampil teks). Di sini keduanya dipasangkan, sambil melacak matriks
 * (`q/Q/cm/Tm/Td`) supaya posisi teks dan gambar bisa dibandingkan antar penghasil PDF (Word, LibreOffice, Skia/Google
 * Docs, MuPDF) yang menaruh sumbu y dan skala dengan cara berbeda.
 */
class PdfSoalReader
{
    private const OPERATOR_TEKS = ['Tj', 'TJ', "'", '"'];

    private const IDENTITAS = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];

    /** Pola penanda soal/label template TKA; baris seperti ini tidak boleh dianggap kepala/kaki halaman. */
    private const POLA_STRUKTUR = '/^(?:(?:tidak|no\.?|nomor)\s*soal|sub\s*kompetensi|kompetensi|bentuk\s*soal|kunci|\d{1,3}\s*[.)]|[A-Ea-e]\s*[.)])/iu';

    private const POLA_NOMOR_HALAMAN = '/^(?:halaman|hal\.?|page)?\s*[-–]?\s*\d{1,3}\s*(?:[-–]|\/|dari|of)?\s*\d{0,3}\s*[-–]?$/iu';

    /**
     * @throws DokumenTidakDapatDibaca
     */
    public function baca(string $path): BacaanDokumen
    {
        $halaman = $this->buka($path);
        $jumlah = count($halaman);

        if ($jumlah === 0) {
            throw new DokumenTidakDapatDibaca('PDF ini tidak memiliki halaman.');
        }
        $maks = (int) config('classroom.max_pdf_halaman');
        if ($jumlah > $maks) {
            throw new DokumenTidakDapatDibaca("PDF terlalu panjang ({$jumlah} halaman, maksimal {$maks}). Pecah menjadi beberapa berkas.");
        }

        $peringatan = [];
        $blok = [];
        foreach ($halaman as $i => $page) {
            foreach ($this->bacaHalaman($page, $i + 1, $peringatan) as $b) {
                $blok[] = $b;
            }
        }

        $blok = $this->buangGambarTakRelevan($blok, $jumlah);
        $blok = $this->buangKepalaKaki($blok, $jumlah);
        $blok = $this->tandaiParagraf($blok);

        return new BacaanDokumen($blok, $jumlah, array_values(array_unique($peringatan)));
    }

    /** @return list<Page> */
    private function buka(string $path): array
    {
        try {
            $config = new Config;
            // Batasi hasil dekompresi satu aliran (gambar sangat besar) supaya memori tidak habis.
            $config->setDecodeMemoryLimit(48 * 1024 * 1024);

            return array_values((new Parser([], $config))->parseFile($path)->getPages());
        } catch (Throwable $e) {
            $pesan = strtolower($e->getMessage());
            $terkunci = str_contains($pesan, 'secured') || str_contains($pesan, 'encrypt') || str_contains($pesan, 'password');

            throw new DokumenTidakDapatDibaca(
                $terkunci
                    ? 'PDF ini dilindungi sandi. Simpan ulang tanpa sandi lalu unggah lagi.'
                    : 'PDF tidak dapat dibaca. Pastikan berkas tidak rusak, atau simpan ulang dari Word atau Google Docs.',
                previous: $e
            );
        }
    }

    /**
     * @param  list<string>  $peringatan
     * @return list<array<string, mixed>>
     */
    private function bacaHalaman(Page $page, int $no, array &$peringatan): array
    {
        try {
            $perintah = $page->extractRawData();
            $teks = $page->getTextArray();
        } catch (Throwable) {
            $peringatan[] = "Halaman {$no} tidak dapat dibaca.";

            return [];
        }

        $jumlahTampil = count(array_filter($perintah, fn ($c) => in_array($c['o'], self::OPERATOR_TEKS, true)));
        if ($jumlahTampil !== count($teks)) {
            // Teks di dalam objek bertingkat membuat hitungan tak cocok; ambil teksnya saja dan jangan menebak letak gambar.
            $peringatan[] = "Halaman {$no}: susunan teks tidak dikenali penuh, jadi gambar di halaman ini perlu ditambahkan manual.";

            return $this->barisTanpaPosisi($page, $no);
        }

        $hasil = [];
        $baris = null;
        $tutup = function () use (&$baris, &$hasil, $no): void {
            if ($baris !== null) {
                $teksBersih = PembersihTeks::bersihkan($baris['teks']);
                if ($teksBersih !== '') {
                    $hasil[] = ['jenis' => 'baris', 'teks' => $teksBersih, 'halaman' => $no, 'y' => $baris['y'], 'ukuran' => $baris['ukuran']];
                }
            }
            $baris = null;
        };

        $ctm = self::IDENTITAS;
        $tumpukan = [];
        $tm = $tlm = self::IDENTITAS;
        $ukuran = 1.0;
        $leading = 0.0;
        $k = 0;

        $tampil = function (string $isi) use (&$baris, &$tutup, &$tm, &$ctm, &$ukuran): void {
            $kini = self::kali($tm, $ctm);
            $ukuranDev = max(1.0, $ukuran * hypot($kini[2], $kini[3]));
            $x = $kini[4];
            $y = $kini[5];

            if ($baris !== null && abs($y - $baris['y']) <= 0.45 * max($baris['ukuran'], $ukuranDev)) {
                $baris['teks'] .= self::pemisah($baris['teks'], $isi, $x - $baris['xAkhir'], $ukuranDev).$isi;
                $baris['xAkhir'] = max($baris['xAkhir'], $x + mb_strlen($isi) * $ukuranDev * 0.5);

                return;
            }

            $tutup();
            $baris = ['teks' => $isi, 'y' => $y, 'ukuran' => $ukuranDev, 'xAkhir' => $x + mb_strlen($isi) * $ukuranDev * 0.5];
        };

        foreach ($perintah as $c) {
            $op = $c['o'];
            $arg = is_string($c['c'] ?? null) ? $c['c'] : '';

            switch ($op) {
                case 'q':
                    $tumpukan[] = $ctm;
                    break;
                case 'Q':
                    if ($tumpukan !== []) {
                        $ctm = array_pop($tumpukan);
                    }
                    break;
                case 'cm':
                    $m = self::angka($arg);
                    if (count($m) === 6) {
                        $ctm = self::kali($m, $ctm);
                    }
                    break;
                case 'BT':
                    $tm = $tlm = self::IDENTITAS;
                    break;
                case 'Tf':
                    $bagian = preg_split('/\s+/', trim($arg)) ?: [];
                    $u = abs((float) end($bagian));
                    if ($u > 0.0) {
                        $ukuran = $u;
                    }
                    break;
                case 'TL':
                    $leading = (float) $arg;
                    break;
                case 'Tm':
                    $m = self::angka($arg);
                    if (count($m) === 6) {
                        $tm = $tlm = $m;
                    }
                    break;
                case 'Td':
                case 'TD':
                    $d = self::angka($arg);
                    if (count($d) === 2) {
                        if ($op === 'TD') {
                            $leading = -$d[1];
                        }
                        $tlm = self::kali([1.0, 0.0, 0.0, 1.0, $d[0], $d[1]], $tlm);
                        $tm = $tlm;
                    }
                    break;
                case 'T*':
                    $tlm = self::kali([1.0, 0.0, 0.0, 1.0, 0.0, -$leading], $tlm);
                    $tm = $tlm;
                    break;
                case "'":
                case '"':
                    $tlm = self::kali([1.0, 0.0, 0.0, 1.0, 0.0, -$leading], $tlm);
                    $tm = $tlm;
                    $tampil((string) ($teks[$k++] ?? ''));
                    break;
                case 'Tj':
                case 'TJ':
                    $tampil((string) ($teks[$k++] ?? ''));
                    break;
                case 'Do':
                    $objek = $page->getXObject(ltrim(trim($arg), '/'));
                    if ($objek instanceof Image) {
                        $tutup();
                        $hasil[] = [
                            'jenis' => 'gambar',
                            'id' => spl_object_id($objek),
                            'objek' => $objek,
                            'halaman' => $no,
                            'lebar' => hypot($ctm[0], $ctm[1]),
                            'tinggi' => hypot($ctm[2], $ctm[3]),
                        ];
                    } elseif ($objek instanceof Form && str_contains($objek->getContent(), ' Do')) {
                        $peringatan[] = "Halaman {$no} memuat gambar di dalam objek bertingkat yang tidak dibaca; tambahkan manual bila ada.";
                    }
                    break;
            }
        }
        $tutup();

        return $hasil;
    }

    /**
     * Cadangan bila pasangan operator-teks gagal: baris dari teks halaman tanpa gambar.
     *
     * @return list<array<string, mixed>>
     */
    private function barisTanpaPosisi(Page $page, int $no): array
    {
        $hasil = [];
        $y = 0.0;
        foreach (preg_split('/\R/u', (string) $page->getText()) ?: [] as $teks) {
            $teks = PembersihTeks::bersihkan($teks);
            if ($teks !== '') {
                $hasil[] = ['jenis' => 'baris', 'teks' => $teks, 'halaman' => $no, 'y' => $y += 14.0, 'ukuran' => 11.0];
            }
        }

        return $hasil;
    }

    /**
     * Gambar yang tidak mungkin bagian dari soal: muncul di banyak halaman (logo kop, watermark), terlalu kecil
     * (butir, garis), atau stensil hitam-putih.
     *
     * @param  list<array<string, mixed>>  $blok
     * @return list<array<string, mixed>>
     */
    private function buangGambarTakRelevan(array $blok, int $jumlahHalaman): array
    {
        $halamanPerGambar = [];
        foreach ($blok as $b) {
            if ($b['jenis'] === 'gambar') {
                $halamanPerGambar[$b['id']][$b['halaman']] = true;
            }
        }
        $ambang = max(3, (int) ceil($jumlahHalaman * 0.5));

        return array_values(array_filter($blok, function (array $b) use ($halamanPerGambar, $ambang, $jumlahHalaman) {
            if ($b['jenis'] !== 'gambar') {
                return true;
            }
            if ($jumlahHalaman >= 3 && count($halamanPerGambar[$b['id']]) >= $ambang) {
                return false;
            }
            if (min($b['lebar'], $b['tinggi']) < 18.0 || PdfGambarDecoder::adalahStensil($b['objek'])) {
                return false;
            }
            [$lebarPx, $tinggiPx] = PdfGambarDecoder::ukuranPiksel($b['objek']);

            return min($lebarPx, $tinggiPx) >= 24;
        }));
    }

    /**
     * Buang baris kepala/kaki halaman: dua baris teratas dan terbawah tiap halaman yang sama di sedikitnya separuh
     * halaman, serta nomor halaman polos.
     *
     * @param  list<array<string, mixed>>  $blok
     * @return list<array<string, mixed>>
     */
    private function buangKepalaKaki(array $blok, int $jumlahHalaman): array
    {
        $perHalaman = [];
        foreach ($blok as $i => $b) {
            if ($b['jenis'] === 'baris') {
                $perHalaman[$b['halaman']][] = $i;
            }
        }

        $ujung = [];
        $hitungKunci = [];
        foreach ($perHalaman as $indeks) {
            $kandidat = array_unique([...array_slice($indeks, 0, 2), ...array_slice($indeks, -2)]);
            $kuncinya = [];
            foreach ($kandidat as $i) {
                $teks = $blok[$i]['teks'];
                if (preg_match(self::POLA_NOMOR_HALAMAN, $teks)) {
                    $ujung[$i] = true;

                    continue;
                }
                if (preg_match(self::POLA_STRUKTUR, $teks)) {
                    continue;
                }
                $kunci = mb_strtolower($teks);
                $kuncinya[$kunci] = true;
                $ujung[$i] = $kunci;
            }
            foreach (array_keys($kuncinya) as $kunci) {
                $hitungKunci[$kunci] = ($hitungKunci[$kunci] ?? 0) + 1;
            }
        }

        $ambang = max(2, (int) ceil($jumlahHalaman * 0.5));
        $buang = [];
        foreach ($ujung as $i => $kunci) {
            if ($kunci === true || ($jumlahHalaman >= 3 && ($hitungKunci[$kunci] ?? 0) >= $ambang)) {
                $buang[$i] = true;
            }
        }

        return array_values(array_filter($blok, fn ($b, $i) => ! isset($buang[$i]), ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Tentukan baris yang membuka paragraf. Jarak antarbaris dalam paragraf (kuartil terbawah jarak/ukuran huruf di
     * seluruh dokumen) dibandingkan dengan jarak ke baris berikutnya; jarak yang lebih lebar berarti paragraf baru.
     * Akhir kalimat lalu huruf kapital juga dianggap paragraf baru (pilihan jawaban tanpa jarak antarparagraf).
     *
     * @param  list<array<string, mixed>>  $blok
     * @return list<array<string, mixed>>
     */
    private function tandaiParagraf(array $blok): array
    {
        $rasio = [];
        $sebelum = null;
        foreach ($blok as $b) {
            if ($b['jenis'] === 'gambar') {
                $sebelum = null;

                continue;
            }
            if ($sebelum !== null && $sebelum['halaman'] === $b['halaman']) {
                $ukuran = max($sebelum['ukuran'], $b['ukuran']);
                $r = abs($sebelum['y'] - $b['y']) / $ukuran;
                if ($r > 0.8 && $r < 4.0) {
                    $rasio[] = $r;
                }
            }
            $sebelum = $b;
        }
        sort($rasio);
        $dasar = $rasio === [] ? 1.25 : min(2.2, max(1.0, $rasio[(int) floor(count($rasio) / 4)]));

        $hasil = [];
        $sebelum = null;
        foreach ($blok as $b) {
            if ($b['jenis'] === 'gambar') {
                $hasil[] = $b;
                $sebelum = null;

                continue;
            }

            if ($sebelum === null) {
                $paragraf = true;
            } elseif ($sebelum['halaman'] !== $b['halaman']) {
                $paragraf = self::akhirKalimat($sebelum['teks']);
            } else {
                $r = abs($sebelum['y'] - $b['y']) / max($sebelum['ukuran'], $b['ukuran']);
                $paragraf = $r > $dasar * 1.18 + 0.05
                    || (self::akhirKalimat($sebelum['teks']) && preg_match('/^[\p{Lu}\d"“(•\-–]/u', $b['teks']) === 1);
            }

            $hasil[] = ['jenis' => 'baris', 'teks' => $b['teks'], 'paragraf' => $paragraf, 'halaman' => $b['halaman'], 'y' => $b['y'], 'ukuran' => $b['ukuran']];
            $sebelum = $b;
        }

        return $hasil;
    }

    private static function akhirKalimat(string $teks): bool
    {
        return preg_match('/[.?!:;…)"”]\s*$/u', $teks) === 1;
    }

    /** Sisipan antara dua potongan teks di baris yang sama: tab untuk celah lebar (kolom tabel), spasi untuk celah kata. */
    private static function pemisah(string $sebelum, string $baru, float $celah, float $ukuran): string
    {
        if ($sebelum === '' || preg_match('/\s$/u', $sebelum) === 1 || preg_match('/^\s/u', $baru) === 1) {
            return '';
        }
        if ($celah > 2.2 * $ukuran) {
            return "\t";
        }

        return $celah > 0.18 * $ukuran ? ' ' : '';
    }

    /** @return list<float> */
    private static function angka(string $arg): array
    {
        return array_map('floatval', preg_split('/\s+/', trim($arg), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Gabungan dua matriks `[a b c d e f]`: `$pertama` diterapkan lebih dulu, lalu `$kedua`.
     *
     * @param  list<float>  $pertama
     * @param  list<float>  $kedua
     * @return list<float>
     */
    private static function kali(array $pertama, array $kedua): array
    {
        return [
            $pertama[0] * $kedua[0] + $pertama[1] * $kedua[2],
            $pertama[0] * $kedua[1] + $pertama[1] * $kedua[3],
            $pertama[2] * $kedua[0] + $pertama[3] * $kedua[2],
            $pertama[2] * $kedua[1] + $pertama[3] * $kedua[3],
            $pertama[4] * $kedua[0] + $pertama[5] * $kedua[2] + $kedua[4],
            $pertama[4] * $kedua[1] + $pertama[5] * $kedua[3] + $kedua[5],
        ];
    }
}
