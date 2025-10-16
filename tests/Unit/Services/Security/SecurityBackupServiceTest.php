<?php

use CristianDev\LaravelServerMonitor\Services\Security\SecurityBackupService;

describe('SecurityBackupService', function () {
    describe('backupCrontabs', function () {
        it('returns array of backup results', function () {
            $service = app(SecurityBackupService::class);
            $results = $service->backupCrontabs();

            expect($results)->toBeArray();

            foreach ($results as $result) {
                expect($result)->toBeString();
            }
        });
    });

    describe('cleanOldBackups', function () {
        it('returns number of deleted backups', function () {
            $service = app(SecurityBackupService::class);
            $testDir = sys_get_temp_dir().'/test-backups-'.uniqid();

            // Create test directory
            if (! is_dir($testDir)) {
                mkdir($testDir, 0755, true);
            }

            // Create some old test directories
            $oldDir = $testDir.'/old-backup-'.uniqid();
            mkdir($oldDir, 0755, true);
            touch($oldDir, strtotime('-35 days'));

            $deletedCount = $service->cleanOldBackups($testDir, 30);

            expect($deletedCount)->toBeInt();
            expect($deletedCount)->toBeGreaterThanOrEqual(0);

            // Cleanup
            if (is_dir($testDir)) {
                shell_exec('rm -rf '.escapeshellarg($testDir));
            }
        });

        it('handles non-existent directory gracefully', function () {
            $service = app(SecurityBackupService::class);
            $nonExistentDir = '/path/that/does/not/exist';

            $result = $service->cleanOldBackups($nonExistentDir);

            expect($result)->toBe(0);
        });
    });

    describe('getBackupStats', function () {
        it('returns correct stats structure for existing directory', function () {
            $service = app(SecurityBackupService::class);
            $testDir = sys_get_temp_dir().'/test-stats-'.uniqid();

            // Create test directory with some content
            if (! is_dir($testDir)) {
                mkdir($testDir, 0755, true);
            }

            $backupDir = $testDir.'/backup1';
            mkdir($backupDir, 0755, true);

            $stats = $service->getBackupStats($testDir);

            expect($stats)->toBeArray();
            expect($stats)->toHaveKeys(['total_backups', 'total_size', 'oldest_backup', 'newest_backup']);
            expect($stats['total_backups'])->toBeInt();
            expect($stats['total_size'])->toBeString();

            // Cleanup
            if (is_dir($testDir)) {
                shell_exec('rm -rf '.escapeshellarg($testDir));
            }
        });

        it('returns zero stats for non-existent directory', function () {
            $service = app(SecurityBackupService::class);
            $nonExistentDir = '/path/that/does/not/exist';

            $stats = $service->getBackupStats($nonExistentDir);

            expect($stats)->toBe([
                'total_backups' => 0,
                'total_size' => '0',
                'oldest_backup' => null,
                'newest_backup' => null,
            ]);
        });
    });

    describe('createSecureBackup', function () {
        it('returns false for non-existent source', function () {
            $service = app(SecurityBackupService::class);
            $nonExistentSource = '/path/that/does/not/exist';
            $destination = sys_get_temp_dir().'/test-backup';

            $result = $service->createSecureBackup($nonExistentSource, $destination);

            expect($result)->toBeFalse();
        });
    });
});