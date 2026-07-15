# SIHEMAT - Sistem Informasi Tabungan Sekolah

SIHEMAT adalah sebuah aplikasi berbasis web (menggunakan framework Laravel dan Livewire) yang dirancang untuk memudahkan sekolah dalam mengelola dan mencatat mutasi tabungan siswa. Aplikasi ini dirancang agar mudah digunakan oleh Guru (sebagai pengelola tabungan kelas) dan Admin (pengelola sistem keseluruhan).

## Fitur Utama

- **Multi Role**: Terdapat peran Admin (untuk mengelola data master) dan Guru/Wali Kelas (untuk melakukan transaksi tabungan kelasnya).
- **Transaksi Cepat via QR Code**: Fitur pencarian siswa secara otomatis menggunakan scanner QR Code via kamera perangkat, mempermudah transaksi tanpa harus mencari nama secara manual.
- **WhatsApp Gateway Terintegrasi**: Mengirim notifikasi mutasi secara otomatis ke nomor WhatsApp orang tua siswa secara real-time. Tersedia opsi menggunakan provider `Fonnte` maupun integrasi custom (`Baileys`).
- **Dashboard & Grafik**: Ringkasan data (Total Saldo, Jumlah Siswa, Total Rombel) serta grafik riwayat mutasi.
- **Cek Saldo Mandiri**: Orang tua dapat melihat sisa saldo, mengecek 5 transaksi terakhir lewat grafik interaktif, dan mengunduh rekapan mutasi lengkap berformat PDF tanpa harus login (hanya dengan memindai/memasukkan kode QR).
- **Export & Import Data**: Mendukung Export data mutasi/rekap ke format Excel dan PDF, serta fitur Import data siswa dari Excel (.xlsx).
- **Backup Database Otomatis**: Fitur backup database secara otomatis ke Google Drive yang jadwal dan retensinya dapat diatur langsung melalui Dashboard Admin.

## Persyaratan Server Hosting

Untuk dapat menjalankan SIHEMAT pada server hosting (seperti cPanel / VPS), pastikan server Anda memenuhi persyaratan Laravel 11.x:
- PHP >= 8.2
- Ekstensi PHP: Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, XML, Zip.
- Database: MySQL atau MariaDB.

## Panduan Instalasi di Server Hosting (cPanel / VPS)

Berikut adalah langkah-langkah ringkas mendeploy aplikasi ini ke server (Shared Hosting/cPanel):

1. **Upload File Aplikasi**:
   - Kompres seluruh isi folder project (kecuali `vendor` dan `node_modules` jika terlalu besar) ke dalam bentuk `.zip`.
   - Upload file zip tersebut ke direktori server Anda (misalnya di folder yang sejajar dengan `public_html`).
   - Ekstrak file zip tersebut.

2. **Pengaturan Direktori Public**:
   - Pindahkan seluruh isi dari folder `public` di Laravel ke dalam folder root domain Anda (contohnya folder `public_html`).
   - Buka file `index.php` yang baru dipindahkan tersebut, lalu sesuaikan jalurnya:
     ```php
     // Ubah baris ini:
     require __DIR__.'/../vendor/autoload.php';
     $app = require_once __DIR__.'/../bootstrap/app.php';

     // Menjadi (asumsi folder laravel bernama "sihemat" dan sejajar dengan public_html):
     require __DIR__.'/../sihemat/vendor/autoload.php';
     $app = require_once __DIR__.'/../sihemat/bootstrap/app.php';
     ```

3. **Konfigurasi Environment**:
   - Ubah nama file `.env.example` (di dalam folder `sihemat`) menjadi `.env`.
   - Sesuaikan pengaturan database (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) dengan database MySQL yang telah Anda buat di cPanel.
   - Atur URL aplikasi (`APP_URL`) dengan nama domain Anda.
   - Atur `APP_ENV=production` dan `APP_DEBUG=false`.

4. **Instalasi Dependency & Generate Key** (Via Terminal/SSH):
   Jalankan perintah berikut di direktori aplikasi Anda:
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan key:generate
   php artisan storage:link
   ```

5. **Migrasi Database**:
   Jalankan perintah migrasi beserta seeder agar data awal (seperti akun Admin bawaan) terisi:
   ```bash
   php artisan migrate:fresh --seed
   ```

### Alternatif Jika Hosting Tidak Memiliki Akses SSH/Composer:
Jika layanan hosting Anda (misal Shared Hosting biasa) tidak mengizinkan menjalankan command `composer` atau fitur terminal SSH, Anda dapat melakukan cara manual berikut:

1. **Jalankan Composer & Generate Key di Komputer Lokal (PC/Laptop) Anda**:
   Buka terminal di komputer Anda, lalu jalankan:
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan key:generate
   ```
2. **Upload Keseluruhan Project Termasuk Folder Vendor**:
   Jadikan file project Anda `.zip` (kali ini **pastikan folder `vendor` ikut dimasukkan**).
3. **Upload dan Ekstrak di cPanel**.
4. **Buat Symlink Storage Secara Manual**:
   Buat sebuah file bernama `symlink.php` di dalam `public_html` (folder public) Anda dengan isi:
   ```php
   <?php
   $targetFolder = $_SERVER['DOCUMENT_ROOT'].'/../sihemat/storage/app/public';
   $linkFolder = $_SERVER['DOCUMENT_ROOT'].'/storage';
   symlink($targetFolder, $linkFolder);
   echo 'Symlink process completed';
   ```
   Lalu buka di browser: `https://domainanda.com/symlink.php`. Hapus file ini setelah selesai.
5. **Migrasi Database via Export/Import SQL**:
   - Di komputer lokal, jalankan `php artisan migrate:fresh --seed`.
   - Buka PHPMyAdmin lokal (misal localhost/phpmyadmin), pilih database Anda, lalu lakukan **Export** menjadi file `.sql`.
   - Buka PHPMyAdmin di cPanel hosting Anda, lalu **Import** file `.sql` tadi ke database server.

6. **Selesai**:
   Aplikasi SIHEMAT sekarang dapat diakses melalui browser Anda menggunakan domain yang telah ditentukan!

## Pengaturan Fitur (Admin Dashboard)
Aplikasi SIHEMAT memiliki halaman **Pengaturan** khusus di dalam Dashboard Admin untuk mempermudah operasional Anda. Anda tidak perlu repot-repot mengedit file `.env` di hosting Anda untuk mengubah pengaturan ini:
- **Pengaturan Sekolah**: Ubah nama dan alamat sekolah Anda kapan saja.
- **Provider WhatsApp**: Pilih penyedia layanan Gateway WhatsApp Anda (misal Fonnte), dan cukup tempelkan API Token-nya ke kolom yang disediakan.
- **Backup Database ke Google Drive**: Atur sendiri Jam Otomatis Backup dan Durasi Penyimpanan (retensi file backup). Terdapat **Panduan Integrasi API Google Drive** lengkap berbahasa Indonesia yang juga bisa Anda akses langsung dari halaman tersebut!

## Akun Default
- **Role**: Admin
- **Username / Email**: (Akan dibuat dari seeder)
- **Password**: (Akan dibuat dari seeder)
*(Pastikan Anda mengubah password bawaan ini demi alasan keamanan).*
