<?php

use CristianDev\LaravelServerMonitor\Services\ServerMonitoringService;

describe('ServerMonitoringService', function () {
    beforeEach(function () {
        $this->service = new ServerMonitoringService();
    });

    describe('threshold configuration', function () {
        it('returns correct disk warning threshold from config', function () {
            config(['server-monitor.monitoring.disk.warning_threshold' => 85]);

            expect($this->service->getDiskWarningThreshold())->toBe(85);
        });

        it('returns default disk warning threshold when config is missing', function () {
            config(['server-monitor.monitoring.disk.warning_threshold' => null]);

            expect($this->service->getDiskWarningThreshold())->toBe(80);
        });

        it('returns correct memory thresholds from config', function () {
            config([
                'server-monitor.monitoring.memory.warning_threshold' => 75,
                'server-monitor.monitoring.memory.critical_threshold' => 85
            ]);

            expect($this->service->getMemoryWarningThreshold())->toBe(75);
            expect($this->service->getMemoryCriticalThreshold())->toBe(85);
        });

        it('returns correct cpu thresholds from config', function () {
            config([
                'server-monitor.monitoring.cpu.warning_threshold' => 60,
                'server-monitor.monitoring.cpu.critical_threshold' => 80
            ]);

            expect($this->service->getCpuWarningThreshold())->toBe(60);
            expect($this->service->getCpuCriticalThreshold())->toBe(80);
        });
    });

    describe('disk space monitoring', function () {
        it('returns ok status when disk usage is below warning threshold', function () {
            $service = Mockery::mock(ServerMonitoringService::class)->makePartial();
            $service->shouldReceive('getDiskUsage')->andReturn(50);
            $service->shouldReceive('getDiskWarningThreshold')->andReturn(80);
            $service->shouldReceive('getDiskCriticalThreshold')->andReturn(90);

            $result = $service->checkDiskSpace();

            expect($result)->toMatchArray([
                'metric' => 'disk_space',
                'value' => 50,
                'unit' => '%',
                'status' => 'ok',
                'message' => 'Disk space usage is 50%'
            ]);
        });

        it('returns warning status when disk usage exceeds warning threshold', function () {
            $service = Mockery::mock(ServerMonitoringService::class)->makePartial();
            $service->shouldReceive('getDiskUsage')->andReturn(85);
            $service->shouldReceive('getDiskWarningThreshold')->andReturn(80);
            $service->shouldReceive('getDiskCriticalThreshold')->andReturn(90);

            $result = $service->checkDiskSpace();

            expect($result['status'])->toBe('warning');
            expect($result['value'])->toBe(85);
        });

        it('returns critical status when disk usage exceeds critical threshold', function () {
            $service = Mockery::mock(ServerMonitoringService::class)->makePartial();
            $service->shouldReceive('getDiskUsage')->andReturn(95);
            $service->shouldReceive('getDiskWarningThreshold')->andReturn(80);
            $service->shouldReceive('getDiskCriticalThreshold')->andReturn(90);

            $result = $service->checkDiskSpace();

            expect($result['status'])->toBe('critical');
            expect($result['value'])->toBe(95);
        });
    });

    describe('memory monitoring', function () {
        it('returns correct memory usage check structure', function () {
            $service = Mockery::mock(ServerMonitoringService::class)->makePartial();
            $service->shouldReceive('getMemoryUsage')->andReturn(65);
            $service->shouldReceive('getMemoryWarningThreshold')->andReturn(80);
            $service->shouldReceive('getMemoryCriticalThreshold')->andReturn(90);

            $result = $service->checkMemoryUsage();

            expect($result)->toHaveKeys(['metric', 'value', 'unit', 'status', 'message']);
            expect($result['metric'])->toBe('memory_usage');
            expect($result['unit'])->toBe('%');
        });

        it('returns warning status for high memory usage', function () {
            $service = Mockery::mock(ServerMonitoringService::class)->makePartial();
            $service->shouldReceive('getMemoryUsage')->andReturn(85);
            $service->shouldReceive('getMemoryWarningThreshold')->andReturn(80);
            $service->shouldReceive('getMemoryCriticalThreshold')->andReturn(90);

            $result = $service->checkMemoryUsage();

            expect($result['status'])->toBe('warning');
        });
    });

    describe('cpu load monitoring', function () {
        it('returns correct cpu load check structure', function () {
            $service = Mockery::mock(ServerMonitoringService::class)->makePartial();
            $service->shouldReceive('getCpuLoad')->andReturn(0.45);
            $service->shouldReceive('getCpuWarningThreshold')->andReturn(70);
            $service->shouldReceive('getCpuCriticalThreshold')->andReturn(90);

            $result = $service->checkCpuLoad();

            expect($result)->toMatchArray([
                'metric' => 'cpu_load',
                'value' => 0.45,
                'unit' => '',
                'status' => 'ok',
                'message' => 'CPU load is 0.45'
            ]);
        });

        it('returns critical status for very high cpu load', function () {
            $service = Mockery::mock(ServerMonitoringService::class)->makePartial();
            $service->shouldReceive('getCpuLoad')->andReturn(95.0);
            $service->shouldReceive('getCpuWarningThreshold')->andReturn(70);
            $service->shouldReceive('getCpuCriticalThreshold')->andReturn(90);

            $result = $service->checkCpuLoad();

            expect($result['status'])->toBe('critical');
        });
    });

    describe('mysql service monitoring', function () {
        it('returns ok status when mysql is running', function () {
            $service = Mockery::mock(ServerMonitoringService::class)->makePartial();
            $service->shouldReceive('getMysqlStatus')->andReturn(true);

            $result = $service->checkMysqlService();

            expect($result)->toMatchArray([
                'metric' => 'mysql_service',
                'value' => 1,
                'unit' => '',
                'status' => 'ok',
                'message' => 'MySQL service is running'
            ]);
        });

        it('returns critical status when mysql is not running', function () {
            $service = Mockery::mock(ServerMonitoringService::class)->makePartial();
            $service->shouldReceive('getMysqlStatus')->andReturn(false);

            $result = $service->checkMysqlService();

            expect($result)->toMatchArray([
                'metric' => 'mysql_service',
                'value' => 0,
                'unit' => '',
                'status' => 'critical',
                'message' => 'MySQL service is not running'
            ]);
        });
    });

    describe('alert generation', function () {
        it('returns empty array when all checks are ok', function () {
            $checks = [
                'disk' => ['status' => 'ok', 'message' => 'Disk OK'],
                'memory' => ['status' => 'ok', 'message' => 'Memory OK'],
                'cpu' => ['status' => 'ok', 'message' => 'CPU OK'],
                'mysql' => ['status' => 'ok', 'message' => 'MySQL OK'],
            ];

            $alerts = $this->service->getAlerts($checks);

            expect($alerts)->toBeEmpty();
        });

        it('returns warning alerts for warning status checks', function () {
            $checks = [
                'disk' => [
                    'status' => 'warning',
                    'message' => 'Disk usage high',
                    'metric' => 'disk_space',
                    'value' => 85
                ],
            ];

            $alerts = $this->service->getAlerts($checks);

            expect($alerts)->toHaveCount(1);
            expect($alerts[0])->toMatchArray([
                'type' => 'WARNING',
                'details' => '🟡 Disk usage high',
                'metric' => 'disk_space',
                'value' => 85
            ]);
        });

        it('returns critical alerts for critical status checks', function () {
            $checks = [
                'mysql' => [
                    'status' => 'critical',
                    'message' => 'MySQL not running',
                    'metric' => 'mysql_service',
                    'value' => 0
                ],
            ];

            $alerts = $this->service->getAlerts($checks);

            expect($alerts)->toHaveCount(1);
            expect($alerts[0])->toMatchArray([
                'type' => 'CRITICAL',
                'details' => '🔴 MySQL not running',
                'metric' => 'mysql_service',
                'value' => 0
            ]);
        });

        it('returns multiple alerts for multiple issues', function () {
            $checks = [
                'disk' => [
                    'status' => 'warning',
                    'message' => 'Disk high',
                    'metric' => 'disk_space',
                    'value' => 85
                ],
                'mysql' => [
                    'status' => 'critical',
                    'message' => 'MySQL down',
                    'metric' => 'mysql_service',
                    'value' => 0
                ],
            ];

            $alerts = $this->service->getAlerts($checks);

            expect($alerts)->toHaveCount(2);
            expect($alerts[0]['type'])->toBe('CRITICAL');
            expect($alerts[1]['type'])->toBe('WARNING');
        });
    });

    describe('complete monitoring workflow', function () {
        it('runs all checks and returns proper structure', function () {
            $service = Mockery::mock(ServerMonitoringService::class)->makePartial();
            $service->shouldReceive('checkDiskSpace')->andReturn(['status' => 'ok']);
            $service->shouldReceive('checkMemoryUsage')->andReturn(['status' => 'ok']);
            $service->shouldReceive('checkCpuLoad')->andReturn(['status' => 'ok']);
            $service->shouldReceive('checkMysqlService')->andReturn(['status' => 'ok']);

            $checks = $service->runAllChecks();

            expect($checks)->toHaveKeys(['disk', 'memory', 'cpu', 'mysql']);
        });
    });
});