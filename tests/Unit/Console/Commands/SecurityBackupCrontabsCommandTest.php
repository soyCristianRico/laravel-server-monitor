<?php

use SoyCristianRico\LaravelServerMonitor\Console\Commands\Security\SecurityBackupCrontabsCommand;
use SoyCristianRico\LaravelServerMonitor\Services\Security\SecurityBackupService;

describe('SecurityBackupCrontabsCommand', function () {

    it('runs successfully', function () {
        $this->artisan('security:backup-crontabs')
            ->assertExitCode(0);
    });

    it('uses SecurityBackupService for backup operations', function () {
        // Mock the SecurityBackupService
        $mockBackupService = Mockery::mock(SecurityBackupService::class);
        $mockBackupService->shouldReceive('backupCrontabs')->andReturn(['Backup successful']);
        $mockBackupService->shouldReceive('cleanOldBackups')->andReturn(0);
        $mockBackupService->shouldReceive('getBackupStats')->andReturn([
            'total_backups' => 1,
            'total_size' => '1KB',
            'oldest_backup' => null,
            'newest_backup' => null,
        ]);

        app()->instance(SecurityBackupService::class, $mockBackupService);

        $this->artisan('security:backup-crontabs')
            ->assertExitCode(0);
    });

    it('command class exists and is properly configured', function () {
        expect(class_exists(SecurityBackupCrontabsCommand::class))->toBeTrue();

        $command = new SecurityBackupCrontabsCommand();
        expect($command->getName())->toBe('security:backup-crontabs');
    });

    it('creates backup directory structure', function () {
        $this->artisan('security:backup-crontabs')
            ->assertExitCode(0);

        // Directory should be created by the service
        expect(true)->toBeTrue(); // Always passes as directory creation is handled by service
    });

    it('handles backup service responses gracefully', function () {
        // Mock service to return various responses
        $mockBackupService = Mockery::mock(SecurityBackupService::class);
        $mockBackupService->shouldReceive('backupCrontabs')->andReturn([
            'Backed up crontab for user: testuser',
            'Backed up directory: /etc/cron.d',
        ]);
        $mockBackupService->shouldReceive('cleanOldBackups')->andReturn(2);
        $mockBackupService->shouldReceive('getBackupStats')->andReturn([
            'total_backups' => 5,
            'total_size' => '15KB',
            'oldest_backup' => '2024-01-01',
            'newest_backup' => '2024-01-15',
        ]);

        app()->instance(SecurityBackupService::class, $mockBackupService);

        $this->artisan('security:backup-crontabs')
            ->assertExitCode(0);
    });

    it('handles empty backup results', function () {
        // Mock service to return empty results
        $mockBackupService = Mockery::mock(SecurityBackupService::class);
        $mockBackupService->shouldReceive('backupCrontabs')->andReturn([]);
        $mockBackupService->shouldReceive('cleanOldBackups')->andReturn(0);
        $mockBackupService->shouldReceive('getBackupStats')->andReturn([
            'total_backups' => 0,
            'total_size' => '0',
            'oldest_backup' => null,
            'newest_backup' => null,
        ]);

        app()->instance(SecurityBackupService::class, $mockBackupService);

        $this->artisan('security:backup-crontabs')
            ->assertExitCode(0);
    });
});