<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Transaksi;
use App\Models\Siswa;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $searchQuery = '';
    public $selectedSiswaId = null;
    public $selectedRombelHistoryId = null;

    public function selectRombelHistory($rombelId)
    {
        $this->selectedRombelHistoryId = $rombelId;
        $this->resetPage();
    }

    public $search = '';
    public $perPage = 15;
    public $startDate = '';
    public $endDate = '';
    public $filterBulan = '';
    public $jenis = '';

    // Automatically search for QR code matches as they type (or scan)
    public function updatedSearchQuery()
    {
        // Jika hasil query cocok persis dengan uuid_qr (berarti hasil scan QR)
        if (!empty($this->searchQuery)) {
            $query = Siswa::where('uuid_qr', $this->searchQuery);
            $exactMatch = $query->first();
            if ($exactMatch) {
                $this->selectSiswa($exactMatch->id);
            }
        }
    }

    // Dipanggil saat menekan tombol Enter pada input pencarian
    public function searchSiswa()
    {
        $this->selectedSiswaId = null;

        if (empty($this->searchQuery)) return;

        $user = auth()->user();
        
        $query = Siswa::query();
        
        if ($user->role === 'guru') {
            $guruId = $user->guru->id;
            $query->where(function ($q) use ($guruId) {
                // Kondisi 1: Berdasarkan nomor urut di rombel aktif guru ini
                $q->whereHas('rombels', function ($r) use ($guruId) {
                    $r->where('guru_id', $guruId)
                      ->whereHas('tahunAjaran', function($ta) {
                          $ta->where('is_active', true);
                      })
                      ->where('rombel_siswa.nomor_urut', $this->searchQuery);
                })
                // Kondisi 2: Berdasarkan uuid_qr (fallback jika scanner tidak memicu updatedSearchQuery)
                ->orWhere(function ($q2) use ($guruId) {
                    $q2->where('uuid_qr', $this->searchQuery)
                       ->whereHas('rombels', function ($r2) use ($guruId) {
                           $r2->where('guru_id', $guruId)
                              ->whereHas('tahunAjaran', function($ta) {
                                  $ta->where('is_active', true);
                              });
                       });
                })
                // Kondisi 3: Berdasarkan NIS
                ->orWhere('nis', $this->searchQuery);
            });
        } else {
            // Admin bisa cari berdasarkan NIS, nama atau QR
            $query->where('nis', $this->searchQuery)
                  ->orWhere('uuid_qr', $this->searchQuery);
        }

        $siswa = $query->first();

        if ($siswa) {
            $this->selectSiswa($siswa->id);
            $this->resetValidation('searchQuery');
        } else {
            $this->addError('searchQuery', 'Siswa tidak ditemukan.');
        }
    }

    public function selectSiswa($siswaId)
    {
        $this->selectedSiswaId = $siswaId;
        $this->searchQuery = '';
        $this->selectedRombelHistoryId = null;
        $this->resetValidation();
        $this->resetFilter();
    }

    public function with(): array
    {
        $siswa = $this->selectedSiswaId ? Siswa::with(['rombels.tahunAjaran'])->find($this->selectedSiswaId) : null;
        $transaksis = null;
        $riwayatRombels = [];

        if ($siswa) {
            $isAdmin = auth()->user()->role === 'admin';
            
            if ($isAdmin) {
                $riwayatRombels = collect($siswa->rombels)->sortBy(function($r) {
                    return $r->tingkat ?? 99;
                })->values();
            }

            $shouldShowTransactions = true;
            if ($isAdmin && !$this->selectedRombelHistoryId) {
                $shouldShowTransactions = false;
            }

            if ($shouldShowTransactions) {
                $query = Transaksi::with(['guru.user'])
                            ->where('siswa_id', $siswa->id)
                            ->latest();
                            
                if ($isAdmin && $this->selectedRombelHistoryId) {
                    $query->where('rombel_id', $this->selectedRombelHistoryId);
                }

                if ($this->startDate) {
                    $query->whereDate('tanggal', '>=', $this->startDate);
                }
                
                if ($this->endDate) {
                    $query->whereDate('tanggal', '<=', $this->endDate);
                }

                if ($this->filterBulan) {
                    $query->whereYear('tanggal', substr($this->filterBulan, 0, 4))
                          ->whereMonth('tanggal', substr($this->filterBulan, 5, 2));
                }

                if ($this->jenis) {
                    $query->where('jenis', $this->jenis);
                }

                $transaksis = $query->paginate($this->perPage);
            }
        }

        return [
            'selectedSiswa' => $siswa,
            'riwayatRombels' => collect($riwayatRombels),
            'transaksis' => $transaksis,
        ];
    }
    
    public function resetFilter()
    {
        $this->reset(['startDate', 'endDate', 'filterBulan', 'jenis']);
        $this->resetPage();
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Mutasi Tabungan Siswa') }}
        </h2>
    </x-slot>

    <div class="py-6 space-y-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Kotak Pencarian -->
            <div class="print:hidden bg-white dark:bg-gray-800 shadow-sm sm:rounded-2xl p-6 border border-gray-100 dark:border-gray-700 mb-6 {{ $this->selectedSiswaId ? '' : 'max-w-2xl mx-auto' }}">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Pilih Siswa (Berdasarkan Nomor Urut/NIS)</h3>
                        
                <div class="relative flex gap-2">
                    <div class="relative flex-1">
                        <input type="text" id="searchInput" wire:model="searchQuery" wire:keydown.enter="searchSiswa" 
                               x-data @focus-search.window="setTimeout(() => $el.focus(), 150)"
                               class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 pl-10 pr-10 py-3" 
                               placeholder="Ketik Nomor Urut / NIS lalu tekan Enter..." autofocus>
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <!-- Loading Spinner -->
                        <div wire:loading wire:target="searchSiswa" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                            <svg class="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                    <button type="button" @click="$dispatch('open-scanner')" class="flex-none bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 rounded-xl px-4 py-3 flex items-center justify-center transition-colors" title="Pindai QR Code">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                    </button>
                </div>
                @error('searchQuery') <span class="text-sm text-red-600 mt-2 block">{{ $message }}</span> @enderror
            </div>

            @if($selectedSiswa)
                <div class="flex flex-col gap-6 mb-6">
                    <!-- Profil Siswa -->
                    <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-6 text-white shadow-lg shadow-emerald-200 dark:shadow-none relative overflow-hidden flex flex-col justify-center h-full">
                        <div class="absolute top-0 right-0 -mt-4 -mr-4 w-32 h-32 bg-white opacity-10 rounded-full blur-2xl"></div>
                        <div class="relative z-10 flex flex-col sm:flex-row items-center sm:items-start gap-4">
                            <div class="w-16 h-16 bg-white/20 rounded-2xl backdrop-blur-sm border border-white/30 flex items-center justify-center text-3xl font-bold flex-shrink-0">
                                {{ substr($selectedSiswa->nama, 0, 1) }}
                            </div>
                            <div class="flex-1 text-center sm:text-left">
                                <h3 class="text-xl font-bold truncate" title="{{ $selectedSiswa->nama }}">{{ $selectedSiswa->nama }}</h3>
                                <div class="flex flex-col gap-1 mt-2 text-emerald-100 text-sm">
                                    <div class="flex items-center justify-center sm:justify-start">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
                                        NIS: {{ $selectedSiswa->nis ?? '-' }}
                                    </div>
                                    <div class="flex items-center justify-center sm:justify-start">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                        WA: {{ $selectedSiswa->no_wa_ortu ?? '-' }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-center sm:text-right bg-white/10 rounded-xl p-3 backdrop-blur-sm border border-white/20 min-w-[140px]">
                                <div class="text-emerald-100 text-xs font-medium mb-1">Total Saldo</div>
                                <div class="text-xl font-bold truncate">Rp {{ number_format($selectedSiswa->saldo, 0, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>

                    @if(auth()->user()->role === 'admin')
                        <!-- Riwayat Kelas / Rombel Siswa -->
                        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700 shadow-sm">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Pilih Riwayat Kelas (Rombel)</h3>
                            @if(count($riwayatRombels) > 0)
                                <div class="grid gap-3 lg:gap-4" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                                    @foreach($riwayatRombels as $rombel)
                                        <button wire:click="selectRombelHistory({{ $rombel->id }})" class="text-left px-5 py-4 rounded-2xl transition-all duration-300 transform hover:-translate-y-1 {{ $selectedRombelHistoryId === $rombel->id ? 'bg-gradient-to-br from-emerald-500 to-emerald-600 text-white shadow-lg shadow-emerald-300/50 dark:shadow-none ring-2 ring-emerald-400 ring-offset-2 dark:ring-offset-gray-900 border-none' : 'bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-md hover:shadow-lg opacity-90 hover:opacity-100 border-none' }}">
                                            <div class="font-bold text-base md:text-lg mb-1">{{ $rombel->nama_kelas }}</div>
                                            <div class="text-xs text-white/80 font-medium">TA: {{ $rombel->tahunAjaran->nama ?? '-' }} {{ $rombel->tahunAjaran && $rombel->tahunAjaran->is_active ? '(Aktif)' : '' }}</div>
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-gray-500 dark:text-gray-400 text-sm">Siswa belum memiliki riwayat kelas.</div>
                            @endif
                        </div>
                    @endif

                    <!-- Filter Mutasi -->
                    @if($transaksis !== null)
                    <div class="print:hidden bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-6 text-white shadow-lg shadow-blue-200 dark:shadow-none flex flex-col justify-center h-full relative overflow-hidden">
                        <div class="absolute -right-4 -top-4 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                        <div class="relative z-10 flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-white">Filter Pencarian Mutasi</h3>
                            <button wire:click="resetFilter" class="p-1.5 bg-white/20 hover:bg-white/30 text-white rounded-lg transition" title="Reset Filter">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                            </button>
                        </div>
                        <div class="relative z-10 flex flex-row gap-3">
                            <div class="flex-1 min-w-0">
                                <label class="block text-xs font-medium text-blue-100 mb-1 truncate" title="Dari Tanggal">Dari Tanggal</label>
                                <input type="date" wire:model.live="startDate" class="w-full py-2 px-2 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors text-xs cursor-pointer text-gray-900">
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <label class="block text-xs font-medium text-blue-100 mb-1 truncate" title="Sampai Tanggal">Sampai Tanggal</label>
                                <input type="date" wire:model.live="endDate" class="w-full py-2 px-2 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors text-xs cursor-pointer text-gray-900">
                            </div>

                            <div class="flex-1 min-w-0">
                                <label class="block text-xs font-medium text-blue-100 mb-1 truncate" title="Bulan">Bulan</label>
                                <input type="month" wire:model.live="filterBulan" class="w-full py-2 px-2 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors text-xs cursor-pointer text-gray-900">
                            </div>
                            
                            <div class="flex-1 min-w-0">
                                <label class="block text-xs font-medium text-blue-100 mb-1 truncate" title="Jenis Transaksi">Jenis Transaksi</label>
                                <select wire:model.live="jenis" class="w-full py-2 px-1 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors text-xs cursor-pointer text-gray-900">
                                    <option value="">Semua Jenis</option>
                                    <option value="setor">Setor</option>
                                    <option value="tarik">Tarik</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                    @else
                    <div class="bg-white dark:bg-gray-800 rounded-2xl p-8 border border-gray-100 dark:border-gray-700 flex flex-col justify-center items-center text-center h-full shadow-sm border-dashed">
                         <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center text-gray-400 mb-4">
                             <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                         </div>
                         <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">Pilih Riwayat Kelas</h3>
                         <p class="text-sm text-gray-500 dark:text-gray-400">Silakan pilih riwayat kelas di atas untuk melihat filter pencarian dan mutasi.</p>
                    </div>
                    @endif
                </div>

                @if($transaksis !== null)
                <!-- Mutasi Table -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden transition-colors">
                    <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Riwayat Transaksi</h3>
                        <a href="{{ route('mutasi.cetak-pdf', ['siswa_id' => $selectedSiswaId, 'start_date' => $startDate, 'end_date' => $endDate, 'bulan' => $filterBulan, 'jenis' => $jenis]) }}" target="_blank" class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Cetak PDF
                        </a>
                    </div>

                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50/50 dark:bg-gray-700/50">
                                    <th class="py-4 px-6 font-semibold text-sm text-gray-600 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 whitespace-nowrap">TANGGAL</th>
                                    <th class="py-4 px-6 font-semibold text-sm text-gray-600 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 whitespace-nowrap">JENIS</th>
                                    <th class="py-4 px-6 font-semibold text-sm text-gray-600 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 text-right whitespace-nowrap">NOMINAL</th>
                                    <th class="py-4 px-6 font-semibold text-sm text-gray-600 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 whitespace-nowrap">PETUGAS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50 text-sm">
                                @forelse($transaksis as $trx)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                        <td class="py-3 px-6 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                            {{ \Carbon\Carbon::parse($trx->tanggal)->translatedFormat('d M Y') }}
                                            <div class="text-xs text-gray-400 dark:text-gray-500">{{ $trx->created_at->format('H:i') }}</div>
                                        </td>
                                        <td class="py-3 px-6 whitespace-nowrap">
                                            @if($trx->jenis === 'setor')
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path></svg>
                                                    Setor
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400">
                                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                                                    Tarik
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-6 text-right whitespace-nowrap">
                                            <div class="font-bold {{ $trx->jenis === 'setor' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ $trx->jenis === 'setor' ? '+' : '-' }} Rp {{ number_format($trx->jumlah, 0, ',', '.') }}
                                            </div>
                                            @if($trx->keterangan)
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 max-w-[120px] truncate ml-auto" title="{{ $trx->keterangan }}">{{ $trx->keterangan }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3 px-6 text-gray-600 dark:text-gray-300">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-700 dark:text-indigo-400 font-bold text-xs">
                                                    {{ substr($trx->guru->user->name ?? 'A', 0, 1) }}
                                                </div>
                                                <span class="truncate max-w-[120px]">{{ $trx->guru->user->name ?? 'Admin' }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-12 text-center">
                                            <div class="flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                                                <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                                <p class="text-lg font-medium text-gray-900 dark:text-gray-200">Belum ada mutasi</p>
                                                <p class="text-sm mt-1">Data riwayat transaksi tabungan akan muncul di sini.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    @if($transaksis->hasPages())
                        <div class="p-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                            {{ $transaksis->links() }}
                        </div>
                    @endif
                </div>
                @endif
            @else
                <!-- Blank State if no student selected -->
                <div class="bg-gray-50 dark:bg-gray-800/50 shadow-inner sm:rounded-2xl p-12 border border-gray-100 dark:border-gray-700 border-dashed flex flex-col items-center justify-center text-center max-w-2xl mx-auto mb-6">
                    <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center text-gray-400 dark:text-gray-500 mb-4 mx-auto">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Menunggu Input</h3>
                    <p class="text-gray-500 dark:text-gray-400 mt-2 max-w-sm mx-auto">
                        Silakan pindai QR Code atau ketik NIS/Nomor Urut siswa di kotak pencarian untuk melihat mutasi tabungannya.
                    </p>
                </div>
            @endif
        </div>
    </div>
    
    <!-- Scanner Modal (AlpineJS) -->
    <div x-data="{
             showScanner: false,
             html5QrcodeScanner: null,
             initScanner() {
                 this.showScanner = true;
                 setTimeout(() => {
                     this.html5QrcodeScanner = new Html5QrcodeScanner(
                         'reader',
                         { fps: 10, qrbox: {width: 250, height: 250} },
                         false);
                     this.html5QrcodeScanner.render((decodedText, decodedResult) => {
                         // On success, set the livewire search query
                         @this.set('searchQuery', decodedText);
                         this.closeScanner();
                     }, (error) => {
                         // ignore errors
                     });
                 }, 100);
             },
             closeScanner() {
                 if (this.html5QrcodeScanner) {
                     this.html5QrcodeScanner.clear();
                     this.html5QrcodeScanner = null;
                 }
                 this.showScanner = false;
             }
         }"
         @open-scanner.window="initScanner()"
         @keydown.escape.window="closeScanner()"
         x-show="showScanner"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;"
         aria-labelledby="modal-title" role="dialog" aria-modal="true">
         
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showScanner" x-transition.opacity class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeScanner()" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="showScanner" x-transition.scale class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4" id="modal-title">Pindai QR Code Siswa</h3>
                            
                            <!-- Scanner Target -->
                            <div id="reader" width="600px" class="bg-gray-100 dark:bg-gray-700 rounded-xl overflow-hidden border-2 border-gray-200 dark:border-gray-600"></div>
                            <p class="text-sm text-gray-500 mt-2 text-center">Arahkan kamera ke QR Code yang ada di label buku tabungan siswa.</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" @click="closeScanner()" class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Tutup Kamera
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Load HTML5-QRCode Library via CDN -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</div>
