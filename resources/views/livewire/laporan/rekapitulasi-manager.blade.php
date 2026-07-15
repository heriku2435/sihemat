<?php

use Livewire\Volt\Component;
use App\Models\TahunAjaran;
use App\Models\Rombel;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public $tahunAjarans = [];
    public $rombels = [];
    
    public $selectedTahunAjaran = null;
    public $selectedRombel = null;
    public $activeTab = 'ganjil';
    public $isDashboard = false;

    public function mount()
    {
        $this->tahunAjarans = TahunAjaran::orderBy('tanggal_mulai', 'desc')->get();
        if ($this->tahunAjarans->count() > 0) {
            $this->selectedTahunAjaran = $this->tahunAjarans->where('is_active', true)->first()->id ?? $this->tahunAjarans->first()->id;
        }

        $this->loadRombels();

        $m = now()->month;
        if ($m >= 7 && $m <= 12) {
            $this->activeTab = 'ganjil';
        } else {
            $this->activeTab = 'genap';
        }
    }

    public function loadRombels()
    {
        if (auth()->user()->role === 'admin') {
            $this->rombels = Rombel::where('tahun_ajaran_id', $this->selectedTahunAjaran)->get();
        } else {
            $guruId = auth()->user()->guru->id ?? null;
            $this->rombels = Rombel::where('tahun_ajaran_id', $this->selectedTahunAjaran)
                                   ->where('guru_id', $guruId)
                                   ->get();
        }

        if ($this->rombels->count() > 0) {
            $this->selectedRombel = $this->rombels->first()->id;
        } else {
            $this->selectedRombel = null;
        }
    }

    public function updatedSelectedTahunAjaran()
    {
        $this->loadRombels();
    }

    public function with(): array
    {
        $data = [];
        $months = [];
        $ta = null;
        $rombel = null;

        if ($this->selectedTahunAjaran && $this->selectedRombel) {
            $ta = TahunAjaran::find($this->selectedTahunAjaran);
            $rombel = Rombel::find($this->selectedRombel);

            if ($ta && $rombel) {
                // Generate months array
                $start = Carbon::parse($ta->tanggal_mulai)->startOfMonth();
                $end = Carbon::parse($ta->tanggal_selesai)->endOfMonth();
                
                $current = $start->copy();
                while ($current <= $end) {
                    $m = $current->month;
                    $isGanjil = $m >= 7 && $m <= 12;
                    
                    if (($this->activeTab === 'ganjil' && $isGanjil) || ($this->activeTab === 'genap' && !$isGanjil)) {
                        $months[] = [
                            'key' => $current->format('Y-m'),
                            'label' => $current->translatedFormat('M y'),
                        ];
                    }
                    $current->addMonth();
                }

                // Get students in this rombel
                $students = $rombel->siswas()->orderBy('nama')->get();

                // Get all transactions for these students in this date range
                $studentIds = $students->pluck('id')->toArray();
                if (!empty($studentIds)) {
                    $transaksis = Transaksi::whereIn('siswa_id', $studentIds)
                        ->whereBetween('tanggal', [$ta->tanggal_mulai, $ta->tanggal_selesai])
                        ->select('siswa_id', 'jenis', 'jumlah', DB::raw("DATE_FORMAT(tanggal, '%Y-%m') as bulan"))
                        ->get();

                    // Group by student and month
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
                            
                            $studentData['monthly'][$monthKey] = $setor - $tarik; // Net mutasi untuk bulan ini
                        }
                        $data[] = $studentData;
                    }
                }
            }
        }

        return [
            'taInfo' => $ta,
            'rombelInfo' => $rombel,
            'months' => $months,
            'rekapData' => $data,
        ];
    }
};
?>

