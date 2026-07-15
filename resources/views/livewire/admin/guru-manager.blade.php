<?php

use Livewire\Volt\Component;
use App\Models\Guru;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

new class extends Component {
    public $gurus;
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $nip = '';
    public $nuptk = '';
    public $jenis_kelamin = 'L';
    public $no_hp = '';
    public $editId = null;
    public $isModalOpen = false;

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {
        $this->gurus = Guru::with('user')->orderBy('created_at', 'desc')->get();
    }

    public function openModal()
    {
        $this->reset(['name', 'email', 'password', 'password_confirmation', 'nip', 'nuptk', 'jenis_kelamin', 'no_hp', 'editId']);
        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
    }

    public function save()
    {
        $userId = $this->editId ? Guru::find($this->editId)->user_id : null;

        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'nip' => 'nullable|string|max:50',
            'nuptk' => 'nullable|string|max:50',
            'jenis_kelamin' => 'required|in:L,P',
            'no_hp' => 'nullable|string|max:20',
        ];

        if (!$this->editId || $this->password) {
            $rules['password'] = 'required|string|min:8|confirmed';
        }

        $this->validate($rules);

        if ($this->editId) {
            $guru = Guru::find($this->editId);
            $user = User::find($guru->user_id);
            
            $user->update([
                'name' => $this->name,
                'email' => $this->email,
            ]);
            
            if ($this->password) {
                $user->update(['password' => Hash::make($this->password)]);
            }

            $guru->update([
                'nama' => $this->name,
                'nip' => $this->nip,
                'nuptk' => $this->nuptk,
                'jenis_kelamin' => $this->jenis_kelamin,
                'no_hp' => $this->no_hp,
            ]);

            session()->flash('message', 'Data Guru berhasil diperbarui.');
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'role' => 'guru',
            ]);

            Guru::create([
                'user_id' => $user->id,
                'nama' => $this->name,
                'nip' => $this->nip,
                'nuptk' => $this->nuptk,
                'jenis_kelamin' => $this->jenis_kelamin,
                'no_hp' => $this->no_hp,
            ]);

            session()->flash('message', 'Guru baru berhasil ditambahkan.');
        }

        $this->isModalOpen = false;
        $this->loadData();
    }

    public function edit($id)
    {
        $guru = Guru::with('user')->find($id);
        $this->editId = $guru->id;
        $this->name = $guru->user->name;
        $this->email = $guru->user->email;
        $this->nip = $guru->nip;
        $this->nuptk = $guru->nuptk;
        $this->jenis_kelamin = $guru->jenis_kelamin;
        $this->no_hp = $guru->no_hp;
        $this->password = '';
        $this->password_confirmation = '';
        $this->isModalOpen = true;
    }

    public function delete($id)
    {
        $guru = Guru::find($id);
        User::find($guru->user_id)->delete();
        $guru->delete();
        $this->loadData();
        session()->flash('message', 'Data Guru berhasil dihapus.');
    }
}; ?>

