@php
    // Data tampilan disiapkan SppdPdfData; template ini hanya mengatur tata letak.
    extract(app(\App\Services\Sppd\SppdPdfData::class)->data($pengajuan));

    // Jalur gambar tanda tangan Kepala Sekolah; hanya diisi untuk arsip (ArsipSppd). Unduhan biasa dikosongkan untuk pulpen.
    $ttdKepsek ??= null;

    /** Gambar tanda tangan Kepala Sekolah di dalam ruang kosong setinggi $tinggi pt (tinggi wadah tetap, tata letak tidak bergeser). */
    $ttd = fn (int $tinggi): string => $ttdKepsek ? '<img src="'.e($ttdKepsek).'" style="height: '.$tinggi.'pt;">' : '';

    /** Baris "a. teks" dengan indentasi menggantung. */
    $li = fn (string $huruf, ?string $teks, int $lebar = 13): string => '<table class="li"><tr>'
        .'<td style="width: '.$lebar.'pt;">'.e($huruf).'</td>'
        .'<td>'.($teks === null || $teks === '' ? '&nbsp;' : e($teks)).'</td></tr></table>';

    /** Blok "label : nilai" (sisi belakang). */
    $kv = function (array $baris, int $lebarLabel = 70): string {
        $html = '<table class="kv">';
        foreach ($baris as [$label, $nilai]) {
            $html .= '<tr><td style="width: '.$lebarLabel.'pt;">'.e($label).'</td><td style="width: 6pt;">:</td><td>'.($nilai === null || $nilai === '' ? '&nbsp;' : e($nilai)).'</td></tr>';
        }

        return $html.'</table>';
    };

    // Nama pejabat tujuan dan tanggal tiba/pulang diisi manual dengan pulpen oleh pejabat penerima.
    $pejabatTujuan = [['Kepala', '']];

    /*
     * Kolom form berukuran tetap dan template ini wajib satu halaman. Isi yang panjang (maksud, pengikut,
     * keterangan, akun) diperkirakan dalam jumlah baris, lalu ukuran font tabel diturunkan bertahap.
     * Pembagi per baris dihitung untuk font 10pt (ukuran awal, sama dengan format Word).
     * Ambang dikalibrasi ulang setelah baris 11 (Lumsum Biaya) ditambahkan: tiap tingkat menyisakan ruang di halaman.
     */
    $perkiraanBaris = fn (?string $teks, int $perBaris): int => $teks === null || $teks === '' ? 1 : (int) ceil(mb_strlen($teks) / $perBaris);
    $barisPengikut = max(1, $pengikuts->sum(fn ($p) => $perkiraanBaris($p->name, 26)), $pengikuts->sum(fn ($p) => $perkiraanBaris($p->jabatan, 34)));
    $beban = $perkiraanBaris($pengajuan->maksud, 38)
        + $barisPengikut
        + $perkiraanBaris($keterangan, 40)
        + $perkiraanBaris($akun, 38)
        + $perkiraanBaris($alatAngkutan, 38)
        + $perkiraanBaris($tujuan, 34)
        + $perkiraanBaris($pemohon->jabatan, 34);
    $fontTabel = match (true) {
        $beban <= 8 => 10.0,
        $beban <= 10 => 9.0,
        $beban <= 12 => 8.3,
        $beban <= 17 => 7.5,
        $beban <= 21 => 7.0,
        default => 6.2,
    };
    $spasiSel = 0.6;

    // Sisi belakang: kolom tiba/berangkat sempit, jadi font turun bila tujuan panjang.
    $fontBelakang = match (true) {
        mb_strlen((string) $tujuan) <= 90 => 10.0,
        mb_strlen((string) $tujuan) <= 150 => 9.0,
        default => 8.0,
    };
    // Tinggi blok tiba/berangkat mengikuti panjang tujuan (±18 karakter per baris pada 10pt).
    $tinggiBlok = max(104 * $fontBelakang / 10, ($perkiraanBaris($tujuan, (int) floor(18 * 10 / $fontBelakang)) + 7.5) * 1.24 * $fontBelakang);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>SPPD {{ $sppd->nomor_sppd }}</title>
    <style>
        @page { margin: 14pt 26pt 14pt 26pt; }
        body { margin: 0; font-family: Helvetica, sans-serif; font-size: 10pt; line-height: 1.2; color: #000; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; }

        table.halaman > tbody > tr > td { vertical-align: top; }
        td.depan { width: 398pt; padding-right: 12pt; }
        td.belakang-kolom { padding-left: 14pt; border-left: 0.7pt dashed #666; }

        .kop td { vertical-align: middle; }
        .kop .teks div { text-align: center; }
        .garis-tebal { border-top: 2.2pt solid #000; margin-top: 3pt; }
        .garis-tipis { border-top: 0.7pt solid #000; margin-top: 1.3pt; }

        table.meta { width: auto; margin-top: 4pt; }
        table.meta td { padding: 0.2pt 0; }

        .judul { text-align: center; font-size: 12pt; font-weight: bold; text-decoration: underline; margin: 3pt 0 3pt; }

        table.isian td { border: 0.6pt solid #000; padding: {{ $spasiSel }}pt 0 {{ $spasiSel }}pt 5pt; font-size: {{ $fontTabel }}pt; }
        table.isian td.no { text-align: center; padding: {{ $spasiSel }}pt 0; }
        table.isian td.mid { vertical-align: middle; }
        table.li { width: 100%; }
        table.isian table.li td, table.li td { border: 0; padding: 0; }

        table.kv { width: 100%; }
        table.kv td { padding: 0.9pt 0; }

        .kecil { font-size: 6.4pt; color: #222; line-height: 1.22; }
        .nama { font-weight: bold; text-decoration: underline; }

        .judul2 { font-size: 11.5pt; font-weight: bold; margin: 0 0 6pt; }
        .judul2 span { font-weight: normal; font-size: 9.5pt; color: #333; }
        table.belakang td.sel { border: 0.6pt solid #000; padding: 6pt 8pt; }
        .garis-tanda { border-bottom: 0.6pt dashed #000; height: 20pt; }

    </style>
</head>
<body>
    <table class="halaman">
        <tr>
            {{-- ===== Sisi depan: isian SPPD ===== --}}
            <td class="depan">
                <table class="kop">
                    <tr>
                        <td style="width: 66pt;"><img src="{{ public_path('logo_smk.png') }}" style="width: 60pt;"></td>
                        <td class="teks">
                            <div style="font-size: 10pt; font-weight: bold;">YAYASAN AL-ULYA KARAWANG</div>
                            <div style="font-size: 18pt; font-weight: bold; line-height: 1.05;">SMK BUDI MULIA</div>
                            <div style="font-size: 8.8pt; font-weight: bold;">Terakreditasi A, berdasarkan SK BAN-SM nomor 763/BAN-SM/SK/2019</div>
                            <div style="font-size: 8.8pt; font-weight: bold;">Jl. Ciherang Wadas, Kec. Telukjambe Timur Kab. Karawang 41361</div>
                            <div style="font-family: 'Times-Roman', serif; font-size: 9.6pt; font-weight: bold;">Telepon (0267) 8458459 e-Mail smk_budimulia@teachers.org</div>
                        </td>
                        <td style="width: 12pt;">&nbsp;</td>
                    </tr>
                </table>
                <div class="garis-tebal"></div>
                <div class="garis-tipis"></div>

                <table class="meta">
                    <tr><td style="width: 56pt;">Lampiran</td><td style="width: 8pt;">:</td><td>Surat Perjalanan Dinas</td></tr>
                    <tr><td>Nomor</td><td>:</td><td>{{ $sppd->nomor_sppd }}</td></tr>
                    <tr><td>Tanggal</td><td>:</td><td>{{ $tanggalTerbit }}</td></tr>
                </table>

                <div class="judul">SURAT PERJALANAN DINAS (SPPD)</div>

                <table class="isian">
                    <tr>
                        <td class="no" style="width: 20pt;">1</td>
                        <td style="width: 160pt;">Pengguna Anggaran/kuasa Pengguna Anggaran</td>
                        <td style="width: 206pt;">{{ $namaPejabat }}</td>
                    </tr>
                    <tr>
                        <td class="no">2</td>
                        <td>Nama/NIP pegawai yang melaksanakan Perjalanan Dinas</td>
                        <td class="mid">{{ $pemohon->name }}</td>
                    </tr>
                    <tr>
                        <td class="no">3</td>
                        <td>{!! $li('a.', 'Pangkat dan Golongan') !!}{!! $li('b.', 'Jabatan') !!}{!! $li('c.', 'Tingkat Biaya Perjalanan Dinas') !!}</td>
                        <td>{!! $li('a.', '-') !!}{!! $li('b.', $pemohon->jabatan ?: '-') !!}{!! $li('c.', 'Disesuaikan') !!}</td>
                    </tr>
                    <tr>
                        <td class="no">4</td>
                        <td>Maksud Perjalanan Dinas</td>
                        <td>{{ $pengajuan->maksud }}</td>
                    </tr>
                    <tr>
                        <td class="no">5</td>
                        <td>Alat angkutan yang digunakan</td>
                        <td>{{ $alatAngkutan }}&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="no">6</td>
                        <td>{!! $li('a.', 'Tempat Berangkat') !!}{!! $li('b.', 'Tempat tujuan') !!}</td>
                        <td>{!! $li('a.', $sekolah) !!}{!! $li('b.', $tujuan) !!}</td>
                    </tr>
                    <tr>
                        <td class="no">7</td>
                        <td>{!! $li('a.', 'Lama berangkat') !!}{!! $li('b.', 'Tanggal berangkat') !!}{!! $li('c.', 'Tanggal harus kembali/tiba di tempat baru*)') !!}</td>
                        <td>{!! $li('a.', $lamaHari.' Hari') !!}{!! $li('b.', $tanggalBerangkat.', '.$jamBerangkat.' WIB') !!}{!! $li('c.', $tanggalKembali.', '.$jamKembali.' WIB') !!}</td>
                    </tr>
                    <tr>
                        <td class="no">8</td>
                        <td>Pengikut : Nama</td>
                        <td>Jabatan</td>
                    </tr>
                    <tr>
                        <td class="no">9</td>
                        <td>
                            @forelse ($pengikuts as $i => $pengikut)
                                <div>{{ $i + 1 }}. {{ $pengikut->name }}</div>
                            @empty
                                &nbsp;
                            @endforelse
                        </td>
                        <td>
                            @forelse ($pengikuts as $pengikut)
                                <div>{{ $pengikut->jabatan ?: '-' }}</div>
                            @empty
                                &nbsp;
                            @endforelse
                        </td>
                    </tr>
                    <tr>
                        <td class="no">10</td>
                        <td>Pembebanan Anggaran{!! $li('a.', 'Instansi', 12) !!}{!! $li('b.', 'Akun', 12) !!}</td>
                        <td>
                            <div>&nbsp;</div>
                            <div>{{ $sekolah }}</div>
                            <div>{{ $akun }}&nbsp;</div>
                        </td>
                    </tr>
                    <tr>
                        {{-- Total lumsum yang dicairkan bendahara; kosong (ditulis tangan) bila belum ada. --}}
                        <td class="no">11</td>
                        <td>Lumsum Biaya :</td>
                        <td>{{ $lumsum }}&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="no">12</td>
                        <td>Keterangan lain-lain</td>
                        <td>{{ $keterangan }}&nbsp;</td>
                    </tr>
                </table>
                <table style="margin-top: 2pt;">
                    <tr>
                        <td style="width: 196pt; padding-right: 8pt; font-size: 8.5pt;">*)coret yang tidak perlu</td>
                        <td>
                            {!! $kv([['Dikeluarkan di', 'Karawang'], ['Tanggal', $tanggalTerbit]], 76) !!}
                            <div style="margin-top: 1pt;">Kuasa Pengguna Anggaran</div>
                            <div style="height: 50pt;">{!! $ttd(46) !!}</div>
                            <div class="nama">{{ $namaPejabat }}</div>
                            <div>NIP. -,-</div>
                        </td>
                    </tr>
                </table>
            </td>

            {{-- ===== Sisi belakang: pengesahan perjalanan ===== --}}
            <td class="belakang-kolom">
                <div class="judul2">Pengesahan Perjalanan <span>&mdash; SPPD Nomor {{ $sppd->nomor_sppd }}</span></div>

                <table class="belakang" style="font-size: {{ $fontBelakang }}pt;">
                    <tr>
                        <td class="sel" colspan="2">
                            {!! $kv([['Berangkat dari', $sekolah], ['Ke', $tujuan], ['Pada Tanggal', $tanggalBerangkat]], 76) !!}
                            <table style="margin-top: 4pt;">
                                <tr>
                                    <td style="width: 52pt;">
                                    </td>
                                    <td>
                                        <div style="height: 50pt;">{!! $ttd(46) !!}</div>
                                        <div>{{ $namaPejabat }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="sel" style="width: 50%;">
                            <div style="height: {{ $tinggiBlok }}pt;">{!! $kv([['Tiba di', $tujuan], ['Pada Tanggal', ''], ...$pejabatTujuan], 70) !!}</div>
                            <div class="garis-tanda"></div>
                        </td>
                        <td class="sel">
                            <div style="height: {{ $tinggiBlok }}pt;">{!! $kv([['Berangkat dari', $tujuan], ['Ke', $sekolah], ['Pada Tanggal', ''], ...$pejabatTujuan], 70) !!}</div>
                            <div class="garis-tanda"></div>
                        </td>
                    </tr>
                    <tr>
                        <td class="sel" colspan="2">
                            <table>
                                <tr>
                                    <td style="width: 22pt;"><strong>III.</strong></td>
                                    <td>
                                        {!! $kv([['Tiba kembali di', 'Karawang'], ['Pada tanggal', $tanggalKembali]], 76) !!}
                                        <div style="margin-top: 3pt;">
                                            Telah diperiksa dengan keterangan bahwa perjalanan tersebut atas perintahnya dan semata-mata untuk
                                            kepentingan jabatan dalam waktu sesingkat-singkatnya.
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            <table style="margin-top: 5pt;">
                                <tr>
                                    <td style="width: 150pt;">
                                    </td>
                                    <td>
                                        <div>Kuasa Pengguna Anggaran</div>
                                        <div style="height: 50pt;">{!! $ttd(46) !!}</div>
                                        <div class="nama">{{ $namaPejabat }}</div>
                                        <div>NIP. -,-</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
