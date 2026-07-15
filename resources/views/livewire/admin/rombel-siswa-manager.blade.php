<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Rombel;
use App\Models\Siswa;

new class extends Component {
    use WithPagination;
    public Rombel $rombel;
    
    public $searchUnassigned = '';
    public $isModalOpen = false;

    public function mount(Rombel $rombel)
    {
        $this->rombel = $rombel;
        
        if (auth()->user()->role === 'guru') {
            abort_if($this->rombel->guru_id !== auth()->user()->guru->id, 403, 'Anda tidak berhak mengelola kelas ini.');
        }

        // Make sure it has relations loaded for header info
        $this->rombel->load('guru.user', 'tahunAjaran');
    }

    public function getAssignedSiswasProperty()
    {
        return $this->rombel->siswas()
            ->where('status', 'Aktif')
            ->has('rombels', '<=', 6)
            ->orderByRaw('CASE WHEN rombel_siswa.nomor_urut IS NULL THEN 1 ELSE 0 END, rombel_siswa.nomor_urut ASC, siswas.nama ASC')
            ->paginate(10);
    }

    public function getAvailableSiswasProperty()
    {
        $activeTahunAjaranId = $this->rombel->tahun_ajaran_id;

        // 1. Filter Terdaftar: Siswa yang sudah terdaftar di kelas manapun di Tahun Ajaran ini
        $assignedInActiveTahunAjaran = Siswa::whereHas('rombels', function($q) use($activeTahunAjaranId) {
            $q->where('tahun_ajaran_id', $activeTahunAjaranId);
        })->pluck('id')->toArray();

        // 2. Filter Lulus: Siswa yang sudah berada lebih dari 6 periode (punya > 6 data di pivot rombel_siswa)
        $graduatedSiswas = Siswa::has('rombels', '>', 6)->pluck('id')->toArray();

        $excludedIds = array_unique(array_merge($assignedInActiveTahunAjaran, $graduatedSiswas));

        // 3. Cari rekomendasi berdasarkan tingkat sebelumnya
        preg_match('/\d+/', $this->rombel->nama_kelas, $matches);
        $currentLevel = isset($matches[0]) ? (int) $matches[0] : null;
        
        $recommendedSiswaIds = [];
        
        if ($currentLevel) {
            $previousLevel = $currentLevel - 1;
            
            // Cari tahun ajaran sebelumnya
            $previousTahunAjaran = \App\Models\TahunAjaran::where('id', '<', $activeTahunAjaranId)->orderBy('id', 'desc')->first();
            
            if ($previousTahunAjaran) {
                // Cari siswa yang di TA lalu berada di kelas tingkat sebelumnya
                $recommendedSiswaIds = Siswa::whereHas('rombels', function($q) use($previousTahunAjaran, $previousLevel) {
                    $q->where('tahun_ajaran_id', $previousTahunAjaran->id)
                      ->where('nama_kelas', 'LIKE', '%' . $previousLevel . '%');
                })->pluck('id')->toArray();
            }
        }

        // Siswa baru (belum pernah masuk kelas manapun)
        $newSiswaIds = Siswa::doesntHave('rombels')->pluck('id')->toArray();
        
        // Gabungkan rekomendasi
        $allRecommendations = array_unique(array_merge($recommendedSiswaIds, $newSiswaIds));
        
        // Pastikan rekomendasi tidak termasuk siswa yang di-exclude
        $allRecommendations = array_diff($allRecommendations, $excludedIds);

        // Buat query utama
        // Hanya siswa dengan status Aktif yang bisa dimasukkan ke kelas
        $query = Siswa::whereNotIn('id', $excludedIds)->where('status', 'Aktif');
        
        if ($this->searchUnassigned) {
            $query->where(function($q) {
                $q->where('nama', 'like', '%' . $this->searchUnassigned . '%')
                  ->orWhere('nis', 'like', '%' . $this->searchUnassigned . '%');
            });
        }
        
        $availableSiswas = $query->orderBy('nama')->limit(30)->get();

        // Tambahkan flag 'is_recommended' secara dinamis ke collection untuk UI
        $availableSiswas->transform(function ($siswa) use ($allRecommendations) {
            $siswa->is_recommended = in_array($siswa->id, $allRecommendations);
            return $siswa;
        });

        // Sort collection: Recommended first, then alphabetically
        return $availableSiswas->sortByDesc('is_recommended')->values();
    }

    public function openModal()
    {
        $this->searchUnassigned = '';
        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
    }

    public function addSiswa($siswaId)
    {
        // Add to this rombel
        $this->rombel->siswas()->attach($siswaId);
        
        // Flash a tiny success to Alerty
        session()->flash('message', 'Siswa ditambahkan ke kelas.');
        
        // Remove focus from search or anything if we want, but keeping it open is good for bulk add.
    }

    public function removeSiswa($siswaId)
    {
        $this->rombel->siswas()->detach($siswaId);
        session()->flash('message', 'Siswa dikeluarkan dari kelas.');
    }

    public function updateNomorUrut($siswaId, $nomorUrut)
    {
        $this->rombel->siswas()->updateExistingPivot($siswaId, [
            'nomor_urut' => $nomorUrut === '' ? null : (int)$nomorUrut,
        ]);
        session()->flash('message', 'Nomor urut diperbarui.');
    }
}; ?>

