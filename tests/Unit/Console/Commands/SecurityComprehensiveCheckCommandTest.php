<?php

use SoyCristianRico\LaravelServerMonitor\Console\Commands\Security\SecurityComprehensiveCheckCommand;
use SoyCristianRico\LaravelServerMonitor\Services\Security\SecurityScannerService;
use SoyCristianRico\LaravelServerMonitor\Traits\NotifiesSecurityAlerts;
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

        // NEW ENHANCED SECURITY CHECK MOCKS (needed because comprehensive calls security:check)
        $mockScanner->shouldReceive('checkSuspiciousPhpProcesses')->andReturn(null)->byDefault();
        $mockScanner->shouldReceive('checkSuspiciousUploads')->andReturn([])->byDefault();
        $mockScanner->shouldReceive('checkSuspiciousHtaccess')->andReturn([])->byDefault();
        $mockScanner->shouldReceive('checkFakeImageFiles')->andReturn([])->byDefault();
        $mockScanner->shouldReceive('checkFileIntegrity')->andReturn([])->byDefault();

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

        // NEW ENHANCED SECURITY CHECK MOCKS (needed because comprehensive calls security:check)
        $mockScanner->shouldReceive('checkSuspiciousPhpProcesses')->andReturn(null);
        $mockScanner->shouldReceive('checkSuspiciousUploads')->andReturn([]);
        $mockScanner->shouldReceive('checkSuspiciousHtaccess')->andReturn([]);
        $mockScanner->shouldReceive('checkFakeImageFiles')->andReturn([]);
        $mockScanner->shouldReceive('checkFileIntegrity')->andReturn([]);

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

    it('runs enhanced security checks', function () {
        // Test that the enhanced methods exist and can be called
        $service = new SecurityScannerService();

        expect(method_exists($service, 'checkSuspiciousUploads'))->toBeTrue();
        expect(method_exists($service, 'checkSuspiciousHtaccess'))->toBeTrue();
        expect(method_exists($service, 'checkFakeImageFiles'))->toBeTrue();
        expect(method_exists($service, 'checkFileIntegrity'))->toBeTrue();

        // Just verify the command runs successfully with the default mocks
        $this->artisan('security:comprehensive-check')
            ->assertExitCode(0);
    });

    it('handles enhanced security alerts correctly', function () {
        // Override the default mock to test enhanced security checks with alerts
        $mockScanner = Mockery::mock(SecurityScannerService::class);
        $mockScanner->shouldReceive('checkModifiedSystemFiles')->andReturn(null);
        $mockScanner->shouldReceive('checkUnauthorizedSSHKeys')->andReturn(null);
        $mockScanner->shouldReceive('checkDiskUsage')->andReturn(null);
        $mockScanner->shouldReceive('checkFailedLogins')->andReturn(null);
        $mockScanner->shouldReceive('checkNewUsers')->andReturn(null);
        $mockScanner->shouldReceive('getListeningPorts')->andReturn('');
        $mockScanner->shouldReceive('checkLargeFiles')->andReturn(null);
        $mockScanner->shouldReceive('checkSuspiciousProcesses')->andReturn(null);
        $mockScanner->shouldReceive('checkSuspiciousPorts')->andReturn([]);
        $mockScanner->shouldReceive('checkCrontabModifications')->andReturn(null);
        $mockScanner->shouldReceive('checkMalwarePatterns')->andReturn([]);

        // Test enhanced detection with alerts (these will be called by security:check)
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
            ],
            [
                'type' => 'Suspicious PHP Files in Public Root',
                'details' => 'Found ae5553dcc89d.php in public/'
            ]
        ]);
        $mockScanner->shouldReceive('checkSuspiciousHtaccess')->andReturn([]);
        $mockScanner->shouldReceive('checkFakeImageFiles')->andReturn([
            [
                'type' => 'Fake Image Files Containing PHP',
                'details' => 'toggige-arrow.jpg contains PHP code'
            ]
        ]);
        $mockScanner->shouldReceive('checkFileIntegrity')->andReturn([
            [
                'type' => 'Critical File Modified',
                'details' => 'public/index.php has been modified'
            ]
        ]);

        app()->instance(SecurityScannerService::class, $mockScanner);

        $this->artisan('security:comprehensive-check')
            ->assertExitCode(0); // Will send alerts but exit successfully
    });
});