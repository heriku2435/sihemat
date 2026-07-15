<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\TahunAjaran;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PublicMutasiController extends Controller
{
    public function cetakPdf($uuid_qr, $tahun_ajaran_id)
    {
        $siswa = Siswa::where('uuid_qr', $uuid_qr)->first();
        $tahunAktif = TahunAjaran::find($tahun_ajaran_id);

        if (!$siswa || !$tahunAktif || !$tahunAktif->is_active) {
            abort(404, 'Data tidak ditemukan atau Tahun Ajaran tidak aktif.');
        }

        $rombelPivot = $siswa->rombels()->where('tahun_ajaran_id', $tahunAktif->id)->first();
        if (!$rombelPivot) {
            abort(404, 'Siswa tidak terdaftar pada tahun ajaran ini.');
        }

        $transaksis = $siswa->transaksis()
            ->where('rombel_id', $rombelPivot->id)
            ->orderBy('tanggal', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        // Calculate running balance and group by month
        $mutasiPerBulan = [];
        $runningBalance = 0;

        foreach ($transaksis as $trx) {
            if ($trx->jenis === 'setor') {
                $runningBalance += $trx->jumlah;
            } else {
                $runningBalance -= $trx->jumlah;
            }
            
            $trx->saldo_berjalan = $runningBalance;

            // F Y returns e.g. "August 2026". We use translatedFormat for Indonesian.
            $bulan = Carbon::parse($trx->tanggal)->translatedFormat('F Y');
            
            if (!isset($mutasiPerBulan[$bulan])) {
                $mutasiPerBulan[$bulan] = [];
            }
            $mutasiPerBulan[$bulan][] = $trx;
        }

        $data = [
            'siswa' => $siswa,
            'rombel' => $rombelPivot,
            'tahunAktif' => $tahunAktif,
            'mutasiPerBulan' => $mutasiPerBulan,
            'totalSaldo' => $runningBalance
        ];

        $pdf = Pdf::loadView('print.public-mutasi', $data);
        return $pdf->stream('Mutasi_Tabungan_' . str_replace(' ', '_', $siswa->nama) . '_' . str_replace('/', '_', $tahunAktif->nama) . '.pdf');
    }
}
