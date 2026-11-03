<?php

namespace App\Services\Classroom\Impor;

/**
 * Memecah isi PDF menjadi draf soal. Dua susunan dikenali:
 *
 *  1. Template TKA (`format/FORMAT SOAL TKA.docx`): tiap soal diawali tabel meta (No Soal, Kompetensi, Sub Kompetensi,
 *     Bentuk Soal, Kunci), lalu paragraf pertanyaan dan pilihan TANPA huruf. Batas pertanyaan dan pilihan dicari dari
 *     kalimat penutup ("... adalah", "?", "(Jawaban benar lebih dari satu)"), cadangannya lima paragraf terakhir.
 *     Bentuk Kategori punya tabel "# | Pernyataan | Benar | Salah".
 *  2. Bernomor: "1. pertanyaan", pilihan "A. ...", "Kunci: B".
 *
 * Gambar tidak ditebak diam-diam: yang letaknya meragukan tetap dipasang tetapi diberi peringatan.
 * Hasilnya draf yang diperiksa guru di editor; `gambar`/`opsiGambar` masih berupa id gambar dari BacaanDokumen.
 */
class SoalParser
{
    private const MAKS_PILIHAN = 8;

    private const R_NO = '/^(?:tidak|no\.?|nomor)\s*soal\b[\s:.\t]*(\d{1,3})?\s*(.*)$/iu';

    private const R_KOMPETENSI = '/^kompetensi\b[\s:\t]*(.*)$/iu';

    private const R_SUB = '/^sub\s*kompetensi\b[\s:\t]*(.*)$/iu';

    private const R_BENTUK = '/^bentuk\s*soal\b[\s:\t]*(.*)$/iu';

    private const R_KUNCI = '/^kunci(?:\s*jawaban)?\b[\s:\t]*(.*)$/iu';

    private const R_HEADER_KATEGORI = '/^#?[\s\t]*pernyataan[\s\t]+benar[\s\t]+salah\b/iu';

    private const R_AKHIR_PERTANYAAN = '/(?:\?|:|(?:adalah|yaitu|ialah|berikut)\b[\s.…:\x{FFFD}_\-–—]*(?:\([^)]*\))?)\s*$|jawaban\s+benar\s+lebih\s+dari\s+satu/iu';

    /** Penanda boleh berdiri sendiri ("A." lalu gambar), tetapi "1.5" atau "A.5" tidak dianggap penanda. */
    private const R_NOMOR = '/^\s*(\d{1,3})\s*[.)](?:(?=\s|$)\s*(.*)|(?=\p{L})(.*))$/u';

    private const R_OPSI = '/^\s*\(?([A-Ha-h])\s*[.)](?:(?=\s|$)\s*(.*)|(?=\p{L})(.*))$/u';

    private const R_KUNCI_BERNOMOR = '/^\s*(?:kunci(?:\s+jawaban)?|jawaban(?:\s+benar)?)\s*[:=]\s*(.*)$/iu';

    private const R_RENTANG_WACANA = '/(?:soal|nomor|no\.?)\s*(?:nomor|no\.?)?\s*(\d{1,3})\s*(?:sampai(?:\s+dengan)?|s\.?\s*d\.?|hingga|[-–])\s*(?:nomor|no\.?)?\s*(\d{1,3})/iu';

    /**
     * @return array{soal: list<array<string, mixed>>, peringatan: list<string>}
     */
    public function parse(BacaanDokumen $bacaan): array
    {
        $peringatan = [];
        $blok = $bacaan->blok;

        $adaTemplate = false;
        foreach ($blok as $b) {
            if ($b['jenis'] === 'baris' && (preg_match(self::R_BENTUK, $b['teks']) === 1 || preg_match(self::R_SUB, $b['teks']) === 1)) {
                $adaTemplate = true;
                break;
            }
        }

        $soal = $adaTemplate ? $this->bacaTemplate($blok, $peringatan) : $this->bacaBernomor($blok, $peringatan);

        if ($soal === []) {
            $peringatan[] = 'Tidak ada soal yang dikenali di dokumen ini. Pastikan memakai format template TKA atau penomoran "1." dengan pilihan "A.".';
        }

        return ['soal' => $soal, 'peringatan' => $peringatan];
    }

