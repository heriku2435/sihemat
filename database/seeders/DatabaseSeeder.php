<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Pengaturan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Buat akun Administrator default
        User::updateOrCreate(
            ['email' => 'admin@sihemat.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'), // Silakan ubah password setelah login
                'role' => 'admin',
            ]
        );

        // Pengaturan default aplikasi
        Pengaturan::updateOrCreate(
            ['key' => 'nama_sekolah'],
            ['value' => 'SD Negeri 1 Contoh']
        );
        Pengaturan::updateOrCreate(
            ['key' => 'alamat_sekolah'],
            ['value' => 'Jl. Pendidikan No. 1']
        );
        Pengaturan::updateOrCreate(
            ['key' => 'kepala_sekolah'],
            ['value' => 'Bapak Kepala Sekolah, S.Pd']
        );
        Pengaturan::updateOrCreate(
            ['key' => 'nip_kepala_sekolah'],
            ['value' => '198001012005011001']
        );
    }
}
