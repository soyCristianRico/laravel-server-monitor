<?php

use CristianDev\LaravelServerMonitor\Services\Security\SecurityBackupService;

describe('SecurityBackupService', function () {
    beforeEach(function () {
        $this->service = new SecurityBackupService();
    });

    describe('backupCrontabs', function () {
        it('returns array of backup results', function () {
            $results = $this->service->backupCrontabs();

            expect($results)->toBeArray();

            foreach ($results as $result) {
                expect($result)->toBeString();
            }
        });
    });

    describe('cleanOldBackups', function () {
        it('returns number of deleted backups', function () {
            $testDir = sys_get_temp_dir().'/test-backups-'.uniqid();

            // Create test directory
            if (! is_dir($testDir)) {
                mkdir($testDir, 0755, true);
            }

            // Create some old test directories
            $oldDir = $testDir.'/old-backup-'.uniqid();
            mkdir($oldDir, 0755, true);
            touch($oldDir, strtotime('-35 days'));

            $deletedCount = $this->service->cleanOldBackups($testDir, 30);

            expect($deletedCount)->toBeInt();
            expect($deletedCount)->toBeGreaterThanOrEqual(0);

            // Cleanup
            if (is_dir($testDir)) {
                shell_exec('rm -rf '.escapeshellarg($testDir));
            }
        });

        it('handles non-existent directory gracefully', function () {
            $nonExistentDir = '/path/that/does/not/exist';

            $result = $this->service->cleanOldBackups($nonExistentDir);

            expect($result)->toBe(0);
        });
    });

    describe('getBackupStats', function () {
        it('returns correct stats structure for existing directory', function () {
            $testDir = sys_get_temp_dir().'/test-stats-'.uniqid();

            // Create test directory with some content
            if (! is_dir($testDir)) {
                mkdir($testDir, 0755, true);
            }

            $backupDir = $testDir.'/backup1';
            mkdir($backupDir, 0755, true);

            $stats = $this->service->getBackupStats($testDir);

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
            $nonExistentDir = '/path/that/does/not/exist';

            $stats = $this->service->getBackupStats($nonExistentDir);

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
            $nonExistentSource = '/path/that/does/not/exist';
            $destination = sys_get_temp_dir().'/test-backup';

            $result = $this->service->createSecureBackup($nonExistentSource, $destination);

            expect($result)->toBeFalse();
        });
    });
});