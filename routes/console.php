<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

use App\Models\Pengaturan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$backupTime = '02:00';
try {
    $backupTime = Pengaturan::where('key', 'backup_time')->value('value') ?: '02:00';
} catch (\Exception $e) {
    // Ignore if not migrated
}

// Jadwal Backup Database Otomatis ke Google Drive
Schedule::command('backup:clean')->dailyAt($backupTime);
Schedule::command('backup:run --only-db')->dailyAt($backupTime);
