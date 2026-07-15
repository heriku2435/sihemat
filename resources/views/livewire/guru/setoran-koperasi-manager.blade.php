<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\SetoranKoperasi;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $jenisTransaksi = 'setor';
    public $jumlahSetor = '';
    public $tanggalSetor = '';
    public $keterangan = 'Diserahkan kepada petugas Bank/Koperasi/BMT/LKP atas nama... ';

    public $cashGuru = 0;
    public $totalDisetor = 0;
    public $totalDitarik = 0;
    public $saldoAktif = 0;
    public $chartData = [];

    public function mount()
    {
        $this->tanggalSetor = now()->toDateString();
        $this->calculateBalances();
    }

    public function calculateBalances()
    {
        $user = auth()->user();
        if ($user->role !== 'guru') return;
        
        $guru_id = $user->guru->id ?? null;
        if (!$guru_id) return;

        $tahun_aktif = \App\Models\TahunAjaran::where('is_active', true)->first();
        if (!$tahun_aktif) return;

        $pemasukan = DB::table('transaksis')
            ->join('rombels', 'transaksis.rombel_id', '=', 'rombels.id')
            ->where('rombels.tahun_ajaran_id', $tahun_aktif->id)
            ->where('transaksis.jenis', 'setor')
            ->where('rombels.guru_id', $guru_id)
            ->sum('transaksis.jumlah');

        $penarikan = DB::table('transaksis')
            ->join('rombels', 'transaksis.rombel_id', '=', 'rombels.id')
            ->where('rombels.tahun_ajaran_id', $tahun_aktif->id)
            ->where('transaksis.jenis', 'tarik')
            ->where('rombels.guru_id', $guru_id)
            ->sum('transaksis.jumlah');
            
        $saldo_aktif = $pemasukan - $penarikan;

        $setor = SetoranKoperasi::where('guru_id', $guru_id)
            ->where('jenis', 'setor')
            ->whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai])
            ->sum('jumlah');
        $tarik = SetoranKoperasi::where('guru_id', $guru_id)
            ->where('jenis', 'tarik')
            ->whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai])
            ->sum('jumlah');
            
        $this->totalDisetor = $setor;
        $this->totalDitarik = $tarik;
        $this->cashGuru = $saldo_aktif - ($setor - $tarik);
        $this->saldoAktif = $saldo_aktif;

        // Load chart data
        $this->loadChartData($guru_id);
    }

    public function loadChartData($guru_id)
    {
        $tahun_aktif = \App\Models\TahunAjaran::where('is_active', true)->first();
        if (!$tahun_aktif) return;

        $dailyData = SetoranKoperasi::where('guru_id', $guru_id)
            ->whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai])
            ->select(
                'tanggal',
                DB::raw("SUM(CASE WHEN jenis = 'setor' THEN jumlah ELSE 0 END) as total_setor"),
                DB::raw("SUM(CASE WHEN jenis = 'tarik' THEN jumlah ELSE 0 END) as total_tarik")
            )
            ->groupBy('tanggal')
            ->orderBy('tanggal', 'desc')
            ->take(6)
            ->get()
            ->reverse()
            ->values();

        $setorData = [];
        $tarikData = [];
        $categories = [];

        foreach ($dailyData as $data) {
            $setorData[] = $data->total_setor;
            $tarikData[] = $data->total_tarik;
            $categories[] = \Carbon\Carbon::parse($data->tanggal)->translatedFormat('l, d M');
        }

        $this->chartData = [
            'setor' => $setorData,
            'tarik' => $tarikData,
            'categories' => $categories
        ];
    }

    public function getRiwayatSetoranProperty()
    {
        $user = auth()->user();
        $guru_id = $user->role === 'guru' ? ($user->guru->id ?? null) : null;
        
        if (!$guru_id) return collect();

        $tahun_aktif = \App\Models\TahunAjaran::where('is_active', true)->first();
        if (!$tahun_aktif) return collect();

        return SetoranKoperasi::where('guru_id', $guru_id)
            ->whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai])
            ->latest()
            ->take(5)
            ->get();
    }

    public function getRekapBulananProperty()
    {
        $user = auth()->user();
        $guru_id = $user->role === 'guru' ? ($user->guru->id ?? null) : null;
        if (!$guru_id) return ['ganjil' => [], 'genap' => []];

        $tahun_aktif = \App\Models\TahunAjaran::where('is_active', true)->first();
        if (!$tahun_aktif) return ['ganjil' => [], 'genap' => []];

        $transactions = SetoranKoperasi::where('guru_id', $guru_id)
            ->whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai])
            ->get();

        $ganjil = [];
        $genap = [];
        
        $monthsGanjil = [7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $monthsGenap = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni'];

        foreach ($monthsGanjil as $m => $name) {
            $setor = $transactions->where('jenis', 'setor')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $tarik = $transactions->where('jenis', 'tarik')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $ganjil[] = ['bulan' => $name, 'setor' => $setor, 'tarik' => $tarik];
        }

        foreach ($monthsGenap as $m => $name) {
            $setor = $transactions->where('jenis', 'setor')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $tarik = $transactions->where('jenis', 'tarik')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $genap[] = ['bulan' => $name, 'setor' => $setor, 'tarik' => $tarik];
        }

        return ['ganjil' => $ganjil, 'genap' => $genap];
    }

    public function getTerbilang($angka)
    {
        $angka = abs($angka);
        $baca = array("", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
        $terbilang = "";

        if ($angka < 12) {
            $terbilang = " " . $baca[$angka];
        } else if ($angka < 20) {
            $terbilang = $this->getTerbilang($angka - 10) . " Belas";
        } else if ($angka < 100) {
            $terbilang = $this->getTerbilang($angka / 10) . " Puluh" . $this->getTerbilang($angka % 10);
        } else if ($angka < 200) {
            $terbilang = " Seratus" . $this->getTerbilang($angka - 100);
        } else if ($angka < 1000) {
            $terbilang = $this->getTerbilang($angka / 100) . " Ratus" . $this->getTerbilang($angka % 100);
        } else if ($angka < 2000) {
            $terbilang = " Seribu" . $this->getTerbilang($angka - 1000);
        } else if ($angka < 1000000) {
            $terbilang = $this->getTerbilang($angka / 1000) . " Ribu" . $this->getTerbilang($angka % 1000);
        } else if ($angka < 1000000000) {
            $terbilang = $this->getTerbilang($angka / 1000000) . " Juta" . $this->getTerbilang($angka % 1000000);
        } else if ($angka < 1000000000000) {
            $terbilang = $this->getTerbilang($angka / 1000000000) . " Miliar" . $this->getTerbilang($angka % 1000000000);
        }

        return $terbilang;
    }

    public function prosesSetor()
    {
        $this->jumlahSetor = str_replace(',', '.', $this->jumlahSetor);
        
        $this->validate([
            'jenisTransaksi' => 'required|in:setor,tarik',
            'jumlahSetor' => 'required|numeric|min:1',
            'tanggalSetor' => 'required|date',
            'keterangan' => 'nullable|string|max:255',
        ]);

        $realAmount = floatval($this->jumlahSetor) * 1000;
        
        if ($this->jenisTransaksi === 'setor') {
            if ($realAmount > $this->cashGuru) {
                $this->addError('jumlahSetor', 'Jumlah setor tidak boleh melebihi Cash di Tangan.');
                return;
            }
        } else {
            if ($realAmount > $this->totalDisetor) {
                $this->addError('jumlahSetor', 'Jumlah tarik tidak boleh melebihi Total di Bank.');
                return;
            }
        }

        $user = auth()->user();
        if ($user->role !== 'guru' || !$user->guru) {
            $this->addError('jumlahSetor', 'Hanya guru yang dapat melakukan transaksi bank.');
            return;
        }

        SetoranKoperasi::create([
            'guru_id' => $user->guru->id,
            'jenis' => $this->jenisTransaksi,
            'jumlah' => $realAmount,
            'tanggal' => $this->tanggalSetor,
            'keterangan' => $this->keterangan,
        ]);

        $this->reset(['jumlahSetor', 'keterangan']);
        $this->tanggalSetor = now()->toDateString();
        
        $this->calculateBalances();

        $formattedAmount = number_format($realAmount, 0, ',', '.');
        $msg = $this->jenisTransaksi === 'setor' 
            ? "Setoran sebesar Rp {$formattedAmount} berhasil dicatat!" 
            : "Penarikan sebesar Rp {$formattedAmount} berhasil dicatat!";
            
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $msg
        ]);
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                {{ __('Transaksi Bank / Koperasi') }}
            </h2>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span id="realtime-clock">{{ \Carbon\Carbon::now()->translatedFormat('l, d F Y - H:i:s') }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- 4 Cards in 1 Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Info Card: Saldo Aktif -->
                <div class="overflow-hidden shadow-lg rounded-2xl border hover:scale-105 hover:shadow-xl transition-all duration-300"
                     style="background: linear-gradient(to bottom right, #f43f5e, #db2777); border-color: #f472b6; box-shadow: 0 10px 15px -3px rgba(244, 114, 182, 0.4);">
                    <div class="p-6 relative overflow-hidden text-white">
                        <!-- Decorative background circle -->
                        <div class="absolute -right-4 -top-4 w-24 h-24 rounded-full blur-xl" style="background-color: rgba(255,255,255,0.1);"></div>
                        
                        <div class="flex items-center justify-between mb-2 relative z-10">
                            <div class="text-sm font-medium" style="color: #fdf2f8;">Total Saldo Aktif</div>
                            <svg class="w-5 h-5" style="color: #fce7f3;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        </div>
                        <div class="text-xl xl:text-2xl font-extrabold tracking-tight relative z-10 truncate" title="Rp {{ number_format($this->saldoAktif, 0, ',', '.') }}">
                            Rp {{ number_format($this->saldoAktif, 0, ',', '.') }}
                        </div>
                        <p class="mt-2 text-xs relative z-10" style="color: rgba(252, 231, 243, 0.8);">
                            Total tabungan siswa (pemasukan - penarikan).
                        </p>
                    </div>
                </div>

                <!-- Info Card: Cash di Tangan -->
                <div class="bg-gradient-to-br from-amber-500 to-orange-500 overflow-hidden shadow-lg shadow-amber-200 dark:shadow-none rounded-2xl border border-amber-400 hover:scale-105 hover:shadow-xl hover:shadow-amber-300 transition-all duration-300">
                    <div class="p-6 relative overflow-hidden text-white">
                        <!-- Decorative background circle -->
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
                        
                        <div class="flex items-center justify-between mb-2 relative z-10">
                            <div class="text-sm font-medium text-amber-50">Cash</div>
                            <svg class="w-5 h-5 text-amber-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <div class="text-xl xl:text-2xl font-extrabold tracking-tight relative z-10 truncate" title="Rp {{ number_format($this->cashGuru, 0, ',', '.') }}">
                            Rp {{ number_format($this->cashGuru, 0, ',', '.') }}
                        </div>
                        <p class="mt-2 text-xs text-amber-100/80 relative z-10">
                            Uang tabungan yang <span class="font-bold text-white">belum</span> disetorkan.
                        </p>
                    </div>
                </div>

                <!-- Info Card: Total Disetor -->
                <div class="bg-gradient-to-br from-blue-500 to-indigo-600 overflow-hidden shadow-lg shadow-blue-200 dark:shadow-none rounded-2xl border border-blue-400 hover:scale-105 hover:shadow-xl hover:shadow-blue-300 transition-all duration-300">
                    <div class="p-6 relative overflow-hidden text-white">
                        <!-- Decorative background circle -->
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
                        
                        <div class="flex items-center justify-between mb-2 relative z-10">
                            <div class="text-sm font-medium text-blue-50">Total Uang di Bank</div>
                            <svg class="w-5 h-5 text-blue-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                        </div>
                        <div class="text-xl xl:text-2xl font-extrabold tracking-tight relative z-10 truncate" title="Rp {{ number_format($this->totalDisetor, 0, ',', '.') }}">
                            Rp {{ number_format($this->totalDisetor, 0, ',', '.') }}
                        </div>
                        <p class="mt-2 text-xs text-blue-100/80 relative z-10">
                            Netto uang tabungan yang saat ini ada di bank/koperasi.
                        </p>
                    </div>
                </div>

                <!-- Info Card: Total Ditarik -->
                <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 overflow-hidden shadow-lg shadow-emerald-200 dark:shadow-none rounded-2xl border border-emerald-400 hover:scale-105 hover:shadow-xl hover:shadow-emerald-300 transition-all duration-300">
                    <div class="p-6 relative overflow-hidden text-white">
                        <!-- Decorative background circle -->
                        <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
                        
                        <div class="flex items-center justify-between mb-2 relative z-10">
                            <div class="text-sm font-medium text-emerald-50">Total Tarik Bank</div>
                            <svg class="w-5 h-5 text-emerald-100" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                        </div>
                        <div class="text-xl xl:text-2xl font-extrabold tracking-tight relative z-10 truncate" title="Rp {{ number_format($this->totalDitarik, 0, ',', '.') }}">
                            Rp {{ number_format($this->totalDitarik, 0, ',', '.') }}
                        </div>
                        <p class="mt-2 text-xs text-emerald-100/80 relative z-10">
                            Total uang ditarik dari bank tahun ajaran ini.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 flex flex-col">
                <div class="p-6 flex-grow">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4 border-b border-gray-100 dark:border-gray-700 pb-2">Catat Transaksi Bank</h3>
                    <style>
                        .layout-30-70 {
                            display: grid;
                            grid-template-columns: 1fr;
                            gap: 1.5rem;
                        }
                        @media (min-width: 768px) {
                            .layout-30-70 {
                                grid-template-columns: 3fr 7fr;
                            }
                        }
                    </style>
                    <form wire:submit="prosesSetor" class="layout-30-70 h-full">
                        
                        <!-- Kolom 1: Transaction Type Selection (30%) -->
                        <div class="space-y-4">
                            <label class="relative flex cursor-pointer rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm focus:outline-none overflow-hidden transition-all {{ $jenisTransaksi === 'setor' ? 'ring-2 ring-emerald-500 dark:ring-emerald-400 border-emerald-500 bg-emerald-50 dark:bg-emerald-900/40' : 'bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700/80 hover:border-emerald-300' }}">
                                <input type="radio" wire:model.live="jenisTransaksi" value="setor" class="sr-only">
                                <div class="flex w-full items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="text-sm">
                                            <p class="font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Setor ke Bank
                                            </p>
                                            <div class="text-gray-500 dark:text-gray-400 mt-1 text-xs">Uang dari tangan guru dimasukkan ke bank.</div>
                                        </div>
                                    </div>
                                    @if($jenisTransaksi === 'setor')
                                        <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400 flex-shrink-0 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </div>
                            </label>

                            <label class="relative flex cursor-pointer rounded-xl border border-gray-200 dark:border-gray-700 p-4 shadow-sm focus:outline-none overflow-hidden transition-all {{ $jenisTransaksi === 'tarik' ? 'ring-2 ring-rose-500 dark:ring-rose-400 border-rose-500 bg-rose-50 dark:bg-rose-900/40' : 'bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700/80 hover:border-rose-300' }}">
                                <input type="radio" wire:model.live="jenisTransaksi" value="tarik" class="sr-only">
                                <div class="flex w-full items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="text-sm">
                                            <p class="font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-full bg-rose-500"></span> Tarik dari Bank
                                            </p>
                                            <div class="text-gray-500 dark:text-gray-400 mt-1 text-xs">Mengambil uang dari bank ke tangan guru.</div>
                                        </div>
                                    </div>
                                    @if($jenisTransaksi === 'tarik')
                                        <svg class="h-5 w-5 text-rose-600 dark:text-rose-400 flex-shrink-0 ml-2" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </div>
                            </label>
                        </div>
                        
                        <!-- Kolom 2: Form Inputs (70%) -->
                        <div class="space-y-4 flex flex-col h-full">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nominal (Ribu Rp)</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">Rp</span>
                                        </div>
                                        <input type="text" wire:model.live="jumlahSetor" 
                                               class="pl-10 block w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm"
                                               placeholder="Contoh: 1500 (untuk 1.500.000)" required>
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">.000</span>
                                        </div>
                                    </div>
                                    @if($jumlahSetor)
                                        <div class="text-xs text-indigo-600 dark:text-indigo-400 mt-1 font-medium">
                                            = Rp {{ number_format(floatval($jumlahSetor) * 1000, 0, ',', '.') }} 
                                            <span class="italic text-gray-500 dark:text-gray-400">({{ trim($this->getTerbilang(floatval($jumlahSetor) * 1000)) }} Rupiah)</span>
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Ketik 1 untuk Rp 1.000</div>
                                    @endif
                                    @error('jumlahSetor') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Transaksi</label>
                                    <input type="date" wire:model="tanggalSetor" 
                                           class="block w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm"
                                           required>
                                    @error('tanggalSetor') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            
                            <div class="flex-grow">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Keterangan (Opsional)</label>
                                <textarea wire:model="keterangan" rows="2" 
                                          class="block w-full h-[88px] rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:ring-emerald-500 focus:border-emerald-500 sm:text-sm"
                                          placeholder="Tambahkan catatan jika diperlukan..."></textarea>
                                @error('keterangan') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="pt-2 mt-auto">
                                @php
                                    $disabled = ($jenisTransaksi === 'setor' && $this->cashGuru <= 0) || ($jenisTransaksi === 'tarik' && $this->totalDisetor <= 0);
                                    $btnColor = $jenisTransaksi === 'setor' ? 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500' : 'bg-rose-600 hover:bg-rose-700 focus:ring-rose-500';
                                @endphp
                                <button type="submit" 
                                        class="inline-flex justify-center items-center py-3 px-6 border border-transparent shadow-sm text-sm font-bold rounded-xl text-white {{ $btnColor }} focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors disabled:opacity-50 w-full"
                                        wire:loading.attr="disabled"
                                        @if($disabled) disabled @endif>
                                    <svg wire:loading wire:target="prosesSetor" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Proses Transaksi
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bagian Bawah: Riwayat & Grafik dalam 1 Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 mb-6">
                <div class="p-5 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                    <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">Riwayat Terakhir</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2">
                    <!-- Tabel Riwayat 5 Terakhir -->
                    <div class="flex flex-col h-full border-b md:border-b-0 md:border-r border-gray-200 dark:border-gray-700">
                        <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-800">
                            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300">Riwayat Transaksi</h4>
                        </div>
                        <div class="overflow-x-auto flex-grow">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/30">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tgl / Ket</th>
                                        <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nominal</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                    @forelse ($this->riwayatSetoran as $setor)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors group cursor-pointer" title="{{ $setor->keterangan ?: 'Tanpa keterangan' }}">
                                            <td class="px-4 py-3">
                                                <div class="flex items-center space-x-3">
                                                    @if($setor->jenis === 'setor')
                                                        <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                                                        </div>
                                                    @else
                                                        <div class="w-8 h-8 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center flex-shrink-0">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <div class="text-xs font-medium text-gray-900 dark:text-gray-100">
                                                            {{ \Carbon\Carbon::parse($setor->tanggal)->translatedFormat('d M Y') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                <div class="text-sm font-bold {{ $setor->jenis === 'setor' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                                    {{ $setor->jenis === 'setor' ? '+' : '-' }}Rp {{ number_format($setor->jumlah, 0, ',', '.') }}
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                                <div class="flex flex-col items-center justify-center">
                                                    <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                                    <p class="text-xs">Belum ada riwayat transaksi.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Grafik Card -->
                    <div class="flex flex-col h-full"
                         x-data="{ chartData: @entangle('chartData') }"
                         x-init="
                            let chart = null;
                            const renderChart = () => {
                                if (!window.ApexCharts) {
                                    // Load ApexCharts if not available
                                    const script = document.createElement('script');
                                    script.src = 'https://cdn.jsdelivr.net/npm/apexcharts';
                                    script.onload = () => initChart();
                                    document.head.appendChild(script);
                                } else {
                                    initChart();
                                }
                            };
                            const initChart = () => {
                                if (chart) chart.destroy();
                                const options = {
                                    series: [{
                                        name: 'Setor',
                                        data: chartData.setor || []
                                    }, {
                                        name: 'Tarik',
                                        data: chartData.tarik || []
                                    }],
                                    chart: {
                                        type: 'area',
                                        height: 300,
                                        fontFamily: 'inherit',
                                        toolbar: { show: false },
                                        zoom: { enabled: false }
                                    },
                                    colors: ['#10b981', '#f43f5e'],
                                    dataLabels: { 
                                        enabled: true,
                                        formatter: function (val) {
                                            if (val === 0 || !val) return '';
                                            if (val >= 1000000) return (val / 1000000).toLocaleString('id-ID') + 'Jt';
                                            if (val >= 1000) return (val / 1000).toLocaleString('id-ID') + 'rb';
                                            return val.toLocaleString('id-ID');
                                        },
                                        style: {
                                            fontSize: '10px',
                                            fontWeight: 'bold',
                                        },
                                        background: {
                                            enabled: true,
                                            padding: 4,
                                            borderRadius: 4,
                                            borderWidth: 0,
                                            opacity: 1
                                        }
                                    },
                                    markers: {
                                        size: 4,
                                        strokeWidth: 2,
                                        hover: { size: 6 }
                                    },
                                    stroke: { curve: 'smooth', width: 2 },
                                    xaxis: {
                                        categories: chartData.categories || [],
                                        tooltip: { enabled: false }
                                    },
                                    yaxis: {
                                        labels: {
                                            formatter: function (value) {
                                                return value >= 1000000 
                                                    ? 'Rp ' + (value / 1000000).toFixed(1) + 'M' 
                                                    : (value >= 1000 ? 'Rp ' + (value / 1000).toFixed(0) + 'K' : 'Rp ' + value);
                                            }
                                        }
                                    },
                                    tooltip: {
                                        y: {
                                            formatter: function (val) {
                                                return 'Rp ' + new Intl.NumberFormat('id-ID').format(val)
                                            }
                                        }
                                    }
                                };
                                chart = new ApexCharts($refs.koperasiChart, options);
                                chart.render();
                            };
                            renderChart();
                            $watch('chartData', value => {
                                if (chart && window.ApexCharts) {
                                    chart.updateOptions({
                                        xaxis: { categories: value.categories }
                                    });
                                    chart.updateSeries([
                                        { name: 'Setor', data: value.setor },
                                        { name: 'Tarik', data: value.tarik }
                                    ]);
                                }
                            });
                         ">
                        <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-800">
                            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300">Grafik Transaksi</h4>
                        </div>
                        <div class="p-4 flex-grow flex items-center justify-center">
                            <div wire:ignore x-ref="koperasiChart" class="w-full h-[300px]"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rekap Bulanan Transaksi -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 mb-6">
                <div class="p-5 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                    <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">Rekap Bulanan Transaksi</h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2">
                    <!-- Semester Ganjil -->
                    <div class="flex flex-col h-full border-b md:border-b-0 md:border-r border-gray-200 dark:border-gray-700">
                        <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-800">
                            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300">Semester Ganjil</h4>
                        </div>
                        <div class="overflow-x-auto flex-grow">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/30">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bulan</th>
                                        <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Setor</th>
                                        <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tarik</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                    @foreach ($this->rekapBulanan['ganjil'] as $rekap)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $rekap['bulan'] }}</td>
                                            <td class="px-4 py-3 text-sm text-right font-bold {{ $rekap['setor'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                                {{ $rekap['setor'] > 0 ? '+Rp ' . number_format($rekap['setor'], 0, ',', '.') : '-' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-right font-bold {{ $rekap['tarik'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400' }}">
                                                {{ $rekap['tarik'] > 0 ? '-Rp ' . number_format($rekap['tarik'], 0, ',', '.') : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Semester Genap -->
                    <div class="flex flex-col h-full">
                        <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-800">
                            <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300">Semester Genap</h4>
                        </div>
                        <div class="overflow-x-auto flex-grow">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/30">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bulan</th>
                                        <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Setor</th>
                                        <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tarik</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                    @foreach ($this->rekapBulanan['genap'] as $rekap)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $rekap['bulan'] }}</td>
                                            <td class="px-4 py-3 text-sm text-right font-bold {{ $rekap['setor'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                                {{ $rekap['setor'] > 0 ? '+Rp ' . number_format($rekap['setor'], 0, ',', '.') : '-' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-right font-bold {{ $rekap['tarik'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400' }}">
                                                {{ $rekap['tarik'] > 0 ? '-Rp ' . number_format($rekap['tarik'], 0, ',', '.') : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Load ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        function updateClock() {
            const el = document.getElementById('realtime-clock');
            if (el) {
                const now = new Date();
                const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                const dateStr = now.toLocaleDateString('id-ID', dateOptions);
                const timeStr = now.toLocaleTimeString('id-ID');
                el.textContent = dateStr + ' - ' + timeStr.replace(/\./g, ':');
            }
        }
        setInterval(updateClock, 1000);
        document.addEventListener('livewire:navigated', updateClock);
        updateClock();
    </script>
</div>
