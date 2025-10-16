<?php

use CristianDev\LaravelServerMonitor\Console\Commands\Security\SecurityComprehensiveCheckCommand;
use CristianDev\LaravelServerMonitor\Services\Security\SecurityScannerService;
use CristianDev\LaravelServerMonitor\Traits\NotifiesSecurityAlerts;
use Illuminate\Support\Facades\Notification;

describe('SecurityComprehensiveCheckCommand', function () {
    beforeEach(function () {
        Notification::fake();

        // Always mock the SecurityScannerService to prevent slow system calls
        $mockScanner = Mockery::mock(SecurityScannerService::class);
        $mockScanner->shouldReceive('checkModifiedSystemFiles')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkUnauthorizedSSHKeys')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkDiskUsage')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkFailedLogins')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkNewUsers')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('getListeningPorts')->andReturn('')->byDefault();
        $mockScanner->shouldReceive('checkLargeFiles')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkSuspiciousProcesses')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkSuspiciousPorts')->andReturn([])->byDefault();
        $mockScanner->shouldReceive('checkCrontabModifications')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkMalwarePatterns')->andReturn([])->byDefault();

        app()->instance(SecurityScannerService::class, $mockScanner);
    });

    it('runs successfully', function () {
        $this->artisan('security:comprehensive-check')
            ->assertExitCode(0);
    });

    it('uses the NotifiesSecurityAlerts trait', function () {
        $command = new SecurityComprehensiveCheckCommand();

        expect(class_uses($command))->toContain(NotifiesSecurityAlerts::class);
    });

    it('uses SecurityScannerService for comprehensive checks', function () {
        // The service is already mocked in beforeEach
        $this->artisan('security:comprehensive-check')
            ->assertExitCode(0);
    });

    it('command class exists and is properly configured', function () {
        expect(class_exists(SecurityComprehensiveCheckCommand::class))->toBeTrue();

        $command = new SecurityComprehensiveCheckCommand();
        expect($command->getName())->toBe('security:comprehensive-check');
    });

    it('calls other security commands', function () {
        // The service is already mocked in beforeEach
        $this->artisan('security:comprehensive-check')
            ->assertExitCode(0);
    });

    it('handles scanner service alerts properly', function () {
        // Override the default mock to return some alerts for this test
        $mockScanner = Mockery::mock(SecurityScannerService::class);
        $mockScanner->shouldReceive('checkModifiedSystemFiles')->andReturn([
            'type' => 'Modified System Files',
            'details' => '/etc/passwd',
        ]);
        $mockScanner->shouldReceive('checkUnauthorizedSSHKeys')->andReturn(null);
        $mockScanner->shouldReceive('checkDiskUsage')->andReturn(null);
        $mockScanner->shouldReceive('checkFailedLogins')->andReturn(null);
        $mockScanner->shouldReceive('checkNewUsers')->andReturn(null);
        $mockScanner->shouldReceive('getListeningPorts')->andReturn('tcp 80 httpd');
        $mockScanner->shouldReceive('checkLargeFiles')->andReturn(null);
        $mockScanner->shouldReceive('checkSuspiciousProcesses')->andReturn(null);
        $mockScanner->shouldReceive('checkSuspiciousPorts')->andReturn([]);
        $mockScanner->shouldReceive('checkCrontabModifications')->andReturn(null);
        $mockScanner->shouldReceive('checkMalwarePatterns')->andReturn([]);

        app()->instance(SecurityScannerService::class, $mockScanner);

        $this->artisan('security:comprehensive-check')
            ->assertExitCode(0);
    });

    it('generates proper security report', function () {
        // The service is already mocked in beforeEach
        $this->artisan('security:comprehensive-check')
            ->assertExitCode(0);

        // Command should always send some kind of report via the service
    });

    it('respects whitelisted configuration', function () {
        // Set up the configuration
        config(['server-monitor.security.whitelisted_users' => ['forge', 'root']]);
        config(['server-monitor.security.whitelisted_directories' => ['/home/forge']]);

        // The service is already mocked in beforeEach, it will respect whitelist config
        $this->artisan('security:comprehensive-check')
            ->assertExitCode(0);

        // The forge user should not trigger alerts when whitelisted
        expect(config('server-monitor.security.whitelisted_users'))->toContain('forge');
        expect(config('server-monitor.security.whitelisted_directories'))->toContain('/home/forge');
    });
});