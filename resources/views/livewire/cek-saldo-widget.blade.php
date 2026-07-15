<?php

use Livewire\Volt\Component;
use App\Models\Siswa;
use App\Models\Rombel;
use App\Models\TahunAjaran;

new class extends Component {
    public $mode = 'form'; // 'form' or 'scan'
    public $rombel_id_input = '';
    public $nomor_urut_input = '';
    
    public $hasil = null;
    public $errorMessage = null;

    public function cekSaldoForm()
    {
        $this->errorMessage = null;
        $this->hasil = null;

        $tahunAktif = TahunAjaran::where('is_active', true)->first();
        
        if (!$tahunAktif) {
            $this->errorMessage = 'Tidak ada Tahun Ajaran aktif.';
            return;
        }

        $rombel = Rombel::where('nama_kelas', $this->rombel_id_input)
            ->where('tahun_ajaran_id', $tahunAktif->id)
            ->first();
        
        if (!$rombel) {
            $this->errorMessage = 'Rombel tidak ditemukan pada tahun ajaran aktif.';
            return;
        }

        $siswa = Siswa::with(['rombels' => function($q) use ($rombel) {
            $q->where('rombel_id', $rombel->id);
        }])->whereHas('rombels', function($q) use ($rombel) {
            $q->where('rombel_id', $rombel->id)
              ->where('nomor_urut', $this->nomor_urut_input);
        })->first();

        if (!$siswa) {
            $this->errorMessage = 'Data siswa tidak ditemukan pada rombel tersebut.';
            return;
        }

        $this->setHasil($siswa, $siswa->rombels->first(), $tahunAktif);
    }

    public function handleScan($qrCodeData)
    {
        $this->errorMessage = null;
        $this->hasil = null;

        $tahunAktif = TahunAjaran::where('is_active', true)->first();
        
        if (!$tahunAktif) {
            $this->errorMessage = 'Tidak ada Tahun Ajaran aktif.';
            return;
        }

        $siswa = Siswa::with(['rombels' => function($q) use ($tahunAktif) {
            $q->where('tahun_ajaran_id', $tahunAktif->id);
        }])->where('uuid_qr', $qrCodeData)->first();

        if (!$siswa) {
            $this->errorMessage = 'QR Code tidak valid atau siswa tidak ditemukan.';
            return;
        }

        $rombelPivot = $siswa->rombels->first();
        
        if (!$rombelPivot) {
            $this->errorMessage = 'Siswa tidak terdaftar pada tahun ajaran aktif.';
            return;
        }

        $this->setHasil($siswa, $rombelPivot, $tahunAktif);
        $this->mode = 'result';
    }

    private function setHasil($siswa, $rombel, $tahunAktif)
    {
        // Get transaksis restricted to the active year's rombel
        $transaksisQuery = $siswa->transaksis()->where('rombel_id', $rombel->id);

        $totalSetor = (clone $transaksisQuery)->where('jenis', 'setor')->sum('jumlah');
        $totalTarik = (clone $transaksisQuery)->where('jenis', 'tarik')->sum('jumlah');
        
        $saldo = $totalSetor - $totalTarik;

        $history = (clone $transaksisQuery)->orderBy('tanggal', 'desc')->orderBy('created_at', 'desc')->take(5)->get();

        $chartHistory = $history->reverse();

        $this->hasil = [
            'uuid_qr' => $siswa->uuid_qr,
            'tahun_ajaran_id' => $tahunAktif->id,
            'nama' => $siswa->nama,
            'rombel' => $rombel->nama_kelas,
            'wali_kelas' => optional($rombel->guru)->nama ?? '-',
            'nomor_urut' => $rombel->pivot->nomor_urut ?? '-',
            'saldo' => $saldo,
            'history' => $history->map(function($t) {
                return [
                    'tanggal' => \Carbon\Carbon::parse($t->tanggal)->format('d M Y') . ' ' . $t->created_at->format('H:i'),
                    'jenis' => $t->jenis,
                    'nominal' => $t->jumlah,
                ];
            }),
            'chartData' => [
                'labels' => $chartHistory->map(fn($t) => \Carbon\Carbon::parse($t->tanggal)->format('d/m'))->values()->toArray(),
                'setor' => $chartHistory->map(fn($t) => $t->jenis === 'setor' ? (float) $t->jumlah : 0)->values()->toArray(),
                'tarik' => $chartHistory->map(fn($t) => $t->jenis === 'tarik' ? (float) $t->jumlah : 0)->values()->toArray(),
            ]
        ];
        
        $this->mode = 'result';
    }

    public function resetWidget()
    {
        $this->mode = 'form';
        $this->hasil = null;
        $this->errorMessage = null;
        $this->rombel_id_input = '';
        $this->nomor_urut_input = '';
    }
}; ?>