<div>
    <div class="{{ $isDashboard ? 'space-y-0' : 'py-8 space-y-6' }}">
        @if(!$isDashboard)
        <!-- Header Card -->
        <div class="bg-indigo-600 rounded-3xl p-8 text-white shadow-xl shadow-indigo-200 dark:shadow-none relative overflow-hidden transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-300">
            <div class="absolute top-0 right-0 -mr-8 -mt-8 w-48 h-48 rounded-full bg-white opacity-10 blur-2xl"></div>
            <div class="absolute bottom-0 right-32 -mb-12 w-32 h-32 rounded-full bg-white opacity-10 blur-xl"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                    <h2 class="text-3xl font-black mb-2 tracking-tight">Rekapitulasi Transaksi</h2>
                    <p class="text-indigo-100 font-medium text-lg max-w-2xl leading-relaxed">
                        Tabel rekapitulasi net transaksi (setor dikurangi tarik) per siswa per bulan berdasarkan semester.
                    </p>
                </div>
            </div>
        </div>

        <!-- Info Card -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-xl shadow-gray-200/50 dark:shadow-none border border-gray-100 dark:border-gray-700 transition-all duration-300">
            <div class="flex flex-col md:flex-row items-center gap-6 justify-around">
                <div class="flex flex-col items-center text-center">
                    <p class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Tahun Ajaran</p>
                    @if(auth()->user()->role === 'admin')
                    <div class="relative inline-block">
                        <select wire:model.live="selectedTahunAjaran" class="bg-indigo-50 dark:bg-indigo-900/30 pl-6 pr-10 py-2 rounded-xl border border-indigo-100 dark:border-indigo-800 text-xl font-black text-indigo-700 dark:text-indigo-400 appearance-none focus:outline-none focus:ring-2 focus:ring-indigo-500 cursor-pointer text-center">
                            @foreach($tahunAjarans as $ta)
                                <option value="{{ $ta->id }}" class="text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 text-base font-medium">{{ $ta->nama }}{{ $ta->is_active ? ' (Aktif)' : '' }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-indigo-700 dark:text-indigo-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>
                    @else
                    <div class="bg-indigo-50 dark:bg-indigo-900/30 px-6 py-2 rounded-xl border border-indigo-100 dark:border-indigo-800">
                        <p class="text-xl font-black text-indigo-700 dark:text-indigo-400">{{ $taInfo ? $taInfo->nama : '-' }}</p>
                    </div>
                    @endif
                </div>
                
                <div class="hidden md:block w-px h-16 bg-gray-200 dark:bg-gray-700"></div>
                
                <div class="flex flex-col items-center text-center">
                    <p class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Kelas / Rombel</p>
                    @if(auth()->user()->role === 'admin' || collect($rombels)->count() > 1)
                    <div class="relative inline-block">
                        <select wire:model.live="selectedRombel" class="bg-emerald-50 dark:bg-emerald-900/30 pl-6 pr-10 py-2 rounded-xl border border-emerald-100 dark:border-emerald-800 text-xl font-black text-emerald-700 dark:text-emerald-400 appearance-none focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer text-center">
                            @foreach($rombels as $r)
                                <option value="{{ $r->id }}" class="text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-200 text-base font-medium">{{ $r->nama_kelas }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-emerald-700 dark:text-emerald-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>
                    @else
                    <div class="bg-emerald-50 dark:bg-emerald-900/30 px-6 py-2 rounded-xl border border-emerald-100 dark:border-emerald-800">
                        <p class="text-xl font-black text-emerald-700 dark:text-emerald-400">{{ $rombelInfo ? $rombelInfo->nama_kelas : '-' }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        <!-- Matrix Table Card -->
        <div class="bg-white dark:bg-gray-800 {{ $isDashboard ? 'rounded-2xl' : 'shadow-xl rounded-3xl' }} overflow-hidden transition-all duration-300 flex flex-col border border-gray-100 dark:border-gray-700">
            
            @if($isDashboard)
            <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                <h3 class="text-gray-800 dark:text-gray-100 font-bold">Rekapitulasi Bulanan Transaksi</h3>
                <span class="bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 text-xs px-2 py-1 rounded-md font-medium">
                    {{ $rombelInfo ? 'Kelas ' . $rombelInfo->nama_kelas : 'Tahun Ajaran Aktif' }}
                </span>
            </div>
            @endif
            <!-- Tabs Pill Segment -->
            <div class="p-6 pb-0 flex justify-center">
                <div class="inline-flex bg-gray-100/80 dark:bg-gray-800/80 backdrop-blur-sm p-1.5 rounded-2xl w-full max-w-2xl">
                    <button wire:click="$set('activeTab', 'ganjil')" class="flex-1 px-4 py-2.5 rounded-xl font-bold text-sm transition-all duration-300 {{ $activeTab === 'ganjil' ? 'bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <div class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            SEMESTER GANJIL
                        </div>
                    </button>
                    <button wire:click="$set('activeTab', 'genap')" class="flex-1 px-4 py-2.5 rounded-xl font-bold text-sm transition-all duration-300 {{ $activeTab === 'genap' ? 'bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300' }}">
                        <div class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path></svg>
                            SEMESTER GENAP
                        </div>
                    </button>
                </div>
            </div>

            <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row justify-between items-center gap-4 bg-white dark:bg-gray-900">
                <h3 class="text-xl font-black text-gray-800 dark:text-gray-100 flex items-center gap-2">
                    <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Data Rekapitulasi - Semester {{ ucfirst($activeTab) }}
                </h3>
                @if($selectedTahunAjaran && $selectedRombel)
                <div class="flex gap-2">
                    <a href="{{ route(auth()->user()->role === 'admin' ? 'admin.rekapitulasi.export-excel' : 'guru.rekapitulasi.export-excel', ['ta' => $selectedTahunAjaran, 'rombel' => $selectedRombel]) }}" target="_blank" class="inline-flex items-center justify-center px-4 py-2 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5 gap-2" style="background-color: #10b981;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Export Excel
                    </a>
                    <a href="{{ route(auth()->user()->role === 'admin' ? 'admin.rekapitulasi.cetak-pdf' : 'guru.rekapitulasi.cetak-pdf', ['ta' => $selectedTahunAjaran, 'rombel' => $selectedRombel, 'semester' => $activeTab]) }}" target="_blank" class="inline-flex items-center justify-center px-4 py-2 text-white rounded-xl font-bold text-xs uppercase tracking-wider transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5 gap-2" style="background-color: #ef4444;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Cetak PDF
                    </a>
                </div>
                @endif
            </div>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse min-w-max">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-800/90 backdrop-blur-sm p-3 border-b border-r border-gray-100 dark:border-gray-700 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-center w-10">No</th>
                            <th class="sticky left-10 z-10 bg-gray-50 dark:bg-gray-800/90 backdrop-blur-sm p-3 border-b border-r border-gray-100 dark:border-gray-700 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[150px]">Nama Siswa</th>
                            @foreach($months as $month)
                                <th class="p-3 border-b border-r border-gray-100 dark:border-gray-700 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-right min-w-[90px]">{{ $month['label'] }}</th>
                            @endforeach
                            <th class="p-3 border-b border-gray-100 dark:border-gray-700 text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider text-right min-w-[100px] bg-indigo-50 dark:bg-indigo-900/30">Total Net Semester</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                        @forelse($rekapData as $index => $row)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors group">
                                <td class="sticky left-0 z-10 bg-white dark:bg-gray-800 group-hover:bg-gray-50 dark:group-hover:bg-gray-700/30 p-2 border-r border-gray-100 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400 text-center">{{ $index + 1 }}</td>
                                <td class="sticky left-10 z-10 bg-white dark:bg-gray-800 group-hover:bg-gray-50 dark:group-hover:bg-gray-700/30 p-2 border-r border-gray-100 dark:border-gray-700">
                                    <div class="font-bold text-gray-900 dark:text-gray-100 text-xs">{{ $row['nama'] }}</div>
                                    <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $row['nis'] }}</div>
                                </td>
                                @php $totalNetRow = 0; @endphp
                                @foreach($months as $month)
                                    @php 
                                        $val = $row['monthly'][$month['key']] ?? 0; 
                                        $totalNetRow += $val;
                                    @endphp
                                    <td class="p-2 border-r border-gray-100 dark:border-gray-700 text-xs font-semibold text-right {{ $val > 0 ? 'text-emerald-600 dark:text-emerald-400' : ($val < 0 ? 'text-red-500' : 'text-gray-400 dark:text-gray-500') }}">
                                        {{ $val != 0 ? number_format($val, 0, ',', '.') : '-' }}
                                    </td>
                                @endforeach
                                <td class="p-2 text-xs font-bold text-right bg-indigo-50 dark:bg-indigo-900/30 {{ $totalNetRow > 0 ? 'text-indigo-600 dark:text-indigo-400' : ($totalNetRow < 0 ? 'text-red-600' : 'text-gray-500') }}">
                                    {{ $totalNetRow != 0 ? number_format($totalNetRow, 0, ',', '.') : '0' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($months) + 3 }}" class="p-12 text-center text-gray-500 dark:text-gray-400">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        <span class="text-lg font-bold text-gray-600 dark:text-gray-300">Belum ada data transaksi</span>
                                        <span class="text-sm mt-1">Belum ada rekap data pada semester ini.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
