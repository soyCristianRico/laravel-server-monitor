<?php

use CristianDev\LaravelServerMonitor\Console\Commands\Security\SecurityMonitorCrontabsCommand;
use CristianDev\LaravelServerMonitor\Traits\NotifiesSecurityAlerts;
use Illuminate\Support\Facades\Notification;

describe('SecurityMonitorCrontabsCommand', function () {
    beforeEach(function () {
        Notification::fake();
    });

    it('runs successfully', function () {
        $this->artisan('security:monitor-crontabs')
            ->assertExitCode(0);
    });

    it('uses the NotifiesSecurityAlerts trait', function () {
        $command = new SecurityMonitorCrontabsCommand();

        expect(class_uses($command))->toContain(NotifiesSecurityAlerts::class);
    });

    it('creates marker file if not exists', function () {
        // Remove marker file if exists to test initial creation
        $markerFile = '/tmp/cron-check';
        if (file_exists($markerFile)) {
            unlink($markerFile);
        }

        // Command should create marker file and exit
        $this->artisan('security:monitor-crontabs')
            ->assertExitCode(0);

        // Marker file should now exist
        expect(file_exists($markerFile))->toBeTrue();
    });

    it('command class exists and is properly configured', function () {
        expect(class_exists(SecurityMonitorCrontabsCommand::class))->toBeTrue();

        $command = new SecurityMonitorCrontabsCommand();
        expect($command->getName())->toBe('security:monitor-crontabs');
    });

    it('handles file system operations safely', function () {
        // Test that command doesn't break with file system operations
        $this->artisan('security:monitor-crontabs')
            ->assertExitCode(0);
    });

    it('updates marker file timestamp on each run', function () {
        $markerFile = '/tmp/cron-check';

        // Ensure marker file exists
        if (! file_exists($markerFile)) {
            touch($markerFile);
        }

        $originalTime = filemtime($markerFile);

        // Wait a moment to ensure timestamp difference
        sleep(1);

        $this->artisan('security:monitor-crontabs')
            ->assertExitCode(0);

        // Marker file timestamp should be updated
        expect(filemtime($markerFile))->toBeGreaterThan($originalTime);
    });

    it('handles crontab change detection', function () {
        // Ensure marker file exists for comparison
        $markerFile = '/tmp/cron-check';
        if (! file_exists($markerFile)) {
            touch($markerFile);
        }

        $this->artisan('security:monitor-crontabs')
            ->assertExitCode(0);

        // Command should complete regardless of crontab changes found
    });
});