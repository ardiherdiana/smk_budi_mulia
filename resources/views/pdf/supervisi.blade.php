@php
    /**
     * Persis format/INSTRUMEN OBSERVASI PEMBELAJARAN MENDALAM NEW.docx: A4, margin 2,54 cm, tanpa kop. Lebar kolom
     * tabel mengikuti grid dokumen (560/4350/358/385/358/384/2621 twip). Font dokumen (Aptos 12 pt) tidak tersedia di
     * dompdf; DejaVu Sans 11 pt dipakai karena ukurannya paling mendekati dan memuat semua huruf (termasuk tanda ✓).
     *
     * @var \App\Models\Supervisi\Supervisi $supervisi
     * @var array<int, string> $skala
     */
    $selesai = $supervisi->selesai();
    $maks = count($skala);
    $perKategori = $supervisi->skor->groupBy('kategori');
    $jumlahButir = $supervisi->skor->count() ?: collect(config('supervisi.instrumen'))->flatten()->count();
    $pembagi = $jumlahButir * $maks;
    $teks = fn (?string $isi): string => filled($isi) ? nl2br(e($isi)) : '&nbsp;';
    $angka = fn (?float $n): string => $n === null ? '' : rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    $baris5sd7 = [
        5 => ['judul' => 'Kelebihan Perencanaan', 'isi' => $supervisi->kelebihan],
        6 => ['judul' => 'Hal yang perlu ditingkatkan', 'isi' => $supervisi->halDitingkatkan],
        7 => ['judul' => 'Rekomendasi Perbaikan', 'isi' => $supervisi->rekomendasi],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Instrumen Observasi Pembelajaran Mendalam - {{ $supervisi->guru?->namaTampil() }}</title>
    <style>
        @page { margin: 72pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; line-height: 1.16; color: #000; }
        p { margin: 0 0 8pt; }
        table { border-collapse: collapse; }
        .judul { text-align: center; font-weight: bold; margin-bottom: 8pt; }
        .identitas { width: 100%; margin-bottom: 8pt; }
        .identitas td { padding: 0; vertical-align: top; line-height: 1.15; }
        /* Dokumen memakai tab di 4,75 cm; DejaVu Sans lebih lebar dari Aptos, jadi kolom label sedikit dilebarkan
           supaya "Penyusun Perencanaan" tetap satu baris. */
        .identitas .label { width: 160pt; font-weight: bold; white-space: nowrap; }
        .identitas .titik { width: 9pt; font-weight: bold; }
        .skala span { display: inline-block; margin-right: 22pt; }
        .instrumen { width: 100%; }
        /* Sel docx tanpa margin atas/bawah, spasi baris 1,15. */
        .instrumen th, .instrumen td { border: 0.5pt solid #000; padding: 0 3pt; line-height: 1.15; vertical-align: top; }
        .instrumen th { font-weight: bold; text-align: center; }
        .instrumen tr { page-break-inside: avoid; }
        .instrumen .c { text-align: center; }
        .instrumen .b { font-weight: bold; }
        .instrumen .uraian { text-align: justify; }
        .instrumen .isian { height: 47pt; }
        .centang { font-family: 'DejaVu Sans', sans-serif; }
        .rumus { margin-top: 10pt; }
        .rumus td { padding: 0 2pt; vertical-align: middle; }
        .pecahan td { padding: 0 3pt; text-align: center; }
        .pecahan .atas { border-bottom: 0.6pt solid #000; }
        .ttd { margin-top: 14pt; margin-left: 288pt; }
        .ttd .ruang { height: 52pt; }
    </style>
</head>
<body>
    <div class="judul">INSTRUMEN OBSERVASI PEMBELAJARAN MENDALAM</div>

    <table class="identitas">
        <tr><td class="label">Penyusun Perencanaan</td><td class="titik">:</td><td>{{ $supervisi->guru?->namaTampil() }}</td></tr>
        <tr><td class="label">Mata Pelajaran</td><td class="titik">:</td><td>{{ $supervisi->mapel?->nama }}</td></tr>
        <tr><td class="label">Kelas</td><td class="titik">:</td><td>{{ $supervisi->rombel?->nama }}</td></tr>
        <tr><td class="label">Tanggal Supervisi</td><td class="titik">:</td><td>{{ $supervisi->tanggal->locale('id')->translatedFormat('d F Y') }}</td></tr>
        <tr><td class="label">Pemberi Umpan Balik</td><td class="titik">:</td><td>{{ $supervisi->supervisor?->namaTampil() }}</td></tr>
    </table>

    <p><strong>Petunjuk pengisian :</strong></p>
    <p class="skala">
        @foreach ($skala as $nilai => $label)
            <span>{{ $nilai }} : {{ $label }}</span>
        @endforeach
    </p>

    <table class="instrumen">
        {{-- Lebar kolom = grid docx (560/4350/358/385/358/384/2621 twip = 28/218/18/19/18/19/131 pt dari lebar teks
             451 pt) dikurangi padding 6 pt. dompdf mengabaikan <colgroup> dan table-layout: fixed membuat kolom sama
             rata, jadi lebar isi dipasang di sel header. --}}
        <thead>
            <tr>
                <th style="width: 22pt;">No</th>
                <th style="width: 210pt;">Kategori</th>
                @foreach ($skala as $nilai => $label)
                    <th style="width: 12.5pt;">{{ $nilai }}</th>
                @endforeach
                <th style="width: 122pt;">Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($perKategori->keys()->values() as $i => $kategori)
                <tr>
                    <td class="b">{{ $i + 1 }}</td>
                    <td class="b">{{ $kategori }}</td>
                    @foreach ($skala as $nilai => $label)
                        <td></td>
                    @endforeach
                    <td></td>
                </tr>
                @foreach ($perKategori->get($kategori) as $butir)
                    <tr>
                        <td></td>
                        <td class="uraian">{{ $butir->uraian }}</td>
                        @foreach ($skala as $nilai => $label)
                            <td class="c"><span class="centang">{{ $butir->skor === $nilai ? '✓' : '' }}</span></td>
                        @endforeach
                        <td>{{ $butir->catatan }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr>
                <td></td>
                <td class="b">JUMLAH SCORE</td>
                <td colspan="{{ $maks + 1 }}">{{ $selesai ? $supervisi->jumlahSkor : '' }}</td>
            </tr>
            @foreach ($baris5sd7 as $nomor => $bagian)
                <tr>
                    <td class="b">{{ $nomor }}</td>
                    <td colspan="{{ $maks + 2 }}" class="b">{{ $bagian['judul'] }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td colspan="{{ $maks + 2 }}" class="isian">{!! $teks($bagian['isi']) !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="rumus">
        <tr>
            <td>TOTAL SCORE</td>
            <td>=</td>
            <td>
                <table class="pecahan">
                    <tr><td class="atas">Jumlah Score</td></tr>
                    <tr><td>{{ $pembagi }}</td></tr>
                </table>
            </td>
            <td>x 100</td>
        </tr>
        <tr>
            <td></td>
            <td>=</td>
            <td colspan="2">{{ $selesai ? $angka($supervisi->totalSkor) : '____________' }}</td>
        </tr>
    </table>

    <div class="ttd">
        Pemberi Umpan Balik,
        <div class="ruang"></div>
        ( {{ $supervisi->supervisor?->namaTampil() ?? '______________________' }} )
    </div>
</body>
</html>
