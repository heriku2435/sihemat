<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mutasi Tabungan - {{ $siswa->nama }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #22c55e; /* emerald-500 */
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0;
            color: #065f46; /* emerald-900 */
            font-size: 20px;
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0 0 0;
            color: #4b5563; /* gray-600 */
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 4px 0;
        }
        .info-table td.label {
            font-weight: bold;
            width: 120px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .data-table th, .data-table td {
            border: 1px solid #d1d5db; /* gray-300 */
            padding: 8px;
            text-align: left;
        }
        .data-table th {
            background-color: #f3f4f6; /* gray-100 */
            color: #374151; /* gray-700 */
            font-weight: bold;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .text-green {
            color: #16a34a; /* green-600 */
        }
        .text-red {
            color: #dc2626; /* red-600 */
        }
        .month-header {
            background-color: #e5e7eb !important; /* gray-200 */
            font-weight: bold;
            color: #1f2937; /* gray-800 */
        }
        .summary-box {
            border: 2px solid #22c55e; /* emerald-500 */
            padding: 15px;
            background-color: #f0fdf4; /* emerald-50 */
            margin-top: 30px;
        }
        .summary-box h3 {
            margin: 0 0 10px 0;
            color: #166534; /* emerald-800 */
            text-align: right;
            font-size: 16px;
        }
        .footer {
            margin-top: 50px;
            font-size: 10px;
            color: #6b7280;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>Laporan Mutasi Tabungan Siswa</h2>
        <p>Tahun Ajaran {{ $tahunAktif->nama }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Nama Siswa</td>
            <td>: {{ $siswa->nama }}</td>
            <td class="label">NIS / No. Urut</td>
            <td>: {{ $siswa->nis ?? '-' }} / {{ $rombel->pivot->nomor_urut ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Rombel (Kelas)</td>
            <td>: {{ $rombel->nama_kelas }}</td>
            <td class="label">Wali Kelas</td>
            <td>: {{ optional($rombel->guru)->nama ?? '-' }}</td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Tanggal</th>
                <th width="12%">Jenis</th>
                <th width="15%" class="text-right">Mutasi (Rp)</th>
                <th width="20%" class="text-right">Saldo Berjalan (Rp)</th>
                <th width="33%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @php $globalIndex = 1; @endphp
            @forelse($mutasiPerBulan as $bulan => $transaksis)
                <tr class="month-header">
                    <td colspan="6">{{ $bulan }}</td>
                </tr>
                @php $lastSaldo = 0; @endphp
                @foreach($transaksis as $trx)
                    @php $lastSaldo = $trx->saldo_berjalan; @endphp
                    <tr>
                        <td class="text-center">{{ $globalIndex++ }}</td>
                        <td>{{ \Carbon\Carbon::parse($trx->tanggal)->format('d/m/Y') }}</td>
                        <td>
                            @if($trx->jenis === 'setor')
                                <span class="text-green">Setor</span>
                            @else
                                <span class="text-red">Tarik</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($trx->jenis === 'setor')
                                <span class="text-green">+{{ number_format($trx->jumlah, 0, ',', '.') }}</span>
                            @else
                                <span class="text-red">-{{ number_format($trx->jumlah, 0, ',', '.') }}</span>
                            @endif
                        </td>
                        <td class="text-right font-bold">{{ number_format($trx->saldo_berjalan, 0, ',', '.') }}</td>
                        <td>{{ $trx->keterangan ?? '-' }}</td>
                    </tr>
                @endforeach
                <tr style="background-color: #f8fafc;">
                    <td colspan="4" class="text-right" style="font-style: italic; padding-right: 15px;">Total Saldo Berjalan {{ $bulan }} :</td>
                    <td class="text-right font-bold">{{ number_format($lastSaldo, 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Belum ada transaksi di tahun ajaran ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="summary-box">
        <h3>Total Saldo Tersedia : Rp {{ number_format($totalSaldo, 0, ',', '.') }}</h3>
    </div>

    <div class="footer">
        Dicetak secara otomatis oleh sistem SIHEMAT pada {{ \Carbon\Carbon::now()->translatedFormat('d F Y H:i:s') }}
    </div>

</body>
</html>
