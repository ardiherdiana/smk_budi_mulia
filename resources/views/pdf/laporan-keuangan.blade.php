<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Keuangan SPPD {{ $tahun }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; }
        h2 { text-align: center; margin-bottom: 2px; }
        p.sub { text-align: center; margin-top: 0; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px 6px; text-align: left; }
        th { background: #eee; }
        td.num { text-align: right; }
        tfoot td { font-weight: bold; }
    </style>
</head>
<body>
    <h2>Laporan Keuangan Perjalanan Dinas</h2>
    <p class="sub">Tahun {{ $tahun }}</p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Pemohon</th>
                <th>Tujuan</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th class="num">Total Pencairan (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->pemohon->name }}</td>
                    <td>{{ $item->tujuan }}</td>
                    <td>{{ $item->tanggal_berangkat->translatedFormat('d M Y') }}</td>
                    <td>{{ $item->status->label() }}</td>
                    <td class="num">{{ number_format((float) $item->pencairans->sum('jumlah'), 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">TOTAL</td>
                <td class="num">{{ number_format((float) $items->flatMap->pencairans->sum('jumlah'), 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
