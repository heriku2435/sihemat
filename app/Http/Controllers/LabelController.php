<?php

namespace App\Http\Controllers;

use App\Models\Rombel;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function cetakRombel(Rombel $rombel)
    {
        // Pengecekan otorisasi jika user adalah guru
        if (auth()->user()->role === 'guru') {
            abort_if($rombel->guru_id !== auth()->user()->guru->id, 403, 'Anda tidak berhak mencetak label kelas ini.');
        }

        // Ambil data siswa yang aktif di kelas tersebut
        $siswas = $rombel->siswas()->get()->filter(function ($siswa) {
            return $siswa->computed_status === 'Aktif';
        })->sort(function($a, $b) {
            $nuA = $a->pivot->nomor_urut;
            $nuB = $b->pivot->nomor_urut;
            
            if ($nuA !== null && $nuB !== null) {
                if ($nuA == $nuB) {
                    return strcmp($a->nama, $b->nama);
                }
                return $nuA <=> $nuB;
            }
            if ($nuA !== null) return -1;
            if ($nuB !== null) return 1;
            
            return strcmp($a->nama, $b->nama);
        })->values();

        // Load relasi tambahan yang mungkin dibutuhkan
        $rombel->load('guru.user');

        return view('print.label-tabungan', compact('rombel', 'siswas'));
    }
}
