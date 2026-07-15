<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <!-- Sidebar overlay for mobile -->
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>

    <!-- Sidebar Component -->
    <nav :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}" class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-gray-800 border-r border-gray-100 dark:border-gray-700 flex flex-col transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:inset-auto lg:h-screen">
        
        <!-- Sidebar Header (Logo) -->
        <div class="h-16 flex items-center justify-between px-6 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2">
                <div class="w-8 h-8 bg-gradient-to-tr from-emerald-500 to-teal-500 rounded-lg flex items-center justify-center shadow-lg shadow-emerald-500/20">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <span class="font-extrabold text-xl tracking-tight text-gray-800 dark:text-gray-100">SIHEMAT</span>
            </a>
            <button @click="sidebarOpen = false" class="lg:hidden text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <!-- User Profile Summary (Sidebar) -->
        <div x-data="{ showProfileMenu: false }" class="border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800">
            <div @click="showProfileMenu = !showProfileMenu" class="p-6 flex flex-col items-center justify-center cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-2xl uppercase shadow-inner mb-3 transition-transform hover:scale-105">
                    {{ substr(auth()->user()->name, 0, 1) }}
                </div>
                <div class="font-bold text-gray-800 dark:text-gray-100 text-center flex items-center gap-1">
                    <span x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                    <svg class="w-4 h-4 transition-transform" :class="showProfileMenu ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </div>
                <div class="text-xs font-medium text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30 px-2 py-0.5 rounded-full mt-1 uppercase tracking-wider border border-emerald-100 dark:border-emerald-800/50">
                    {{ auth()->user()->role }}
                </div>
            </div>
            
            <!-- Profile Menu Dropdown -->
            <div x-show="showProfileMenu" x-transition class="px-4 pb-4 space-y-2" style="display: none;">
                <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    Profil Akun
                </a>
                <button wire:click="logout" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 hover:text-red-700 dark:hover:text-red-300">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Keluar (Logout)
                </button>
            </div>
        </div>

        <!-- Navigation Links -->
        <div class="flex-1 overflow-y-auto py-4 px-3 space-y-1 custom-scrollbar">
            <!-- Main Dashboard -->
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('dashboard') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                <svg class="w-5 h-5 {{ request()->routeIs('dashboard') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                Dashboard
            </a>

            @if(auth()->user()->role === 'admin')
                <div class="pt-4 pb-1">
                    <p class="px-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Master Data</p>
                    <a href="{{ route('admin.tahun-ajaran') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('admin.tahun-ajaran') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('admin.tahun-ajaran') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Tahun Ajaran
                    </a>
                    <a href="{{ route('admin.guru') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('admin.guru') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('admin.guru') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        Data Guru
                    </a>
                    <a href="{{ route('admin.siswa') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('admin.siswa') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('admin.siswa') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Data Siswa
                    </a>
                    <a href="{{ route('admin.rombel') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('admin.rombel') || request()->routeIs('admin.rombel.siswa') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('admin.rombel') || request()->routeIs('admin.rombel.siswa') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        Manajemen Rombel
                    </a>
                    <a href="{{ route('admin.transaksi') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('admin.transaksi') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('admin.transaksi') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Transaksi Tabungan
                    </a>
                </div>

                <div class="pt-4 pb-1 border-t border-gray-100 dark:border-gray-700">
                    <p class="px-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Sistem</p>
                    <a href="{{ route('admin.pengaturan') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('admin.pengaturan') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('admin.pengaturan') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Pengaturan
                    </a>
                    <a href="{{ route('admin.wa-gateway') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('admin.wa-gateway') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('admin.wa-gateway') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                        WhatsApp Gateway
                    </a>
                </div>

                <div class="pt-4 pb-1 border-t border-gray-100 dark:border-gray-700">
                    <p class="px-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Laporan</p>
                    <a href="{{ route('admin.rekapitulasi') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm mb-1 {{ request()->routeIs('admin.rekapitulasi') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('admin.rekapitulasi') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Rekapitulasi
                    </a>
                    <div x-data="{ open: {{ request()->routeIs('admin.mutasi.*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('admin.mutasi.*') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 {{ request()->routeIs('admin.mutasi.*') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Mutasi
                            </div>
                            <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition-all ease-out duration-200" x-transition:enter-start="opacity-0 max-h-0" x-transition:enter-end="opacity-100 max-h-40" x-transition:leave="transition-all ease-in duration-200" x-transition:leave-start="opacity-100 max-h-40" x-transition:leave-end="opacity-0 max-h-0" class="overflow-hidden" style="display: none;">
                            <div class="mt-2 ml-5 pl-4 border-l-2 border-gray-100 dark:border-gray-700 space-y-1">
                                <a href="{{ route('admin.mutasi.tabungan') }}" wire:navigate class="block px-3 py-2 text-sm rounded-lg transition-colors relative before:absolute before:-left-4 before:top-1/2 before:-translate-y-1/2 before:w-3 before:h-0.5 before:bg-gray-200 dark:before:bg-gray-600 {{ request()->routeIs('admin.mutasi.tabungan') ? 'bg-emerald-50 text-emerald-600 font-semibold dark:bg-emerald-900/30 dark:text-emerald-400 before:!bg-emerald-400' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700/50' }}">Mutasi Tabungan</a>
                                <a href="{{ route('admin.mutasi.bank') }}" wire:navigate class="block px-3 py-2 text-sm rounded-lg transition-colors relative before:absolute before:-left-4 before:top-1/2 before:-translate-y-1/2 before:w-3 before:h-0.5 before:bg-gray-200 dark:before:bg-gray-600 {{ request()->routeIs('admin.mutasi.bank') ? 'bg-emerald-50 text-emerald-600 font-semibold dark:bg-emerald-900/30 dark:text-emerald-400 before:!bg-emerald-400' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700/50' }}">Mutasi Bank</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            
            @if(auth()->user()->role === 'guru')
                <div class="pt-4 pb-1">
                    <p class="px-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Operasional</p>
                    <a href="{{ route('guru.siswa') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('guru.siswa') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('guru.siswa') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        Data Siswa
                    </a>
                    <a href="{{ route('guru.rombel') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('guru.rombel') || request()->routeIs('guru.rombel.siswa') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('guru.rombel') || request()->routeIs('guru.rombel.siswa') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        Kelola Kelas
                    </a>
                    <a href="{{ route('guru.transaksi') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('guru.transaksi') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('guru.transaksi') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Transaksi Tabungan
                    </a>
                    <a href="{{ route('guru.setoran-koperasi') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('guru.setoran-koperasi') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('guru.setoran-koperasi') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                        Setor ke Bank
                    </a>
                </div>

                <div class="pt-4 pb-1 border-t border-gray-100 dark:border-gray-700">
                    <p class="px-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Laporan</p>
                    <a href="{{ route('guru.rekapitulasi') }}" wire:navigate class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition font-medium text-sm mb-1 {{ request()->routeIs('guru.rekapitulasi') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                        <svg class="w-5 h-5 {{ request()->routeIs('guru.rekapitulasi') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Rekapitulasi
                    </a>
                    <div x-data="{ open: {{ request()->routeIs('guru.mutasi.*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl transition font-medium text-sm {{ request()->routeIs('guru.mutasi.*') ? 'bg-emerald-50 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50 hover:text-gray-900 dark:hover:text-gray-200' }}">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 {{ request()->routeIs('guru.mutasi.*') ? 'text-emerald-500 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                Mutasi
                            </div>
                            <svg class="w-4 h-4 transition-transform duration-200" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>
                        <div x-show="open" x-transition:enter="transition-all ease-out duration-200" x-transition:enter-start="opacity-0 max-h-0" x-transition:enter-end="opacity-100 max-h-40" x-transition:leave="transition-all ease-in duration-200" x-transition:leave-start="opacity-100 max-h-40" x-transition:leave-end="opacity-0 max-h-0" class="overflow-hidden" style="display: none;">
                            <div class="mt-2 ml-5 pl-4 border-l-2 border-gray-100 dark:border-gray-700 space-y-1">
                                <a href="{{ route('guru.mutasi.tabungan') }}" wire:navigate class="block px-3 py-2 text-sm rounded-lg transition-colors relative before:absolute before:-left-4 before:top-1/2 before:-translate-y-1/2 before:w-3 before:h-0.5 before:bg-gray-200 dark:before:bg-gray-600 {{ request()->routeIs('guru.mutasi.tabungan') ? 'bg-emerald-50 text-emerald-600 font-semibold dark:bg-emerald-900/30 dark:text-emerald-400 before:!bg-emerald-400' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700/50' }}">Mutasi Tabungan</a>
                                <a href="{{ route('guru.mutasi.bank') }}" wire:navigate class="block px-3 py-2 text-sm rounded-lg transition-colors relative before:absolute before:-left-4 before:top-1/2 before:-translate-y-1/2 before:w-3 before:h-0.5 before:bg-gray-200 dark:before:bg-gray-600 {{ request()->routeIs('guru.mutasi.bank') ? 'bg-emerald-50 text-emerald-600 font-semibold dark:bg-emerald-900/30 dark:text-emerald-400 before:!bg-emerald-400' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-700/50' }}">Mutasi Bank</a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar Footer Removed as Profile links moved to Avatar and Dark Mode moved to Header -->
    </nav>
</div>
