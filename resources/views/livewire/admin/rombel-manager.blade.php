<?php

use Livewire\Volt\Component;
use App\Models\Rombel;
use App\Models\Guru;
use App\Models\TahunAjaran;

new class extends Component {
    public $rombels;
    public $activeTahunAjaran;
    
    public $nama_kelas = '';
    public $tingkat = '';
    public $guru_id = '';
    public $editId = null;
    public $isModalOpen = false;

    public function getAvailableGurusProperty()
    {
        if (!$this->activeTahunAjaran) return collect();
        
        $assignedGuruIds = collect($this->rombels)
            ->when($this->editId, function($collection) {
                return $collection->where('id', '!=', $this->editId);
            })
            ->pluck('guru_id')
            ->filter()
            ->toArray();

        return Guru::with('user')->whereNotIn('id', $assignedGuruIds)->get();
    }

    public function mount()
    {
        $this->activeTahunAjaran = TahunAjaran::where('is_active', true)->first();
        $this->loadData();
    }

    public function loadData()
    {
        if ($this->activeTahunAjaran) {
            $query = Rombel::with('guru.user')->withCount('siswas')
                ->where('tahun_ajaran_id', $this->activeTahunAjaran->id);

            if (auth()->user()->role === 'guru') {
                $query->where('guru_id', auth()->user()->guru->id);
            }

            $this->rombels = $query->orderBy('nama_kelas')->get();
        } else {
            $this->rombels = collect();
        }
    }

    public function openModal()
    {
        if (!$this->activeTahunAjaran) {
            session()->flash('error', 'Tidak ada Tahun Ajaran aktif. Silakan atur terlebih dahulu.');
            return;
        }
        $this->reset(['nama_kelas', 'tingkat', 'guru_id', 'editId']);
        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
    }

    public function save()
    {
        if (!$this->activeTahunAjaran) {
            session()->flash('error', 'Tidak ada Tahun Ajaran aktif.');
            return;
        }

        $this->validate([
            'nama_kelas' => 'required|string|max:255',
            'tingkat' => 'required|integer|in:1,2,3,4,5,6',
            'guru_id' => 'required|exists:gurus,id',
        ]);

        if ($this->editId) {
            Rombel::find($this->editId)->update([
                'nama_kelas' => $this->nama_kelas,
                'tingkat' => $this->tingkat,
                'guru_id' => $this->guru_id,
            ]);
            session()->flash('message', 'Data Rombel berhasil diperbarui.');
        } else {
            Rombel::create([
                'tahun_ajaran_id' => $this->activeTahunAjaran->id,
                'nama_kelas' => $this->nama_kelas,
                'tingkat' => $this->tingkat,
                'guru_id' => $this->guru_id,
            ]);
            session()->flash('message', 'Rombel baru berhasil ditambahkan.');
        }

        $this->isModalOpen = false;
        $this->reset(['nama_kelas', 'tingkat', 'guru_id', 'editId']);
        $this->loadData();
    }

    public function edit($id)
    {
        $rombel = Rombel::find($id);
        $this->editId = $rombel->id;
        $this->nama_kelas = $rombel->nama_kelas;
        $this->tingkat = $rombel->tingkat;
        $this->guru_id = $rombel->guru_id;
        $this->isModalOpen = true;
    }

    public function delete($id)
    {
        Rombel::find($id)->delete();
        $this->loadData();
        session()->flash('message', 'Rombel berhasil dihapus.');
    }
}; ?>

