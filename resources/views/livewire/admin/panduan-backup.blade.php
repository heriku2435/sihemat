<?php

use Livewire\Volt\Component;

new class extends Component {
    // 
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-gray-800 leading-tight">
            {{ __('Panduan Backup Google Drive API') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto bg-white overflow-hidden shadow-sm rounded-2xl border border-gray-100 p-8 prose prose-emerald max-w-none">
            
            <p class="text-gray-600 mb-8">Karena backup database otomatis ini diunggah langsung ke akun Google Drive Anda, Anda wajib menghubungkan website ini ke API Google. Prosesnya memang sedikit panjang, tapi hanya perlu dilakukan <strong>satu kali saja</strong>.</p>

            <hr class="my-6">

            <h3 class="text-lg font-bold text-emerald-800 mb-2">Langkah 1: Buat Project di Google Cloud Console</h3>
            <ol class="list-decimal pl-5 space-y-2 mb-6 text-gray-700">
                <li>Buka <a href="https://console.cloud.google.com/" target="_blank" class="text-emerald-600 hover:underline">Google Cloud Console</a>. Pastikan Anda login dengan akun Google yang ingin digunakan untuk menyimpan backup.</li>
                <li>Klik <em>dropdown</em> project di bagian atas (sebelah logo Google Cloud), lalu klik <strong>New Project</strong>.</li>
                <li>Beri nama project (misal: <code>SIHEMAT Backup</code>), lalu klik <strong>Create</strong>.</li>
            </ol>

            <h3 class="text-lg font-bold text-emerald-800 mb-2">Langkah 2: Aktifkan Google Drive API</h3>
            <ol class="list-decimal pl-5 space-y-2 mb-6 text-gray-700">
                <li>Pastikan Anda berada di project yang baru dibuat.</li>
                <li>Di menu kiri atas (ikon hamburger ☰), masuk ke <strong>APIs & Services > Library</strong>.</li>
                <li>Cari <strong>Google Drive API</strong>, klik, lalu tekan tombol <strong>Enable</strong>.</li>
            </ol>

            <h3 class="text-lg font-bold text-emerald-800 mb-2">Langkah 3: Konfigurasi OAuth Consent Screen</h3>
            <ol class="list-decimal pl-5 space-y-2 mb-6 text-gray-700">
                <li>Masuk ke menu <strong>APIs & Services > OAuth consent screen</strong>.</li>
                <li>Pilih <strong>External</strong> (jika akun Gmail biasa) lalu klik <strong>Create</strong>.</li>
                <li>Isi informasi wajib:<br>
                    - <strong>App name</strong>: SIHEMAT Backup<br>
                    - <strong>User support email</strong>: Email Anda<br>
                    - <strong>Developer contact information</strong>: Email Anda
                </li>
                <li>Klik <strong>Save and Continue</strong> sampai selesai (tidak perlu mengisi scope yang rumit).</li>
                <li>Pada bagian <strong>Test users</strong>, pastikan Anda mengklik <strong>Add Users</strong> dan masukkan <strong>Email Google Anda sendiri</strong> (agar API bisa digunakan). Lalu <em>Save</em>.</li>
                <li>Klik <strong>Publish App</strong> (di halaman ringkasan Consent Screen) agar aplikasinya aktif.</li>
            </ol>

            <h3 class="text-lg font-bold text-emerald-800 mb-2">Langkah 4: Buat Kredensial OAuth (Client ID & Secret)</h3>
            <ol class="list-decimal pl-5 space-y-2 mb-6 text-gray-700">
                <li>Masuk ke menu <strong>APIs & Services > Credentials</strong>.</li>
                <li>Klik <strong>Create Credentials</strong> di bagian atas, pilih <strong>OAuth client ID</strong>.</li>
                <li>Pilih <strong>Application type</strong> -> <strong>Web application</strong>.</li>
                <li>Beri nama (bebas).</li>
                <li>Pada bagian <strong>Authorized redirect URIs</strong>, tambahkan: <code>https://developers.google.com/oauthplayground</code> (Sangat Penting).</li>
                <li>Klik <strong>Create</strong>.</li>
                <li>Akan muncul <em>pop-up</em> berisi <strong>Client ID</strong> dan <strong>Client Secret</strong>. Salin dan simpan keduanya dengan baik!</li>
            </ol>

            <h3 class="text-lg font-bold text-emerald-800 mb-2">Langkah 5: Dapatkan Refresh Token via OAuth Playground</h3>
            <ol class="list-decimal pl-5 space-y-2 mb-6 text-gray-700">
                <li>Buka tab baru dan kunjungi <a href="https://developers.google.com/oauthplayground/" target="_blank" class="text-emerald-600 hover:underline">Google OAuth 2.0 Playground</a>.</li>
                <li>Di pojok kanan atas, klik ikon <strong>Roda Gigi (Settings)</strong>.</li>
                <li>Centang opsi <strong>Use your own OAuth credentials</strong>.</li>
                <li>Masukkan <strong>Client ID</strong> dan <strong>Client Secret</strong> yang Anda dapatkan di Langkah 4.</li>
                <li>Di daftar sebelah kiri, cari kotak "Drive API v3", klik untuk meluaskannya, lalu centang baris <code>https://www.googleapis.com/auth/drive</code>.</li>
                <li>Klik tombol <strong>Authorize APIs</strong>. (Pilih akun Google Anda jika diminta, abaikan peringatan keamanan karena ini aplikasi Anda sendiri).</li>
                <li>Setelah dikembalikan ke Playground, klik tombol <strong>Exchange authorization code for tokens</strong>.</li>
                <li>Anda akan mendapatkan <strong>Refresh token</strong>. Salin token tersebut!</li>
            </ol>

            <h3 class="text-lg font-bold text-emerald-800 mb-2">Langkah 6: Tentukan Folder Tujuan di Google Drive</h3>
            <ol class="list-decimal pl-5 space-y-2 mb-6 text-gray-700">
                <li>Buka <a href="https://drive.google.com/" target="_blank" class="text-emerald-600 hover:underline">Google Drive</a> Anda.</li>
                <li>Buat folder baru khusus untuk menampung backup (misal: <code>Sihemat_Backup</code>).</li>
                <li>Buka folder tersebut.</li>
                <li>Perhatikan URL di <em>browser</em> Anda. URL-nya akan terlihat seperti: <code>https://drive.google.com/drive/folders/1aBcDeFgHiJkLmNoPqRsTuVwXyZ</code></li>
                <li>Salin ID Foldernya, yaitu kode acak di bagian paling akhir (<code>1aBcDeFgHiJkLmNoPqRsTuVwXyZ</code>).</li>
            </ol>

            <hr class="my-6">

            <div class="bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg">
                <h3 class="text-emerald-800 font-bold mb-2">Langkah Terakhir</h3>
                <p class="text-emerald-700 text-sm">Kembali ke halaman <a href="{{ route('admin.pengaturan') }}" class="font-bold underline">Pengaturan Backup</a> dan masukkan semua ID dan Token yang telah Anda dapatkan ke dalam form yang tersedia. Klik Simpan!</p>
            </div>
            
        </div>
    </div>
</div>
