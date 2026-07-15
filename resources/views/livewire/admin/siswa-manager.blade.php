<?php

use Livewire\Volt\Component;
use App\Models\Siswa;
use Illuminate\Validation\Rule;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

new class extends Component {
    use WithPagination, WithFileUploads;

    public $nama = '';
    public $nis = '';
    public $no_wa_ortu = '';
    public $status = 'Aktif';
    public $perPage = 5;
    public $editId = null;
    public $isModalOpen = false;
    public $isImportModalOpen = false;
    public $importFile = null;
    public $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingPerPage()
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'siswas' => Siswa::when($this->search, function($query) {
                $query->where('nama', 'like', '%' . $this->search . '%')
                      ->orWhere('nis', 'like', '%' . $this->search . '%');
            })->orderBy('nama')->paginate($this->perPage),
        ];
    }

    public function openModal()
    {
        $this->reset(['nama', 'nis', 'no_wa_ortu', 'status', 'editId']);
        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
    }

    public function openImportModal()
    {
        $this->reset('importFile');
        $this->isImportModalOpen = true;
    }

    public function closeImportModal()
    {
        $this->isImportModalOpen = false;
        $this->reset('importFile');
    }

    public function downloadTemplate()
    {
        $data = [
            ['nama', 'nis', 'no_wa_ortu', 'status'],
            ['Ahmad Budi', '1011', '081234567890', 'Aktif'],
            ['Siti Aminah', '1012', '089876543210', 'Aktif']
        ];
        
        $fileName = 'template_siswa.xlsx';
        $path = storage_path('app/' . $fileName);
        
        \Shuchkin\SimpleXLSXGen::fromArray($data)->saveAs($path);
        
        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function importSiswa()
    {
        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls,csv|max:5120', // max 5MB
        ]);

        try {
            $path = $this->importFile->getRealPath();
            $ext = $this->importFile->getClientOriginalExtension();
            $imported = 0;

            if (strtolower($ext) === 'csv') {
                $file = fopen($path, 'r');
                $header = fgetcsv($file);
                if (!$header) throw new \Exception("File CSV kosong atau format tidak valid.");

                $header = array_map(function($col) { return strtolower(trim($col)); }, $header);
                $namaIdx = array_search('nama', $header);
                $nisIdx = array_search('nis', $header);
                $waIdx = array_search('no_wa_ortu', $header);
                $statusIdx = array_search('status', $header);

                if ($namaIdx === false) throw new \Exception("Kolom 'nama' wajib ada di baris pertama CSV.");

                while (($row = fgetcsv($file)) !== false) {
                    $nama = isset($row[$namaIdx]) ? trim($row[$namaIdx]) : '';
                    if (empty($nama)) continue;

                    $status = ($statusIdx !== false && isset($row[$statusIdx])) ? trim($row[$statusIdx]) : 'Aktif';
                    if (!in_array($status, ['Aktif', 'Mutasi', 'Drop Out', 'Lulus'])) $status = 'Aktif';

                    Siswa::create([
                        'nama' => $nama,
                        'nis' => ($nisIdx !== false && isset($row[$nisIdx])) ? trim($row[$nisIdx]) : null,
                        'no_wa_ortu' => ($waIdx !== false && isset($row[$waIdx])) ? trim($row[$waIdx]) : null,
                        'status' => $status,
                        'uuid_qr' => (string) \Illuminate\Support\Str::uuid(),
                    ]);
                    $imported++;
                }
                fclose($file);
            } else {
                if ($xlsx = \Shuchkin\SimpleXLSX::parse($path)) {
                    $rows = $xlsx->rows();
                    if (count($rows) === 0) throw new \Exception("File Excel kosong.");

                    $header = array_shift($rows);
                    $header = array_map(function($col) { return strtolower(trim((string)$col)); }, $header);

                    $namaIdx = array_search('nama', $header);
                    $nisIdx = array_search('nis', $header);
                    $waIdx = array_search('no_wa_ortu', $header);
                    $statusIdx = array_search('status', $header);

                    if ($namaIdx === false) throw new \Exception("Kolom 'nama' wajib ada di baris pertama Excel.");

                    foreach ($rows as $row) {
                        $nama = isset($row[$namaIdx]) ? trim((string)$row[$namaIdx]) : '';
                        if (empty($nama)) continue;

                        $status = ($statusIdx !== false && isset($row[$statusIdx])) ? trim((string)$row[$statusIdx]) : 'Aktif';
                        if (!in_array($status, ['Aktif', 'Mutasi', 'Drop Out', 'Lulus'])) $status = 'Aktif';

                        Siswa::create([
                            'nama' => $nama,
                            'nis' => ($nisIdx !== false && isset($row[$nisIdx])) ? trim((string)$row[$nisIdx]) : null,
                            'no_wa_ortu' => ($waIdx !== false && isset($row[$waIdx])) ? trim((string)$row[$waIdx]) : null,
                            'status' => $status,
                            'uuid_qr' => (string) \Illuminate\Support\Str::uuid(),
                        ]);
                        $imported++;
                    }
                } else {
                    throw new \Exception(\Shuchkin\SimpleXLSX::parseError());
                }
            }

            session()->flash('message', "Berhasil mengimport $imported data Siswa.");
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal mengimport data: ' . $e->getMessage());
        }

        $this->isImportModalOpen = false;
        $this->reset('importFile');
    }

    public function save()
    {
        $rules = [
            'nama' => 'required|string|max:255',
            'nis' => ['nullable', 'string', 'max:50', Rule::unique('siswas')->ignore($this->editId)],
            'no_wa_ortu' => 'nullable|string|max:20',
            'status' => 'required|string|in:Aktif,Mutasi,Drop Out,Lulus',
        ];

        $this->validate($rules);

        if ($this->editId) {
            $siswa = Siswa::find($this->editId);
            $siswa->update([
                'nama' => $this->nama,
                'nis' => $this->nis,
                'no_wa_ortu' => $this->no_wa_ortu,
                'status' => $this->status,
            ]);

            session()->flash('message', 'Data Siswa berhasil diperbarui.');
        } else {
            Siswa::create([
                'nama' => $this->nama,
                'nis' => $this->nis,
                'no_wa_ortu' => $this->no_wa_ortu,
                'status' => $this->status,
                'uuid_qr' => (string) \Illuminate\Support\Str::uuid(),
            ]);

            session()->flash('message', 'Siswa baru berhasil ditambahkan.');
        }

        $this->isModalOpen = false;
        $this->reset(['nama', 'nis', 'no_wa_ortu', 'status', 'editId']);
    }

    public function edit($id)
    {
        $siswa = Siswa::find($id);
        $this->editId = $siswa->id;
        $this->nama = $siswa->nama;
        $this->nis = $siswa->nis;
        $this->no_wa_ortu = $siswa->no_wa_ortu;
        $this->status = $siswa->status;
        $this->isModalOpen = true;
    }

    public function delete($id)
    {
        abort_if(auth()->user()->role !== 'admin', 403);
        
        Siswa::find($id)->delete();
        session()->flash('message', 'Data Siswa berhasil dihapus.');
    }
}; ?>

