<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>SIHEMAT - Aplikasi Tabungan Siswa Modern</title>
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-gray-900 bg-gray-50 selection:bg-emerald-500 selection:text-white">
        
        <!-- Navbar -->
        <nav class="fixed w-full z-50 bg-white/80 backdrop-blur-lg border-b border-gray-100 transition-all duration-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-20 items-center">
                    <div class="flex-shrink-0 flex items-center gap-2">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-lg shadow-emerald-200 flex items-center justify-center text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <span class="font-extrabold text-2xl tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-emerald-600 to-teal-800">SIHEMAT</span>
                    </div>
                    
                    <div class="hidden md:flex space-x-8">
                        <a href="#fitur" class="text-sm font-medium text-gray-600 hover:text-emerald-600 transition">Fitur</a>
                        <a href="#cek-saldo" class="text-sm font-medium text-gray-600 hover:text-emerald-600 transition">Cek Saldo</a>
                    </div>

                    <div class="flex items-center gap-4">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ url('/dashboard') }}" class="text-sm font-medium px-5 py-2.5 rounded-full bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900 transition hidden md:block">Masuk</a>
                                <a href="{{ route('login') }}" class="text-sm font-medium px-6 py-2.5 rounded-full bg-emerald-600 text-white hover:bg-emerald-700 shadow-lg shadow-emerald-200 transition transform hover:-translate-y-0.5">Portal Guru</a>
                            @endauth
                        @endif
                    </div>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <div class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden">
            <!-- Decorative Blobs -->
            <div class="absolute top-0 left-1/2 w-full -translate-x-1/2 overflow-hidden -z-10 h-full">
                <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-emerald-200/50 mix-blend-multiply filter blur-3xl opacity-70"></div>
                <div class="absolute top-40 -left-40 w-96 h-96 rounded-full bg-teal-200/50 mix-blend-multiply filter blur-3xl opacity-70"></div>
                <div class="absolute -bottom-40 left-1/2 w-96 h-96 rounded-full bg-cyan-200/50 mix-blend-multiply filter blur-3xl opacity-70"></div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative text-center">
                <h1 class="text-5xl md:text-7xl font-extrabold tracking-tight text-gray-900 mb-6 drop-shadow-sm">
                    Tabungan Siswa, <br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 to-teal-600">Lebih Transparan.</span>
                </h1>
                <p class="mt-4 text-lg md:text-xl text-gray-600 max-w-2xl mx-auto font-medium">
                    Pantau mutasi dan saldo tabungan anak Anda secara real-time tanpa perlu login. Aman, praktis, dan mendukung notifikasi WhatsApp.
                </p>
                <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="#cek-saldo" class="px-8 py-4 text-base font-semibold rounded-full text-white bg-gray-900 hover:bg-gray-800 shadow-xl hover:shadow-2xl transition transform hover:-translate-y-1 flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        Cek Saldo Sekarang
                    </a>
                    <button class="px-8 py-4 text-base font-semibold rounded-full text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition flex items-center justify-center gap-2 ring-1 ring-inset ring-emerald-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Scan QR Code
                    </button>
                </div>
            </div>
        </div>

        <!-- Cek Saldo Section (Widget) -->
        <div id="cek-saldo" class="py-16 bg-white relative z-10 border-y border-gray-100">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <livewire:cek-saldo-widget />
            </div>
        </div>

        <!-- Fitur Section -->
        <div id="fitur" class="py-24 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-emerald-600 font-semibold tracking-wide uppercase text-sm">Kenapa SIHEMAT?</h2>
                    <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                        Didesain untuk Kemudahan
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 hover:shadow-xl transition duration-300">
                        <div class="w-14 h-14 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600 mb-6">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Notifikasi WhatsApp</h3>
                        <p class="text-gray-600">Setiap setoran dan penarikan akan langsung dilaporkan ke orang tua via WhatsApp secara real-time.</p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 hover:shadow-xl transition duration-300">
                        <div class="w-14 h-14 rounded-2xl bg-teal-50 flex items-center justify-center text-teal-600 mb-6">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Scan QR Praktis</h3>
                        <p class="text-gray-600">Siswa dan orang tua cukup memindai QR Code di buku tabungan untuk mengecek sisa saldo tanpa ribet.</p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100 hover:shadow-xl transition duration-300">
                        <div class="w-14 h-14 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 mb-6">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">Kalkulasi Otomatis</h3>
                        <p class="text-gray-600">Guru tidak perlu repot menghitung manual. SIHEMAT mencatat dan merekap mutasi secara otomatis.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-100 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="flex items-center gap-2">
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span class="font-bold text-gray-900">SIHEMAT</span>
                </div>
                <p class="text-gray-500 text-sm font-medium">© {{ date('Y') }} Aplikasi Tabungan Siswa. All rights reserved.</p>
            </div>
        </footer>
    </body>
</html>
