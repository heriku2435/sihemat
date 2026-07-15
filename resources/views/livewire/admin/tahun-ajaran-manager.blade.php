<?php

use Livewire\Volt\Component;
use App\Models\TahunAjaran;

new class extends Component {
    public $tahunAjarans;
    public $nama = '';
    public $tanggal_mulai = '';
    public $tanggal_selesai = '';
    public $editId = null;

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->tahunAjarans = TahunAjaran::orderBy('tanggal_mulai', 'desc')->get();
    }

    public function save()
    {
        $this->validate([
            'nama' => 'required|string|max:255',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
        ]);

        if ($this->editId) {
            TahunAjaran::find($this->editId)->update([
                'nama' => $this->nama,
                'tanggal_mulai' => $this->tanggal_mulai,
                'tanggal_selesai' => $this->tanggal_selesai,
            ]);
            session()->flash('message', 'Tahun Ajaran berhasil diperbarui.');
        } else {
            TahunAjaran::create([
                'nama' => $this->nama,
                'tanggal_mulai' => $this->tanggal_mulai,
                'tanggal_selesai' => $this->tanggal_selesai,
                'is_active' => TahunAjaran::count() === 0 ? true : false,
            ]);
            session()->flash('message', 'Tahun Ajaran berhasil ditambahkan.');
        }

        $this->reset(['nama', 'tanggal_mulai', 'tanggal_selesai', 'editId']);
        $this->loadData();
    }

    public function edit($id)
    {
        $ta = TahunAjaran::find($id);
        $this->editId = $ta->id;
        $this->nama = $ta->nama;
        $this->tanggal_mulai = $ta->tanggal_mulai;
        $this->tanggal_selesai = $ta->tanggal_selesai;
    }

    public function cancelEdit()
    {
        $this->reset(['nama', 'tanggal_mulai', 'tanggal_selesai', 'editId']);
    }

    public function delete($id)
    {
        TahunAjaran::find($id)->delete();
        $this->loadData();
        session()->flash('message', 'Tahun Ajaran berhasil dihapus.');
    }

    public function setActive($id)
    {
        TahunAjaran::query()->update(['is_active' => false]);
        TahunAjaran::find($id)->update(['is_active' => true]);
        $this->loadData();
        session()->flash('message', 'Tahun Ajaran aktif berhasil diubah.');
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Manajemen Tahun Ajaran') }}
        </h2>
    </x-slot>

    <div class="py-6 space-y-6">
        @if (session()->has('message'))
            <div class="mb-4 p-4 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400 rounded-xl flex items-center gap-2 text-sm font-medium">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                {{ session('message') }}
            </div>
        @endif

        <!-- Form Tambah/Edit -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 p-6 transition-colors">
            <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-4">{{ $editId ? 'Edit Tahun Ajaran' : 'Tambah Tahun Ajaran Baru' }}</h3>
            
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama (contoh: 2024/2025)</label>
                        <input type="text" wire:model="nama" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Mulai</label>
                        <input type="date" wire:model="tanggal_mulai" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Selesai</label>
                        <input type="date" wire:model="tanggal_selesai" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors" required>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    @if($editId)
                        <button type="button" wire:click="cancelEdit" class="px-4 py-2 text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 font-medium transition-colors">
                            Batal
                        </button>
                    @endif
                    <button type="submit" class="bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium py-2 px-6 rounded-xl shadow-lg shadow-emerald-200 dark:shadow-none transition-all">
                        {{ $editId ? 'Simpan Perubahan' : 'Tambahkan' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Daftar Tahun Ajaran -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden transition-colors">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-medium border-b border-gray-100 dark:border-gray-700 uppercase tracking-wider text-xs">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Nama Tahun Ajaran</th>
                            <th class="px-6 py-4 font-semibold">Periode</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($tahunAjarans as $ta)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $ta->nama }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                                    {{ \Carbon\Carbon::parse($ta->tanggal_mulai)->translatedFormat('d M Y') }} 
                                    <span class="mx-1 text-gray-400">-</span> 
                                    {{ \Carbon\Carbon::parse($ta->tanggal_selesai)->translatedFormat('d M Y') }}
                                </td>
                                <td class="px-6 py-4">
                                    @if($ta->is_active)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                            Tahun Aktif
                                        </span>
                                    @else
                                        <button wire:click="setActive({{ $ta->id }})" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors border border-gray-200 dark:border-gray-600">
                                            Jadikan Aktif
                                        </button>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right space-x-3">
                                    <button wire:click="edit({{ $ta->id }})" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium transition-colors">Edit</button>
                                    <button wire:click="delete({{ $ta->id }})" wire:confirm="Yakin ingin menghapus tahun ajaran ini?" class="text-rose-600 dark:text-rose-400 hover:text-rose-800 dark:hover:text-rose-300 font-medium transition-colors">Hapus</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                        <svg class="w-12 h-12 mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <p class="text-base font-medium text-gray-900 dark:text-gray-100">Belum ada data Tahun Ajaran</p>
                                        <p class="text-sm mt-1">Silakan tambahkan tahun ajaran baru pada form di atas.</p>
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