<div x-data="{ isModalOpen: @entangle('isModalOpen') }">
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Manajemen Data Guru') }}
        </h2>
    </x-slot>

    <div class="py-6 space-y-6">
        @if (session()->has('message'))
            <div x-data x-init="alerty.toasts('{{ session('message') }}', {place: 'top', time: 3000, bgColor: '#10b981', fontColor: '#ffffff'})"></div>
        @endif
        @if (session()->has('error'))
            <div x-data x-init="alerty.toasts('{{ session('error') }}', {place: 'top', time: 3000, bgColor: '#ef4444', fontColor: '#ffffff'})"></div>
        @endif
        @if (session()->has('warning'))
            <div x-data x-init="alerty.toasts('{{ session('warning') }}', {place: 'top', time: 3000, bgColor: '#f59e0b', fontColor: '#ffffff'})"></div>
        @endif
        @if (session()->has('info'))
            <div x-data x-init="alerty.toasts('{{ session('info') }}', {place: 'top', time: 3000, bgColor: '#3b82f6', fontColor: '#ffffff'})"></div>
        @endif

        <!-- Daftar Guru -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden transition-colors">
            <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100">Daftar Guru</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Total: {{ count($gurus) }} data guru</p>
                </div>
                <button wire:click="openModal" class="bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-lg shadow-emerald-200 dark:shadow-none transition-all flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Tambah Guru
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-medium border-b border-gray-100 dark:border-gray-700 uppercase tracking-wider text-xs">
                        <tr>
                            <th class="px-6 py-4 font-semibold">Nama / Email</th>
                            <th class="px-6 py-4 font-semibold">NIP / NUPTK</th>
                            <th class="px-6 py-4 font-semibold text-center">L/P</th>
                            <th class="px-6 py-4 font-semibold">No. HP</th>
                            <th class="px-6 py-4 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($gurus as $guru)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full {{ $guru->jenis_kelamin == 'L' ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400' : 'bg-pink-100 dark:bg-pink-900/40 text-pink-600 dark:text-pink-400' }} flex items-center justify-center font-bold text-sm uppercase">
                                            {{ substr($guru->user->name, 0, 2) }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="font-bold text-gray-900 dark:text-gray-100">{{ $guru->user->name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $guru->user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-gray-800 dark:text-gray-200 font-medium">{{ $guru->nip ?? '-' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $guru->nuptk ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if($guru->jenis_kelamin == 'L')
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">L</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-400">P</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                        {{ $guru->no_hp ?: '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button wire:click="edit({{ $guru->id }})" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors" title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>
                                    <button x-data x-on:click="
                                        Swal.fire({
                                            title: 'Hapus Guru?',
                                            text: 'Data yang dihapus tidak dapat dikembalikan!',
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonColor: '#ef4444',
                                            cancelButtonColor: '#6b7280',
                                            confirmButtonText: 'Ya, Hapus!',
                                            cancelButtonText: 'Batal'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                $wire.delete({{ $guru->id }})
                                            }
                                        })
                                    " class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/30 transition-colors" title="Hapus">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                        <svg class="w-12 h-12 mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                        <p class="text-base font-medium text-gray-900 dark:text-gray-100">Belum ada data Guru</p>
                                        <p class="text-sm mt-1">Silakan tambahkan data guru baru melalui tombol di atas.</p>
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
        <div x-show="isModalOpen" @click.away="$wire.closeModal()" class="relative w-full max-w-2xl p-4 md:p-6 mx-4 bg-white dark:bg-gray-800 rounded-2xl shadow-2xl transition-all" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
            
            <!-- Modal header -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    </div>
                    {{ $editId ? 'Edit Data Guru' : 'Tambah Guru Baru' }}
                </h3>
                <button type="button" wire:click="closeModal" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ms-auto inline-flex justify-center items-center dark:hover:bg-gray-600 dark:hover:text-white transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 14 14"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"></path></svg>
                </button>
            </div>
            
            <!-- Modal body -->
            <div class="pt-5 max-h-[70vh] overflow-y-auto custom-scrollbar pr-2">
                <form wire:submit="save" id="guruForm" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Lengkap</label>
                            <input type="text" wire:model="name" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors" required>
                            @error('name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email (Login)</label>
                            <input type="email" wire:model="email" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors" required>
                            @error('email') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password {!! $editId ? '<span class="text-gray-400 text-xs font-normal">(Kosongkan jika tidak diubah)</span>' : '' !!}</label>
                            <input type="password" wire:model="password" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors" {{ $editId ? '' : 'required' }}>
                            @error('password') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Konfirmasi Password</label>
                            <input type="password" wire:model="password_confirmation" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors" {{ $editId ? '' : 'required' }}>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">NIP <span class="text-gray-400 text-xs font-normal">(Opsional)</span></label>
                            <input type="text" wire:model="nip" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors">
                            @error('nip') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">NUPTK <span class="text-gray-400 text-xs font-normal">(Opsional)</span></label>
                            <input type="text" wire:model="nuptk" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors">
                            @error('nuptk') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenis Kelamin</label>
                            <select wire:model="jenis_kelamin" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors">
                                <option value="L">Laki-Laki</option>
                                <option value="P">Perempuan</option>
                            </select>
                            @error('jenis_kelamin') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">No. WhatsApp</label>
                            <input type="text" wire:model="no_hp" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500 transition-colors" placeholder="08123456789">
                            @error('no_hp') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Modal footer -->
            <div class="flex justify-end gap-3 pt-5 mt-5 border-t border-gray-100 dark:border-gray-700">
                <button type="button" wire:click="closeModal" class="px-5 py-2.5 text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 font-medium transition-colors">
                    Batal
                </button>
                <button type="submit" form="guruForm" class="bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-lg shadow-emerald-200 dark:shadow-none transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    {{ $editId ? 'Simpan Perubahan' : 'Tambahkan' }}
                </button>
            </div>
        </div>
    </div>
</div>