<div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-3xl shadow-2xl p-8 md:p-12 text-white relative overflow-hidden" x-data="{
    localMode: 'form',
    scanner: null,
    startScanner() {
        this.localMode = 'scan';
        this.$nextTick(() => {
            if(!this.scanner) {
                if (window.isSecureContext === false) {
                    let statusEl = document.getElementById('scanner-status');
                    if(statusEl) {
                        statusEl.innerHTML = '<div class=\'p-3 bg-red-100 text-red-700 rounded-lg text-sm mt-4\'><strong>Akses Kamera Ditolak Browser!</strong><br>Fitur kamera hanya bisa berjalan di koneksi aman (HTTPS). Karena Anda menggunakan Laragon (sihemat.test), silakan aktifkan SSL/HTTPS di Laragon Anda.</div>';
                    }
                    return;
                }

                try {
                    this.scanner = new Html5QrcodeScanner('reader', { fps: 10, qrbox: {width: 250, height: 250} }, false);
                    this.scanner.render((decodedText, decodedResult) => {
                        this.scanner.clear();
                        @this.call('handleScan', decodedText);
                    }, (errorMessage) => {
                        // parse error, ignore
                    });
                } catch(e) {
                    let statusEl = document.getElementById('scanner-status');
                    if(statusEl) {
                        statusEl.innerHTML = '<span class=\'text-red-500\'>Gagal memuat kamera: ' + e + '</span>';
                    }
                }
            }
        });
    },
    stopScanner() {
        if(this.scanner) {
            try { this.scanner.clear(); } catch(e){}
            this.scanner = null;
        }
        this.localMode = 'form';
        @this.set('mode', 'form');
    }
}"
x-effect="if($wire.mode === 'result') localMode = 'result'; if($wire.mode === 'form') localMode = 'form';">
    <!-- CDN html5-qrcode -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        /* Override Tailwind reset for html5-qrcode buttons and selects */
        #reader button {
            background-color: #10b981 !important; /* emerald-500 */
            color: white !important;
            padding: 8px 16px !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            margin: 8px 0 !important;
        }
        #reader select {
            padding: 8px !important;
            border-radius: 8px !important;
            border: 1px solid #d1d5db !important; /* gray-300 */
            margin: 8px 0 !important;
            color: #1f2937 !important; /* gray-800 */
        }
        #reader a {
            color: #10b981 !important;
        }
        #reader__dashboard_section_csr span {
            color: #4b5563 !important; /* gray-600 */
            font-weight: 500 !important;
        }
    </style>

    <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500 rounded-full mix-blend-overlay filter blur-3xl opacity-20 pointer-events-none"></div>
    
    <div class="relative z-10 text-center mb-8">
        <h2 class="text-3xl font-bold mb-2">Cek Tabungan Siswa</h2>
        <p class="text-gray-400">Pindai QR Code atau masukkan data untuk melihat saldo.</p>
    </div>

    @if($errorMessage)
        <div class="relative z-10 mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-xl text-red-200 text-sm flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            {{ $errorMessage }}
        </div>
    @endif

    <div x-show="localMode === 'form'" x-transition class="relative z-10 space-y-6">
        <form wire:submit="cekSaldoForm" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Nama Rombel</label>
                    <input type="text" wire:model="rombel_id_input" class="w-full bg-gray-800/50 border border-gray-700 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:ring-emerald-500 focus:border-emerald-500 transition" placeholder="Contoh: 1A">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Nomor Urut / Absen</label>
                    <input type="number" wire:model="nomor_urut_input" class="w-full bg-gray-800/50 border border-gray-700 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:ring-emerald-500 focus:border-emerald-500 transition" placeholder="Contoh: 12">
                </div>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-4 pt-2">
                <button type="submit" class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-4 rounded-xl shadow-lg shadow-emerald-500/30 transition transform hover:-translate-y-0.5">
                    Lihat Saldo
                </button>
                <button type="button" @click="startScanner()" class="flex-1 bg-gray-700 hover:bg-gray-600 text-white font-bold py-4 rounded-xl transition flex justify-center items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Scan QR Code
                </button>
            </div>
        </form>
    </div>

    <!-- Mode Scan (handled purely by Alpine for instant DOM) -->
    <div x-show="localMode === 'scan'" style="display: none;" class="relative z-10 space-y-6">
        <div class="bg-white rounded-xl overflow-hidden p-4 text-gray-900 min-h-[350px] flex flex-col justify-center items-center w-full">
            <div id="reader" class="w-full h-full min-h-[300px]"></div>
            <div id="scanner-status" class="mt-2 text-sm text-gray-500 font-medium text-center">
                Mempersiapkan kamera... (Tunggu sebentar)
            </div>
        </div>
        <button type="button" @click="stopScanner()" class="w-full bg-gray-700 hover:bg-gray-600 text-white font-bold py-4 rounded-xl transition">
            Batalkan Scan
        </button>
    </div>

    @if($hasil)
    <div x-show="localMode === 'result'" class="relative z-10 mt-6 border-t border-gray-700 pt-6">
        <div class="bg-gray-800/80 rounded-2xl p-6 border border-gray-700 mb-6 shadow-inner">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <h3 class="text-xl font-bold text-white">{{ $hasil['nama'] }}</h3>
                    <p class="text-gray-400 text-sm mt-1">
                        Kelas: {{ $hasil['rombel'] }} &nbsp;|&nbsp; 
                        No: {{ $hasil['nomor_urut'] }} &nbsp;|&nbsp; 
                        Wali Kelas: {{ $hasil['wali_kelas'] }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-emerald-400 font-semibold uppercase tracking-wider mb-1">Saldo Tersedia</p>
                    <p class="text-3xl font-extrabold text-white">Rp {{ number_format($hasil['saldo'], 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="border-t border-gray-700 pt-4">
                <p class="text-sm font-semibold text-gray-300 mb-3">5 Mutasi Terakhir</p>
                @if(count($hasil['history']) > 0)
                    <ul class="space-y-3">
                        @foreach($hasil['history'] as $trx)
                            <li class="flex justify-between items-center bg-gray-900/50 p-3 rounded-lg text-sm">
                                <span class="text-gray-400">{{ $trx['tanggal'] }}</span>
                                @if($trx['jenis'] === 'setor')
                                    <span class="text-emerald-400 font-medium">+ Rp {{ number_format($trx['nominal'], 0, ',', '.') }}</span>
                                @else
                                    <span class="text-red-400 font-medium">- Rp {{ number_format($trx['nominal'], 0, ',', '.') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-gray-500 text-sm italic">Belum ada transaksi.</p>
                @endif
            </div>

            <!-- Grafik -->
            <div class="border-t border-gray-700 pt-4 mt-6">
                <p class="text-sm font-semibold text-gray-300 mb-3">Grafik 5 Mutasi Terakhir</p>
                @if(count($hasil['history']) > 0)
                <div x-data="{ chartData: $wire.entangle('hasil.chartData') }"
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
                            if (!chartData || !chartData.labels || chartData.labels.length === 0) return;
                            if (chart) chart.destroy();
                            const options = {
                                series: [
                                    { name: 'Setor', data: chartData.setor },
                                    { name: 'Tarik', data: chartData.tarik }
                                ],
                                chart: {
                                    type: 'bar',
                                    height: 250,
                                    toolbar: { show: false },
                                    fontFamily: 'inherit',
                                    background: 'transparent'
                                },
                                theme: { mode: 'dark' },
                                colors: ['#10b981', '#ef4444'],
                                plotOptions: {
                                    bar: { horizontal: false, columnWidth: '50%', borderRadius: 4 }
                                },
                                dataLabels: { enabled: false },
                                xaxis: {
                                    categories: chartData.labels,
                                    axisBorder: { show: false },
                                    axisTicks: { show: false }
                                },
                                yaxis: {
                                    labels: {
                                        formatter: function (val) {
                                            if (val >= 1000000) return (val/1000000) + ' Jt';
                                            if (val >= 1000) return (val/1000) + ' rb';
                                            return val;
                                        }
                                    }
                                },
                                grid: { borderColor: '#374151', strokeDashArray: 4 }
                            };
                            chart = new ApexCharts($refs.chart, options);
                            chart.render();
                        };
                        $watch('chartData', () => renderChart());
                        if (chartData) renderChart();
                     ">
                    <div x-ref="chart" class="w-full min-h-[250px]"></div>
                </div>
                @else
                    <p class="text-gray-500 text-sm italic">Belum ada data untuk grafik.</p>
                @endif
            </div>
        </div>

        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            <a href="/cek-saldo/cetak-pdf/{{ $hasil['uuid_qr'] ?? '' }}/{{ $hasil['tahun_ajaran_id'] ?? '' }}" target="_blank" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-4 rounded-xl shadow-lg transition flex items-center justify-center gap-2 text-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Unduh Laporan Mutasi (PDF)
            </a>
            <button type="button" wire:click="resetWidget" class="flex-none bg-gray-700 hover:bg-gray-600 text-white font-bold py-4 px-6 rounded-xl transition">
                Kembali
            </button>
        </div>
    </div>
    @endif
</div>
