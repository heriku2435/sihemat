<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Http;

new class extends Component {
    public $status = 'unknown'; // 'unknown', 'disconnected', 'qr', 'connected'
    public $qrCode = null;
    
    public $isServerRunning = false;
    
    public function mount()
    {
        $this->checkStatus();
    }

    public function checkStatus()
    {
        $endpoint = rtrim(env('WA_GATEWAY_URL', 'http://localhost:3000'), '/') . '/status';
        try {
            $response = Http::timeout(2)->get($endpoint);
            $this->isServerRunning = true;
            if ($response->successful()) {
                $data = $response->json();
                $this->status = $data['status'] ?? 'unknown';
                $this->qrCode = $data['qr'] ?? null;
            } else {
                $this->status = 'disconnected';
                $this->qrCode = null;
            }
        } catch (\Exception $e) {
            $this->isServerRunning = false;
            $this->status = 'offline';
            $this->qrCode = null;
        }
    }
    
    // ...

    private function configureNodePath()
    {
        // Try to automatically detect Node.js path in Laragon
        $laragonNodePath = 'C:\\laragon\\bin\\nodejs';
        if (is_dir($laragonNodePath)) {
            $dirs = array_filter(glob($laragonNodePath . '\*'), 'is_dir');
            if (!empty($dirs)) {
                rsort($dirs); // Get highest version (e.g., node-v22 over node-v18)
                $nodePath = $dirs[0];
                putenv('PATH=' . $nodePath . ';' . getenv('PATH'));
                return $nodePath;
            }
        }
        return null;
    }

    public function startServer()
    {
        $this->configureNodePath();
        $waGatewayPath = base_path('wa-gateway');
        // Command to start index.js in background and redirect output to a log file
        $command = 'cd "' . $waGatewayPath . '" && start /B node index.js > server.log 2>&1';
        pclose(popen($command, 'r'));
        
        sleep(3); // Give it a bit more time to fetch WA version
        $this->checkStatus();
    }

    public function stopServer()
    {
        $endpoint = rtrim(env('WA_GATEWAY_URL', 'http://localhost:3000'), '/') . '/shutdown';
        try {
            Http::timeout(2)->post($endpoint);
        } catch (\Exception $e) {}
        
        sleep(2);
        $this->checkStatus();
    }

    public $installOutput = '';
    public $isInstalling = false;

    public function installDependencies()
    {
        if ($this->isInstalling) return;

        $nodePath = $this->configureNodePath();
        $waGatewayPath = base_path('wa-gateway');
        $npmCmd = $nodePath ? '"' . $nodePath . '\\npm.cmd"' : 'npm';
        
        $logFile = $waGatewayPath . DIRECTORY_SEPARATOR . 'install.log';
        // Gunakan @ untuk mencegah error "Resource temporarily unavailable" jika file sedang dikunci
        @file_put_contents($logFile, "Memulai instalasi dependensi, mohon tunggu...\n");
        
        $command = 'cd "' . $waGatewayPath . '" && ' . $npmCmd . ' install --no-color > install.log 2>&1';
        pclose(popen('start /B cmd /C "' . $command . '"', 'r'));
        
        $this->isInstalling = true;
        $this->installOutput = "Memulai instalasi dependensi, mohon tunggu...\n";
    }

    public function checkInstallProgress()
    {
        if ($this->isInstalling) {
            $logFile = base_path('wa-gateway' . DIRECTORY_SEPARATOR . 'install.log');
            if (file_exists($logFile)) {
                $content = @file_get_contents($logFile);
                if ($content !== false) {
                    $this->installOutput = $content;
                    
                    // Cek apakah instalasi sudah selesai dari output npm
                    if (str_contains(strtolower($this->installOutput), 'added ') || 
                        str_contains(strtolower($this->installOutput), 'up to date') || 
                        str_contains(strtolower($this->installOutput), 'found ') ||
                        str_contains(strtolower($this->installOutput), 'error!')) {
                        $this->isInstalling = false;
                    }
                }
            }
        }
    }
}; ?>