<div x-data="{ isModalOpen: @entangle('isModalOpen'), isImportModalOpen: @entangle('isImportModalOpen') }">
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Manajemen Data Siswa') }}
        </h2>
    </x-slot>

    <div class="py-6 space-y-6">
        @if (session()->has('message'))
            <div x-data x-init="alerty.toasts('{{ session('message') }}', {place: 'top', time: 3000, bgColor: '#10b981', fontColor: '#ffffff'})"></div>
        @endif
        @if (session()->has('error'))
            <div x-data x-init="alerty.toasts('{{ session('error') }}', {place: 'top', time: 3000, bgColor: '#ef4444', fontColor: '#ffffff'})"></div>
        @endif

        <!-- Daftar Siswa -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden transition-colors">
            <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Daftar Siswa</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Kelola data siswa dan tabungan.</p>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto md:flex-1 md:justify-end">
                    <div class="relative w-full md:flex-1 md:max-w-xl">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <input type="text" wire:model.live="search" class="w-full pl-10 pr-4 py-2.5 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors text-sm" placeholder="Cari nama atau NIS...">
                    </div>
                    
                    <select wire:model.live="perPage" class="py-2.5 px-3 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors text-sm w-full sm:w-20 cursor-pointer">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="15">15</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    
                    <button wire:click="openImportModal" class="bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 font-medium py-2.5 px-5 rounded-xl shadow-sm transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        Import
                    </button>
                    
                    <button wire:click="openModal" class="bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-lg shadow-emerald-200 dark:shadow-none transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Tambah Siswa
                    </button>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-medium border-b border-gray-100 dark:border-gray-700 uppercase tracking-wider text-xs">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Nama Siswa</th>
                            <th class="px-6 py-4 font-semibold">NIS</th>
                            <th class="px-6 py-4 font-semibold">No. WA Orang Tua</th>
                            <th class="px-6 py-4 font-semibold">Status</th>
                            <th class="px-6 py-4 font-semibold">Kode QR</th>
                            <th class="px-6 py-4 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($siswas as $siswa)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-sm uppercase">
                                            {{ substr($siswa->nama, 0, 2) }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="font-bold text-gray-900 dark:text-gray-100">{{ $siswa->nama }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-gray-800 dark:text-gray-200 font-medium">{{ $siswa->nis ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                        {{ $siswa->no_wa_ortu ?: '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $statusClass = match($siswa->computed_status) {
                                            'Aktif' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/50',
                                            'Lulus' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400 border-blue-200 dark:border-blue-800/50',
                                            'Mutasi' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 border-amber-200 dark:border-amber-800/50',
                                            'Drop Out' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400 border-rose-200 dark:border-rose-800/50',
                                            default => 'bg-gray-100 text-gray-700 dark:bg-gray-900/40 dark:text-gray-400 border-gray-200 dark:border-gray-800/50'
                                        };
                                    @endphp
                                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded text-[10px] font-semibold border {{ $statusClass }} uppercase tracking-wider">
                                        {{ $siswa->computed_status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                                        <div class="text-xs truncate w-24 font-mono text-gray-500" title="{{ $siswa->uuid_qr }}">{{ $siswa->uuid_qr ?? 'Belum ada' }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button wire:click="edit({{ $siswa->id }})" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    @if(auth()->user()->role === 'admin')
                                    <button x-data x-on:click="
                                        Swal.fire({
                                            title: 'Hapus Siswa?',
                                            text: 'Data yang dihapus tidak dapat dikembalikan!',
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonColor: '#ef4444',
                                            cancelButtonColor: '#6b7280',
                                            confirmButtonText: 'Ya, Hapus!',
                                            cancelButtonText: 'Batal'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                $wire.delete({{ $siswa->id }})
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
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                        <svg class="w-12 h-12 mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                        <p class="text-base font-medium text-gray-900 dark:text-gray-100">Belum ada data Siswa</p>
                                        <p class="text-sm mt-1">Silakan tambahkan data siswa baru melalui tombol di atas.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="p-4 border-t border-gray-100 dark:border-gray-700">
                {{ $siswas->links() }}
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
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    </div>
                    {{ $editId ? 'Edit Data Siswa' : 'Tambah Siswa Baru' }}
                </h3>
                <button type="button" wire:click="closeModal" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 14 14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"></path></svg>
                </button>
            </div>
            
            <!-- Modal body -->
            <div class="pt-5 max-h-[70vh] overflow-y-auto custom-scrollbar pr-2">
                <form wire:submit="save" id="siswaForm" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Lengkap Siswa</label>
                        <input type="text" wire:model="nama" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors" required>
                        @error('nama') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nomor Induk Siswa (NIS)</label>
                        <input type="text" wire:model="nis" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors">
                        @error('nis') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status Siswa</label>
                        <select wire:model="status" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors">
                            <option value="Aktif">Aktif</option>
                            <option value="Lulus">Lulus</option>
                            <option value="Mutasi">Mutasi</option>
                            <option value="Drop Out">Drop Out</option>
                        </select>
                        @error('status') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">No. WhatsApp Orang Tua</label>
                        <input type="text" wire:model="no_wa_ortu" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors" placeholder="08123456789">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Digunakan untuk mengirim notifikasi penarikan/setoran tabungan.</p>
                        @error('no_wa_ortu') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </form>
            </div>
            
            <!-- Modal footer -->
            <div class="flex justify-end gap-3 pt-5 mt-5 border-t border-gray-100 dark:border-gray-700">
                <button type="button" wire:click="closeModal" class="px-5 py-2.5 text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 font-medium transition-colors">
                    Batal
                </button>
                <button type="submit" form="siswaForm" class="bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-lg shadow-emerald-200 dark:shadow-none transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    {{ $editId ? 'Simpan Perubahan' : 'Tambahkan Siswa' }}
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Form Import -->
    <div x-cloak x-show="isImportModalOpen" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overflow-x-hidden bg-black/50 backdrop-blur-sm transition-opacity" x-transition.opacity>
        <div x-show="isImportModalOpen" @click.away="$wire.closeImportModal()" class="relative w-[90%] md:w-full max-w-md p-4 md:p-6 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl transition-all" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
            
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    </div>
                    Import Data Siswa
                </h3>
                <button type="button" wire:click="closeImportModal" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 14 14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"></path></svg>
                </button>
            </div>
            
            <div class="pt-5 max-h-[70vh] overflow-y-auto custom-scrollbar pr-2">
                <form wire:submit="importSiswa" id="importForm" class="space-y-4">
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 rounded-xl text-sm mb-4 border border-blue-100 dark:border-blue-800/50">
                        <p class="font-semibold mb-1 flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Format File (.xlsx, .csv)</p>
                        <p class="mt-1">Gunakan file Excel/CSV. Baris pertama harus berisi header kolom (huruf kecil semua):</p>
                        <ul class="list-disc ml-5 mt-1 opacity-90 space-y-0.5 mb-3">
                            <li><strong>nama</strong> (wajib)</li>
                            <li><strong>nis</strong> (opsional)</li>
                            <li><strong>no_wa_ortu</strong> (opsional)</li>
                            <li><strong>status</strong> (opsional: Aktif/Mutasi/Lulus)</li>
                        </ul>
                        <button type="button" wire:click="downloadTemplate" class="inline-flex items-center gap-1.5 text-blue-700 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium underline-offset-4 hover:underline transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            Download Template Excel (.xlsx)
                        </button>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pilih File Excel/CSV</label>
                        <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" class="w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 dark:file:bg-emerald-900/40 dark:file:text-emerald-400 dark:hover:file:bg-emerald-900/60 transition-colors border border-gray-300 dark:border-gray-600 rounded-xl cursor-pointer">
                        <div wire:loading wire:target="importFile" class="text-sm text-emerald-600 dark:text-emerald-400 mt-2 flex items-center gap-1.5">
                            <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memuat file...
                        </div>
                        @error('importFile') <span class="text-xs text-rose-500 mt-1 block flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> {{ $message }}</span> @enderror
                    </div>
                </form>
            </div>
            
            <div class="flex justify-end gap-3 pt-5 mt-5 border-t border-gray-100 dark:border-gray-700">
                <button type="button" wire:click="closeImportModal" class="px-5 py-2.5 text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 font-medium transition-colors">
                    Batal
                </button>
                <button type="submit" form="importForm" class="bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-lg shadow-emerald-200 dark:shadow-none transition-all flex items-center gap-2" wire:loading.attr="disabled">
                    <svg wire:loading.remove wire:target="importSiswa" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    <svg wire:loading wire:target="importSiswa" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span wire:loading.remove wire:target="importSiswa">Import Sekarang</span>
                    <span wire:loading wire:target="importSiswa">Memproses...</span>
                </button>
            </div>
        </div>
    </div>
</div>
