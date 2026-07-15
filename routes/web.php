<?php

use Livewire\Volt\Volt;

Route::view('/', 'welcome');
Route::get('/test-transaksi', function() {
    auth()->loginUsingId(2);
    return redirect()->route('guru.transaksi');
});

Route::get('/dashboard', function () {
    if (auth()->user()->role === 'admin') {
        return redirect()->route('admin.dashboard');
    }
    return redirect()->route('guru.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Volt::route('tahun-ajaran', 'admin.tahun-ajaran-manager')->name('tahun-ajaran');
    Volt::route('guru', 'admin.guru-manager')->name('guru');
    Volt::route('siswa', 'admin.siswa-manager')->name('siswa');
    Volt::route('rombel', 'admin.rombel-manager')->name('rombel');
    Volt::route('rombel/{rombel}/siswa', 'admin.rombel-siswa-manager')->name('rombel.siswa');
    Route::get('rombel/{rombel}/cetak-label', [\App\Http\Controllers\LabelController::class, 'cetakRombel'])->name('rombel.cetak-label');
    Volt::route('pengaturan', 'admin.pengaturan')->name('pengaturan');
    Volt::route('wa-gateway', 'admin.wa-gateway-manager')->name('wa-gateway');
    Volt::route('rekapitulasi', 'laporan.rekapitulasi-manager')->name('rekapitulasi');
    Volt::route('transaksi', 'guru.transaksi-manager')->name('transaksi');
    Volt::route('mutasi/tabungan', 'mutasi.tabungan-manager')->name('mutasi.tabungan');
    Volt::route('mutasi/bank', 'mutasi.bank-manager')->name('mutasi.bank');
});

Route::middleware(['auth', 'role:guru'])->prefix('guru')->name('guru.')->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Volt::route('siswa', 'admin.siswa-manager')->name('siswa');
    Volt::route('rombel', 'admin.rombel-manager')->name('rombel');
    Volt::route('rombel/{rombel}/siswa', 'admin.rombel-siswa-manager')->name('rombel.siswa');
    Route::get('rombel/{rombel}/cetak-label', [\App\Http\Controllers\LabelController::class, 'cetakRombel'])->name('rombel.cetak-label');
    Volt::route('transaksi', 'guru.transaksi-manager')->name('transaksi');
    Volt::route('rekapitulasi', 'laporan.rekapitulasi-manager')->name('rekapitulasi');
    Volt::route('setoran-koperasi', 'guru.setoran-koperasi-manager')->name('setoran-koperasi');
    Volt::route('mutasi/tabungan', 'mutasi.tabungan-manager')->name('mutasi.tabungan');
    Volt::route('mutasi/bank', 'mutasi.bank-manager')->name('mutasi.bank');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('mutasi/cetak-pdf', [\App\Http\Controllers\PrintMutasiController::class, 'cetak'])
    ->middleware(['auth'])
    ->name('mutasi.cetak-pdf');

Route::get('mutasi-bank/cetak-pdf', [\App\Http\Controllers\PrintMutasiBankController::class, 'cetak'])
    ->middleware(['auth'])
    ->name('mutasi.bank.cetak-pdf');

Route::get('admin/rekapitulasi/cetak-pdf', [\App\Http\Controllers\PrintRekapitulasiController::class, 'cetak'])
    ->middleware(['auth', 'role:admin'])
    ->name('admin.rekapitulasi.cetak-pdf');

Route::get('admin/rekapitulasi/export-excel', [\App\Http\Controllers\PrintRekapitulasiController::class, 'exportExcel'])
    ->middleware(['auth', 'role:admin'])
    ->name('admin.rekapitulasi.export-excel');

Route::get('guru/rekapitulasi/cetak-pdf', [\App\Http\Controllers\PrintRekapitulasiController::class, 'cetak'])
    ->middleware(['auth', 'role:guru'])
    ->name('guru.rekapitulasi.cetak-pdf');

Route::get('guru/rekapitulasi/export-excel', [\App\Http\Controllers\PrintRekapitulasiController::class, 'exportExcel'])
    ->middleware(['auth', 'role:guru'])
    ->name('guru.rekapitulasi.export-excel');

Route::get('cek-saldo/cetak-pdf/{uuid_qr}/{tahun_ajaran_id}', [\App\Http\Controllers\PublicMutasiController::class, 'cetakPdf'])
    ->name('public.mutasi.cetak-pdf');

require __DIR__.'/auth.php';