<div x-data="{ isModalOpen: @entangle('isModalOpen') }">
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ auth()->user()->role === 'admin' ? route('admin.rombel') : route('guru.rombel') }}" wire:navigate class="p-2 rounded-xl bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700 text-gray-500 hover:text-emerald-600 dark:hover:text-emerald-400 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h2 class="font-bold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Atur Anggota Kelas') }}
                </h2>
                <div class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    Kelas {{ $rombel->nama_kelas }} &middot; Wali: {{ optional(optional($rombel->guru)->user)->name ?? '-' }}
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-6 space-y-6">
        @if (session()->has('message'))
            <div x-data x-init="alerty.toasts('{{ session('message') }}', {place: 'top', time: 2000, bgColor: '#10b981', fontColor: '#ffffff'})"></div>
        @endif

        <!-- Panel Daftar Anggota Saat Ini -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden transition-colors">
            <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Daftar Siswa di Kelas Ini</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-2">Total: {{ $this->assignedSiswas->total() }} Siswa Terdaftar</p>
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm" style="background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Jangan lupa untuk mengisi No. Urut siswa secara manual agar urutan absen dan label cetak sesuai.
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ auth()->user()->role === 'admin' ? route('admin.rombel.cetak-label', $rombel->id) : route('guru.rombel.cetak-label', $rombel->id) }}" target="_blank" class="bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 font-medium py-2.5 px-5 rounded-xl shadow-sm transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Cetak Label Kelas
                    </a>
                    <button wire:click="openModal" class="bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-lg shadow-indigo-200 dark:shadow-none transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Tambah Siswa ke Kelas
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-medium border-b border-gray-100 dark:border-gray-700 uppercase tracking-wider text-xs">
                        <tr>
                            <th class="px-6 py-4 font-semibold w-16 text-center">No</th>
                            <th class="px-6 py-4 font-semibold w-24 text-center">No. Urut</th>
                            <th class="px-6 py-4 font-semibold">Nama Siswa</th>
                            <th class="px-6 py-4 font-semibold">NIS</th>
                            <th class="px-6 py-4 font-semibold">No. WhatsApp</th>
                            <th class="px-6 py-4 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($this->assignedSiswas as $index => $siswa)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4 text-center font-medium text-gray-500">{{ ($this->assignedSiswas->currentPage() - 1) * $this->assignedSiswas->perPage() + $index + 1 }}</td>
                                <td class="px-6 py-4 text-center">
                                    <input type="number" 
                                           wire:change="updateNomorUrut({{ $siswa->id }}, $event.target.value)" 
                                           value="{{ $siswa->pivot->nomor_urut }}" 
                                           class="w-16 text-center text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500"
                                           placeholder="-">
                                </td>
                                <td class="px-6 py-4 font-bold text-gray-900 dark:text-gray-100">{{ $siswa->nama }}</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">{{ $siswa->nis ?? '-' }}</td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                        {{ $siswa->no_wa_ortu ?: '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button x-data x-on:click="
                                        Swal.fire({
                                            title: 'Keluarkan Siswa?',
                                            text: 'Siswa akan dikeluarkan dari kelas ini!',
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonColor: '#ef4444',
                                            cancelButtonColor: '#6b7280',
                                            confirmButtonText: 'Ya, Keluarkan!',
                                            cancelButtonText: 'Batal'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                $wire.removeSiswa({{ $siswa->id }})
                                            }
                                        })
                                    " class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-colors" title="Keluarkan dari kelas">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                        <svg class="w-12 h-12 mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                        <p class="text-base font-medium text-gray-900 dark:text-gray-100">Belum ada siswa di kelas ini</p>
                                        <p class="text-sm mt-1">Silakan klik "Masukkan Siswa" untuk menambahkan data siswa ke kelas.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($this->assignedSiswas->hasPages())
                <div class="p-4 border-t border-gray-100 dark:border-gray-700">
                    {{ $this->assignedSiswas->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal Cari & Masukkan Siswa -->
    <div x-cloak x-show="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overflow-x-hidden bg-black/50 backdrop-blur-sm transition-opacity" x-transition.opacity>
        <div x-show="isModalOpen" @click.away="$wire.closeModal()" class="relative w-[95%] md:w-full max-w-2xl bg-white dark:bg-gray-800 rounded-2xl shadow-2xl flex flex-col h-[85vh] sm:h-auto sm:max-h-[85vh] transition-all" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
            
            <!-- Modal header -->
            <div class="flex items-center justify-between p-4 md:p-6 border-b border-gray-100 dark:border-gray-700 shrink-0">
                <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                    </div>
                    Pilih Siswa ke Kelas
                </h3>
                <button type="button" wire:click="closeModal" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 14 14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"></path></svg>
                </button>
            </div>
            
            <!-- Modal body: Search Box -->
            <div class="p-4 md:p-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 shrink-0">
                <div class="relative w-full">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="searchUnassigned" class="w-full pl-10 pr-4 py-3 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-colors" placeholder="Ketik nama atau NIS untuk mencari...">
                </div>
            </div>

            <!-- Modal body: Student List (Scrollable) -->
            <div class="flex-1 overflow-y-auto custom-scrollbar p-0 m-0">
                <table class="w-full text-sm text-left m-0">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($this->availableSiswas as $siswa)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group {{ $siswa->is_recommended ? 'bg-emerald-50/50 dark:bg-emerald-900/10' : '' }}">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="font-bold text-gray-900 dark:text-gray-100">{{ $siswa->nama }}</div>
                                        @if($siswa->is_recommended)
                                            <span class="inline-flex items-center justify-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50 uppercase tracking-wider shadow-sm">
                                                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                Direkomendasikan
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 mt-0.5">{{ $siswa->nis ?? 'Tidak ada NIS' }}</div>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <button wire:click="addSiswa({{ $siswa->id }})" class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 font-medium text-xs shadow-sm shadow-indigo-200 dark:shadow-none transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                        Tambah
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-6 py-12 text-center">
                                    <div class="text-gray-500 dark:text-gray-400">
                                        @if($searchUnassigned)
                                            Tidak ditemukan siswa dengan kata kunci "{{ $searchUnassigned }}"
                                        @else
                                            Semua siswa sudah masuk ke kelas ini.
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <!-- Modal footer -->
            <div class="p-4 md:p-6 border-t border-gray-100 dark:border-gray-700 shrink-0 flex justify-end bg-gray-50/50 dark:bg-gray-900/50 rounded-b-2xl">
                <button type="button" wire:click="closeModal" class="px-6 py-2.5 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-600 font-medium transition-colors shadow-sm">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>
