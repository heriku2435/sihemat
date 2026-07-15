<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{ 
          darkMode: localStorage.getItem('darkMode') === 'true',
          toggleDarkMode() {
              this.darkMode = !this.darkMode;
              localStorage.setItem('darkMode', this.darkMode);
          }
      }"
      x-init="$watch('darkMode', val => val ? document.documentElement.classList.add('dark') : document.documentElement.classList.remove('dark')); if(darkMode) document.documentElement.classList.add('dark');"
      :class="{ 'dark': darkMode }">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SIHEMAT') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 transition-colors duration-200" x-data="{ sidebarOpen: false }">
        <div class="flex h-screen overflow-hidden">
            <!-- Sidebar -->
            <div class="print:hidden">
                <livewire:layout.navigation />
            </div>

            <!-- Main Content Area -->
            <div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden bg-gray-50 dark:bg-gray-900">
                <!-- Top Header -->
                <header class="print:hidden sticky top-0 z-30 bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 px-4 sm:px-6 h-16 flex items-center justify-between transition-colors duration-200">
                    <div class="flex items-center">
                        <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none p-2 -ml-2 rounded-xl">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        </button>
                        <div class="lg:hidden font-bold text-lg text-emerald-600 dark:text-emerald-400 tracking-tight ml-2">SIHEMAT</div>
                    </div>
                    
                    <div class="flex items-center">
                        <!-- Dark Mode Toggle -->
                        <button @click="toggleDarkMode()" class="p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full transition-colors" title="Toggle Dark Mode">
                            <svg x-show="!darkMode" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                            <svg x-show="darkMode" class="w-6 h-6 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: none;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        </button>
                    </div>
                </header>

                <!-- Page Heading (Desktop/Mobile) -->
                @if (isset($header))
                    <div class="print:hidden bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 hidden lg:block transition-colors duration-200">
                        <div class="max-w-7xl mx-auto py-5 px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </div>
                    <div class="print:hidden bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 lg:hidden transition-colors duration-200">
                        <div class="py-4 px-4 sm:px-6">
                            {{ $header }}
                        </div>
                    </div>
                @endif

                <!-- Page Content -->
                <main class="p-4 sm:p-6 lg:p-8 flex-1 w-full max-w-7xl mx-auto">
                    {{ $slot }}
                </main>
            </div>
        </div>
        <!-- AlertifyJS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css"/>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/default.min.css"/>
        <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>
        <script>
            if (!window.hasAlertifyListener) {
                window.addEventListener('notify', event => {
                    alertify.set('notifier', 'position', 'top-right');
                    
                    // Extract data from Livewire 3 event
                    let data = event.detail[0] || event.detail;
                    let type = data.type || 'success';
                    let message = data.message || '';
                    
                    if (type === 'success') {
                        alertify.success(message);
                    } else if (type === 'error') {
                        alertify.error(message);
                    } else if (type === 'warning') {
                        alertify.warning(message);
                    } else {
                        alertify.message(message);
                    }
                });
                window.hasAlertifyListener = true;
            }
        </script>
    </body>
</html>