    // ---------------------------------------------------------------- template TKA

    /**
     * @param  list<array<string, mixed>>  $blok
     * @param  list<string>  $peringatan
     * @return list<array<string, mixed>>
     */
    private function bacaTemplate(array $blok, array &$peringatan): array
    {
        $daftar = [];
        $cur = null;
        $jumlah = count($blok);

        foreach ($blok as $i => $b) {
            if ($b['jenis'] === 'gambar') {
                if ($cur !== null) {
                    $cur['badan'][] = $b;
                }

                continue;
            }

            $teks = $b['teks'];
            $labelNo = preg_match(self::R_NO, $teks, $mNo) === 1;
            $labelKomp = preg_match(self::R_KOMPETENSI, $teks) === 1 && preg_match(self::R_SUB, $teks) !== 1;
            $mulaiBaru = $labelNo
                || ($cur === null && $labelKomp)
                || ($cur !== null && $cur['fase'] === 'badan' && $labelKomp && $this->labelMetaBerikut($blok, $i, $jumlah));

            if ($mulaiBaru) {
                if ($cur !== null) {
                    $daftar[] = $cur;
                }
                $cur = ['no' => null, 'komp' => '', 'sub' => '', 'bentuk' => '', 'kunci' => '', 'badan' => [], 'fase' => 'meta', 'aktif' => null];
                if ($labelNo) {
                    $cur['no'] = isset($mNo[1]) && $mNo[1] !== '' ? (int) $mNo[1] : null;

                    continue;
                }
            }

            if ($cur === null) {
                continue;
            }

            if ($cur['fase'] === 'meta') {
                if (preg_match(self::R_SUB, $teks, $m) === 1) {
                    [$cur['aktif'], $cur['sub']] = ['sub', $this->nilaiLabel($m[1])];
                } elseif (preg_match(self::R_KOMPETENSI, $teks, $m) === 1) {
                    [$cur['aktif'], $cur['komp']] = ['komp', $this->nilaiLabel($m[1])];
                } elseif (preg_match(self::R_BENTUK, $teks, $m) === 1) {
                    [$cur['aktif'], $cur['bentuk']] = ['bentuk', $this->nilaiLabel($m[1])];
                } elseif (preg_match(self::R_KUNCI, $teks, $m) === 1) {
                    [$cur['aktif'], $cur['kunci'], $cur['fase']] = ['kunci', $this->nilaiLabel($m[1]), 'kunci'];
                } elseif ($cur['aktif'] === null) {
                    if ($cur['no'] === null && preg_match('/^\d{1,3}$/', $teks) === 1) {
                        $cur['no'] = (int) $teks;
                    }
                } elseif ($cur['aktif'] !== 'bentuk' || preg_match('/^\(?[\p{L} ]{1,25}\)?$/u', $teks) === 1) {
                    $cur[$cur['aktif']] = trim($cur[$cur['aktif']].' '.$this->nilaiLabel($teks));
                } else {
                    $cur['fase'] = 'badan';
                    $cur['badan'][] = $b;
                }

                continue;
            }

            if ($cur['fase'] === 'kunci') {
                if ($this->barisKunci($teks)) {
                    $cur['kunci'] = trim($cur['kunci'].' '.$this->nilaiLabel($teks));

                    continue;
                }
                $cur['fase'] = 'badan';
            }

            $cur['badan'][] = $b;
        }
        if ($cur !== null) {
            $daftar[] = $cur;
        }

        $hasil = [];
        foreach ($daftar as $urut => $blk) {
            $hasil[] = $this->susunTemplate($blk, $blk['no'] ?? $urut + 1, $peringatan);
        }

        return $hasil;
    }