<div wire:poll.3s="checkStatus">
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('WhatsApp Gateway Manager') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-2xl p-6 border border-gray-100 dark:border-gray-700">
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Status Gateway</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-4 max-w-md">
                            Sistem notifikasi WhatsApp berjalan sebagai service terpisah (Node.js). Anda dapat mengelola status server dari halaman ini.
                        </p>
                        
                        <div class="flex items-center gap-3 mb-6">
                            @if($isServerRunning && $status === 'connected')
                                <span class="px-4 py-2 bg-emerald-100 text-emerald-800 rounded-full font-bold text-sm flex items-center gap-2">
                                    <div class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></div>
                                    Terkoneksi (Online)
                                </span>
                            @elseif($isServerRunning && $status === 'qr')
                                <span class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-full font-bold text-sm flex items-center gap-2">
                                    <div class="w-2.5 h-2.5 bg-yellow-500 rounded-full animate-pulse"></div>
                                    Menunggu Scan QR
                                </span>
                            @elseif($isServerRunning)
                                <span class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-full font-bold text-sm flex items-center gap-2">
                                    <div class="w-2.5 h-2.5 bg-yellow-500 rounded-full animate-pulse"></div>
                                    Server Menyala (Menunggu WA)
                                </span>
                            @else
                                <span class="px-4 py-2 bg-red-100 text-red-800 rounded-full font-bold text-sm flex items-center gap-2">
                                    <div class="w-2.5 h-2.5 bg-red-500 rounded-full"></div>
                                    Server Offline
                                </span>
                            @endif
                        </div>
                        
                        <div class="flex flex-wrap gap-3">
                            @if(!$isServerRunning)
                                <button wire:click="startServer" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-md transition-colors flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Nyalakan Gateway
                                </button>
                            @else
                                <button wire:click="stopServer" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded-xl shadow-md transition-colors flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"></path></svg>
                                    Matikan Gateway
                                </button>
                            @endif
                            <button wire:click="checkStatus" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-bold rounded-xl transition-colors flex items-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                Refresh
                            </button>
                        </div>
                    </div>
                    
                    <!-- QR Code Area -->
                    <div class="flex-shrink-0 w-full md:w-80 h-80 bg-gray-50 dark:bg-gray-700/50 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-600 flex flex-col items-center justify-center p-6 text-center">
                        @if($status === 'qr' && $qrCode)
                            <img src="{{ $qrCode }}" alt="QR Code WhatsApp" class="w-56 h-56 rounded-xl shadow-sm mb-3">
                            <p class="text-sm font-bold text-gray-600 dark:text-gray-300">Scan via WhatsApp > Tautkan Perangkat</p>
                        @elseif($status === 'connected')
                            <div class="w-24 h-24 bg-emerald-100 dark:bg-emerald-900/50 rounded-full flex items-center justify-center text-emerald-500 mb-4">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </div>
                            <h4 class="text-lg font-bold text-gray-800 dark:text-gray-100">Siap Digunakan!</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gateway berjalan dengan normal.</p>
                        @else
                            <div class="w-20 h-20 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center text-gray-400 dark:text-gray-400 mb-4">
                                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Server Offline.<br>Klik Nyalakan Gateway untuk memunculkan QR Code.</p>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="mt-6 bg-white dark:bg-gray-800 shadow-sm sm:rounded-2xl p-6 border border-gray-100 dark:border-gray-700">
                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">Instalasi & Setup</h3>
                <p class="text-gray-500 dark:text-gray-400 mb-4">
                    Jika ini adalah pertama kalinya Anda menggunakan WhatsApp Gateway di komputer/server ini, Anda wajib menginstal library NodeJS-nya (menjalankan <code>npm install</code>).
                </p>
                
                <button wire:click="installDependencies" @if($isInstalling) disabled @endif wire:loading.attr="disabled" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg wire:loading.remove wire:target="installDependencies" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    <svg wire:loading wire:target="installDependencies" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span wire:loading.remove wire:target="installDependencies">Install Dependensi (npm install)</span>
                    <span wire:loading wire:target="installDependencies">Mempersiapkan Instalasi...</span>
                </button>

                <div>
                    @if($isInstalling)
                        <div wire:poll.1s="checkInstallProgress" class="hidden"></div>
                    @endif

                    @if($installOutput)
                        <div class="mt-4 p-4 bg-gray-900 rounded-xl overflow-x-auto h-64 overflow-y-auto relative" wire:ignore.self>
                            @if($isInstalling)
                                <div class="absolute top-2 right-2 flex items-center gap-2 text-yellow-400 text-xs font-bold bg-gray-800 px-3 py-1 rounded-full border border-gray-700">
                                    <div class="w-2 h-2 bg-yellow-400 rounded-full animate-ping"></div>
                                    Memproses...
                                </div>
                            @elseif($installOutput !== '')
                                <div class="absolute top-2 right-2 text-green-400 text-xs font-bold bg-gray-800 px-3 py-1 rounded-full border border-gray-700">
                                    Selesai
                                </div>
                            @endif
                            <pre class="text-green-400 text-xs font-mono whitespace-pre-wrap" id="install-log">{{ $installOutput }}</pre>
                        </div>
                        <script>
                            // Auto scroll to bottom
                            const logContainer = document.querySelector('.h-64.overflow-y-auto');
                            if (logContainer) {
                                logContainer.scrollTop = logContainer.scrollHeight;
                            }
                        </script>
                    @endif
                </div>
            </div>
            
            <div class="mt-6 bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-r-xl">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            <strong>Catatan Penting:</strong> Fitur Nyalakan/Matikan dari UI ini didesain khusus untuk server lokal seperti Laragon/XAMPP di Windows. Server akan menyala di background (port 3000). Jangan lupa jalankan <code>npm install</code> di folder <code>wa-gateway</code> terlebih dahulu jika Anda belum pernah menginstalnya.
                        </p>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>
