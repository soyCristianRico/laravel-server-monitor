<?php

namespace CristianDev\LaravelServerMonitor\Console\Commands\Security;

use CristianDev\LaravelServerMonitor\Services\Security\SecurityBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SecurityBackupCrontabsCommand extends Command
{
    protected $signature = 'security:backup-crontabs';

    protected $description = 'Backup all crontab files for security audit trail';

    public function handle(): int
    {
        $backupService = app(SecurityBackupService::class);

        $this->info('Starting crontab backup...');

        // Perform backup
        $backupResults = $backupService->backupCrontabs();

        // Display results
        foreach ($backupResults as $result) {
            $this->info($result);
        }

        // Clean old backups
        $backupDir = function_exists('storage_path')
            ? storage_path('app/security/crontab-backups')
            : sys_get_temp_dir() . '/laravel_server_monitor_backups';

        $deletedCount = $backupService->cleanOldBackups($backupDir);

        if ($deletedCount > 0) {
            $this->info("Removed $deletedCount old backup directories");
        }

        // Get backup statistics
        $stats = $backupService->getBackupStats($backupDir);

        // Log the backup
        Log::info('Crontab backup completed', [
            'backup_count' => count($backupResults),
            'backup_location' => $backupDir,
            'deleted_old' => $deletedCount,
            'stats' => $stats,
        ]);

        $this->info("Backup completed! {$stats['total_backups']} total backups, using {$stats['total_size']} of disk space.");

        return 0;
    }
}