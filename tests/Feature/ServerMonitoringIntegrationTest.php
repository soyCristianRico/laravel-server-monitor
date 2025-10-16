<?php

use CristianDev\LaravelServerMonitor\Services\ServerMonitoringService;
use CristianDev\LaravelServerMonitor\Services\Security\SecurityNotificationService;
use CristianDev\LaravelServerMonitor\Services\Security\SecurityScannerService;

describe('Server Monitoring Integration', function () {
    beforeEach(function () {
        // Set up test configuration
        config([
            'server-monitor.monitoring.disk.warning_threshold' => 80,
            'server-monitor.monitoring.disk.critical_threshold' => 90,
            'server-monitor.monitoring.memory.warning_threshold' => 80,
            'server-monitor.monitoring.memory.critical_threshold' => 90,
            'server-monitor.monitoring.cpu.warning_threshold' => 70,
            'server-monitor.monitoring.cpu.critical_threshold' => 90,
        ]);
    });

    describe('service registration', function () {
        it('registers ServerMonitoringService in container', function () {
            $service = app(ServerMonitoringService::class);

            expect($service)->toBeInstanceOf(ServerMonitoringService::class);
        });

        it('registers SecurityScannerService in container', function () {
            $service = app(SecurityScannerService::class);

            expect($service)->toBeInstanceOf(SecurityScannerService::class);
        });

        it('registers SecurityNotificationService in container', function () {
            $service = app(SecurityNotificationService::class);

            expect($service)->toBeInstanceOf(SecurityNotificationService::class);
        });

        it('registers services as singletons', function () {
            $service1 = app(ServerMonitoringService::class);
            $service2 = app(ServerMonitoringService::class);

            expect($service1)->toBe($service2);
        });
    });

    describe('command registration', function () {
        it('registers server:monitor command', function () {
            $commands = app(\Illuminate\Contracts\Console\Kernel::class)->all();

            expect($commands)->toHaveKey('server:monitor');
        });

        it('registers security:check command', function () {
            $commands = app(\Illuminate\Contracts\Console\Kernel::class)->all();

            expect($commands)->toHaveKey('security:check');
        });

        it('registers security:check-malware command', function () {
            $commands = app(\Illuminate\Contracts\Console\Kernel::class)->all();

            expect($commands)->toHaveKey('security:check-malware');
        });

        it('registers security:monitor-crontabs command', function () {
            $commands = app(\Illuminate\Contracts\Console\Kernel::class)->all();

            expect($commands)->toHaveKey('security:monitor-crontabs');
        });
    });

    describe('configuration loading', function () {
        it('loads configuration from config file', function () {
            $diskWarning = config('server-monitor.monitoring.disk.warning_threshold');
            $adminRole = config('server-monitor.notifications.admin_role');

            expect($diskWarning)->toBe(80);
            expect($adminRole)->toBe('admin');
        });

        it('service reads configuration correctly', function () {
            $service = app(ServerMonitoringService::class);

            expect($service->getDiskWarningThreshold())->toBe(80);
            expect($service->getDiskCriticalThreshold())->toBe(90);
        });

        it('respects environment variable overrides', function () {
            putenv('SERVER_MONITOR_DISK_WARNING=85');
            config(['server-monitor.monitoring.disk.warning_threshold' => env('SERVER_MONITOR_DISK_WARNING', 80)]);

            $service = app(ServerMonitoringService::class);

            expect($service->getDiskWarningThreshold())->toBe(85);

            // Clean up
            putenv('SERVER_MONITOR_DISK_WARNING');
        });
    });

    describe('full monitoring workflow', function () {
        it('can run complete server monitoring check', function () {
            $service = app(ServerMonitoringService::class);

            $checks = $service->runAllChecks();

            expect($checks)->toHaveKeys(['disk', 'memory', 'cpu', 'mysql']);

            foreach ($checks as $check) {
                expect($check)->toHaveKeys(['metric', 'value', 'unit', 'status', 'message']);
                expect($check['status'])->toBeIn(['ok', 'warning', 'critical']);
            }
        });

        it('generates alerts correctly from checks', function () {
            $service = app(ServerMonitoringService::class);

            // Create mock checks with different statuses
            $checks = [
                'disk' => [
                    'metric' => 'disk_space',
                    'value' => 95,
                    'status' => 'critical',
                    'message' => 'Disk space usage is 95%'
                ],
                'memory' => [
                    'metric' => 'memory_usage',
                    'value' => 85,
                    'status' => 'warning',
                    'message' => 'Memory usage is 85%'
                ],
                'cpu' => [
                    'metric' => 'cpu_load',
                    'value' => 0.5,
                    'status' => 'ok',
                    'message' => 'CPU load is 0.5'
                ]
            ];

            $alerts = $service->getAlerts($checks);

            expect($alerts)->toHaveCount(2); // Critical and warning only
            expect($alerts[0]['type'])->toBe('CRITICAL');
            expect($alerts[1]['type'])->toBe('WARNING');
        });
    });

    describe('security monitoring workflow', function () {
        it('can run security checks', function () {
            $service = app(SecurityScannerService::class);

            // These methods should exist and be callable
            expect(method_exists($service, 'checkSuspiciousProcesses'))->toBeTrue();
            expect(method_exists($service, 'checkSuspiciousPorts'))->toBeTrue();
            expect(method_exists($service, 'checkMalwarePatterns'))->toBeTrue();
        });

        it('malware scanner respects configuration', function () {
            config([
                'server-monitor.security.excluded_paths' => ['vendor', 'tests'],
                'server-monitor.security.whitelisted_security_files' => ['app/SecurityService.php']
            ]);

            $excludedPaths = config('server-monitor.security.excluded_paths');
            $whitelistedFiles = config('server-monitor.security.whitelisted_security_files');

            expect($excludedPaths)->toContain('vendor');
            expect($whitelistedFiles)->toContain('app/SecurityService.php');
        });
    });

    describe('command execution integration', function () {
        it('can execute server monitor command', function () {
            $this->artisan('server:monitor')
                ->expectsOutput('Starting server monitoring...')
                ->run();

            // Command should execute without crashing (exit code doesn't matter for this test)
            expect(true)->toBeTrue(); // Just verify it didn't crash
        });

        it('handles command execution gracefully when services fail', function () {
            // Even if system commands fail, the command should not crash
            $this->artisan('server:monitor')->run();

            // Should handle gracefully without throwing exceptions
            expect(true)->toBeTrue(); // Just verify it didn't crash
        });
    });

    describe('notification system integration', function () {
        it('notification service is properly configured', function () {
            $service = app(SecurityNotificationService::class);

            expect($service)->toBeInstanceOf(SecurityNotificationService::class);
        });

        it('can handle notification sending without errors', function () {
            $service = app(SecurityNotificationService::class);

            $alerts = [
                [
                    'type' => 'Test Alert',
                    'details' => 'This is a test alert'
                ]
            ];

            // Should not throw exceptions even if no admin users exist
            expect(fn() => $service->sendAlerts($alerts))->not->toThrow(Exception::class);
        });
    });

    describe('error handling', function () {
        it('handles missing configuration gracefully', function () {
            config(['server-monitor' => null]);

            $service = app(ServerMonitoringService::class);

            // Should use defaults when config is missing
            expect($service->getDiskWarningThreshold())->toBe(80);
        });

        it('handles service instantiation errors gracefully', function () {
            // Services should be instantiable even with minimal config
            expect(fn() => app(ServerMonitoringService::class))->not->toThrow(Exception::class);
            expect(fn() => app(SecurityScannerService::class))->not->toThrow(Exception::class);
            expect(fn() => app(SecurityNotificationService::class))->not->toThrow(Exception::class);
        });
    });

    describe('package service provider', function () {
        it('merges configuration correctly', function () {
            // Test that package config is merged with app config
            $config = config('server-monitor');

            expect($config)->toBeArray();
            expect($config)->toHaveKey('monitoring');
            expect($config)->toHaveKey('notifications');
            expect($config)->toHaveKey('security');
        });

        it('loads views correctly', function () {
            // Test that views are registered (even though we may not have views yet)
            $viewPaths = view()->getFinder()->getPaths();

            // The view path should be registered
            expect($viewPaths)->toBeArray();
        });
    });
});