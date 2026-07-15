<table>
    <tr>
        <td colspan="15" style="text-align: center; font-weight: bold; font-size: 14pt;">REKAPITULASI TABUNGAN SISWA</td>
    </tr>
    <tr>
        <td colspan="15" style="text-align: center;">Tahun Ajaran: {{ $ta->nama }} ({{ \Carbon\Carbon::parse($ta->tanggal_mulai)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($ta->tanggal_selesai)->format('d/m/Y') }})</td>
    </tr>
    <tr>
        <td colspan="15" style="text-align: center;">Kelas: {{ $rombel->nama_kelas }} | Wali Kelas: {{ $rombel->guru->nama ?? '-' }}</td>
    </tr>
    <tr></tr>
    <tr>
        <th style="border: 1px solid #000; font-weight: bold; background-color: #d1d5db; text-align: center; width: 40px;">No</th>
        <th style="border: 1px solid #000; font-weight: bold; background-color: #d1d5db; width: 100px;">NIS</th>
        <th style="border: 1px solid #000; font-weight: bold; background-color: #d1d5db; width: 250px;">Nama Siswa</th>
        @foreach($months as $month)
            <th style="border: 1px solid #000; font-weight: bold; background-color: #d1d5db; text-align: center;">{{ $month['label'] }}</th>
        @endforeach
        <th style="border: 1px solid #000; font-weight: bold; background-color: #d1d5db; text-align: right;">Total Net Tahunan</th>
    </tr>
    
    @php 
        $grandTotalNet = 0; 
        $monthlyTotals = array_fill_keys(array_column($months, 'key'), 0);
    @endphp

    @forelse($rekapData as $index => $row)
        <tr>
            <td style="border: 1px solid #000; text-align: center;">{{ $index + 1 }}</td>
            <td style="border: 1px solid #000;">{{ $row['nis'] }}</td>
            <td style="border: 1px solid #000;">{{ $row['nama'] }}</td>
            @php $totalNetRow = 0; @endphp
            @foreach($months as $month)
                @php 
                    $val = $row['monthly'][$month['key']] ?? 0; 
                    $totalNetRow += $val;
                    $monthlyTotals[$month['key']] += $val;
                @endphp
                <td style="border: 1px solid #000; text-align: right;">{{ $val }}</td>
            @endforeach
            @php $grandTotalNet += $totalNetRow; @endphp
            <td style="border: 1px solid #000; text-align: right; font-weight: bold;">{{ $totalNetRow }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="{{ count($months) + 4 }}" style="border: 1px solid #000; text-align: center;">Belum ada data</td>
        </tr>
    @endforelse

    @if(count($rekapData) > 0)
    <tr>
        <td colspan="3" style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f3f4f6;">TOTAL ROMBEL</td>
        @foreach($months as $month)
            <td style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f3f4f6;">{{ $monthlyTotals[$month['key']] }}</td>
        @endforeach
        <td style="border: 1px solid #000; text-align: right; font-weight: bold; background-color: #f3f4f6;">{{ $grandTotalNet }}</td>
    </tr>
    @endif
</table>