<div x-data="{ isModalOpen: @entangle('isModalOpen') }">
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <h2 class="font-bold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Manajemen Rombel (Kelas)') }}
            </h2>
            <div class="text-sm px-4 py-1.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 rounded-full font-medium border border-emerald-100 dark:border-emerald-800/50 flex items-center gap-2 shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                TA Aktif: {{ $activeTahunAjaran ? $activeTahunAjaran->nama : 'Belum diatur' }}
            </div>
        </div>
    </x-slot>

    <div class="py-6 space-y-6">
        @if (session()->has('message'))
            <div x-data x-init="alerty.toasts('{{ session('message') }}', {place: 'top', time: 3000, bgColor: '#10b981', fontColor: '#ffffff'})"></div>
        @endif
        @if (session()->has('error'))
            <div x-data x-init="alerty.toasts('{{ session('error') }}', {place: 'top', time: 3000, bgColor: '#ef4444', fontColor: '#ffffff'})"></div>
        @endif

        <!-- Daftar Rombel -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden transition-colors">
            <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Daftar Rombel</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Total: {{ count($rombels) }} Rombel</p>
                </div>
                
                @if(auth()->user()->role === 'admin')
                <button wire:click="openModal" class="bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-lg shadow-emerald-200 dark:shadow-none transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Tambah Rombel
                </button>
                @endif
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-medium border-b border-gray-100 dark:border-gray-700 uppercase tracking-wider text-xs">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Nama Kelas</th>
                            <th class="px-6 py-4 font-semibold">Wali Kelas</th>
                            <th class="px-6 py-4 font-semibold">Jumlah Siswa</th>
                            <th class="px-6 py-4 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($rombels as $rombel)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-xl bg-orange-100 dark:bg-orange-900/40 text-orange-600 dark:text-orange-400 flex items-center justify-center font-bold text-lg uppercase shadow-sm">
                                            {{ substr($rombel->nama_kelas, 0, 1) }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="font-bold text-gray-900 dark:text-gray-100 text-base">Kelas {{ $rombel->nama_kelas }}</div>
                                            <div class="text-xs text-gray-500">Tingkat: {{ $rombel->tingkat ?? '-' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300 font-medium">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        {{ optional(optional($rombel->guru)->user)->name ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-800/50">
                                        {{ $rombel->siswas_count }} Siswa
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2 flex justify-end">
                                    <a href="{{ auth()->user()->role === 'admin' ? route('admin.rombel.siswa', $rombel->id) : route('guru.rombel.siswa', $rombel->id) }}" wire:navigate class="inline-flex items-center justify-center h-8 px-3 rounded-lg text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors font-medium text-xs border border-indigo-200 dark:border-indigo-800/50">
                                        Atur Siswa
                                    </a>
                                    @if(auth()->user()->role === 'admin')
                                    <button wire:click="edit({{ $rombel->id }})" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button x-data x-on:click="
                                        Swal.fire({
                                            title: 'Hapus Rombel?',
                                            text: 'Data yang dihapus tidak dapat dikembalikan!',
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonColor: '#ef4444',
                                            cancelButtonColor: '#6b7280',
                                            confirmButtonText: 'Ya, Hapus!',
                                            cancelButtonText: 'Batal'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                $wire.delete({{ $rombel->id }})
                                            }
                                        })
                                    " class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-colors" title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                        <svg class="w-12 h-12 mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                        <p class="text-base font-medium text-gray-900 dark:text-gray-100">Belum ada data Rombel</p>
                                        <p class="text-sm mt-1">Silakan tambahkan Rombel baru melalui tombol di atas.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Form Tambah/Edit -->
    <div x-cloak x-show="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overflow-x-hidden bg-black/50 backdrop-blur-sm transition-opacity" x-transition.opacity>
        <div x-show="isModalOpen" @click.away="$wire.closeModal()" class="relative w-[90%] md:w-full max-w-md p-4 md:p-6 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl transition-all" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
            
            <!-- Modal header -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    </div>
                    {{ $editId ? 'Edit Rombel' : 'Tambah Rombel Baru' }}
                </h3>
                <button type="button" wire:click="closeModal" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 14 14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"></path></svg>
                </button>
            </div>
            
            <!-- Modal body -->
            <div class="pt-5">
                <form wire:submit="save" id="rombelForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Rombel / Kelas</label>
                        <input type="text" wire:model="nama_kelas" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors" placeholder="Contoh: 1A, 2B, dll" required>
                        @error('nama_kelas') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tingkat / Level Kelas</label>
                        <select wire:model="tingkat" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors" required>
                            <option value="">-- Pilih Tingkat --</option>
                            <option value="1">1 (Satu)</option>
                            <option value="2">2 (Dua)</option>
                            <option value="3">3 (Tiga)</option>
                            <option value="4">4 (Empat)</option>
                            <option value="5">5 (Lima)</option>
                            <option value="6">6 (Enam)</option>
                        </select>
                        @error('tingkat') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Wali Kelas (Guru)</label>
                        <select wire:model="guru_id" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors" required>
                            <option value="">-- Pilih Wali Kelas --</option>
                            @forelse($this->availableGurus as $guru)
                                <option value="{{ $guru->id }}">{{ optional($guru->user)->name ?? 'Unknown Guru' }}</option>
                            @empty
                                <option value="" disabled>-- Semua guru sudah menjadi wali kelas --</option>
                            @endforelse
                        </select>
                        @error('guru_id') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </form>
            </div>
            
            <!-- Modal footer -->
            <div class="flex justify-end gap-3 pt-5 mt-5 border-t border-gray-100 dark:border-gray-700">
                <button type="button" wire:click="closeModal" class="px-5 py-2.5 text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 font-medium transition-colors">
                    Batal
                </button>
                <button type="submit" form="rombelForm" class="bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-lg shadow-emerald-200 dark:shadow-none transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    {{ $editId ? 'Simpan Perubahan' : 'Tambahkan Rombel' }}
                </button>
            </div>
        </div>
    </div>
</div>
