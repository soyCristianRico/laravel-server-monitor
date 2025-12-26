<?php

use SoyCristianRico\LaravelServerMonitor\Console\Commands\Security\SecurityCheckCommand;
use SoyCristianRico\LaravelServerMonitor\Services\Security\SecurityScannerService;
use SoyCristianRico\LaravelServerMonitor\Traits\NotifiesSecurityAlerts;
use Illuminate\Support\Facades\Notification;

describe('SecurityCheckCommand', function () {
    beforeEach(function () {
        Notification::fake();

        // Always mock the SecurityScannerService to prevent slow system calls
        $mockScanner = Mockery::mock(SecurityScannerService::class);
        $mockScanner->shouldReceive('checkSuspiciousProcesses')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkSuspiciousPorts')->andReturn([])->byDefault();
        $mockScanner->shouldReceive('checkCrontabModifications')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkFailedLogins')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkNewUsers')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkModifiedSystemFiles')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkUnauthorizedSSHKeys')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkLargeFiles')->andReturn(null)->byDefault();

        // NEW ENHANCED SECURITY CHECK MOCKS
        $mockScanner->shouldReceive('checkSuspiciousPhpProcesses')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkSuspiciousUploads')->andReturn([])->byDefault();
        $mockScanner->shouldReceive('checkSuspiciousHtaccess')->andReturn([])->byDefault();
        $mockScanner->shouldReceive('checkFakeImageFiles')->andReturn([])->byDefault();
        $mockScanner->shouldReceive('checkFileIntegrity')->andReturn([])->byDefault();

        app()->instance(SecurityScannerService::class, $mockScanner);
    });

    it('runs successfully without issues', function () {
        $this->artisan('security:check')
            ->assertExitCode(0);
    });

    it('uses the NotifiesSecurityAlerts trait', function () {
        expect(class_uses(SecurityCheckCommand::class))->toContain(NotifiesSecurityAlerts::class);
    });

    it('uses SecurityScannerService for checks', function () {
        // The service is already mocked in beforeEach
        $this->artisan('security:check')
            ->assertExitCode(0);
    });

    it('command class exists and is properly configured', function () {
        expect(class_exists(SecurityCheckCommand::class))->toBeTrue();

        // Test command signature/name through reflection instead of instantiation
        $reflection = new ReflectionClass(SecurityCheckCommand::class);
        $signatureProperty = $reflection->getProperty('signature');
        $signatureProperty->setAccessible(true);

        // Create an instance without constructor to check signature
        $command = $reflection->newInstanceWithoutConstructor();
        expect($signatureProperty->getValue($command))->toBe('security:check');
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
        $mockScanner->shouldReceive('checkFailedLogins')->andReturn(null);
        $mockScanner->shouldReceive('checkNewUsers')->andReturn(null);
        $mockScanner->shouldReceive('checkModifiedSystemFiles')->andReturn(null);
        $mockScanner->shouldReceive('checkUnauthorizedSSHKeys')->andReturn(null);
        $mockScanner->shouldReceive('checkLargeFiles')->andReturn(null);

        // NEW ENHANCED SECURITY CHECK MOCKS
        $mockScanner->shouldReceive('checkSuspiciousPhpProcesses')->andReturn(null);
        $mockScanner->shouldReceive('checkSuspiciousUploads')->andReturn([]);
        $mockScanner->shouldReceive('checkSuspiciousHtaccess')->andReturn([]);
        $mockScanner->shouldReceive('checkFakeImageFiles')->andReturn([]);
        $mockScanner->shouldReceive('checkFileIntegrity')->andReturn([]);

        app()->instance(SecurityScannerService::class, $mockScanner);

        $this->artisan('security:check')
            ->assertExitCode(1); // Should return 1 because we have an alert
    });

    it('handles enhanced security detections correctly', function () {
        // Override the default mock to test enhanced security checks
        $mockScanner = Mockery::mock(SecurityScannerService::class);
        $mockScanner->shouldReceive('checkSuspiciousProcesses')->andReturn(null);
        $mockScanner->shouldReceive('checkSuspiciousPorts')->andReturn([]);
        $mockScanner->shouldReceive('checkCrontabModifications')->andReturn(null);
        $mockScanner->shouldReceive('checkFailedLogins')->andReturn(null);
        $mockScanner->shouldReceive('checkNewUsers')->andReturn(null);
        $mockScanner->shouldReceive('checkModifiedSystemFiles')->andReturn(null);
        $mockScanner->shouldReceive('checkUnauthorizedSSHKeys')->andReturn(null);
        $mockScanner->shouldReceive('checkLargeFiles')->andReturn(null);

        // Test enhanced detection with alerts
        $mockScanner->shouldReceive('checkSuspiciousPhpProcesses')->andReturn([
            [
                'type' => 'Suspicious PHP Processes in /tmp',
                'details' => 'php -f /tmp/httpd.conf detected'
            ]
        ]);
        $mockScanner->shouldReceive('checkSuspiciousUploads')->andReturn([
            [
                'type' => 'PHP Files in Storage Directory',
                'details' => 'Found malicious.php in storage/app/public'
            ]
        ]);
        $mockScanner->shouldReceive('checkSuspiciousHtaccess')->andReturn([
            [
                'type' => 'Suspicious .htaccess Files',
                'details' => 'Found .htaccess in public/css/'
            ]
        ]);
        $mockScanner->shouldReceive('checkFakeImageFiles')->andReturn([]);
        $mockScanner->shouldReceive('checkFileIntegrity')->andReturn([]);

        app()->instance(SecurityScannerService::class, $mockScanner);

        $this->artisan('security:check')
            ->assertExitCode(1); // Should return 1 because we have alerts
    });

    it('runs all enhanced security checks', function () {
        // Test that all new methods are called
        $mockScanner = Mockery::mock(SecurityScannerService::class);
        $mockScanner->shouldReceive('checkSuspiciousProcesses')->once()->andReturn(null);
        $mockScanner->shouldReceive('checkSuspiciousPorts')->once()->andReturn([]);
        $mockScanner->shouldReceive('checkCrontabModifications')->once()->andReturn(null);
        $mockScanner->shouldReceive('checkFailedLogins')->once()->andReturn(null);
        $mockScanner->shouldReceive('checkNewUsers')->once()->andReturn(null);
        $mockScanner->shouldReceive('checkModifiedSystemFiles')->once()->andReturn(null);
        $mockScanner->shouldReceive('checkUnauthorizedSSHKeys')->once()->andReturn(null);
        $mockScanner->shouldReceive('checkLargeFiles')->once()->andReturn(null);

        // Ensure new enhanced methods are called
        $mockScanner->shouldReceive('checkSuspiciousPhpProcesses')->once()->andReturn(null);
        $mockScanner->shouldReceive('checkSuspiciousUploads')->once()->andReturn([]);
        $mockScanner->shouldReceive('checkSuspiciousHtaccess')->once()->andReturn([]);
        $mockScanner->shouldReceive('checkFakeImageFiles')->once()->andReturn([]);
        $mockScanner->shouldReceive('checkFileIntegrity')->once()->andReturn([]);

        app()->instance(SecurityScannerService::class, $mockScanner);

        $this->artisan('security:check')
            ->assertExitCode(0);
    });
});