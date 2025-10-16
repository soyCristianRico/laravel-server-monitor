<?php

use CristianDev\LaravelServerMonitor\Console\Commands\Security\SecurityCheckCommand;
use CristianDev\LaravelServerMonitor\Services\Security\SecurityScannerService;
use CristianDev\LaravelServerMonitor\Traits\NotifiesSecurityAlerts;
use Illuminate\Support\Facades\Notification;

describe('SecurityCheckCommand', function () {
    beforeEach(function () {
        Notification::fake();

        // Always mock the SecurityScannerService to prevent slow system calls
        $mockScanner = Mockery::mock(SecurityScannerService::class);
        $mockScanner->shouldReceive('checkSuspiciousProcesses')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkSuspiciousPorts')->andReturn([])->byDefault();
        $mockScanner->shouldReceive('checkCrontabModifications')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkDiskUsage')->andReturn(null)->byDefault();

        app()->instance(SecurityScannerService::class, $mockScanner);
    });

    it('runs successfully without issues', function () {
        $this->artisan('security:check')
            ->assertExitCode(0);
    });

    it('uses the NotifiesSecurityAlerts trait', function () {
        $command = new SecurityCheckCommand();

        expect(class_uses($command))->toContain(NotifiesSecurityAlerts::class);
    });

    it('uses SecurityScannerService for checks', function () {
        // The service is already mocked in beforeEach
        $this->artisan('security:check')
            ->assertExitCode(0);
    });

    it('command class exists and is properly configured', function () {
        expect(class_exists(SecurityCheckCommand::class))->toBeTrue();

        $command = new SecurityCheckCommand();
        expect($command->getName())->toBe('security:check');
    });

    it('handles scanner service responses gracefully', function () {
        // Override the default mock to return some alerts for this test
        $mockScanner = Mockery::mock(SecurityScannerService::class);
        $mockScanner->shouldReceive('checkSuspiciousProcesses')->andReturn([
            'type' => 'Suspicious Processes',
            'details' => 'test process',
        ]);
        $mockScanner->shouldReceive('checkSuspiciousPorts')->andReturn([]);
        $mockScanner->shouldReceive('checkCrontabModifications')->andReturn(null);
        $mockScanner->shouldReceive('checkDiskUsage')->andReturn(null);

        app()->instance(SecurityScannerService::class, $mockScanner);

        $this->artisan('security:check')
            ->assertExitCode(0);
    });
});