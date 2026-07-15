@php
    // Get active academic year
    $tahun_aktif = \App\Models\TahunAjaran::where('is_active', true)->first();
    
    $chartData = [0, 0, 0, 0, 0, 0];
    $user = auth()->user();
    $is_guru = $user->role === 'guru';
    $guru_id = $is_guru ? $user->guru->id : null;
    $guru_rombel = null;

    if ($tahun_aktif) {
        if ($is_guru) {
            $guru_rombel = \App\Models\Rombel::where('guru_id', $guru_id)
                ->where('tahun_ajaran_id', $tahun_aktif->id)
                ->first();
        }

        $siswa_query = \Illuminate\Support\Facades\DB::table('rombel_siswa')
            ->join('rombels', 'rombel_siswa.rombel_id', '=', 'rombels.id')
            ->join('siswas', 'rombel_siswa.siswa_id', '=', 'siswas.id')
            ->where('rombels.tahun_ajaran_id', $tahun_aktif->id)
            ->where('siswas.status', 'Aktif');
            
        $pemasukan_query = \Illuminate\Support\Facades\DB::table('transaksis')
            ->join('rombels', 'transaksis.rombel_id', '=', 'rombels.id')
            ->join('siswas', 'transaksis.siswa_id', '=', 'siswas.id')
            ->where('rombels.tahun_ajaran_id', $tahun_aktif->id)
            ->where('siswas.status', 'Aktif')
            ->where('transaksis.jenis', 'setor');
            
        $penarikan_query = \Illuminate\Support\Facades\DB::table('transaksis')
            ->join('rombels', 'transaksis.rombel_id', '=', 'rombels.id')
            ->join('siswas', 'transaksis.siswa_id', '=', 'siswas.id')
            ->where('rombels.tahun_ajaran_id', $tahun_aktif->id)
            ->where('siswas.status', 'Aktif')
            ->where('transaksis.jenis', 'tarik');
            
        $chart_query = \Illuminate\Support\Facades\DB::table('transaksis')
            ->join('rombels', 'transaksis.rombel_id', '=', 'rombels.id')
            ->join('siswas', 'transaksis.siswa_id', '=', 'siswas.id')
            ->where('rombels.tahun_ajaran_id', $tahun_aktif->id)
            ->whereBetween('transaksis.tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai])
            ->where('siswas.status', 'Aktif')
            ->where('transaksis.jenis', 'setor');

        // Apply Guru scoping
        if ($is_guru) {
            $siswa_query->where('rombels.guru_id', $guru_id);
            $pemasukan_query->where('rombels.guru_id', $guru_id);
            $penarikan_query->where('rombels.guru_id', $guru_id);
            $chart_query->where('rombels.guru_id', $guru_id);
        }

        $total_siswa = $siswa_query->distinct('rombel_siswa.siswa_id')->count('rombel_siswa.siswa_id');
        $pemasukan = $pemasukan_query->sum('transaksis.jumlah');
        $penarikan = $penarikan_query->sum('transaksis.jumlah');
        $saldo_aktif = $pemasukan - $penarikan;
        
        if ($is_guru) {
            $guruId = auth()->user()->guru->id ?? null;
            if ($guruId) {
                $setor = \App\Models\SetoranKoperasi::where('guru_id', $guruId)
                    ->where('jenis', 'setor')
                    ->whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai])
                    ->sum('jumlah');
                $tarik = \App\Models\SetoranKoperasi::where('guru_id', $guruId)
                    ->where('jenis', 'tarik')
                    ->whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai])
                    ->sum('jumlah');
                $saldo_disetor = $setor - $tarik;
            } else {
                $saldo_disetor = 0;
            }
        } else {
            $setor = \App\Models\SetoranKoperasi::where('jenis', 'setor')
                ->whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai])
                ->sum('jumlah');
            $tarik = \App\Models\SetoranKoperasi::where('jenis', 'tarik')
                ->whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai])
                ->sum('jumlah');
            $saldo_disetor = $setor - $tarik;
        }
            
        $cash_guru = $saldo_aktif - $saldo_disetor;

        // Data Grafik Setoran Siswa (6 hari transaksi terakhir)
        $siswaDatesQuery = \Illuminate\Support\Facades\DB::table('transaksis')
            ->join('rombels', 'transaksis.rombel_id', '=', 'rombels.id')
            ->join('siswas', 'transaksis.siswa_id', '=', 'siswas.id')
            ->where('rombels.tahun_ajaran_id', $tahun_aktif->id)
            ->whereBetween('transaksis.tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai])
            ->where('siswas.status', 'Aktif')
            ->where('transaksis.jenis', 'setor');
            
        if ($is_guru) {
            $siswaDatesQuery->where('rombels.guru_id', $guru_id);
        }
        
        $siswaDates = $siswaDatesQuery->select('transaksis.tanggal')
            ->groupBy('transaksis.tanggal')
            ->orderBy('transaksis.tanggal', 'desc')
            ->limit(6)
            ->pluck('tanggal')
            ->toArray();
            
        sort($siswaDates);
        
        $chartDataSiswa = [];
        $chartCategoriesSiswa = [];
        
        foreach ($siswaDates as $date) {
            $sumQuery = \Illuminate\Support\Facades\DB::table('transaksis')
                ->join('rombels', 'transaksis.rombel_id', '=', 'rombels.id')
                ->join('siswas', 'transaksis.siswa_id', '=', 'siswas.id')
                ->where('rombels.tahun_ajaran_id', $tahun_aktif->id)
                ->where('siswas.status', 'Aktif')
                ->where('transaksis.jenis', 'setor')
                ->where('transaksis.tanggal', $date);
                
            if ($is_guru) {
                $sumQuery->where('rombels.guru_id', $guru_id);
            }
            
            $total = $sumQuery->sum('transaksis.jumlah');
            $chartDataSiswa[] = (float) $total;
            $chartCategoriesSiswa[] = \Carbon\Carbon::parse($date)->translatedFormat('d M');
        }

        // Data Grafik Setoran Bank (6 hari transaksi terakhir)
        $bankDatesQuery = \App\Models\SetoranKoperasi::where('jenis', 'setor')
            ->whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai]);
        if ($is_guru) {
            $bankDatesQuery->where('guru_id', $guru_id);
        }
        
        $bankDates = $bankDatesQuery->select('tanggal')
            ->groupBy('tanggal')
            ->orderBy('tanggal', 'desc')
            ->limit(6)
            ->pluck('tanggal')
            ->toArray();
            
        sort($bankDates);
        
        $chartDataBank = [];
        $chartCategoriesBank = [];
        
        foreach ($bankDates as $date) {
            $sumQuery = \App\Models\SetoranKoperasi::where('jenis', 'setor')
                ->where('tanggal', $date);
            if ($is_guru) {
                $sumQuery->where('guru_id', $guru_id);
            }
            $total = $sumQuery->sum('jumlah');
            $chartDataBank[] = (float) $total;
            $chartCategoriesBank[] = \Carbon\Carbon::parse($date)->translatedFormat('d M');
        }

        // Rekap Bulanan Siswa & Bank
        $transactions_siswa_query = \App\Models\Transaksi::whereHas('siswa', function($q) {
            $q->where('status', 'Aktif');
        })->whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai]);
        $transactions_bank_query = \App\Models\SetoranKoperasi::whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai]);
        
        if ($is_guru) {
            if ($guru_rombel) {
                $transactions_siswa_query->where('rombel_id', $guru_rombel->id);
            } else {
                $transactions_siswa_query->where('rombel_id', -1);
            }
            $transactions_bank_query->where('guru_id', $guru_id);
        }
        
        $transactions_siswa = $transactions_siswa_query->get();
        $transactions_bank = $transactions_bank_query->get();

        $rekapBulananSiswa = ['ganjil' => [], 'genap' => []];
        $rekapBulananBank = ['ganjil' => [], 'genap' => []];
        
        $monthsGanjil = [7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $monthsGenap = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni'];

        foreach ($monthsGanjil as $m => $name) {
            $siswa_setor = $transactions_siswa->where('jenis', 'setor')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $siswa_tarik = $transactions_siswa->where('jenis', 'tarik')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $rekapBulananSiswa['ganjil'][] = ['bulan' => $name, 'setor' => $siswa_setor, 'tarik' => $siswa_tarik];

            $bank_setor = $transactions_bank->where('jenis', 'setor')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $bank_tarik = $transactions_bank->where('jenis', 'tarik')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $rekapBulananBank['ganjil'][] = ['bulan' => $name, 'setor' => $bank_setor, 'tarik' => $bank_tarik];
        }

        foreach ($monthsGenap as $m => $name) {
            $siswa_setor = $transactions_siswa->where('jenis', 'setor')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $siswa_tarik = $transactions_siswa->where('jenis', 'tarik')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $rekapBulananSiswa['genap'][] = ['bulan' => $name, 'setor' => $siswa_setor, 'tarik' => $siswa_tarik];

            $bank_setor = $transactions_bank->where('jenis', 'setor')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $bank_tarik = $transactions_bank->where('jenis', 'tarik')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $rekapBulananBank['genap'][] = ['bulan' => $name, 'setor' => $bank_setor, 'tarik' => $bank_tarik];
        }

        // Default jika kosong
        if (empty($chartCategoriesSiswa)) {
            $chartCategoriesSiswa = ['-'];
            $chartDataSiswa = [0];
        }
        if (empty($chartCategoriesBank)) {
            $chartCategoriesBank = ['-'];
            $chartDataBank = [0];
        }
    } else {
        $total_siswa = 0;
        $saldo_aktif = 0;
        $saldo_disetor = 0;
        $cash_guru = 0;
        $chartCategoriesSiswa = ['-'];
        $chartDataSiswa = [0];
        $chartCategoriesBank = ['-'];
        $chartDataBank = [0];
        $rekapBulananSiswa = ['ganjil' => [], 'genap' => []];
        $rekapBulananBank = ['ganjil' => [], 'genap' => []];
    }
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-100 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span id="realtime-clock">{{ \Carbon\Carbon::now()->translatedFormat('l, d F Y - H:i:s') }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <!-- Welcome Card -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 mb-1">Selamat Datang, {{ auth()->user()->name }}!</h3>
                <p class="text-gray-500 dark:text-gray-400 text-sm">Ini adalah dashboard {{ ucfirst(auth()->user()->role) }} Anda.</p>
            </div>
            
            @if($tahun_aktif)
            <div class="inline-flex items-center gap-2 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-100 dark:border-emerald-800/50 px-4 py-2 rounded-xl">
                <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                <div class="text-sm">
                    <span class="text-emerald-600 dark:text-emerald-400 font-medium">Tahun Ajaran Aktif:</span>
                    <span class="text-gray-700 dark:text-gray-300 font-bold ml-1">{{ $tahun_aktif->nama }}</span>
                </div>
            </div>
            @else
            <div class="inline-flex items-center gap-2 bg-red-50 dark:bg-red-900/30 border border-red-100 dark:border-red-800/50 px-4 py-2 rounded-xl">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <div class="text-sm">
                    <span class="text-red-600 dark:text-red-400 font-medium">Belum ada Tahun Ajaran yang aktif!</span>
                </div>
            </div>
            @endif
        </div>
        
        <!-- Statistics Cards -->
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Card: Saldo Aktif -->
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-5 text-white shadow-lg shadow-emerald-200 dark:shadow-none flex flex-col justify-between relative overflow-hidden">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
                <div>
                    <p class="text-emerald-100 text-xs font-semibold uppercase tracking-wider">Total Saldo Aktif</p>
                    <h4 class="text-2xl font-bold mt-2">Rp {{ number_format($saldo_aktif, 0, ',', '.') }}</h4>
                </div>
                <div class="mt-4 flex items-center text-emerald-100 text-sm font-medium">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                    {{ $is_guru && $guru_rombel ? 'Kelas ' . $guru_rombel->nama_kelas : 'Semua Tabungan' }}
                </div>
            </div>

            <!-- Card: Saldo Disetor ke Koperasi/Bank -->
            <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-5 text-white shadow-lg shadow-blue-200 dark:shadow-none flex flex-col justify-between relative overflow-hidden">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
                <div>
                    <p class="text-blue-100 text-xs font-semibold uppercase tracking-wider">Disetor ke Bank</p>
                    <h4 class="text-2xl font-bold mt-2">Rp {{ number_format($saldo_disetor, 0, ',', '.') }}</h4>
                </div>
                <div class="mt-4 flex items-center text-blue-100 text-sm font-medium">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                    Aman di Bank/Koperasi
                </div>
            </div>

            <!-- Card: Cash di Guru -->
            <div class="bg-gradient-to-br from-amber-500 to-orange-500 rounded-2xl p-5 text-white shadow-lg shadow-amber-200 dark:shadow-none flex flex-col justify-between relative overflow-hidden">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
                <div>
                    <p class="text-amber-100 text-xs font-semibold uppercase tracking-wider">Tunai di Guru</p>
                    <h4 class="text-2xl font-bold mt-2">Rp {{ number_format($cash_guru, 0, ',', '.') }}</h4>
                </div>
                <div class="mt-4 flex items-center text-amber-100 text-sm font-medium">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Belum disetorkan
                </div>
            </div>

            <!-- Card: Total Siswa -->
            <div class="bg-gradient-to-br from-purple-500 to-fuchsia-600 rounded-2xl p-5 text-white shadow-lg shadow-purple-200 dark:shadow-none flex flex-col justify-between relative overflow-hidden">
                <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
                <div>
                    <p class="text-purple-100 text-xs font-semibold uppercase tracking-wider">Total Siswa Aktif</p>
                    <h4 class="text-2xl font-bold mt-2">{{ $total_siswa }} <span class="text-sm font-normal text-purple-200">Siswa</span></h4>
                </div>
                <div class="mt-4 flex items-center text-purple-100 text-sm font-medium">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    {{ $is_guru && $guru_rombel ? 'Di Kelas ' . $guru_rombel->nama_kelas : 'Terdaftar di kelas' }}
                </div>
            </div>
            
        </div>

        <!-- Grafik (Grid 2 kolom) -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Grafik Setoran Siswa -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700 shadow-sm relative w-full overflow-hidden">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-gray-800 dark:text-gray-100 font-bold">Grafik Setoran Siswa</h3>
                    <span class="bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400 text-xs px-2 py-1 rounded-md font-medium">6 Transaksi Terakhir</span>
                </div>
                <div id="chartSetoran" class="w-full" style="min-height: 250px;"></div>
            </div>

            <!-- Grafik Setoran Bank -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-100 dark:border-gray-700 shadow-sm relative w-full overflow-hidden">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-gray-800 dark:text-gray-100 font-bold">Grafik Setoran Bank</h3>
                    <span class="bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 text-xs px-2 py-1 rounded-md font-medium">6 Transaksi Terakhir</span>
                </div>
                <div id="chartSetoranBank" class="w-full" style="min-height: 250px;"></div>
            </div>
        </div>

        @php
            $currentMonth = \Carbon\Carbon::now()->month;
            $defaultTab = ($currentMonth >= 7 && $currentMonth <= 12) ? 'ganjil' : 'genap';
        @endphp
        <!-- Rekap Bulanan -->
        <div class="mt-6" x-data="{ activeTab: '{{ $defaultTab }}' }">
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden mb-6">
                <div class="p-5 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">Rekap Transaksi Bulanan</h3>
                    
                    <!-- Pill Segment Tab -->
                    <div class="inline-flex bg-gray-100 dark:bg-gray-900 rounded-xl p-1">
                        <button @click="activeTab = 'ganjil'" :class="{'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow': activeTab === 'ganjil', 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200': activeTab !== 'ganjil'}" class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200">
                            Semester Ganjil
                        </button>
                        <button @click="activeTab = 'genap'" :class="{'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow': activeTab === 'genap', 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200': activeTab !== 'genap'}" class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200">
                            Semester Genap
                        </button>
                    </div>
                </div>
                
                <!-- Tab Ganjil -->
                <div x-show="activeTab === 'ganjil'" class="grid grid-cols-1 md:grid-cols-2">
                    <!-- Transaksi Siswa -->
                    <div class="flex flex-col h-full border-b md:border-b-0 md:border-r border-gray-200 dark:border-gray-700">
                        <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-800">
                            <h4 class="text-sm font-bold text-emerald-600 dark:text-emerald-400">Transaksi Siswa</h4>
                        </div>
                        <div class="overflow-x-auto flex-grow">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/30">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Bulan</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Setor</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Tarik</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                    @foreach ($rekapBulananSiswa['ganjil'] ?? [] as $rekap)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $rekap['bulan'] }}</td>
                                            <td class="px-4 py-3 text-sm text-right font-bold {{ $rekap['setor'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                                {{ $rekap['setor'] > 0 ? '+Rp ' . number_format($rekap['setor'], 0, ',', '.') : '-' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-right font-bold {{ $rekap['tarik'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400' }}">
                                                {{ $rekap['tarik'] > 0 ? '-Rp ' . number_format($rekap['tarik'], 0, ',', '.') : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50/50 dark:bg-gray-800/30 border-t border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700 dark:text-gray-200">Total</th>
                                        <th class="px-4 py-3 text-right text-sm font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format(collect($rekapBulananSiswa['ganjil'] ?? [])->sum('setor'), 0, ',', '.') }}</th>
                                        <th class="px-4 py-3 text-right text-sm font-bold text-rose-600 dark:text-rose-400">Rp {{ number_format(collect($rekapBulananSiswa['ganjil'] ?? [])->sum('tarik'), 0, ',', '.') }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Transaksi Bank -->
                    <div class="flex flex-col h-full">
                        <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-800">
                            <h4 class="text-sm font-bold text-blue-600 dark:text-blue-400">Setor Bank/Koperasi</h4>
                        </div>
                        <div class="overflow-x-auto flex-grow">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/30">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Bulan</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Setor</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Tarik</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                    @foreach ($rekapBulananBank['ganjil'] ?? [] as $rekap)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $rekap['bulan'] }}</td>
                                            <td class="px-4 py-3 text-sm text-right font-bold {{ $rekap['setor'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                                {{ $rekap['setor'] > 0 ? '+Rp ' . number_format($rekap['setor'], 0, ',', '.') : '-' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-right font-bold {{ $rekap['tarik'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400' }}">
                                                {{ $rekap['tarik'] > 0 ? '-Rp ' . number_format($rekap['tarik'], 0, ',', '.') : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50/50 dark:bg-gray-800/30 border-t border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700 dark:text-gray-200">Total</th>
                                        <th class="px-4 py-3 text-right text-sm font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format(collect($rekapBulananBank['ganjil'] ?? [])->sum('setor'), 0, ',', '.') }}</th>
                                        <th class="px-4 py-3 text-right text-sm font-bold text-rose-600 dark:text-rose-400">Rp {{ number_format(collect($rekapBulananBank['ganjil'] ?? [])->sum('tarik'), 0, ',', '.') }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Tab Genap -->
                <div x-show="activeTab === 'genap'" style="display: none;" class="grid grid-cols-1 md:grid-cols-2">
                    <!-- Transaksi Siswa -->
                    <div class="flex flex-col h-full border-b md:border-b-0 md:border-r border-gray-200 dark:border-gray-700">
                        <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-800">
                            <h4 class="text-sm font-bold text-emerald-600 dark:text-emerald-400">Transaksi Siswa</h4>
                        </div>
                        <div class="overflow-x-auto flex-grow">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/30">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Bulan</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Setor</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Tarik</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                    @foreach ($rekapBulananSiswa['genap'] ?? [] as $rekap)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $rekap['bulan'] }}</td>
                                            <td class="px-4 py-3 text-sm text-right font-bold {{ $rekap['setor'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                                {{ $rekap['setor'] > 0 ? '+Rp ' . number_format($rekap['setor'], 0, ',', '.') : '-' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-right font-bold {{ $rekap['tarik'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400' }}">
                                                {{ $rekap['tarik'] > 0 ? '-Rp ' . number_format($rekap['tarik'], 0, ',', '.') : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50/50 dark:bg-gray-800/30 border-t border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700 dark:text-gray-200">Total</th>
                                        <th class="px-4 py-3 text-right text-sm font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format(collect($rekapBulananSiswa['genap'] ?? [])->sum('setor'), 0, ',', '.') }}</th>
                                        <th class="px-4 py-3 text-right text-sm font-bold text-rose-600 dark:text-rose-400">Rp {{ number_format(collect($rekapBulananSiswa['genap'] ?? [])->sum('tarik'), 0, ',', '.') }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Transaksi Bank -->
                    <div class="flex flex-col h-full">
                        <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-800">
                            <h4 class="text-sm font-bold text-blue-600 dark:text-blue-400">Setor Bank/Koperasi</h4>
                        </div>
                        <div class="overflow-x-auto flex-grow">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/30">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Bulan</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Setor</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Tarik</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                    @foreach ($rekapBulananBank['genap'] ?? [] as $rekap)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                            <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $rekap['bulan'] }}</td>
                                            <td class="px-4 py-3 text-sm text-right font-bold {{ $rekap['setor'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400' }}">
                                                {{ $rekap['setor'] > 0 ? '+Rp ' . number_format($rekap['setor'], 0, ',', '.') : '-' }}
                                            </td>
                                            <td class="px-4 py-3 text-sm text-right font-bold {{ $rekap['tarik'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-400' }}">
                                                {{ $rekap['tarik'] > 0 ? '-Rp ' . number_format($rekap['tarik'], 0, ',', '.') : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50/50 dark:bg-gray-800/30 border-t border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-sm font-bold text-gray-700 dark:text-gray-200">Total</th>
                                        <th class="px-4 py-3 text-right text-sm font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format(collect($rekapBulananBank['genap'] ?? [])->sum('setor'), 0, ',', '.') }}</th>
                                        <th class="px-4 py-3 text-right text-sm font-bold text-rose-600 dark:text-rose-400">Rp {{ number_format(collect($rekapBulananBank['genap'] ?? [])->sum('tarik'), 0, ',', '.') }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Riwayat Transaksi Terbaru -->
        <div class="mt-6 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                <h3 class="text-gray-800 dark:text-gray-100 font-bold">Riwayat Transaksi Terbaru</h3>
                <span class="bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 text-xs px-2 py-1 rounded-md font-medium">
                    {{ $is_guru && $guru_rombel ? 'Kelas ' . $guru_rombel->nama_kelas : 'Tahun Ajaran Aktif' }}
                </span>
            </div>
            
            @php
                $riwayat_transaksi = collect();
                if ($tahun_aktif) {
                    $riwayat_query = \Illuminate\Support\Facades\DB::table('transaksis as t')
                        ->join('siswas as s', 't.siswa_id', '=', 's.id')
                        ->join('rombels as r', 't.rombel_id', '=', 'r.id')
                        ->join('gurus as g', 't.guru_id', '=', 'g.id')
                        ->where('r.tahun_ajaran_id', $tahun_aktif->id)
                        ->whereBetween('t.tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai])
                        ->where('s.status', 'Aktif');

                    if ($is_guru) {
                        $riwayat_query->where('r.guru_id', $guru_id);
                    }

                    $riwayat_transaksi = $riwayat_query->select(
                            't.id', 
                            's.nama as nama_siswa', 
                            'r.nama_kelas', 
                            'g.nama as nama_guru',
                            't.jumlah', 
                            't.jenis', 
                            't.tanggal', 
                            't.created_at',
                            \Illuminate\Support\Facades\DB::raw('(
                                (SELECT COALESCE(SUM(jumlah), 0) FROM transaksis WHERE siswa_id = t.siswa_id AND jenis = "setor" AND created_at <= t.created_at) - 
                                (SELECT COALESCE(SUM(jumlah), 0) FROM transaksis WHERE siswa_id = t.siswa_id AND jenis = "tarik" AND created_at <= t.created_at)
                            ) as saldo_akhir')
                        )
                        ->orderByDesc('t.created_at')
                        ->paginate(5);
                }
            @endphp
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">
                            <th class="px-6 py-4 font-semibold">Tanggal</th>
                            <th class="px-6 py-4 font-semibold">Nama Siswa</th>
                            <th class="px-6 py-4 font-semibold">{{ $is_guru ? 'Kelas' : 'Kelas & Petugas' }}</th>
                            <th class="px-6 py-4 font-semibold">Jenis</th>
                            <th class="px-6 py-4 font-semibold text-right">Nominal</th>
                            <th class="px-6 py-4 font-semibold text-right">Saldo Akhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse($riwayat_transaksi as $trx)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ \Carbon\Carbon::parse($trx->tanggal)->translatedFormat('d M Y') }}
                                <br>
                                <span class="text-xs">{{ \Carbon\Carbon::parse($trx->created_at)->format('H:i') }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8 rounded-full {{ $trx->jenis === 'setor' ? 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-400' : 'bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400' }} flex items-center justify-center font-bold text-xs uppercase">
                                        {{ substr($trx->nama_siswa, 0, 2) }}
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $trx->nama_siswa }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                <div class="flex flex-col gap-1">
                                    <div>
                                        <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-md text-xs font-medium">{{ $trx->nama_kelas }}</span>
                                    </div>
                                    @if(!$is_guru)
                                    <div class="flex items-center gap-1 text-xs text-gray-500 mt-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                        {{ $trx->nama_guru }}
                                    </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($trx->jenis === 'setor')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                        Setoran
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400">
                                        Penarikan
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold {{ $trx->jenis === 'setor' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }} text-right">
                                {{ $trx->jenis === 'setor' ? '+' : '-' }} Rp {{ number_format($trx->jumlah, 0, ',', '.') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-700 dark:text-gray-200 text-right">
                                Rp {{ number_format($trx->saldo_akhir, 0, ',', '.') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                                <p>Belum ada riwayat transaksi di tahun ajaran aktif ini.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($riwayat_transaksi, 'hasPages') && $riwayat_transaksi->hasPages())
            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700">
                {{ $riwayat_transaksi->links() }}
            </div>
            @endif
        </div>


    </div>

    <!-- Scripts for Chart -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        document.addEventListener('livewire:navigated', function () {
            // Prevent multiple chart instances if navigating back and forth
            if (document.querySelector("#chartSetoran").innerHTML !== "") {
                document.querySelector("#chartSetoran").innerHTML = "";
            }
            if (document.querySelector("#chartSetoranBank").innerHTML !== "") {
                document.querySelector("#chartSetoranBank").innerHTML = "";
            }
            
            const chartData = @json($chartDataSiswa);
            const chartBankData = @json($chartDataBank);
            const isDarkMode = document.documentElement.classList.contains('dark');
            const textColor = isDarkMode ? '#9ca3af' : '#6b7280';
            const gridColor = isDarkMode ? '#374151' : '#f3f4f6';
            const chartCategoriesSiswa = @json($chartCategoriesSiswa);
            const chartCategoriesBank = @json($chartCategoriesBank);

            const commonOptions = {
                chart: {
                    type: 'area',
                    height: 250,
                    toolbar: { show: false },
                    fontFamily: 'inherit',
                    zoom: { enabled: false }
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.4,
                        opacityTo: 0.05,
                        stops: [0, 90, 100]
                    }
                },
                stroke: { curve: 'smooth', width: 3 },
                xaxis: {
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: { style: { colors: textColor, fontSize: '12px' } }
                },
                yaxis: {
                    labels: {
                        formatter: function (value) {
                            if (value >= 1000000) return "Rp " + (value / 1000000).toLocaleString('id-ID') + " Jt";
                            if (value >= 1000) return "Rp " + (value / 1000).toLocaleString('id-ID') + " Rb";
                            return "Rp " + value.toLocaleString('id-ID');
                        },
                        style: { colors: textColor, fontSize: '12px' },
                        minWidth: 40
                    }
                },
                grid: {
                    borderColor: gridColor,
                    strokeDashArray: 4,
                    xaxis: { lines: { show: true } },
                    yaxis: { lines: { show: true } },
                    padding: { top: 0, right: 0, bottom: 0, left: 10 }
                },
                tooltip: {
                    theme: isDarkMode ? 'dark' : 'light',
                    y: { formatter: function (val) { return "Rp " + val.toLocaleString('id-ID') } }
                }
            };

            const optionsSiswa = {
                ...commonOptions,
                series: [{ name: 'Setoran Siswa', data: chartData }],
                colors: ['#10b981'], // Emerald-500
                xaxis: {
                    ...commonOptions.xaxis,
                    categories: chartCategoriesSiswa
                },
                dataLabels: { 
                    enabled: true,
                    formatter: function (val) {
                        if (val >= 1000000) return (val / 1000000).toLocaleString('id-ID') + "Jt";
                        if (val >= 1000) return (val / 1000).toLocaleString('id-ID') + "rb";
                        return val.toLocaleString('id-ID');
                    },
                    style: { colors: ['#fff'], fontSize: '10px', fontWeight: 'bold' },
                    background: { enabled: true, foreColor: '#10b981', borderRadius: 4, padding: 4, borderWidth: 0 }
                }
            };

            const optionsBank = {
                ...commonOptions,
                series: [{ name: 'Setoran Bank', data: chartBankData }],
                colors: ['#3b82f6'], // Blue-500
                xaxis: {
                    ...commonOptions.xaxis,
                    categories: chartCategoriesBank
                },
                dataLabels: { 
                    enabled: true,
                    formatter: function (val) {
                        if (val >= 1000000) return (val / 1000000).toLocaleString('id-ID') + "Jt";
                        if (val >= 1000) return (val / 1000).toLocaleString('id-ID') + "rb";
                        return val.toLocaleString('id-ID');
                    },
                    style: { colors: ['#fff'], fontSize: '10px', fontWeight: 'bold' },
                    background: { enabled: true, foreColor: '#3b82f6', borderRadius: 4, padding: 4, borderWidth: 0 }
                }
            };

            const chart1 = new ApexCharts(document.querySelector("#chartSetoran"), optionsSiswa);
            chart1.render();
            
            const chart2 = new ApexCharts(document.querySelector("#chartSetoranBank"), optionsBank);
            chart2.render();

            // Observe dark mode changes to update chart colors dynamically
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.attributeName === "class") {
                        const isDark = document.documentElement.classList.contains('dark');
                        const newStyles = {
                            xaxis: { labels: { style: { colors: isDark ? '#9ca3af' : '#6b7280' } } },
                            yaxis: { labels: { style: { colors: isDark ? '#9ca3af' : '#6b7280' } } },
                            grid: { borderColor: isDark ? '#374151' : '#f3f4f6' },
                            tooltip: { theme: isDark ? 'dark' : 'light' }
                        };
                        chart1.updateOptions(newStyles);
                        chart2.updateOptions(newStyles);
                    }
                });
            });
            observer.observe(document.documentElement, { attributes: true });
        });

        // Realtime Clock
        function updateClock() {
            const now = new Date();
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const dateStr = now.toLocaleDateString('id-ID', dateOptions);
            const timeStr = now.toLocaleTimeString('id-ID');
            document.getElementById('realtime-clock').textContent = dateStr + ' - ' + timeStr.replace(/\./g, ':');
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</x-app-layout>
