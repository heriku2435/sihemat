<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rombel_siswa', function (Blueprint $table) {
            $table->integer('nomor_urut')->nullable()->after('siswa_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rombel_siswa', function (Blueprint $table) {
            $table->dropColumn('nomor_urut');
        });
    }
};
