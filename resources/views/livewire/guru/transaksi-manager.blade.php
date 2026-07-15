<?php

use Livewire\Volt\Component;
use App\Models\Siswa;
use App\Models\Rombel;
use App\Models\Transaksi;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;

new class extends Component {
    public $searchQuery = '';
    public $selectedSiswaId = null;
    public $jenisTransaksi = 'setor'; // 'setor' or 'tarik'
    public $jumlahTransaksi = '';
    public $tanggalTransaksi = '';
    public $keterangan = '';
    
    public $chartData = ['categories' => [], 'setor' => [], 'tarik' => []];

    public function mount()
    {
        $this->tanggalTransaksi = now()->toDateString();
        $this->updateChartData();
    }

    // Automatically search for QR code matches as they type (or scan)
    public function updatedSearchQuery()
    {
        // Jika hasil query cocok persis dengan uuid_qr (berarti hasil scan QR)
        if (!empty($this->searchQuery)) {
            $query = Siswa::where('uuid_qr', $this->searchQuery)->where('status', 'Aktif');
            
            $user = auth()->user();
            if ($user->role === 'guru') {
                $guruId = $user->guru->id;
                $query->whereHas('rombels', function ($q) use ($guruId) {
                    $q->where('guru_id', $guruId)
                      ->whereHas('tahunAjaran', function($ta) {
                          $ta->where('is_active', true);
                      });
                });
            }
            
            $exactMatch = $query->first();
            if ($exactMatch) {
                $this->selectSiswa($exactMatch->id);
            }
        }
    }

    // Dipanggil saat menekan tombol Enter pada input pencarian
    public function searchSiswa()
    {
        $this->selectedSiswaId = null;

        if (empty($this->searchQuery)) return;

        $query = Siswa::where('status', 'Aktif');

        $user = auth()->user();
        if ($user->role === 'guru') {
            $guruId = $user->guru->id;
            
            $query->where(function ($q) use ($guruId) {
                // Kondisi 1: Berdasarkan nomor urut di rombel aktif guru ini
                $q->whereHas('rombels', function ($r) use ($guruId) {
                    $r->where('guru_id', $guruId)
                      ->whereHas('tahunAjaran', function($ta) {
                          $ta->where('is_active', true);
                      })
                      ->where('rombel_siswa.nomor_urut', $this->searchQuery);
                })
                // Kondisi 2: Berdasarkan uuid_qr (fallback jika scanner tidak memicu updatedSearchQuery)
                ->orWhere(function ($q2) use ($guruId) {
                    $q2->where('uuid_qr', $this->searchQuery)
                       ->whereHas('rombels', function ($r2) use ($guruId) {
                           $r2->where('guru_id', $guruId)
                              ->whereHas('tahunAjaran', function($ta) {
                                  $ta->where('is_active', true);
                              });
                       });
                });
            });
        }

        $siswa = $query->first();

        if ($siswa) {
            $this->selectSiswa($siswa->id);
            $this->resetValidation('searchQuery');
        } else {
            $this->addError('searchQuery', 'Siswa dengan Nomor Urut tersebut tidak ditemukan.');
        }
    }

    public function selectSiswa($siswaId)
    {
        $this->selectedSiswaId = $siswaId;
        $this->searchQuery = '';
        $this->resetValidation();
        $this->jumlahTransaksi = '';
        $this->tanggalTransaksi = now()->toDateString();
        $this->updateChartData();
        $this->dispatch('chart-updated', chartData: $this->chartData);
        $this->keterangan = '';
        $this->dispatch('focus-nominal');
    }

    public function getSelectedSiswaProperty()
    {
        if (!$this->selectedSiswaId) return null;
        return Siswa::with('rombels')->find($this->selectedSiswaId);
    }

    #[Computed]
    public function riwayatTransaksi()
    {
        $tahun_aktif = \App\Models\TahunAjaran::where('is_active', true)->first();
        $query = Transaksi::with('guru.user', 'siswa')->latest();
        
        if ($this->selectedSiswaId) {
            $query->where('siswa_id', $this->selectedSiswaId);
        } else {
            if ($tahun_aktif) {
                $guruId = auth()->user()->guru->id ?? null;
                $rombelAktif = \App\Models\Rombel::where('guru_id', $guruId)->where('tahun_ajaran_id', $tahun_aktif->id)->first();
                if ($rombelAktif) {
                    $query->where('rombel_id', $rombelAktif->id);
                } else {
                    $query->where('rombel_id', -1);
                }
            } else {
                $query->where('guru_id', auth()->user()->guru->id ?? null);
            }
        }
        
        if ($tahun_aktif) {
            $query->whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai]);
        }
        
        return $query->take(5)->get();
    }

    public function updateChartData()
    {
        $tahun_aktif = \App\Models\TahunAjaran::where('is_active', true)->first();
        $query = Transaksi::query();
        
        if ($this->selectedSiswaId) {
            $query->where('siswa_id', $this->selectedSiswaId);
        } else {
            if ($tahun_aktif) {
                $guruId = auth()->user()->guru->id ?? null;
                $rombelAktif = \App\Models\Rombel::where('guru_id', $guruId)->where('tahun_ajaran_id', $tahun_aktif->id)->first();
                if ($rombelAktif) {
                    $query->where('rombel_id', $rombelAktif->id);
                } else {
                    $query->where('rombel_id', -1);
                }
            } else {
                $query->where('guru_id', auth()->user()->guru->id ?? null);
            }
        }
        
        if ($tahun_aktif) {
            $query->whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai]);
        }
        
        $dailyData = $query->select(
                'tanggal',
                DB::raw("SUM(CASE WHEN jenis = 'setor' THEN jumlah ELSE 0 END) as total_setor"),
                DB::raw("SUM(CASE WHEN jenis = 'tarik' THEN jumlah ELSE 0 END) as total_tarik")
            )
            ->groupBy('tanggal')
            ->orderBy('tanggal', 'desc')
            ->take(6)
            ->get()
            ->reverse()
            ->values();

        $categories = [];
        $setorData = [];
        $tarikData = [];

        foreach ($dailyData as $data) {
            $categories[] = \Carbon\Carbon::parse($data->tanggal)->translatedFormat('l, d M');
            $setorData[] = $data->total_setor;
            $tarikData[] = $data->total_tarik;
        }

        $this->chartData = [
            'setor' => $setorData,
            'tarik' => $tarikData,
            'categories' => $categories
        ];
    }

    #[Computed]
    public function rekapBulanan()
    {
        $tahun_aktif = \App\Models\TahunAjaran::where('is_active', true)->first();
        if (!$tahun_aktif) return ['ganjil' => [], 'genap' => []];

        $query = Transaksi::whereBetween('tanggal', [$tahun_aktif->tanggal_mulai, $tahun_aktif->tanggal_selesai]);
        if ($this->selectedSiswaId) {
            $query->where('siswa_id', $this->selectedSiswaId);
        } else {
            $query->where('guru_id', auth()->user()->guru->id ?? null);
        }
        $transactions = $query->get();

        $ganjil = [];
        $genap = [];
        
        $monthsGanjil = [7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $monthsGenap = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni'];

        foreach ($monthsGanjil as $m => $name) {
            $setor = $transactions->where('jenis', 'setor')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $tarik = $transactions->where('jenis', 'tarik')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $ganjil[] = ['bulan' => $name, 'setor' => $setor, 'tarik' => $tarik];
        }

        foreach ($monthsGenap as $m => $name) {
            $setor = $transactions->where('jenis', 'setor')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $tarik = $transactions->where('jenis', 'tarik')->filter(fn($t) => \Carbon\Carbon::parse($t->tanggal)->month == $m)->sum('jumlah');
            $genap[] = ['bulan' => $name, 'setor' => $setor, 'tarik' => $tarik];
        }

        return ['ganjil' => $ganjil, 'genap' => $genap];
    }

    public function getTerbilang($angka)
    {
        $angka = abs($angka);
        $baca = array("", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
        $terbilang = "";

        if ($angka < 12) {
            $terbilang = " " . $baca[$angka];
        } else if ($angka < 20) {
            $terbilang = $this->getTerbilang($angka - 10) . " Belas";
        } else if ($angka < 100) {
            $terbilang = $this->getTerbilang($angka / 10) . " Puluh" . $this->getTerbilang($angka % 10);
        } else if ($angka < 200) {
            $terbilang = " Seratus" . $this->getTerbilang($angka - 100);
        } else if ($angka < 1000) {
            $terbilang = $this->getTerbilang($angka / 100) . " Ratus" . $this->getTerbilang($angka % 100);
        } else if ($angka < 2000) {
            $terbilang = " Seribu" . $this->getTerbilang($angka - 1000);
        } else if ($angka < 1000000) {
            $terbilang = $this->getTerbilang($angka / 1000) . " Ribu" . $this->getTerbilang($angka % 1000);
        } else if ($angka < 1000000000) {
            $terbilang = $this->getTerbilang($angka / 1000000) . " Juta" . $this->getTerbilang($angka % 1000000);
        } else if ($angka < 1000000000000) {
            $terbilang = $this->getTerbilang($angka / 1000000000) . " Miliar" . $this->getTerbilang($angka % 1000000000);
        }

        return $terbilang;
    }

    public function prosesTransaksi()
    {
        // We multiply the user input by 1000 for the actual amount
        $this->jumlahTransaksi = str_replace(',', '.', $this->jumlahTransaksi);
        
        $this->validate([
            'jumlahTransaksi' => 'required|numeric|min:0.5',
            'jenisTransaksi' => 'required|in:setor,tarik',
            'tanggalTransaksi' => 'required|date',
        ]);

        $realAmount = floatval($this->jumlahTransaksi) * 1000;

        if (auth()->user()->role === 'admin') {
            $this->addError('jumlahTransaksi', 'Admin tidak diperkenankan melakukan transaksi.');
            return;
        }

        $siswa = $this->selectedSiswa;
        if (!$siswa) return;

        // Validasi Saldo untuk penarikan
        if ($this->jenisTransaksi === 'tarik') {
            if ($siswa->saldo < $realAmount) {
                $this->addError('jumlahTransaksi', 'Saldo tidak mencukupi untuk penarikan ini.');
                return;
            }
        }

        // Tentukan Rombel & Guru
        $user = auth()->user();
        $guruId = null;
        if ($user->role === 'guru') {
            $guruId = $user->guru->id;
        }

        // Find the active rombel of the student
        $activeRombel = $siswa->rombels()->whereHas('tahunAjaran', function($q) {
            $q->where('is_active', true);
        })->first();

        DB::transaction(function () use ($siswa, $guruId, $activeRombel, $realAmount) {
            Transaksi::create([
                'siswa_id' => $siswa->id,
                'rombel_id' => $activeRombel ? $activeRombel->id : null,
                'guru_id' => $guruId,
                'jenis' => $this->jenisTransaksi,
                'jumlah' => $realAmount,
                'tanggal' => $this->tanggalTransaksi,
                'keterangan' => $this->keterangan,
            ]);
        });

        $siswa->refresh();
        $totalTarik = Transaksi::where('siswa_id', $siswa->id)->where('jenis', 'tarik')->sum('jumlah');
        $totalSetor = Transaksi::where('siswa_id', $siswa->id)->where('jenis', 'setor')->sum('jumlah');
        $guruName = $user->role === 'guru' && $user->guru ? $user->guru->nama : 'Admin';
        $guruHp = $user->role === 'guru' && $user->guru ? $user->guru->no_hp : '-';
        $this->sendWaNotification($siswa, $this->jenisTransaksi, $realAmount, $totalSetor, $totalTarik, $siswa->saldo, $guruName, $guruHp);

        $pesanSukses = 'Proses ' . $this->jenisTransaksi . ' tabungan ' . $siswa->nama . ' sebesar Rp ' . number_format($realAmount, 0, ',', '.') . ' berhasil.';
        $this->dispatch('notify', type: 'success', message: $pesanSukses);
        
        $this->jumlahTransaksi = '';
        $this->keterangan = '';
        // Biarkan profil siswa tetap tampil agar riwayat langsung terlihat
        // $this->searchQuery = ''; 
        // $this->selectedSiswaId = null; 
        
        $this->updateChartData();
        $this->dispatch('chart-updated', chartData: $this->chartData);
        $this->dispatch('focus-search');
    }

    private function sendWaNotification($siswa, $jenis, $nominal, $totalSetor, $totalTarik, $saldo, $guruName, $guruHp)
    {
        if (empty($siswa->no_wa_ortu)) {
            return;
        }
        
        $waProvider = \App\Models\Pengaturan::where('key', 'wa_provider')->value('value') ?? 'fonnte';
        
        $pesan = "Hai orangtua/wali dari *{$siswa->nama}*,\n\n"
               . "Ananda telah melakukan transaksi *" . strtoupper($jenis) . "* pada tabungan sekolah sebesar *Rp " . number_format($nominal, 0, ',', '.') . "*.\n\n"
               . "*INFORMASI*\n"
               . "- Total Penarikan: *Rp " . number_format($totalTarik, 0, ',', '.') . "*\n"
               . "- Total Setor: *Rp " . number_format($totalSetor, 0, ',', '.') . "*\n"
               . "- Sisa Saldo: *Rp " . number_format($saldo, 0, ',', '.') . "*\n\n"
               . "Jika ada pertanyaan hubungi *{$guruName}* ({$guruHp}). Atau Anda dapat mengecek riwayat transaksi melalui LAMAN CEK SALDO (" . url('/cek-saldo') . ")\n\n"
               . "_(Pesan ini dikirim otomatis oleh Sistem, mohon untuk tidak membalas pesan ini)_";

        if ($waProvider === 'baileys') {
            $endpoint = env('WA_GATEWAY_URL', 'http://localhost:3000/send-message');
            try {
                \Illuminate\Support\Facades\Http::post($endpoint, [
                    'number' => $siswa->no_wa_ortu,
                    'message' => $pesan
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('WA Gateway Error: ' . $e->getMessage());
            }
        } else {
            // Default to Fonnte
            $token = \App\Models\Pengaturan::where('key', 'token_fonnte')->value('value');
            if (!$token) return;
            
            try {
                \Illuminate\Support\Facades\Http::withHeaders([
                    'Authorization' => $token,
                ])->post('https://api.fonnte.com/send', [
                    'target' => $siswa->no_wa_ortu,
                    'message' => $pesan,
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Fonnte Error: ' . $e->getMessage());
            }
        }
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <h2 class="font-bold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Transaksi Tabungan') }}
            </h2>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span id="realtime-clock">{{ \Carbon\Carbon::now()->translatedFormat('l, d F Y - H:i:s') }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(auth()->user()->role === 'admin')
                <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-xl shadow-sm max-w-3xl mx-auto mt-8">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-8 w-8 text-rose-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-bold text-rose-800">Akses Dibatasi</h3>
                            <p class="text-sm text-rose-700 mt-1">
                                Akun Administrator tidak diperkenankan melakukan transaksi tabungan. Fitur transaksi ini hanya tersedia untuk akun Guru (Wali Kelas).
                            </p>
                        </div>
                    </div>
                </div>
            @else
            <!-- Notifications now handled globally via AlertifyJS -->

            <!-- Kotak Pencarian -->
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-2xl p-6 border border-gray-100 dark:border-gray-700 mb-6 {{ $this->selectedSiswa ? '' : 'max-w-2xl mx-auto' }}">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Pilih Siswa (Berdasarkan Nomor Urut)</h3>
                        
                        <div class="relative flex gap-2">
                            <div class="relative flex-1">
                                <input type="text" id="searchInput" wire:model="searchQuery" wire:keydown.enter="searchSiswa" 
                                       x-data @focus-search.window="setTimeout(() => $el.focus(), 150)"
                                       class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 pl-10 pr-10 py-3" 
                                       placeholder="Ketik Nomor Urut lalu tekan Enter..." autofocus>
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                </div>
                                <!-- Loading Spinner -->
                                <div wire:loading wire:target="searchSiswa" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <svg class="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>
                            <button type="button" @click="$dispatch('open-scanner')" class="flex-none bg-indigo-50 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 rounded-xl px-4 py-3 flex items-center justify-center transition-colors" title="Pindai QR Code">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                            </button>
                        </div>
                        @error('searchQuery') <span class="text-sm text-red-600 mt-2 block">{{ $message }}</span> @enderror
            </div>

            @if($this->selectedSiswa)
                <form wire:submit.prevent="prosesTransaksi">
                    <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 1.5rem;">
                    <!-- Profil Siswa Terpilih (60%) -->
                    <div style="flex: 6 1 0%; min-width: min(100%, 350px); height: 188px;">
                        <div class="shadow-xl rounded-3xl p-6 md:p-8 text-white relative overflow-hidden h-full border border-white/20" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);">
                            
                            <div class="relative z-10 h-full flex items-center gap-5">
                                <!-- Foto/Inisial Berbentuk Persegi Panjang (4x6) -->
                                <div class="w-20 sm:w-24 flex-none bg-white/20 flex items-center justify-center text-5xl font-bold border-2 rounded-2xl shadow-inner backdrop-blur-sm" style="aspect-ratio: 4/6; border-color: #fbbf24; color: #fbbf24;">
                                    {{ substr($this->selectedSiswa->nama, 0, 1) }}
                                </div>
                                
                                <!-- Informasi Nama dan Saldo (Kanan) -->
                                <div class="flex-1 flex flex-col justify-center py-1">
                                    <div class="bg-black/20 rounded-xl p-3 sm:p-4 backdrop-blur-sm border border-white/10 mb-2">
                                        <p class="text-indigo-100 text-xs mb-1 uppercase tracking-wider font-semibold">Sisa Saldo</p>
                                        <h4 class="text-2xl sm:text-3xl font-bold font-mono">Rp {{ number_format($this->selectedSiswa->saldo, 0, ',', '.') }}</h4>
                                    </div>
                                    
                                    <div>
                                        <h3 class="text-xl sm:text-2xl font-bold leading-tight line-clamp-1">{{ $this->selectedSiswa->nama }}</h3>
                                        <p class="text-indigo-100 text-sm mt-1 font-medium">NIS: {{ $this->selectedSiswa->nis ?? '-' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Jenis Transaksi (40%) -->
                    <style>
                        .radio-setor:checked ~ .btn-setor { background-color: #10b981 !important; border-color: #10b981 !important; color: white !important; }
                        .radio-setor:not(:checked) ~ .btn-setor { border-color: #e5e7eb; background-color: white; color: #6b7280; }
                        .radio-setor:not(:checked) ~ .btn-setor:hover { border-color: #10b981 !important; color: #10b981 !important; }
                        
                        .radio-tarik:checked ~ .btn-tarik { background-color: #ef4444 !important; border-color: #ef4444 !important; color: white !important; }
                        .radio-tarik:not(:checked) ~ .btn-tarik { border-color: #e5e7eb; background-color: white; color: #6b7280; }
                        .radio-tarik:not(:checked) ~ .btn-tarik:hover { border-color: #ef4444 !important; color: #ef4444 !important; }
                    </style>
                    <div style="flex: 4 1 0%; min-width: min(100%, 250px); height: 188px;">
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-2xl p-4 sm:p-5 border-2 h-full flex flex-col justify-center" style="border-color: #818cf8;">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-3 border-b border-gray-100 dark:border-gray-700 pb-2">Jenis Transaksi</h3>
                            
                            <div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <label class="relative cursor-pointer" onclick="document.getElementById('nominalInput').focus()">
                                            <input type="radio" wire:model="jenisTransaksi" value="setor" class="radio-setor sr-only">
                                            <div class="btn-setor py-2 px-1 text-center rounded-xl border-2 transition-all duration-200 hover:scale-105 hover:shadow-md">
                                                <svg class="w-7 h-7 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                                                <span class="text-sm font-bold block">SETOR</span>
                                            </div>
                                        </label>
                                        
                                        <label class="relative cursor-pointer" onclick="document.getElementById('nominalInput').focus()">
                                            <input type="radio" wire:model="jenisTransaksi" value="tarik" class="radio-tarik sr-only">
                                            <div class="btn-tarik py-2 px-1 text-center rounded-xl border-2 transition-all duration-200 hover:scale-105 hover:shadow-md">
                                                <svg class="w-7 h-7 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                                <span class="text-sm font-bold block">TARIK</span>
                                            </div>
                                        </label>
                                    </div>
                                    @error('jenisTransaksi') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                                </div>
                        </div>
                    </div>
                </div>

                <!-- Form Input Transaksi -->
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-2xl p-6 md:p-8 border border-gray-100 dark:border-gray-700 mb-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-6 border-b border-gray-100 dark:border-gray-700 pb-4">Detail Transaksi</h3>
                    <div class="space-y-6">
                        <!-- Nominal, Tanggal, & Keterangan in 3 columns -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <!-- Nominal -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nominal (Ribu Rp)</label>
                                        <div class="w-full px-4 py-3 rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 transition-colors flex items-center cursor-text overflow-hidden"
                                             style="min-height: 52px;"
                                             onclick="document.getElementById('nominalInput').focus()"
                                             x-data="{ val: @entangle('jumlahTransaksi').live }">
                                            
                                            <span class="text-gray-500 font-bold mr-1" x-show="val">Rp</span>
                                            
                                            <div class="relative flex items-center min-w-[20px]">
                                                <!-- Invisible span to auto-size the wrapper based on typed text -->
                                                <span class="invisible whitespace-pre font-mono text-lg" x-text="val ? val + ' ' : 'Ketik...'"></span>
                                                <input id="nominalInput" type="text" inputmode="decimal" autocomplete="off"
                                                       wire:model.live="jumlahTransaksi"
                                                       class="absolute inset-0 w-full h-full bg-transparent border-none p-0 focus:ring-0 text-gray-900 dark:text-gray-100 font-mono text-lg"
                                                       placeholder="Ketik..."
                                                       @focus-nominal.window="$nextTick(() => { $el.focus() })"
                                                       x-init="$nextTick(() => { $el.focus() })">
                                            </div>
                                            
                                            <span class="text-gray-400 font-bold" x-show="val">.000</span>
                                        </div>
                                        @if($jumlahTransaksi)
                                            <div class="text-xs text-indigo-600 dark:text-indigo-400 mt-1 font-medium">
                                                = Rp {{ number_format(floatval($jumlahTransaksi) * 1000, 0, ',', '.') }}
                                                <span class="italic text-gray-500 dark:text-gray-400">({{ trim($this->getTerbilang(floatval($jumlahTransaksi) * 1000)) }} Rupiah)</span>
                                            </div>
                                        @else
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Ketik 1 untuk Rp 1.000</div>
                                        @endif
                                        @error('jumlahTransaksi') <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Tanggal Transaksi -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tanggal Transaksi</label>
                                        <input type="date" wire:model="tanggalTransaksi" 
                                               class="w-full py-3 px-4 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 transition-colors" 
                                               style="min-height: 52px;"
                                               required>
                                        @error('tanggalTransaksi') <span class="text-sm text-red-600 mt-1 block">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Keterangan -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Keterangan (Opsional)</label>
                                        <input type="text" wire:model="keterangan" 
                                               class="w-full py-3 px-4 rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-indigo-500 focus:ring-indigo-500 transition-colors"
                                               style="min-height: 52px;"
                                               placeholder="Tulis catatan jika ada...">
                                    </div>
                                </div>

                                <!-- Submit -->
                                <button type="submit" class="w-full py-4 px-4 border border-transparent rounded-xl shadow-sm text-lg font-bold text-white transition-colors {{ $jenisTransaksi === 'setor' ? 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-200 dark:shadow-none' : 'bg-rose-600 hover:bg-rose-700 shadow-rose-200 dark:shadow-none' }}">
                                    Proses {{ ucfirst($jenisTransaksi) }} Saldo
                                </button>
                        </div>
                    </div>
                </form>
            @else
                <!-- Blank State if no student selected -->
                <div class="bg-gray-50 dark:bg-gray-800/50 shadow-inner sm:rounded-2xl p-12 border border-gray-100 dark:border-gray-700 border-dashed flex flex-col items-center justify-center text-center max-w-2xl mx-auto mb-6">
                    <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center text-gray-400 dark:text-gray-500 mb-4 mx-auto">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Menunggu Input</h3>
                    <p class="text-gray-500 dark:text-gray-400 mt-2 max-w-sm mx-auto">
                        Silakan pindai QR Code atau ketik nama/NIS siswa di kotak pencarian untuk mulai melakukan transaksi.
                    </p>
                </div>
            @endif
                <!-- Riwayat & Grafik -->
                <div class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 mb-6">
                    <div class="p-5 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                        <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">Riwayat Terakhir</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2">
                        <!-- Tabel Riwayat 5 Terakhir -->
                        <div class="flex flex-col h-full border-b md:border-b-0 md:border-r border-gray-200 dark:border-gray-700">
                            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-800">
                                <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300">Riwayat Transaksi</h4>
                            </div>
                            <div class="overflow-x-auto flex-grow">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50/50 dark:bg-gray-800/30">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tgl / Siswa</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Petugas</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nominal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                        @forelse ($this->riwayatTransaksi as $trx)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors group cursor-pointer" title="{{ $trx->keterangan ?: 'Tanpa keterangan' }}">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center space-x-3">
                                                        @if($trx->jenis === 'setor')
                                                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center flex-shrink-0">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                                                            </div>
                                                        @else
                                                            <div class="w-8 h-8 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center flex-shrink-0">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                                            </div>
                                                        @endif
                                                        <div>
                                                            <div class="text-xs font-medium text-gray-900 dark:text-gray-100">
                                                                {{ \Carbon\Carbon::parse($trx->tanggal)->translatedFormat('d M Y') }}
                                                            </div>
                                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                                {{ optional($trx->siswa)->nama ?? 'Siswa tidak ditemukan' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-left text-xs text-gray-500 dark:text-gray-400">
                                                    {{ optional(optional($trx->guru)->user)->name ?? 'Admin' }}
                                                </td>
                                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                                    <div class="text-sm font-bold {{ $trx->jenis === 'setor' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                                        {{ $trx->jenis === 'setor' ? '+' : '-' }}Rp {{ number_format($trx->jumlah, 0, ',', '.') }}
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                                    <div class="flex flex-col items-center justify-center">
                                                        <svg class="w-10 h-10 text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                                        <p class="text-xs">Belum ada riwayat transaksi.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Grafik Card -->
                        <div class="flex flex-col h-full"
                             x-data="{ chartData: @entangle('chartData') }"
                             x-init="
                                let chart = null;
                                const renderChart = () => {
                                    if (!window.ApexCharts) {
                                        const script = document.createElement('script');
                                        script.src = 'https://cdn.jsdelivr.net/npm/apexcharts';
                                        script.onload = () => initChart();
                                        document.head.appendChild(script);
                                    } else {
                                        initChart();
                                    }
                                };
                                const initChart = () => {
                                    const options = {
                                        series: [
                                            { name: 'Setor', data: chartData.setor },
                                            { name: 'Tarik', data: chartData.tarik }
                                        ],
                                        chart: {
                                            type: 'area',
                                            height: 300,
                                            toolbar: { show: false },
                                            zoom: { enabled: false },
                                            fontFamily: 'inherit'
                                        },
                                        colors: ['#10b981', '#ef4444'],
                                        fill: {
                                            type: 'gradient',
                                            gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 90, 100] }
                                        },
                                        dataLabels: {
                                            enabled: true,
                                            formatter: function (val) {
                                                if(val === 0) return '';
                                                let num = parseInt(val);
                                                if(num >= 1000000) return (num/1000000).toLocaleString('id-ID') + ' Jt';
                                                if(num >= 1000) return (num/1000).toLocaleString('id-ID') + ' rb';
                                                return num.toLocaleString('id-ID');
                                            },
                                            offsetY: -5,
                                            style: { fontSize: '10px', colors: ['#4b5563'] },
                                            background: { enabled: false }
                                        },
                                        markers: {
                                            size: 4,
                                            colors: ['#fff'],
                                            strokeColors: ['#10b981', '#ef4444'],
                                            strokeWidth: 2,
                                            hover: { size: 6 }
                                        },
                                        stroke: { curve: 'smooth', width: 2 },
                                        xaxis: {
                                            categories: chartData.categories,
                                            axisBorder: { show: false },
                                            axisTicks: { show: false },
                                            labels: { style: { colors: '#9ca3af', fontSize: '11px' } }
                                        },
                                        yaxis: {
                                            labels: {
                                                formatter: (value) => 'Rp ' + (value/1000).toLocaleString('id-ID') + 'k',
                                                style: { colors: '#9ca3af', fontSize: '11px' }
                                            }
                                        },
                                        grid: {
                                            borderColor: '#f3f4f6',
                                            strokeDashArray: 4,
                                            yaxis: { lines: { show: true } },
                                            padding: { top: 0, right: 0, bottom: 0, left: 10 }
                                        },
                                        legend: { show: false },
                                        tooltip: {
                                            y: { formatter: (value) => 'Rp ' + value.toLocaleString('id-ID') }
                                        }
                                    };
                                    chart = new ApexCharts($refs.trxChart, options);
                                    chart.render();
                                };
                                renderChart();
                                
                                window.addEventListener('chart-updated', (event) => {
                                    if (chart && window.ApexCharts) {
                                        chart.updateOptions({
                                            xaxis: { categories: event.detail.chartData.categories }
                                        });
                                        chart.updateSeries([
                                            { name: 'Setor', data: event.detail.chartData.setor },
                                            { name: 'Tarik', data: event.detail.chartData.tarik }
                                        ]);
                                    }
                                });
                             ">
                            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-800">
                                <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300">Grafik Transaksi</h4>
                            </div>
                            <div class="p-4 flex-grow flex items-center justify-center">
                                <div wire:ignore x-ref="trxChart" class="w-full h-[300px]"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rekap Bulanan Transaksi -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 mb-6">
                    <div class="p-5 border-b border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                        <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">Rekap Bulanan Transaksi</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2">
                        <!-- Semester Ganjil -->
                        <div class="flex flex-col h-full border-b md:border-b-0 md:border-r border-gray-200 dark:border-gray-700">
                            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-800">
                                <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300">Semester Ganjil</h4>
                            </div>
                            <div class="overflow-x-auto flex-grow">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50/50 dark:bg-gray-800/30">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bulan</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Setor</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tarik</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                        @foreach ($this->rekapBulanan['ganjil'] as $rekap)
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
                                </table>
                            </div>
                        </div>

                        <!-- Semester Genap -->
                        <div class="flex flex-col h-full">
                            <div class="px-5 pt-5 pb-2 border-b border-gray-100 dark:border-gray-800">
                                <h4 class="text-sm font-bold text-gray-700 dark:text-gray-300">Semester Genap</h4>
                            </div>
                            <div class="overflow-x-auto flex-grow">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50/50 dark:bg-gray-800/30">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bulan</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Setor</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tarik</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-800">
                                        @foreach ($this->rekapBulanan['genap'] as $rekap)
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
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <!-- end riwayat & grafik -->
            @endif
        </div>
    </div>
    
    <!-- Scanner Modal (AlpineJS) -->
    <div x-data="{
             showScanner: false,
             html5QrcodeScanner: null,
             initScanner() {
                 this.showScanner = true;
                 setTimeout(() => {
                     this.html5QrcodeScanner = new Html5QrcodeScanner(
                         'reader',
                         { fps: 10, qrbox: {width: 250, height: 250} },
                         false);
                     this.html5QrcodeScanner.render((decodedText, decodedResult) => {
                         // On success, set the livewire search query
                         @this.set('searchQuery', decodedText);
                         this.closeScanner();
                     }, (error) => {
                         // ignore errors
                     });
                 }, 100);
             },
             closeScanner() {
                 if (this.html5QrcodeScanner) {
                     this.html5QrcodeScanner.clear();
                     this.html5QrcodeScanner = null;
                 }
                 this.showScanner = false;
             }
         }"
         @open-scanner.window="initScanner()"
         @keydown.escape.window="closeScanner()"
         x-show="showScanner"
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;"
         aria-labelledby="modal-title" role="dialog" aria-modal="true">
         
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showScanner" x-transition.opacity class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeScanner()" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="showScanner" x-transition.scale class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4" id="modal-title">Pindai QR Code Siswa</h3>
                            
                            <!-- Scanner Target -->
                            <div id="reader" width="600px" class="bg-gray-100 dark:bg-gray-700 rounded-xl overflow-hidden border-2 border-gray-200 dark:border-gray-600"></div>
                            <p class="text-sm text-gray-500 mt-2 text-center">Arahkan kamera ke QR Code yang ada di label buku tabungan siswa.</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" @click="closeScanner()" class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Tutup Kamera
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Load HTML5-QRCode Library via CDN -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <!-- Load ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        // Realtime Clock
        function updateClock() {
            const now = new Date();
            const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const dateStr = now.toLocaleDateString('id-ID', dateOptions);
            const timeStr = now.toLocaleTimeString('id-ID');
            const clockEl = document.getElementById('realtime-clock');
            if (clockEl) {
                clockEl.textContent = dateStr + ' - ' + timeStr.replace(/\./g, ':');
            }
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</div>
