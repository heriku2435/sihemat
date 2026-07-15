<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Pengaturan;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            $gdriveClientId = Pengaturan::where('key', 'gdrive_client_id')->value('value');
            $gdriveClientSecret = Pengaturan::where('key', 'gdrive_client_secret')->value('value');
            $gdriveRefreshToken = Pengaturan::where('key', 'gdrive_refresh_token')->value('value');
            $gdriveFolderId = Pengaturan::where('key', 'gdrive_folder_id')->value('value');
            $backupRetention = Pengaturan::where('key', 'backup_retention')->value('value');

            if ($gdriveClientId) {
                Config::set('filesystems.disks.google.clientId', $gdriveClientId);
            }
            if ($gdriveClientSecret) {
                Config::set('filesystems.disks.google.clientSecret', $gdriveClientSecret);
            }
            if ($gdriveRefreshToken) {
                Config::set('filesystems.disks.google.refreshToken', $gdriveRefreshToken);
            }
            if ($gdriveFolderId) {
                Config::set('filesystems.disks.google.folderId', $gdriveFolderId);
            }
            if ($backupRetention) {
                Config::set('backup.cleanup.default_strategy.keep_all_backups_for_days', $backupRetention);
                Config::set('backup.cleanup.default_strategy.keep_daily_backups_for_days', 0);
                Config::set('backup.cleanup.default_strategy.keep_weekly_backups_for_weeks', 0);
                Config::set('backup.cleanup.default_strategy.keep_monthly_backups_for_months', 0);
                Config::set('backup.cleanup.default_strategy.keep_yearly_backups_for_years', 0);
            }
        } catch (\Exception $e) {
            // Ignore during migrations or if table doesn't exist yet
        }
    }
}
