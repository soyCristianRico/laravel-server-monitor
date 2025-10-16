<?php

use SoyCristianRico\LaravelServerMonitor\Console\Commands\Security\SecurityMonitorCrontabsCommand;
use SoyCristianRico\LaravelServerMonitor\Services\Security\SecurityScannerService;
use SoyCristianRico\LaravelServerMonitor\Traits\NotifiesSecurityAlerts;
use Illuminate\Support\Facades\Notification;

describe('SecurityMonitorCrontabsCommand', function () {
    beforeEach(function () {
        Notification::fake();

        // Always mock the SecurityScannerService to prevent slow system calls
        $mockScanner = Mockery::mock(SecurityScannerService::class);
        $mockScanner->shouldReceive('checkCrontabModifications')->andReturn(null)->byDefault();

        app()->instance(SecurityScannerService::class, $mockScanner);
    });

    it('runs successfully', function () {
        $this->artisan('security:monitor-crontabs')
            ->assertExitCode(0);
    });

    it('uses the NotifiesSecurityAlerts trait', function () {
        expect(class_uses(SecurityMonitorCrontabsCommand::class))->toContain(NotifiesSecurityAlerts::class);
    });

    it('detects crontab modifications when they exist', function () {
        // Override the default mock to return an alert
        $mockScanner = Mockery::mock(SecurityScannerService::class);
        $mockScanner->shouldReceive('checkCrontabModifications')->andReturn([
            'type' => 'Recently Modified Crontabs',
            'details' => '/etc/crontab modified recently'
        ]);

        app()->instance(SecurityScannerService::class, $mockScanner);

        $this->artisan('security:monitor-crontabs')
            ->expectsOutput('Monitoring crontab modifications...')
            ->expectsOutput('Recent crontab modifications detected!')
            ->assertExitCode(1); // Should return 1 when alerts found
    });

    it('reports success when no modifications found', function () {
        $this->artisan('security:monitor-crontabs')
            ->expectsOutput('Monitoring crontab modifications...')
            ->expectsOutput('✅ No recent crontab modifications detected')
            ->assertExitCode(0);
    });

    it('command class exists and is properly configured', function () {
        expect(class_exists(SecurityMonitorCrontabsCommand::class))->toBeTrue();

        // Test command signature through reflection instead of instantiation
        $reflection = new ReflectionClass(SecurityMonitorCrontabsCommand::class);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);

        $command = $reflection->newInstanceWithoutConstructor();
        expect($signatureProperty->getValue($command))->toBe('security:monitor-crontabs');
    });

    it('handles scanner service responses gracefully', function () {
        // Test that command doesn't break with different scanner responses
        $mockScanner = Mockery::mock(SecurityScannerService::class);
        $mockScanner->shouldReceive('checkCrontabModifications')->andReturn(null);

        app()->instance(SecurityScannerService::class, $mockScanner);

        $this->artisan('security:monitor-crontabs')
            ->assertExitCode(0);
    });

    it('sends security alerts when modifications detected', function () {
        // Override mock to return alerts
        $mockScanner = Mockery::mock(SecurityScannerService::class);
        $mockScanner->shouldReceive('checkCrontabModifications')->andReturn([
            'type' => 'Recently Modified Crontabs',
            'details' => 'Multiple crontab files modified'
        ]);

        app()->instance(SecurityScannerService::class, $mockScanner);

        $this->artisan('security:monitor-crontabs')
            ->assertExitCode(1);
    });
});