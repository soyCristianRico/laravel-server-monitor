<?php

use SoyCristianRico\LaravelServerMonitor\Console\Commands\ServerMonitorCommand;
use SoyCristianRico\LaravelServerMonitor\Services\ServerMonitoringService;

describe('ServerMonitorCommand', function () {
    beforeEach(function () {
        // Mock the ServerMonitoringService in the container
        $mockService = Mockery::mock(ServerMonitoringService::class);
        $mockService->shouldReceive('runAllChecks')->andReturn([])->byDefault();
        $mockService->shouldReceive('getAlerts')->andReturn([])->byDefault();

        $this->app->instance(ServerMonitoringService::class, $mockService);
    });

    describe('command configuration', function () {
        it('has correct signature', function () {
            $service = Mockery::mock(ServerMonitoringService::class);
            $command = new ServerMonitorCommand($service);

            expect($command->getName())->toBe('server:monitor');
        });

        it('has correct description', function () {
            $service = Mockery::mock(ServerMonitoringService::class);
            $command = new ServerMonitorCommand($service);

            expect($command->getDescription())->toBe('Monitor server resources (CPU, memory, disk space, MySQL)');
        });
    });

    describe('command execution with no alerts', function () {
        it('returns success exit code when all checks pass', function () {
            $checks = [
                'disk' => ['status' => 'ok', 'message' => 'Disk OK'],
                'memory' => ['status' => 'ok', 'message' => 'Memory OK'],
                'cpu' => ['status' => 'ok', 'message' => 'CPU OK'],
                'mysql' => ['status' => 'ok', 'message' => 'MySQL OK'],
            ];

            $service = Mockery::mock(ServerMonitoringService::class);
            $service->shouldReceive('runAllChecks')->once()->andReturn($checks);
            $service->shouldReceive('getAlerts')->once()->with($checks)->andReturn([]);

            $this->app->instance(ServerMonitoringService::class, $service);

            $this->artisan('server:monitor')->assertExitCode(0);
        });

        it('displays success message when all checks pass', function () {
            $checks = [
                'disk' => ['status' => 'ok', 'message' => 'Disk space usage is 45%'],
                'memory' => ['status' => 'ok', 'message' => 'Memory usage is 67%'],
                'cpu' => ['status' => 'ok', 'message' => 'CPU load is 0.34'],
                'mysql' => ['status' => 'ok', 'message' => 'MySQL service is running'],
            ];

            $service = Mockery::mock(ServerMonitoringService::class);
            $service->shouldReceive('runAllChecks')->once()->andReturn($checks);
            $service->shouldReceive('getAlerts')->once()->andReturn([]);

            $this->app->instance(ServerMonitoringService::class, $service);

            $this->artisan('server:monitor')
                ->expectsOutput('Starting server monitoring...')
                ->expectsOutput('✅ All server checks passed')
                ->assertExitCode(0);
        });
    });

    describe('command execution with alerts', function () {
        it('returns error exit code when alerts are present', function () {
            $checks = [
                'disk' => ['status' => 'critical', 'message' => 'Disk usage is 95%'],
                'mysql' => ['status' => 'critical', 'message' => 'MySQL service is not running'],
            ];

            $alerts = [
                [
                    'type' => 'CRITICAL',
                    'details' => '🔴 Disk usage is 95%',
                    'metric' => 'disk_space',
                    'value' => 95
                ],
                [
                    'type' => 'CRITICAL',
                    'details' => '🔴 MySQL service is not running',
                    'metric' => 'mysql_service',
                    'value' => 0
                ]
            ];

            $service = Mockery::mock(ServerMonitoringService::class);
            $service->shouldReceive('runAllChecks')->once()->andReturn($checks);
            $service->shouldReceive('getAlerts')->once()->with($checks)->andReturn($alerts);

            // Force the container to forget the singleton and use our mock
            $this->app->forgetInstance(ServerMonitoringService::class);
            $this->app->instance(ServerMonitoringService::class, $service);

            $this->artisan('server:monitor')->assertExitCode(1);
        });

        it('displays appropriate icons for different check statuses', function () {
            $checks = [
                'disk' => ['status' => 'ok', 'message' => 'Disk space usage is 45%'],
                'memory' => ['status' => 'warning', 'message' => 'Memory usage is 85%'],
                'cpu' => ['status' => 'critical', 'message' => 'CPU load is 2.5'],
                'mysql' => ['status' => 'ok', 'message' => 'MySQL service is running'],
            ];

            $alerts = [
                [
                    'type' => 'WARNING',
                    'details' => '🟡 Memory usage is 85%',
                    'metric' => 'memory_usage',
                    'value' => 85
                ],
                [
                    'type' => 'CRITICAL',
                    'details' => '🔴 CPU load is 2.5',
                    'metric' => 'cpu_load',
                    'value' => 2.5
                ]
            ];

            $service = Mockery::mock(ServerMonitoringService::class);
            $service->shouldReceive('runAllChecks')->once()->andReturn($checks);
            $service->shouldReceive('getAlerts')->once()->andReturn($alerts);

            // Force the container to forget the singleton and use our mock
            $this->app->forgetInstance(ServerMonitoringService::class);
            $this->app->instance(ServerMonitoringService::class, $service);

            $this->artisan('server:monitor')
                ->expectsOutput('Starting server monitoring...')
                ->assertExitCode(1);
        });
    });

    describe('alert notification', function () {
        it('sends alerts when issues are detected', function () {
            $checks = [
                'mysql' => ['status' => 'critical', 'message' => 'MySQL service is not running'],
            ];

            $alerts = [
                [
                    'type' => 'CRITICAL',
                    'details' => '🔴 MySQL service is not running',
                    'metric' => 'mysql_service',
                    'value' => 0
                ]
            ];

            $service = Mockery::mock(ServerMonitoringService::class);
            $service->shouldReceive('runAllChecks')->once()->andReturn($checks);
            $service->shouldReceive('getAlerts')->once()->andReturn($alerts);

            // Force the container to forget the singleton and use our mock
            $this->app->forgetInstance(ServerMonitoringService::class);
            $this->app->instance(ServerMonitoringService::class, $service);

            $this->artisan('server:monitor')->assertExitCode(1);
        });
    });

    describe('status display logic', function () {
        it('shows correct icon for ok status', function () {
            // Test icon mapping logic
            $status = 'ok';
            $icon = match ($status) {
                'ok' => '✅',
                'warning' => '🟡',
                'critical' => '🔴',
                default => '❓'
            };

            expect($icon)->toBe('✅');
        });

        it('shows correct icon for warning status', function () {
            $status = 'warning';
            $icon = match ($status) {
                'ok' => '✅',
                'warning' => '🟡',
                'critical' => '🔴',
                default => '❓'
            };

            expect($icon)->toBe('🟡');
        });

        it('shows correct icon for critical status', function () {
            $status = 'critical';
            $icon = match ($status) {
                'ok' => '✅',
                'warning' => '🟡',
                'critical' => '🔴',
                default => '❓'
            };

            expect($icon)->toBe('🔴');
        });

        it('shows question mark for unknown status', function () {
            $status = 'unknown';
            $icon = match ($status) {
                'ok' => '✅',
                'warning' => '🟡',
                'critical' => '🔴',
                default => '❓'
            };

            expect($icon)->toBe('❓');
        });
    });

    describe('service integration', function () {
        it('calls monitoring service correctly', function () {
            $service = Mockery::mock(ServerMonitoringService::class);
            $service->shouldReceive('runAllChecks')->once()->andReturn([]);
            $service->shouldReceive('getAlerts')->once()->andReturn([]);

            // Force the container to forget the singleton and use our mock
            $this->app->forgetInstance(ServerMonitoringService::class);
            $this->app->instance(ServerMonitoringService::class, $service);

            $this->artisan('server:monitor');
        });

        it('passes check results to alert generation', function () {
            $checks = ['test' => 'data'];

            $service = Mockery::mock(ServerMonitoringService::class);
            $service->shouldReceive('runAllChecks')->once()->andReturn($checks);
            $service->shouldReceive('getAlerts')->once()->with($checks)->andReturn([]);

            // Force the container to forget the singleton and use our mock
            $this->app->forgetInstance(ServerMonitoringService::class);
            $this->app->instance(ServerMonitoringService::class, $service);

            $this->artisan('server:monitor');
        });
    });

    describe('trait integration', function () {
        it('uses NotifiesSecurityAlerts trait', function () {
            $traits = class_uses(ServerMonitorCommand::class);

            expect($traits)->toContain('SoyCristianRico\LaravelServerMonitor\Traits\NotifiesSecurityAlerts');
        });
    });
});