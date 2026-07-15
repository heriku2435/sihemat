<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Login - {{ config('app.name', 'SIHEMAT') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900 selection:bg-emerald-500 selection:text-white">
        <div class="min-h-[100dvh] flex flex-col justify-center items-center relative overflow-hidden bg-gradient-to-br from-emerald-900 via-gray-900 to-gray-900">
            <!-- Background Ornaments -->
            <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-emerald-500 rounded-full mix-blend-overlay filter blur-[100px] opacity-30 animate-blob pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-teal-500 rounded-full mix-blend-overlay filter blur-[100px] opacity-20 animate-blob animation-delay-2000 pointer-events-none"></div>
            
            <div class="w-full max-w-md px-6 py-10 z-10">
                <div class="text-center mb-10">
                    <a href="/" wire:navigate class="inline-block transition transform hover:scale-105">
                        <div class="w-16 h-16 bg-gradient-to-tr from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center shadow-xl shadow-emerald-500/30 mx-auto">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                    </a>
                    <h1 class="mt-4 text-3xl font-extrabold text-white tracking-tight">SIHEMAT</h1>
                    <p class="text-emerald-100 font-medium mt-1">Aplikasi Tabungan Siswa Pintar</p>
                </div>

                <div class="bg-white/10 backdrop-blur-xl border border-white/20 shadow-2xl overflow-hidden rounded-[2rem] p-8">
                    {{ $slot }}
                </div>
                
                <p class="text-center text-gray-400 text-sm mt-8">
                    &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </p>
            </div>
        </div>
    </body>
</html>
