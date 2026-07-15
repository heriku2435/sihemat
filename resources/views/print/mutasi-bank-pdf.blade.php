<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Mutasi Bank</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            color: #2c3e50;
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #7f8c8d;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table td {
            vertical-align: top;
        }
        .info-label {
            font-weight: bold;
            width: 120px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #bdc3c7;
            padding: 8px;
        }
        table.data-table th {
            background-color: #ecf0f1;
            font-weight: bold;
            text-align: left;
            color: #2c3e50;
        }
        table.data-table th.text-center, table.data-table td.text-center {
            text-align: center;
        }
        table.data-table th.text-right, table.data-table td.text-right {
            text-align: right;
        }
        .text-justify {
            text-align: justify;
        }
        .setor {
            color: #27ae60;
            font-weight: bold;
        }
        .tarik {
            color: #c0392b;
            font-weight: bold;
        }
        .signature-area {
            width: 100%;
            margin-top: 40px;
        }
        .signature-box {
            width: 300px;
            float: right;
            text-align: center;
        }
        .signature-box p {
            margin: 0 0 70px 0;
        }
        .signature-box .name {
            font-weight: bold;
            text-decoration: underline;
        }
        .clear {
            clear: both;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>LAPORAN MUTASI BANK / KOPERASI</h1>
        <p>Aplikasi Sistem Informasi Tabungan Siswa</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="info-label">Dicetak Oleh</td>
            <td>: {{ auth()->user()->name }} ({{ ucfirst(auth()->user()->role) }})</td>
        </tr>
        <tr>
            <td class="info-label">Filter Periode</td>
            <td>: 
                @if($bulan)
                    Bulan {{ \Carbon\Carbon::createFromFormat('Y-m', $bulan)->translatedFormat('F Y') }}
                @elseif($startDate && $endDate)
                    {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }} - {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') }}
                @elseif($startDate)
                    Mulai {{ \Carbon\Carbon::parse($startDate)->translatedFormat('d F Y') }}
                @elseif($endDate)
                    Hingga {{ \Carbon\Carbon::parse($endDate)->translatedFormat('d F Y') }}
                @else
                    Semua Data
                @endif
            </td>
        </tr>
        <tr>
            <td class="info-label">Total Saldo Akhir</td>
            <td>: <strong>Rp {{ number_format($totalSaldoBank, 0, ',', '.') }}</strong></td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%" class="text-center">No</th>
                <th width="20%">Tanggal</th>
                <th width="15%">Jenis</th>
                <th width="15%" class="text-right">Nominal</th>
                <th width="15%" class="text-right">Saldo</th>
                <th width="30%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($mutasis as $index => $mutasi)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($mutasi->tanggal)->translatedFormat('d M Y') }} <span style="color: #7f8c8d; font-size: 10px;">{{ \Carbon\Carbon::parse($mutasi->created_at)->format('H:i') }}</span></td>
                <td>{{ ucfirst($mutasi->jenis) }}</td>
                <td class="text-right">
                    <span class="{{ $mutasi->jenis == 'setor' ? 'setor' : 'tarik' }}">
                        {{ $mutasi->jenis == 'setor' ? '+' : '-' }} Rp {{ number_format($mutasi->jumlah, 0, ',', '.') }}
                    </span>
                </td>
                <td class="text-right">Rp {{ number_format($mutasi->saldo_akhir, 0, ',', '.') }}</td>
                <td class="text-justify">{{ $mutasi->keterangan ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="text-center" style="padding: 20px;">Tidak ada transaksi ditemukan pada periode ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="signature-area">
        <div class="signature-box">
            <p>Admin / Petugas Bank,<br>{{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</p>
            <div class="name">{{ auth()->user()->name }}</div>
        </div>
        <div class="clear"></div>
    </div>

</body>
</html>
