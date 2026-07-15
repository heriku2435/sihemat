<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\SetoranKoperasi;
use Carbon\Carbon;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $perPage = 15;
    public $startDate = '';
    public $endDate = '';
    public $jenis = '';
    public $filterBulan = '';

    public $tahunAjarans = [];
    public $selectedTahunAjaran = null;
    public $selectedRombel = null;
    public $rombels = [];

    public function mount()
    {
        $this->tahunAjarans = \App\Models\TahunAjaran::orderBy('tanggal_mulai', 'desc')->get();
        if ($this->tahunAjarans->count() > 0) {
            $this->selectedTahunAjaran = $this->tahunAjarans->where('is_active', true)->first()->id ?? $this->tahunAjarans->first()->id;
        }
        $this->loadRombels();
    }

    public function updatedSelectedTahunAjaran()
    {
        $this->selectedRombel = null;
        $this->loadRombels();
    }

    public function loadRombels()
    {
        if ($this->selectedTahunAjaran) {
            $this->rombels = \App\Models\Rombel::where('tahun_ajaran_id', $this->selectedTahunAjaran)->orderBy('nama_kelas')->get();
        } else {
            $this->rombels = collect();
        }
    }

    public function with(): array
    {
        $query = SetoranKoperasi::with(['guru.user'])->latest();
        
        if (auth()->user()->role === 'guru') {
            $query->where('guru_id', auth()->user()->guru->id);
        }

        if ($this->search) {
            if (auth()->user()->role !== 'admin') {
                $query->whereHas('guru.user', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                });
            }
        }

        if (auth()->user()->role === 'admin' && $this->selectedRombel) {
            $rombel = \App\Models\Rombel::find($this->selectedRombel);
            if ($rombel) {
                $query->where('guru_id', $rombel->guru_id);
            }
        }

        if ($this->selectedTahunAjaran) {
            $ta = \App\Models\TahunAjaran::find($this->selectedTahunAjaran);
            if ($ta) {
                $query->whereBetween('tanggal', [$ta->tanggal_mulai, $ta->tanggal_selesai]);
            }
        }

        if ($this->startDate) {
            if (auth()->user()->role !== 'admin') {
                $query->whereDate('tanggal', '>=', $this->startDate);
            }
        }
        
        if ($this->endDate) {
            if (auth()->user()->role !== 'admin') {
                $query->whereDate('tanggal', '<=', $this->endDate);
            }
        }

        if ($this->jenis) {
            $query->where('jenis', $this->jenis);
        }

        if ($this->filterBulan) {
            $query->whereYear('tanggal', substr($this->filterBulan, 0, 4))
                  ->whereMonth('tanggal', substr($this->filterBulan, 5, 2));
        }

        $paginated = $query->paginate($this->perPage);

        foreach ($paginated as $mutasi) {
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

        $totalSaldoQuery = SetoranKoperasi::query();
        
        if (auth()->user()->role === 'guru') {
            $totalSaldoQuery->where('guru_id', auth()->user()->guru->id);
        } else if (auth()->user()->role === 'admin') {
            if ($this->selectedRombel) {
                $rombel = \App\Models\Rombel::find($this->selectedRombel);
                if ($rombel) {
                    $totalSaldoQuery->where('guru_id', $rombel->guru_id);
                }
            }
            if ($this->selectedTahunAjaran) {
                $ta = \App\Models\TahunAjaran::find($this->selectedTahunAjaran);
                if ($ta) {
                    $totalSaldoQuery->whereBetween('tanggal', [$ta->tanggal_mulai, $ta->tanggal_selesai]);
                }
            }
        }

        $totalSaldoBank = $totalSaldoQuery->selectRaw("SUM(CASE WHEN jenis = 'setor' THEN jumlah ELSE -jumlah END) as total")
          ->value('total') ?? 0;

        return [
            'mutasis' => $paginated,
            'totalSaldoBank' => $totalSaldoBank,
        ];
    }
    
    public function resetFilter()
    {
        $this->reset(['search', 'startDate', 'endDate', 'jenis', 'filterBulan', 'selectedRombel']);
        if ($this->tahunAjarans->count() > 0) {
            $this->selectedTahunAjaran = $this->tahunAjarans->where('is_active', true)->first()->id ?? $this->tahunAjarans->first()->id;
            $this->loadRombels();
        }
        $this->resetPage();
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Mutasi Bank') }}
        </h2>
    </x-slot>

    <div class="py-8 space-y-6">
        <!-- Header Card -->
        <div class="bg-indigo-600 rounded-3xl p-8 text-white shadow-xl shadow-indigo-200 dark:shadow-none relative overflow-hidden transition-all duration-300 hover:shadow-2xl hover:shadow-indigo-300">
            <!-- Decorative circles -->
            <div class="absolute top-0 right-0 -mr-8 -mt-8 w-48 h-48 rounded-full bg-white opacity-10 blur-2xl"></div>
            <div class="absolute bottom-0 right-32 -mb-12 w-32 h-32 rounded-full bg-white opacity-10 blur-xl"></div>
            
            <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h3 class="text-2xl font-black tracking-tight mb-2">Riwayat Mutasi Bank / Koperasi</h3>
                    <p class="text-indigo-100 text-sm md:text-base font-medium max-w-2xl">
                        Daftar seluruh transaksi penyetoran atau penarikan dana ke bank atau koperasi sekolah. Pantau arus kas secara *real-time* dan transparan.
                    </p>
                </div>
                
                <!-- Total Balance Widget -->
                <div class="bg-white/20 backdrop-blur-md border border-white/30 rounded-2xl p-4 md:text-right flex-shrink-0 shadow-inner w-full md:w-auto">
                    <p class="text-indigo-100 text-xs uppercase tracking-wider font-bold mb-1 flex items-center md:justify-end">
                        <svg class="w-4 h-4 mr-1.5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Total Saldo Saat Ini
                    </p>
                    <p class="text-2xl md:text-3xl font-black text-white tracking-tight">
                        Rp {{ number_format($totalSaldoBank, 0, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="print:hidden bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-6 text-white shadow-lg shadow-blue-200 dark:shadow-none flex flex-col justify-center h-full relative overflow-hidden">
            <div class="absolute -right-4 -top-4 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
            <div class="relative z-10 flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-white">Filter Pencarian Mutasi</h3>
                <button wire:click="resetFilter" class="p-1.5 bg-white/20 hover:bg-white/30 text-white rounded-lg transition" title="Reset Filter">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                </button>
            </div>
            
            <div class="relative z-10 flex flex-col md:flex-row gap-3">
                @if(auth()->user()->role === 'admin')
                <div class="flex-1 min-w-0">
                    <label class="block text-xs font-medium text-blue-100 mb-1 truncate" title="Tahun Ajaran">Tahun Ajaran</label>
                    <select wire:model.live="selectedTahunAjaran" class="w-full py-2 px-2 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors text-xs cursor-pointer text-gray-900">
                        <option value="">Semua Tahun Ajaran</option>
                        @foreach($tahunAjarans as $ta)
                            <option value="{{ $ta->id }}">{{ $ta->nama }} {{ $ta->is_active ? '(Aktif)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="flex-1 min-w-0">
                    <label class="block text-xs font-medium text-blue-100 mb-1 truncate" title="Pilih Rombel">Pilih Rombel / Kelas</label>
                    <select wire:model.live="selectedRombel" class="w-full py-2 px-2 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors text-xs cursor-pointer text-gray-900">
                        <option value="">Semua Rombel</option>
                        @foreach($rombels as $r)
                            <option value="{{ $r->id }}">{{ $r->nama_kelas }}</option>
                        @endforeach
                    </select>
                </div>
                @else
                <div class="flex-1 min-w-0">
                    <label class="block text-xs font-medium text-blue-100 mb-1 truncate" title="Dari Tanggal">Dari Tanggal</label>
                    <input type="date" wire:model.live="startDate" class="w-full py-2 px-2 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors text-xs cursor-pointer text-gray-900">
                </div>
                
                <div class="flex-1 min-w-0">
                    <label class="block text-xs font-medium text-blue-100 mb-1 truncate" title="Sampai Tanggal">Sampai Tanggal</label>
                    <input type="date" wire:model.live="endDate" class="w-full py-2 px-2 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors text-xs cursor-pointer text-gray-900">
                </div>
                @endif

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

        <!-- Table Card -->
        <div class="bg-gradient-to-br from-purple-500 to-fuchsia-600 shadow-xl rounded-3xl overflow-hidden transition-all duration-300 hover:shadow-2xl flex flex-col">
            <div class="p-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                <h3 class="text-xl font-black text-white">Data Transaksi</h3>
                <a href="{{ route('mutasi.bank.cetak-pdf', ['search' => $search, 'start_date' => $startDate, 'end_date' => $endDate, 'bulan' => $filterBulan, 'jenis' => $jenis]) }}" target="_blank" class="inline-flex items-center justify-center px-5 py-2.5 bg-white/20 hover:bg-white/30 border border-white/30 rounded-xl font-bold text-xs text-white uppercase tracking-wider transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Cetak PDF
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 flex-1">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700">
                                <th class="py-5 px-6 font-bold text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Tanggal & Waktu</th>
                                <th class="py-5 px-6 font-bold text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Jenis Transaksi</th>
                                <th class="py-5 px-6 font-bold text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider text-right whitespace-nowrap">Nominal</th>
                                <th class="py-5 px-6 font-bold text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider text-right whitespace-nowrap">Saldo</th>
                                <th class="py-5 px-6 font-bold text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50 text-sm">
                            @forelse($mutasis as $mutasi)
                                <tr class="hover:bg-fuchsia-50/50 dark:hover:bg-fuchsia-900/20 transition-colors duration-200 group">
                                    <td class="py-4 px-6 whitespace-nowrap">
                                        <div class="font-semibold text-gray-800 dark:text-gray-200">{{ \Carbon\Carbon::parse($mutasi->tanggal)->translatedFormat('d F Y') }}</div>
                                        <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 flex items-center">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            {{ $mutasi->created_at->format('H:i') }} WIB
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 whitespace-nowrap">
                                        @if($mutasi->jenis === 'setor')
                                            <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-800/50">
                                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-2 animate-pulse"></div>
                                                Setor Dana
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-bold bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400 border border-rose-100 dark:border-rose-800/50">
                                                <div class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-2 animate-pulse"></div>
                                                Tarik Dana
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-4 px-6 text-right whitespace-nowrap">
                                        <div class="font-bold text-sm {{ $mutasi->jenis === 'setor' ? 'text-emerald-500 dark:text-emerald-400' : 'text-rose-500 dark:text-rose-400' }}">
                                            {{ $mutasi->jenis === 'setor' ? '+' : '-' }} Rp {{ number_format($mutasi->jumlah, 0, ',', '.') }}
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 text-right whitespace-nowrap">
                                        <div class="font-bold text-sm text-gray-900 dark:text-white">
                                            Rp {{ number_format($mutasi->saldo_akhir, 0, ',', '.') }}
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 text-gray-500 dark:text-gray-400 font-medium text-justify">
                                        <span class="block max-w-xs">{{ $mutasi->keterangan ?? '-' }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-16 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                                            <div class="w-24 h-24 bg-gray-50 dark:bg-gray-800 rounded-full flex items-center justify-center mb-4 border-2 border-dashed border-gray-200 dark:border-gray-700">
                                                <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                                            </div>
                                            <p class="text-xl font-bold text-gray-800 dark:text-gray-200">Belum Ada Transaksi</p>
                                            <p class="text-sm mt-2 max-w-sm">Data riwayat transaksi penyetoran atau penarikan bank akan muncul di sini.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($mutasis->hasPages())
                    <div class="p-6 border-t border-gray-100 dark:border-gray-700 bg-gray-50/30 dark:bg-gray-800/30">
                        {{ $mutasis->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