    /** Apakah dalam beberapa baris ke depan ada label Sub Kompetensi/Bentuk Soal (tanda tabel meta soal berikutnya). */
    private function labelMetaBerikut(array $blok, int $dari, int $jumlah): bool
    {
        for ($j = $dari + 1; $j < min($jumlah, $dari + 9); $j++) {
            if ($blok[$j]['jenis'] === 'baris' && (preg_match(self::R_SUB, $blok[$j]['teks']) === 1 || preg_match(self::R_BENTUK, $blok[$j]['teks']) === 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $blk
     * @param  list<string>  $peringatan
     * @return array<string, mixed>
     */
    private function susunTemplate(array $blk, int $nomor, array &$peringatan): array
    {
        $bentuk = mb_strtolower($blk['bentuk']);
        $tipe = match (true) {
            str_contains($bentuk, 'mcma'), str_contains($bentuk, 'lebih dari satu') => 'PGK_MCMA',
            str_contains($bentuk, 'kategori') => 'PGK_KATEGORI',
            str_contains($bentuk, 'pilihan ganda'), preg_match('/\bpg\b/u', $bentuk) === 1 => 'PG',
            str_contains($bentuk, 'essay'), str_contains($bentuk, 'esai'), str_contains($bentuk, 'uraian') => 'ESSAY',
            default => null,
        };

        $draf = $this->drafKosong($nomor, $blk['komp'], $blk['sub']);
        $badan = $blk['badan'];

        $header = null;
        foreach ($badan as $i => $t) {
            if ($t['jenis'] === 'baris' && preg_match(self::R_HEADER_KATEGORI, $t['teks']) === 1) {
                $header = $i;
                break;
            }
        }

        if ($header !== null || $tipe === 'PGK_KATEGORI') {
            $tipe = 'PGK_KATEGORI';
        }

        if ($header !== null) {
            $this->isiStem($draf, $this->itemBadan(array_slice($badan, 0, $header)));
            $draf['opsi'] = $this->pernyataanTabel(array_slice($badan, $header + 1));
            $draf['opsiGambar'] = array_fill(0, count($draf['opsi']), null);
        } elseif ($tipe === 'ESSAY') {
            $this->isiStem($draf, $this->itemBadan($badan));
        } else {
            $items = $this->itemBadan($badan);
            [$stem, $opsiItem, $ditebak] = $this->bagiStemOpsi($items);
            $this->isiStem($draf, $stem);
            if ($opsiItem !== []) {
                [$draf['opsi'], $draf['opsiGambar']] = $this->susunPilihan($opsiItem, $nomor, $peringatan);
                if ($ditebak) {
                    $peringatan[] = "Soal {$nomor}: batas pertanyaan dan pilihan ditebak dari paragraf terakhir, periksa kembali.";
                }
            }
        }

        if ($tipe === null) {
            $tipe = $draf['opsi'] === [] ? 'ESSAY' : 'PG';
            if ($blk['bentuk'] === '') {
                $peringatan[] = "Soal {$nomor}: bentuk soal tidak tertulis, dibaca sebagai ".($tipe === 'ESSAY' ? 'essay' : 'pilihan ganda').'.';
            }
        }
        if ($tipe === 'PG' && preg_match('/jawaban\s+benar\s+lebih\s+dari\s+satu/iu', $draf['teks']) === 1) {
            $tipe = 'PGK_MCMA';
        }
        if ($tipe !== 'ESSAY' && count($draf['opsi']) < 2) {
            $peringatan[] = "Soal {$nomor}: pilihan jawaban tidak terbaca, isi sendiri di editor.";
        }

        $draf['tipe'] = $tipe;
        $draf['kunci'] = $this->bacaKunci($tipe, $blk['kunci'], $draf['opsi'], $nomor, $peringatan);

        return $this->periksaStem($draf, $peringatan);
    }

    /**
     * Baris tabel pernyataan Kategori: "A. teks" dengan lanjutan baris di bawahnya.
     *
     * @param  list<array<string, mixed>>  $token
     * @return list<string>
     */
    private function pernyataanTabel(array $token): array
    {
        $hasil = [];
        foreach ($token as $t) {
            if ($t['jenis'] !== 'baris') {
                continue;
            }
            $teks = trim(str_replace("\t", ' ', $t['teks']));
            $huruf = chr(ord('A') + count($hasil));
            if (preg_match('/^\(?'.$huruf.'\s*[.)]\s*(.*)$/iu', $teks, $m) === 1) {
                $hasil[] = $m[1];
            } elseif ($hasil !== []) {
                $hasil[count($hasil) - 1] = trim($hasil[count($hasil) - 1].' '.$teks);
            }
        }

        return array_slice(array_values(array_filter($hasil, fn ($p) => $p !== '')), 0, self::MAKS_PILIHAN);
    }

    /**
     * Pisahkan item badan menjadi pertanyaan dan pilihan. Pertanyaan berakhir di paragraf terakhir yang berbunyi
     * penutup soal dan masih diikuti 2-8 paragraf; kalau tak ada, lima paragraf terakhir dianggap pilihan.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: bool}
     */
    private function bagiStemOpsi(array $items): array
    {
        $posisi = array_keys(array_filter($items, fn ($it) => $it['t'] === 'par'));
        $n = count($posisi);

        // Jalur utama: pilihan berhuruf ("A." lalu "B." dst. berurutan, huruf kapital); yang terakhir menang supaya
        // daftar "a. b. c." di dalam pertanyaan tidak tertukar.
        for ($k = $n - 1; $k >= 0; $k--) {
            if (preg_match('/^\(?A\s*[.)](?:\s|$)/u', $items[$posisi[$k]]['teks']) !== 1) {
                continue;
            }
            $berurutan = 1;
            for ($j = $k + 1; $j < $n; $j++) {
                if (preg_match('/^\(?'.chr(ord('A') + $berurutan).'\s*[.)](?:\s|$)/u', $items[$posisi[$j]]['teks']) !== 1) {
                    break;
                }
                $berurutan++;
            }
            if ($berurutan >= 2) {
                return [array_slice($items, 0, $posisi[$k]), array_slice($items, $posisi[$k]), false];
            }
        }

        if ($n < 3) {
            return [$items, [], false];
        }

        $batas = null;
        for ($k = $n - 3; $k >= 0 && $n - 1 - $k <= self::MAKS_PILIHAN; $k--) {
            if (preg_match(self::R_AKHIR_PERTANYAAN, $items[$posisi[$k]]['teks']) === 1) {
                $batas = $posisi[$k];
                break;
            }
        }

        $ditebak = false;
        if ($batas === null) {
            $ditebak = true;
            $jumlahPilihan = min(5, $n - 1);
            $batas = $posisi[$n - 1 - $jumlahPilihan];
        }

        return [array_slice($items, 0, $batas + 1), array_slice($items, $batas + 1), $ditebak];
    }

    /**
     * Pilihan dari item setelah pertanyaan. Paragraf membuka pilihan baru; gambar menempel ke pilihan bergambar
     * terakhir yang belum punya gambar, atau menjadi pilihan sendiri (pilihan hanya gambar) bila belum ada teks.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<string>  $peringatan
     * @return array{0: list<string>, 1: list<int|null>}
     */
    private function susunPilihan(array $items, int $nomor, array &$peringatan): array
    {
        $adaTeks = (bool) array_filter($items, fn ($it) => $it['t'] === 'par');
        $opsi = [];
        $gambar = [];
        $diragukan = false;

        foreach ($items as $it) {
            if ($it['t'] === 'par') {
                // Huruf pilihan ("A. ") dari penomoran daftar dibuang; editor memberi huruf sendiri.
                $opsi[] = preg_replace('/^\(?'.chr(ord('A') + count($opsi)).'\s*[.)]\s*/u', '', $it['teks']) ?? $it['teks'];
                $gambar[] = null;

                continue;
            }

            $terakhir = count($opsi) - 1;
            if ($adaTeks && $terakhir >= 0 && $gambar[$terakhir] === null) {
                $gambar[$terakhir] = $it['id'];
                $diragukan = $diragukan || $opsi[$terakhir] !== '';
            } else {
                $opsi[] = '';
                $gambar[] = $it['id'];
            }
        }

        if ($diragukan) {
            $peringatan[] = "Soal {$nomor}: gambar di antara pilihan dipasang ke pilihan di atasnya, periksa kembali.";
        }
        if (count($opsi) > self::MAKS_PILIHAN) {
            $peringatan[] = "Soal {$nomor}: pilihan lebih dari ".self::MAKS_PILIHAN.', sisanya dibuang.';
            $opsi = array_slice($opsi, 0, self::MAKS_PILIHAN);
            $gambar = array_slice($gambar, 0, self::MAKS_PILIHAN);
        }

        return [$opsi, $gambar];
    }

    // ---------------------------------------------------------------- format bernomor

    /**
     * @param  list<array<string, mixed>>  $blok
     * @param  list<string>  $peringatan
     * @return list<array<string, mixed>>
     */
    private function bacaBernomor(array $blok, array &$peringatan): array
    {
        $adaSatu = false;
        foreach ($blok as $b) {
            if ($b['jenis'] === 'baris' && preg_match(self::R_NOMOR, $b['teks'], $m) === 1 && (int) $m[1] === 1) {
                $adaSatu = true;
                break;
            }
        }

        $hasil = [];
        $cur = null;
        $diharapkan = null;
        // Gambar yang muncul di antara soal (sebelum nomor berikutnya) menjadi gambar soal berikutnya; yang didahului
        // kalimat "untuk soal nomor X sampai Y" dibagi ke semua soal dalam rentang itu (wacana bersama).
        $zonaGambar = [];
        $wacanaAktif = null;
        /** @var list<array{dari: int, sampai: int, teks: string, gambar: list<int>}> $wacana */
        $wacana = [];

        $tutup = function () use (&$cur, &$hasil): void {
            if ($cur !== null) {
                $hasil[] = $cur;
            }
            $cur = null;
        };

        foreach ($blok as $b) {
            if ($b['jenis'] === 'gambar') {
                if ($cur !== null && $cur['fase'] === 'stem') {
                    $cur['draf']['gambar'][] = $b['id'];
                } elseif ($cur !== null && $cur['fase'] === 'opsi') {
                    $this->pasangGambarPilihan($cur['draf'], $b['id']);
                } else {
                    $zonaGambar[] = $b['id'];
                    if ($wacanaAktif !== null) {
                        $wacana[$wacanaAktif]['gambar'][] = $b['id'];
                    }
                }

                continue;
            }

            $teks = $b['teks'];
            $mulai = null;
            if (preg_match(self::R_NOMOR, $teks, $m) === 1) {
                $nomorBaris = (int) $m[1];
                // Nomor 1 boleh muncul lagi sebagai bagian baru, tetapi hanya setelah soal sebelumnya lengkap
                // (supaya daftar bernomor di dalam pertanyaan tidak memecah soal).
                $lengkap = $cur === null || $cur['fase'] === 'selesai' || count($cur['draf']['opsi']) >= 2;
                if ($diharapkan === null) {
                    $mulai = ! $adaSatu || $nomorBaris === 1 ? $nomorBaris : null;
                } elseif (
                    $nomorBaris === $diharapkan
                    || ($lengkap && $nomorBaris > $diharapkan && $nomorBaris <= $diharapkan + 10) // soal yang dihapus guru menyisakan lompatan nomor
                    || ($lengkap && $nomorBaris === 1)
                ) {
                    $mulai = $nomorBaris;
                }
            }

            if ($mulai !== null) {
                $tutup();
                $draf = $this->drafKosong($mulai, '', '');
                $draf['teks'] = trim(str_replace("\t", ' ', ($m[2] ?? '').($m[3] ?? '')));
                $draf['gambar'] = $zonaGambar;

                foreach ($wacana as $w) {
                    if ($mulai >= $w['dari'] && $mulai <= $w['sampai']) {
                        $draf['teks'] = trim($w['teks']."\n".$draf['teks']);
                        $draf['gambar'] = array_values(array_unique([...$w['gambar'], ...$draf['gambar']]));
                    }
                }
                $zonaGambar = [];
                $wacanaAktif = null;
                $cur = ['draf' => $draf, 'fase' => 'stem', 'kunciMentah' => ''];
                $diharapkan = $mulai + 1;

                continue;
            }

            if ($cur !== null && preg_match(self::R_KUNCI_BERNOMOR, $teks, $mk) === 1) {
                $cur['kunciMentah'] = trim($mk[1]);
                $cur['fase'] = 'selesai';

                continue;
            }

            $opsiCocok = $cur !== null && in_array($cur['fase'], ['stem', 'opsi'], true)
                && preg_match(self::R_OPSI, $teks, $mo) === 1
                && strtoupper($mo[1]) === chr(ord('A') + count($cur['draf']['opsi']));
            if ($opsiCocok) {
                $cur['fase'] = 'opsi';
                $cur['draf']['opsi'][] = trim(str_replace("\t", ' ', ($mo[2] ?? '').($mo[3] ?? '')));
                $cur['draf']['opsiGambar'][] = null;

                continue;
            }

            if ($cur !== null && $cur['fase'] === 'stem') {
                $cur['draf']['teks'] = trim($cur['draf']['teks'].($b['paragraf'] ? "\n" : ' ').str_replace("\t", ' ', $teks));
            } elseif ($cur !== null && $cur['fase'] === 'opsi') {
                $terakhir = count($cur['draf']['opsi']) - 1;
                $cur['draf']['opsi'][$terakhir] = trim($cur['draf']['opsi'][$terakhir].' '.str_replace("\t", ' ', $teks));
            } elseif (preg_match(self::R_RENTANG_WACANA, $teks, $mr) === 1) {
                $wacana[] = ['dari' => (int) $mr[1], 'sampai' => (int) $mr[2], 'teks' => $teks, 'gambar' => $zonaGambar];
                $wacanaAktif = array_key_last($wacana);
            }
            // Teks lain di antara soal (judul bagian, petunjuk) bukan bagian soal manapun dan dilewati.
        }
        $tutup();

        return $this->selesaikanBernomor($hasil, $peringatan);
    }

    /** @param array<string, mixed> $draf */
    private function pasangGambarPilihan(array &$draf, int $id): void
    {
        $terakhir = count($draf['opsi']) - 1;
        if ($terakhir >= 0 && $draf['opsiGambar'][$terakhir] === null) {
            $draf['opsiGambar'][$terakhir] = $id;
        } else {
            $draf['opsi'][] = '';
            $draf['opsiGambar'][] = $id;
        }
    }

    /**
     * @param  list<array{draf: array<string, mixed>, fase: string, kunciMentah: string}>  $daftar
     * @param  list<string>  $peringatan
     * @return list<array<string, mixed>>
     */
    private function selesaikanBernomor(array $daftar, array &$peringatan): array
    {
        // Daftar bernomor di awal dokumen (petunjuk pengerjaan) bukan soal: tanpa pilihan, dan nomor 1 muncul lagi sesudahnya.
        $pertamaBerpilihan = null;
        foreach ($daftar as $i => $d) {
            if (count($d['draf']['opsi']) >= 2) {
                $pertamaBerpilihan = $i;
                break;
            }
        }
        if ($pertamaBerpilihan !== null && $pertamaBerpilihan > 0) {
            $adaSatuLagi = false;
            foreach (array_slice($daftar, 1) as $d) {
                $adaSatuLagi = $adaSatuLagi || $d['draf']['nomor'] === 1;
            }
            if ($adaSatuLagi) {
                $daftar = array_slice($daftar, $pertamaBerpilihan);
                $peringatan[] = 'Daftar bernomor di awal dokumen (petunjuk) dilewati.';
            }
        }

        $hasil = [];
        foreach ($daftar as $d) {
            $draf = $d['draf'];
            $nomor = $draf['nomor'];

            $tipe = $draf['opsi'] === [] ? 'ESSAY' : 'PG';
            if ($tipe === 'PG' && (preg_match('/jawaban\s+benar\s+lebih\s+dari\s+satu/iu', $draf['teks']) === 1 || $this->banyakJawaban($d['kunciMentah']))) {
                $tipe = 'PGK_MCMA';
            }
            $draf['tipe'] = $tipe;
            $draf['kunci'] = $this->bacaKunci($tipe, $d['kunciMentah'], $draf['opsi'], $nomor, $peringatan);
            if ($tipe !== 'ESSAY' && count($draf['opsi']) < 2) {
                $peringatan[] = "Soal {$nomor}: pilihan jawaban tidak terbaca, isi sendiri di editor.";
            }

            $hasil[] = $this->periksaStem($draf, $peringatan);
        }

        return $hasil;
    }

    private function banyakJawaban(string $kunci): bool
    {
        return preg_match_all('/\b[A-Ha-h]\b/u', $kunci) > 1 || preg_match_all('/\d{1,2}/', $kunci) > 1;
    }

    // ---------------------------------------------------------------- kunci

    /**
     * Ubah teks kunci jadi format penyimpanan: PG "2", MCMA "0,2,4", Kategori "1,0,1". Kosong (disertai peringatan)
     * bila tidak terbaca; editor tetap memaksa guru memilih sebelum menyimpan.
     *
     * @param  list<string>  $opsi
     * @param  list<string>  $peringatan
     */
    private function bacaKunci(string $tipe, string $mentah, array $opsi, int $nomor, array &$peringatan): string
    {
        if ($tipe === 'ESSAY') {
            return '';
        }

        $mentah = trim($mentah);
        $n = count($opsi);
        $kunci = match ($tipe) {
            'PG' => $this->kunciPg($mentah, $opsi),
            'PGK_MCMA' => $this->kunciMcma($mentah, $n),
            'PGK_KATEGORI' => $this->kunciKategori($mentah, $n),
            default => null,
        };

        if ($kunci === null) {
            $peringatan[] = "Soal {$nomor}: kunci jawaban ".($mentah === '' ? 'belum diisi' : 'tidak terbaca').' di dokumen, pilih sendiri di editor.';

            return '';
        }

        return $kunci;
    }

    /** @param list<string> $opsi */
    private function kunciPg(string $mentah, array $opsi): ?string
    {
        $n = count($opsi);
        if (preg_match('/^\(?([A-Ha-h])\)?[\s.):]*$/u', $mentah, $m) === 1) {
            $i = ord(strtoupper($m[1])) - ord('A');

            return $i < $n ? (string) $i : null;
        }
        if (preg_match('/^(?:pernyataan|pilihan|opsi)?\s*(\d{1,2})$/iu', $mentah, $m) === 1) {
            $i = (int) $m[1] - 1;

            return $i >= 0 && $i < $n ? (string) $i : null;
        }

        $normal = fn (string $s) => preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($s));
        foreach ($opsi as $i => $teks) {
            if ($mentah !== '' && $normal($teks) !== '' && $normal($teks) === $normal($mentah)) {
                return (string) $i;
            }
        }

        return null;
    }

    private function kunciMcma(string $mentah, int $n): ?string
    {
        $indeks = [];
        if (preg_match_all('/\d{1,2}/', $mentah, $angka) > 0) {
            foreach ($angka[0] as $a) {
                $indeks[] = (int) $a - 1;
            }
        } elseif (preg_match_all('/\b([A-Ha-h])\b/u', $mentah, $huruf) > 0) {
            foreach ($huruf[1] as $h) {
                $indeks[] = ord(strtoupper($h)) - ord('A');
            }
        }

        $indeks = array_values(array_unique(array_filter($indeks, fn ($i) => $i >= 0 && $i < $n)));
        sort($indeks);

        return $indeks === [] ? null : implode(',', $indeks);
    }

    private function kunciKategori(string $mentah, int $n): ?string
    {
        $peta = [];
        if (preg_match_all('/\b([A-Ha-h])\s*[.):]?\s*(benar|salah)\b/iu', $mentah, $pasangan, PREG_SET_ORDER) > 0) {
            foreach ($pasangan as $p) {
                $peta[ord(strtoupper($p[1])) - ord('A')] = mb_strtolower($p[2]) === 'benar' ? '1' : '0';
            }
        } elseif (preg_match_all('/\b(benar|salah)\b/iu', $mentah, $urut) === $n && $n > 0) {
            foreach ($urut[1] as $i => $nilai) {
                $peta[$i] = mb_strtolower($nilai) === 'benar' ? '1' : '0';
            }
        }

        if ($peta === []) {
            return null;
        }

        $hasil = [];
        for ($i = 0; $i < $n; $i++) {
            $hasil[] = $peta[$i] ?? '';
        }

        return implode(',', $hasil);
    }

    // ---------------------------------------------------------------- bersama

    /** @return array<string, mixed> */
    private function drafKosong(int $nomor, string $kompetensi, string $sub): array
    {
        return [
            'nomor' => $nomor,
            'tipe' => 'PG',
            'teks' => '',
            'gambar' => [],
            'kompetensi' => $kompetensi,
            'subKompetensi' => $sub,
            'opsi' => [],
            'opsiGambar' => [],
            'kunci' => '',
        ];
    }

    /**
     * Item badan: paragraf (baris yang dirangkai dari baris-baris sambungan) dan gambar, berurutan.
     *
     * @param  list<array<string, mixed>>  $token
     * @return list<array<string, mixed>>
     */
    private function itemBadan(array $token): array
    {
        $items = [];
        $par = null;
        $hurufBerikut = 0;
        foreach ($token as $t) {
            if ($t['jenis'] === 'gambar') {
                if ($par !== null) {
                    $items[] = $par;
                    $par = null;
                }
                $items[] = ['t' => 'gambar', 'id' => $t['id']];

                continue;
            }

            $teks = trim(str_replace("\t", ' ', $t['teks']));

            // Pilihan berhuruf dan butir daftar selalu paragraf sendiri, walau jarak antarbarisnya rapat.
            $pilihan = false;
            if (preg_match(self::R_OPSI, $teks, $m) === 1) {
                $urut = ord(strtoupper($m[1])) - ord('A');
                if ($urut === 0 || $urut === $hurufBerikut) {
                    $pilihan = true;
                    $hurufBerikut = $urut + 1;
                }
            }
            $butir = preg_match('/^[•·▪■●○◦\-–]\s/u', $teks) === 1;

            if ($par === null || $t['paragraf'] || $pilihan || $butir) {
                if ($par !== null) {
                    $items[] = $par;
                }
                $par = ['t' => 'par', 'teks' => $teks];
            } else {
                $par['teks'] .= ' '.$teks;
            }
        }
        if ($par !== null) {
            $items[] = $par;
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $draf
     * @param  list<array<string, mixed>>  $items
     */
    private function isiStem(array &$draf, array $items): void
    {
        $paragraf = [];
        foreach ($items as $it) {
            if ($it['t'] === 'gambar') {
                $draf['gambar'][] = $it['id'];
            } else {
                $paragraf[] = $it['teks'];
            }
        }
        $draf['teks'] = implode("\n", $paragraf);
    }

    /**
     * @param  array<string, mixed>  $draf
     * @param  list<string>  $peringatan
     * @return array<string, mixed>
     */
    private function periksaStem(array $draf, array &$peringatan): array
    {
        $draf['gambar'] = array_slice(array_values(array_unique($draf['gambar'])), 0, (int) config('classroom.max_gambar_soal'));
        if (trim($draf['teks']) === '') {
            $peringatan[] = "Soal {$draf['nomor']}: teks pertanyaan tidak terbaca, tulis sendiri di editor.";
        }
        if (mb_strlen($draf['teks']) > 10000) {
            $draf['teks'] = mb_substr($draf['teks'], 0, 10000);
            $peringatan[] = "Soal {$draf['nomor']}: teks terlalu panjang dan dipotong 10.000 karakter.";
        }

        return $draf;
    }

    private function nilaiLabel(string $teks): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\t", ' ', $teks)) ?? $teks);
    }

    /** Baris yang masih bagian sel Kunci (huruf, nomor, daftar nomor, atau pasangan "A. Benar"), bukan awal badan soal. */
    private function barisKunci(string $teks): bool
    {
        $t = trim(str_replace("\t", ' ', $teks));

        return preg_match('/^pernyataan$/iu', $t) === 1
            || preg_match('/^[A-Ha-h]\s*[.):]?\s*(?:benar|salah)$/iu', $t) === 1
            || preg_match('/^(?:benar|salah)$/iu', $t) === 1
            || preg_match('/^(?:(?:pernyataan|pilihan)\s*)?\d{1,2}(?:\s*[,;&]\s*(?:(?:pernyataan|pilihan)\s*)?\d{1,2})*$/iu', $t) === 1
            || preg_match('/^[A-Ha-h](?:\s*[,;&]\s*[A-Ha-h])*$/u', $t) === 1;
    }
}
