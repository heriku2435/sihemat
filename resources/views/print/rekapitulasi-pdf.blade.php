<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rekapitulasi Tabungan Siswa</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
        }
        .header p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #666;
        }
        .info-table {
            width: 100%;
            margin-bottom: 20px;
            font-size: 11px;
        }
        .info-table td {
            padding: 3px 0;
        }
        .info-label {
            width: 100px;
            font-weight: bold;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .data-table th, .data-table td {
            border: 1px solid #ddd;
            padding: 6px 4px;
        }
        .data-table th {
            background-color: #f8f9fa;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 8px;
        }
        .data-table td {
            font-size: 9px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        
        .val-pos { color: #059669; }
        .val-neg { color: #dc2626; }
        
        .signature-section {
            margin-top: 40px;
            width: 100%;
        }
        .signature-box {
            float: right;
            text-align: center;
            width: 250px;
        }
        .signature-box p {
            margin: 0 0 60px 0;
        }
        .signature-box .name {
            font-weight: bold;
            text-decoration: underline;
        }
        .clear {
            clear: both;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>REKAPITULASI TABUNGAN SISWA</h1>
        <p>Aplikasi Sistem Informasi Tabungan Siswa (SIHEMAT)</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="info-label">Dicetak Oleh</td>
            <td>: {{ auth()->user()->name }} ({{ ucfirst(auth()->user()->role) }})</td>
        </tr>
        <tr>
            <td class="info-label">Tahun Ajaran</td>
            <td>: {{ $ta->nama }} ({{ \Carbon\Carbon::parse($ta->tanggal_mulai)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($ta->tanggal_selesai)->format('d/m/Y') }})</td>
        </tr>
        <tr>
            <td class="info-label">Rombel / Kelas</td>
            <td>: {{ $rombel->nama_kelas }}</td>
        </tr>
        <tr>
            <td class="info-label">Wali Kelas</td>
            <td>: {{ $rombel->guru->nama ?? '-' }}</td>
        </tr>
    </table>

    @php
        $monthsGanjil = [];
        $monthsGenap = [];
        foreach($months as $m) {
            $monthNum = (int) explode('-', $m['key'])[1];
            if ($monthNum >= 7 && $monthNum <= 12) {
                $monthsGanjil[] = $m;
            } else {
                $monthsGenap[] = $m;
            }
        }
    @endphp

    <!-- TABEL SEMESTER GANJIL -->
    <h3 style="margin-top: 0; margin-bottom: 10px; font-size: 12px; text-decoration: underline;">SEMESTER GANJIL (JULI - DESEMBER)</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th width="20">No</th>
                <th class="text-left">Nama Siswa</th>
                @foreach($monthsGanjil as $month)
                    <th width="50">{{ $month['label'] }}</th>
                @endforeach
                <th width="60">Total Net Ganjil</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $grandTotalNetGanjil = 0; 
                $monthlyTotalsGanjil = array_fill_keys(array_column($monthsGanjil, 'key'), 0);
            @endphp
            
            @forelse($rekapData as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $row['nama'] }}</strong><br>
                        <span style="font-size: 8px; color: #666;">NIS: {{ $row['nis'] }}</span>
                    </td>
                    
                    @php $totalNetRowGanjil = 0; @endphp
                    @foreach($monthsGanjil as $month)
                        @php 
                            $val = $row['monthly'][$month['key']] ?? 0; 
                            $totalNetRowGanjil += $val;
                            $monthlyTotalsGanjil[$month['key']] += $val;
                        @endphp
                        <td class="text-right">
                            {{ $val != 0 ? number_format($val, 0, ',', '.') : '-' }}
                        </td>
                    @endforeach
                    
                    @php $grandTotalNetGanjil += $totalNetRowGanjil; @endphp
                    <td class="text-right">
                        <strong>{{ $totalNetRowGanjil != 0 ? number_format($totalNetRowGanjil, 0, ',', '.') : '0' }}</strong>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($monthsGanjil) + 3 }}" class="text-center" style="padding: 20px;">
                        Belum ada data transaksi untuk rombel ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(count($rekapData) > 0)
        <tfoot>
            <tr style="background-color: #f8f9fa;">
                <td colspan="2" class="text-right" style="font-weight: bold;">TOTAL ROMBEL GANJIL</td>
                @foreach($monthsGanjil as $month)
                    <td class="text-right" style="font-weight: bold;">
                        {{ $monthlyTotalsGanjil[$month['key']] != 0 ? number_format($monthlyTotalsGanjil[$month['key']], 0, ',', '.') : '-' }}
                    </td>
                @endforeach
                <td class="text-right" style="font-weight: bold; background-color: #e2e8f0;">
                    Rp {{ number_format($grandTotalNetGanjil, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="page-break"></div>

    <!-- TABEL SEMESTER GENAP -->
    <h3 style="margin-top: 0; margin-bottom: 10px; font-size: 12px; text-decoration: underline;">SEMESTER GENAP (JANUARI - JUNI)</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th width="20">No</th>
                <th class="text-left">Nama Siswa</th>
                @foreach($monthsGenap as $month)
                    <th width="50">{{ $month['label'] }}</th>
                @endforeach
                <th width="60">Total Net Genap</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $grandTotalNetGenap = 0; 
                $monthlyTotalsGenap = array_fill_keys(array_column($monthsGenap, 'key'), 0);
            @endphp
            
            @forelse($rekapData as $index => $row)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $row['nama'] }}</strong><br>
                        <span style="font-size: 8px; color: #666;">NIS: {{ $row['nis'] }}</span>
                    </td>
                    
                    @php $totalNetRowGenap = 0; @endphp
                    @foreach($monthsGenap as $month)
                        @php 
                            $val = $row['monthly'][$month['key']] ?? 0; 
                            $totalNetRowGenap += $val;
                            $monthlyTotalsGenap[$month['key']] += $val;
                        @endphp
                        <td class="text-right">
                            {{ $val != 0 ? number_format($val, 0, ',', '.') : '-' }}
                        </td>
                    @endforeach
                    
                    @php $grandTotalNetGenap += $totalNetRowGenap; @endphp
                    <td class="text-right">
                        <strong>{{ $totalNetRowGenap != 0 ? number_format($totalNetRowGenap, 0, ',', '.') : '0' }}</strong>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($monthsGenap) + 3 }}" class="text-center" style="padding: 20px;">
                        Belum ada data transaksi untuk rombel ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if(count($rekapData) > 0)
        <tfoot>
            <tr style="background-color: #f8f9fa;">
                <td colspan="2" class="text-right" style="font-weight: bold;">TOTAL ROMBEL GENAP</td>
                @foreach($monthsGenap as $month)
                    <td class="text-right" style="font-weight: bold;">
                        {{ $monthlyTotalsGenap[$month['key']] != 0 ? number_format($monthlyTotalsGenap[$month['key']], 0, ',', '.') : '-' }}
                    </td>
                @endforeach
                <td class="text-right" style="font-weight: bold; background-color: #e2e8f0;">
                    Rp {{ number_format($grandTotalNetGenap, 0, ',', '.') }}
                </td>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="signature-section">
        <div class="signature-box">
            <p>{{ config('app.timezone', 'Asia/Jakarta') == 'Asia/Jakarta' ? 'Jakarta' : 'Kota' }}, {{ now()->translatedFormat('d F Y') }}</p>
            <p><strong>Mengetahui,</strong><br>{{ ucfirst(auth()->user()->role) }}</p>
            <div class="name">{{ auth()->user()->name }}</div>
        </div>
        <div class="clear"></div>
    </div>

</body>
</html>
