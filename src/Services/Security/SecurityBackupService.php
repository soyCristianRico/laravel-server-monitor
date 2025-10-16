<?php

namespace SoyCristianRico\LaravelServerMonitor\Services\Security;

use Illuminate\Support\Facades\Log;

class SecurityBackupService
{
    public function backupCrontabs(): array
    {
        $backupDir = $this->getBackupDirectory();
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupPath = "$backupDir/$timestamp";

        if (! file_exists($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $backupResults = [];

        // Backup user crontabs
        $users = shell_exec('ls /var/spool/cron/crontabs 2>/dev/null');
        if (! empty($users)) {
            foreach (explode("\n", trim($users)) as $user) {
                if (empty($user)) {
                    continue;
                }

                $userCrontab = shell_exec("crontab -u $user -l 2>/dev/null");
                if (! empty($userCrontab)) {
                    $filename = "$backupPath/user-$user.cron";
                    file_put_contents($filename, $userCrontab);
                    chmod($filename, 0600);
                    $backupResults[] = "Backed up crontab for user: $user";
                }
            }
        }

        // Backup system cron directories
        $systemCronDirs = [
            '/etc/crontab',
            '/etc/cron.d',
            '/etc/cron.daily',
            '/etc/cron.hourly',
            '/etc/cron.weekly',
            '/etc/cron.monthly',
        ];

        foreach ($systemCronDirs as $cronItem) {
            if (file_exists($cronItem)) {
                $basename = basename($cronItem);
                if (is_dir($cronItem)) {
                    shell_exec("cp -R $cronItem $backupPath/$basename 2>/dev/null");
                    $backupResults[] = "Backed up directory: $cronItem";
                } else {
                    shell_exec("cp $cronItem $backupPath/$basename 2>/dev/null");
                    chmod("$backupPath/$basename", 0600);
                    $backupResults[] = "Backed up file: $cronItem";
                }
            }
        }

        return $backupResults;
    }

    public function cleanOldBackups(string $directory, int $daysToKeep = 30): int
    {
        $deleted = 0;

        if (! is_dir($directory)) {
            return $deleted;
        }

        $oldBackups = shell_exec("find $directory -type d -mtime +$daysToKeep");
        if (! empty(trim($oldBackups))) {
            foreach (explode("\n", trim($oldBackups)) as $oldBackup) {
                if (! empty($oldBackup) && is_dir($oldBackup)) {
                    shell_exec("rm -rf $oldBackup");
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    public function createSecureBackup(string $source, string $destination): bool
    {
        if (! file_exists($source)) {
            return false;
        }

        $destDir = dirname($destination);
        if (! file_exists($destDir)) {
            mkdir($destDir, 0755, true);
        }

        if (is_dir($source)) {
            $result = shell_exec("cp -R $source $destination 2>&1");
        } else {
            $result = shell_exec("cp $source $destination 2>&1");
        }

        if (file_exists($destination)) {
            if (is_file($destination)) {
                chmod($destination, 0600);
            }

            return true;
        }

        Log::error('Failed to create backup', [
            'source' => $source,
            'destination' => $destination,
            'error' => $result,
        ]);

        return false;
    }

    public function getBackupStats(string $directory): array
    {
        if (! is_dir($directory)) {
            return [
                'total_backups' => 0,
                'total_size' => '0',
                'oldest_backup' => null,
                'newest_backup' => null,
            ];
        }

        $totalBackups = intval(shell_exec("find $directory -type d -maxdepth 1 | wc -l")) - 1;
        $totalSize = shell_exec("du -sh $directory | cut -f1");
        $oldestBackup = shell_exec("ls -t $directory | tail -n 1");
        $newestBackup = shell_exec("ls -t $directory | head -n 1");

        return [
            'total_backups' => $totalBackups,
            'total_size' => trim($totalSize),
            'oldest_backup' => trim($oldestBackup),
            'newest_backup' => trim($newestBackup),
        ];
    }

    protected function getBackupDirectory(): string
    {
        $baseDir = function_exists('storage_path')
            ? storage_path('app/security/crontab-backups')
            : sys_get_temp_dir() . '/laravel_server_monitor_backups';

        if (! file_exists($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        return $baseDir;
    }
}