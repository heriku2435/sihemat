<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SetoranKoperasi;

class PrintMutasiBankController extends Controller
{
    public function cetak(Request $request)
    {
        $search = $request->query('search');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $bulan = $request->query('bulan');
        $jenis = $request->query('jenis');

        $query = SetoranKoperasi::with(['guru.user'])->oldest('tanggal')->oldest('created_at');
        
        if (auth()->user()->role === 'guru') {
            $query->where('guru_id', auth()->user()->guru->id);
        }

        if ($search) {
            $query->whereHas('guru.user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }

        if ($startDate) {
            $query->whereDate('tanggal', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->whereDate('tanggal', '<=', $endDate);
        }

        if ($jenis) {
            $query->where('jenis', $jenis);
        }

        if ($bulan) {
            $query->whereYear('tanggal', substr($bulan, 0, 4))
                  ->whereMonth('tanggal', substr($bulan, 5, 2));
        }

        $mutasis = $query->get();

        foreach ($mutasis as $mutasi) {
            $saldoQuery = SetoranKoperasi::where(function($q) use ($mutasi) {
                $q->where('tanggal', '<', $mutasi->tanggal)
                  ->orWhere(function($q) use ($mutasi) {
                      $q->where('tanggal', '=', $mutasi->tanggal)
                        ->where('created_at', '<=', $mutasi->created_at)
                        ->where('id', '<=', $mutasi->id);
                  });
            });

            if (auth()->user()->role === 'guru') {
                $saldoQuery->where('guru_id', auth()->user()->guru->id);
            }

            $mutasi->saldo_akhir = $saldoQuery->selectRaw("SUM(CASE WHEN jenis = 'setor' THEN jumlah ELSE -jumlah END) as total")
                                              ->value('total') ?? 0;
        }

        $totalSaldoBank = SetoranKoperasi::when(auth()->user()->role === 'guru', function($q) {
            $q->where('guru_id', auth()->user()->guru->id);
        })->selectRaw("SUM(CASE WHEN jenis = 'setor' THEN jumlah ELSE -jumlah END) as total")
          ->value('total') ?? 0;

        $pdf = app('dompdf.wrapper')->loadView('print.mutasi-bank-pdf', [
            'mutasis' => $mutasis,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'bulan' => $bulan,
            'totalSaldoBank' => $totalSaldoBank,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('mutasi-bank-' . date('Y-m-d-His') . '.pdf');
    }
}
