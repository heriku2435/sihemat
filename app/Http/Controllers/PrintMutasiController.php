<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Siswa;
use App\Models\Transaksi;

class PrintMutasiController extends Controller
{
    public function cetak(Request $request)
    {
        $request->validate([
            'siswa_id' => 'required|exists:siswas,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'bulan' => 'nullable|string',
            'jenis' => 'nullable|in:setor,tarik',
        ]);

        $siswa = Siswa::with('rombels.guru.user')->findOrFail($request->siswa_id);
        
        $query = Transaksi::with(['guru.user'])
                    ->select('transaksis.*', \Illuminate\Support\Facades\DB::raw('(
                                (SELECT COALESCE(SUM(jumlah), 0) FROM transaksis t2 WHERE t2.siswa_id = transaksis.siswa_id AND t2.jenis = "setor" AND t2.created_at <= transaksis.created_at) - 
                                (SELECT COALESCE(SUM(jumlah), 0) FROM transaksis t2 WHERE t2.siswa_id = transaksis.siswa_id AND t2.jenis = "tarik" AND t2.created_at <= transaksis.created_at)
                            ) as saldo_akhir'))
                    ->where('siswa_id', $siswa->id)
                    ->oldest('tanggal')
                    ->oldest('created_at');

        if ($request->start_date) {
            $query->whereDate('tanggal', '>=', $request->start_date);
        }
        
        if ($request->end_date) {
            $query->whereDate('tanggal', '<=', $request->end_date);
        }

        if ($request->bulan) {
            $query->whereYear('tanggal', substr($request->bulan, 0, 4))
                  ->whereMonth('tanggal', substr($request->bulan, 5, 2));
        }

        if ($request->jenis) {
            $query->where('jenis', $request->jenis);
        }

        $transaksis = $query->get();

        $pdf = app('dompdf.wrapper')->loadView('print.mutasi-pdf', compact('siswa', 'transaksis', 'request'));
        
        // Optional: configure pdf paper
        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream('Mutasi_Tabungan_' . str_replace(' ', '_', $siswa->nama) . '.pdf');
    }
}
