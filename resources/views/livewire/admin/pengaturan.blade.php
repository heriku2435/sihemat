<?php

use Livewire\Volt\Component;
use App\Models\Pengaturan;

new class extends Component {
    public $nama_sekolah = '';
    public $alamat_sekolah = '';
    public $token_fonnte = '';
    public $wa_provider = 'fonnte';
    public $gdrive_client_id = '';
    public $gdrive_client_secret = '';
    public $gdrive_refresh_token = '';
    public $gdrive_folder_id = '';
    public $backup_time = '02:00';
    public $backup_retention = 7;

    public function mount()
    {
        $this->nama_sekolah = Pengaturan::where('key', 'nama_sekolah')->value('value') ?? '';
        $this->alamat_sekolah = Pengaturan::where('key', 'alamat_sekolah')->value('value') ?? '';
        $this->token_fonnte = Pengaturan::where('key', 'token_fonnte')->value('value') ?? '';
        $this->wa_provider = Pengaturan::where('key', 'wa_provider')->value('value') ?? 'fonnte';
        $this->gdrive_client_id = Pengaturan::where('key', 'gdrive_client_id')->value('value') ?? '';
        $this->gdrive_client_secret = Pengaturan::where('key', 'gdrive_client_secret')->value('value') ?? '';
        $this->gdrive_refresh_token = Pengaturan::where('key', 'gdrive_refresh_token')->value('value') ?? '';
        $this->gdrive_folder_id = Pengaturan::where('key', 'gdrive_folder_id')->value('value') ?? '';
        $this->backup_time = Pengaturan::where('key', 'backup_time')->value('value') ?? '02:00';
        $this->backup_retention = Pengaturan::where('key', 'backup_retention')->value('value') ?? 7;
    }

    public function save()
    {
        Pengaturan::updateOrCreate(['key' => 'nama_sekolah'], ['value' => $this->nama_sekolah]);
        Pengaturan::updateOrCreate(['key' => 'alamat_sekolah'], ['value' => $this->alamat_sekolah]);
        Pengaturan::updateOrCreate(['key' => 'token_fonnte'], ['value' => $this->token_fonnte]);
        Pengaturan::updateOrCreate(['key' => 'wa_provider'], ['value' => $this->wa_provider]);
        Pengaturan::updateOrCreate(['key' => 'gdrive_client_id'], ['value' => $this->gdrive_client_id]);
        Pengaturan::updateOrCreate(['key' => 'gdrive_client_secret'], ['value' => $this->gdrive_client_secret]);
        Pengaturan::updateOrCreate(['key' => 'gdrive_refresh_token'], ['value' => $this->gdrive_refresh_token]);
        Pengaturan::updateOrCreate(['key' => 'gdrive_folder_id'], ['value' => $this->gdrive_folder_id]);
        Pengaturan::updateOrCreate(['key' => 'backup_time'], ['value' => $this->backup_time]);
        Pengaturan::updateOrCreate(['key' => 'backup_retention'], ['value' => $this->backup_retention]);

        session()->flash('message', 'Pengaturan berhasil disimpan.');
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-800 leading-tight">
            {{ __('Pengaturan Aplikasi') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="bg-white overflow-hidden shadow-sm rounded-2xl border border-gray-100 p-6">
            @if (session()->has('message'))
                <div class="mb-4 p-4 bg-emerald-100 text-emerald-700 rounded-xl flex items-center gap-2 text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    {{ session('message') }}
                </div>
            @endif

            <form wire:submit="save" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Sekolah</label>
                    <input type="text" wire:model="nama_sekolah" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Contoh: SD Negeri 1 Nusantara">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alamat Sekolah</label>
                    <textarea wire:model="alamat_sekolah" rows="3" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Alamat lengkap sekolah"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Provider WhatsApp</label>
                        <select wire:model="wa_provider" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="fonnte">Fonnte (Hosting Umum)</option>
                            <option value="baileys">Node.js Lokal Baileys (VPS/Server Sendiri)</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Pilih metode pengiriman pesan WhatsApp.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Token API Fonnte (Jika pilih Fonnte)</label>
                        <input type="text" wire:model="token_fonnte" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 font-mono text-sm" placeholder="Token dari md.fonnte.com">
                        <p class="text-xs text-gray-500 mt-1">Kosongkan jika Anda menggunakan server Baileys lokal.</p>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-6 mt-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 24 24"><path d="M21.35 11.1h-5.26l-3.3 5.72h10.45l-1.89-5.72zm-12.7 5.72H3.4l1.9-5.72h5.25l-1.9 5.72zm11.23-7.5l-3.55-6.17a1.67 1.67 0 0 0-1.44-.82H9.1a1.67 1.67 0 0 0-1.44.82L4.1 8.5v.02L7.66 14.7l3.55-6.17h8.67z"></path></svg>
                        Pengaturan Backup Otomatis Google Drive
                    </h3>
                    <p class="text-sm text-gray-500 mb-6">Backup database aplikasi akan diunggah secara otomatis setiap jam 02:00 dini hari ke Google Drive Anda. Kosongkan jika belum menggunakan fitur backup. Lihat <a href="{{ route('admin.panduan-backup') }}" target="_blank" class="text-emerald-600 hover:underline">Panduan Instalasi API Google Drive</a>.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Client ID</label>
                            <input type="text" wire:model="gdrive_client_id" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 font-mono text-sm" placeholder="***.apps.googleusercontent.com">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
                            <input type="password" wire:model="gdrive_client_secret" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 font-mono text-sm" placeholder="GOCSPX-***">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Refresh Token</label>
                            <input type="password" wire:model="gdrive_refresh_token" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 font-mono text-sm" placeholder="1//0g***">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Folder ID (Opsional)</label>
                            <input type="text" wire:model="gdrive_folder_id" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 font-mono text-sm" placeholder="1aBcD***">
                            <p class="text-xs text-gray-500 mt-1">Biarkan kosong jika ingin menyimpan backup di direktori root Google Drive.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Waktu Backup Otomatis</label>
                            <input type="time" wire:model="backup_time" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <p class="text-xs text-gray-500 mt-1">Sistem akan melakukan backup setiap hari pada jam ini.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Durasi Penyimpanan (Hari)</label>
                            <input type="number" min="1" max="365" wire:model="backup_retention" class="w-full rounded-xl border-gray-300 shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <p class="text-xs text-gray-500 mt-1">Backup yang lebih lama dari durasi ini akan dihapus otomatis.</p>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-gray-100 flex justify-end">
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-lg shadow-emerald-200 transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                        Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
