<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TahunAjaran;
use App\Models\Rombel;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PrintRekapitulasiController extends Controller
{
    public function cetak(Request $request)
    {
        $taId = $request->query('ta');
        $rombelId = $request->query('rombel');

        if (!$taId || !$rombelId) {
            abort(404, 'Tahun Ajaran atau Rombel tidak valid.');
        }

        $ta = TahunAjaran::findOrFail($taId);
        $rombel = Rombel::findOrFail($rombelId);

        // Validasi akses untuk guru
        if (auth()->user()->role === 'guru') {
            if ($rombel->guru_id !== auth()->user()->guru->id) {
                abort(403, 'Anda tidak memiliki akses ke rombel ini.');
            }
        }

        $months = [];
        $data = [];

        $start = Carbon::parse($ta->tanggal_mulai)->startOfMonth();
        $end = Carbon::parse($ta->tanggal_selesai)->endOfMonth();
        
        $current = $start->copy();
        while ($current <= $end) {
            $months[] = [
                'key' => $current->format('Y-m'),
                'label' => $current->translatedFormat('M y'),
            ];
            $current->addMonth();
        }

        $students = $rombel->siswas()->orderBy('nama')->get();
        $studentIds = $students->pluck('id')->toArray();
        
        if (!empty($studentIds)) {
            $transaksis = Transaksi::whereIn('siswa_id', $studentIds)
                ->whereBetween('tanggal', [$ta->tanggal_mulai, $ta->tanggal_selesai])
                ->select('siswa_id', 'jenis', 'jumlah', DB::raw("DATE_FORMAT(tanggal, '%Y-%m') as bulan"))
                ->get();

            foreach ($students as $siswa) {
                $studentData = [
                    'id' => $siswa->id,
                    'nama' => $siswa->nama,
                    'nis' => $siswa->nis,
                    'monthly' => []
                ];

                foreach ($months as $month) {
                    $monthKey = $month['key'];
                    $txs = $transaksis->where('siswa_id', $siswa->id)->where('bulan', $monthKey);
                    
                    $setor = $txs->where('jenis', 'setor')->sum('jumlah');
                    $tarik = $txs->where('jenis', 'tarik')->sum('jumlah');
                    
                    $studentData['monthly'][$monthKey] = $setor - $tarik; 
                }
                $data[] = $studentData;
            }
        }

        $pdf = app('dompdf.wrapper')->loadView('print.rekapitulasi-pdf', [
            'ta' => $ta,
            'rombel' => $rombel,
            'months' => $months,
            'rekapData' => $data,
        ]);

        // Portrait paper because the table is split into two semesters
        $pdf->setPaper('a4', 'portrait');

        return $pdf->stream('Rekapitulasi_Tabungan_' . $rombel->nama_kelas . '.pdf');
    }

    public function exportExcel(Request $request)
    {
        $taId = $request->query('ta');
        $rombelId = $request->query('rombel');

        if (!$taId || !$rombelId) {
            abort(404, 'Tahun Ajaran atau Rombel tidak valid.');
        }

        $ta = TahunAjaran::findOrFail($taId);
        $rombel = Rombel::findOrFail($rombelId);

        if (auth()->user()->role === 'guru') {
            if ($rombel->guru_id !== auth()->user()->guru->id) {
                abort(403, 'Anda tidak memiliki akses ke rombel ini.');
            }
        }

        $months = [];
        $data = [];

        $start = Carbon::parse($ta->tanggal_mulai)->startOfMonth();
        $end = Carbon::parse($ta->tanggal_selesai)->endOfMonth();
        
        $current = $start->copy();
        while ($current <= $end) {
            $months[] = [
                'key' => $current->format('Y-m'),
                'label' => $current->translatedFormat('M y'),
            ];
            $current->addMonth();
        }

        $students = $rombel->siswas()->orderBy('nama')->get();
        $studentIds = $students->pluck('id')->toArray();
        
        if (!empty($studentIds)) {
            $transaksis = Transaksi::whereIn('siswa_id', $studentIds)
                ->whereBetween('tanggal', [$ta->tanggal_mulai, $ta->tanggal_selesai])
                ->select('siswa_id', 'jenis', 'jumlah', DB::raw("DATE_FORMAT(tanggal, '%Y-%m') as bulan"))
                ->get();

            foreach ($students as $siswa) {
                $studentData = [
                    'id' => $siswa->id,
                    'nama' => $siswa->nama,
                    'nis' => $siswa->nis,
                    'monthly' => []
                ];

                foreach ($months as $month) {
                    $monthKey = $month['key'];
                    $txs = $transaksis->where('siswa_id', $siswa->id)->where('bulan', $monthKey);
                    
                    $setor = $txs->where('jenis', 'setor')->sum('jumlah');
                    $tarik = $txs->where('jenis', 'tarik')->sum('jumlah');
                    
                    $studentData['monthly'][$monthKey] = $setor - $tarik; 
                }
                $data[] = $studentData;
            }
        }

        $fileName = 'Rekapitulasi_Tabungan_' . str_replace(' ', '_', $rombel->nama_kelas) . '.xls';
        
        $view = view('print.rekapitulasi-excel', [
            'ta' => $ta,
            'rombel' => $rombel,
            'months' => $months,
            'rekapData' => $data,
        ])->render();

        return response($view)
            ->header('Content-Type', 'application/vnd.ms-excel')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }
}
