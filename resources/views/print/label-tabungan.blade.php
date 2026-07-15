<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Label Tabungan - {{ $rombel->nama_kelas }}</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,600,700" rel="stylesheet" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background: #e5e7eb;
            color: #111827;
        }

        .print-container {
            width: 210mm; /* A4 width */
            margin: 20px auto;
            background: #ffffff;
            padding: 10mm;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .header-print {
            text-align: center;
            margin-bottom: 20px;
        }

        .header-print h2 {
            margin: 0;
            font-size: 16px;
        }

        .header-print p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #6b7280;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 4mm;
            justify-items: center;
        }

        .label-box {
            width: 6.8cm; /* Adjusted slightly under 7cm to fit 3 per row on A4 */
            height: 4cm;
            box-sizing: border-box;
            border: 1px dashed #d1d5db;
            border-radius: 4px;
            padding: 8px;
            display: flex;
            align-items: center;
            page-break-inside: avoid;
            background: #ffffff;
            gap: 8px;
            overflow: hidden;
        }

        .qr-container {
            background: #ffffff;
            padding: 4px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            flex-shrink: 0;
        }

        .qr-container svg {
            display: block;
        }

        .label-details {
            flex-grow: 1;
            overflow: hidden;
        }

        .detail-row {
            margin-bottom: 2px;
            font-size: 10px;
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .detail-row.name {
            font-size: 11px;
            font-weight: 700;
            margin-bottom: 4px;
            color: #1f2937;
            white-space: normal; /* Allow name to wrap if it's too long */
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .label-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        .print-button-container {
            text-align: center;
            margin: 20px;
        }

        .print-btn {
            background-color: #10b981;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);
        }

        .print-btn:hover {
            background-color: #059669;
        }

        @media print {
            body {
                background: none;
            }
            .print-container {
                margin: 0;
                padding: 0;
                box-shadow: none;
                width: 100%;
            }
            .print-button-container {
                display: none;
            }
            .label-box {
                border: 1px solid #000; /* Solid border for printing cutting lines */
                background: #fff;
            }
        }
    </style>
</head>
<body>
    <div class="print-button-container">
        <button class="print-btn" onclick="window.print()">Cetak Label Sekarang</button>
        <p style="margin-top: 10px; font-size: 14px; color: #6b7280;">*Pastikan orientasi cetak adalah <strong>Portrait</strong> dan Margin di-set <strong>Minimum / None</strong> agar muat 3 baris.</p>
    </div>

    <div class="print-container">
        <div class="header-print">
            <h2>LABEL BUKU TABUNGAN</h2>
            <p>Kelas: {{ $rombel->nama_kelas }} &bull; Wali Kelas: {{ optional(optional($rombel->guru)->user)->name ?? '-' }}</p>
        </div>

        <div class="grid">
            @foreach($siswas as $index => $siswa)
            <div class="label-box">
                <div class="qr-container">
                    {!! QrCode::size(70)->generate($siswa->uuid_qr) !!}
                </div>
                <div class="label-details">
                    <div class="detail-row name">{{ $siswa->nama }}</div>
                    <div class="detail-row">
                        <span class="label-label">Kelas</span><br>
                        <strong>{{ $rombel->nama_kelas }}</strong>
                    </div>
                    <div class="detail-row">
                        <span class="label-label">No. Urut & ID Rombel</span><br>
                        <strong>{{ str_pad($siswa->pivot->nomor_urut ?? $loop->iteration, 2, '0', STR_PAD_LEFT) }}</strong> / R-{{ str_pad($rombel->id, 4, '0', STR_PAD_LEFT) }}
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</body>
</html>
