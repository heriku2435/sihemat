<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Mutasi Tabungan - {{ $siswa->nama }}</title>
    <style>
        @page { margin: 1cm; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 5px;
        }
        .header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 18px;
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0 0 0;
            color: #7f8c8d;
            font-size: 12px;
        }
        .info-table {
            width: 100%;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .info-table td {
            padding: 4px 8px;
        }
        .mutasi-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .mutasi-table th, .mutasi-table td {
            border: 1px solid #ddd;
            padding: 4px;
            text-align: left;
        }
        .mutasi-table th {
            background-color: #34495e;
            color: #ffffff;
            font-size: 11px;
            text-transform: uppercase;
        }
        .mutasi-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .setor { color: #27ae60; font-weight: bold; }
        .tarik { color: #c0392b; font-weight: bold; }
        .footer {
            margin-top: 20px;
            width: 100%;
            font-size: 10px;
        }
        .footer-table {
            width: 100%;
            border: none;
        }
        .footer-table td {
            border: none;
            text-align: center;
            width: 50%;
        }
        .signature-space {
            height: 40px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Riwayat Mutasi Tabungan Siswa</h2>
        <p>Aplikasi SIHEMAT - Dicetak pada: {{ now()->translatedFormat('d F Y H:i') }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%"><strong>Nama Siswa</strong></td>
            <td width="35%">: {{ $siswa->nama }}</td>
            <td width="15%"><strong>Total Saldo</strong></td>
            <td width="35%">: Rp {{ number_format($siswa->saldo, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td><strong>NIS/No.Urut</strong></td>
            <td>: {{ $siswa->nis ?? '-' }} / {{ collect($siswa->rombels)->first()->pivot->nomor_urut ?? '-' }}</td>
            <td><strong>Kelas</strong></td>
            <td>: {{ collect($siswa->rombels)->first()->nama_kelas ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Periode</strong></td>
            <td colspan="3">: 
                @if($request->start_date && $request->end_date)
                    {{ \Carbon\Carbon::parse($request->start_date)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($request->end_date)->format('d/m/Y') }}
                @elseif($request->start_date)
                    Mulai {{ \Carbon\Carbon::parse($request->start_date)->format('d/m/Y') }}
                @elseif($request->end_date)
                    Sampai {{ \Carbon\Carbon::parse($request->end_date)->format('d/m/Y') }}
                @elseif($request->bulan)
                    Bulan {{ \Carbon\Carbon::parse($request->bulan . '-01')->translatedFormat('F Y') }}
                @else
                    Semua Transaksi
                @endif
                @if($request->jenis)
                    ({{ ucfirst($request->jenis) }})
                @endif
            </td>
        </tr>
    </table>

    <table class="mutasi-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="20%">Tanggal</th>
                <th width="10%">Jenis</th>
                <th width="15%" class="text-right">Nominal</th>
                <th width="15%" class="text-right">Saldo</th>
                <th width="35%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($transaksis as $index => $trx)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($trx->tanggal)->translatedFormat('d M Y') }} <span style="color: #7f8c8d; font-size: 10px;">{{ \Carbon\Carbon::parse($trx->created_at)->format('H:i') }}</span></td>
                <td>{{ ucfirst($trx->jenis) }}</td>
                <td class="text-right">
                    <span class="{{ $trx->jenis == 'setor' ? 'setor' : 'tarik' }}">
                        {{ $trx->jenis == 'setor' ? '+' : '-' }} Rp {{ number_format($trx->jumlah, 0, ',', '.') }}
                    </span>
                </td>
                <td class="text-right">Rp {{ number_format($trx->saldo_akhir, 0, ',', '.') }}</td>
                <td>{{ $trx->keterangan ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center" style="padding: 20px;">Tidak ada transaksi ditemukan pada periode ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <table class="footer-table">
            <tr>
                <td></td>
                <td>
                    Mengetahui,<br>
                    <strong>Wali Kelas</strong>
                    <div class="signature-space"></div>
                    <strong><u>{{ $siswa->rombels->first()->guru->user->name ?? '_______________________' }}</u></strong>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
