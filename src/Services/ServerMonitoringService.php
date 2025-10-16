<?php

namespace CristianDev\LaravelServerMonitor\Services;

class ServerMonitoringService
{
    public function getDiskWarningThreshold(): int
    {
        return config('server-monitor.monitoring.disk.warning_threshold', 80);
    }

    public function getDiskCriticalThreshold(): int
    {
        return config('server-monitor.monitoring.disk.critical_threshold', 90);
    }

    public function getMemoryWarningThreshold(): int
    {
        return config('server-monitor.monitoring.memory.warning_threshold', 80);
    }

    public function getMemoryCriticalThreshold(): int
    {
        return config('server-monitor.monitoring.memory.critical_threshold', 90);
    }

    public function getCpuWarningThreshold(): int
    {
        return config('server-monitor.monitoring.cpu.warning_threshold', 70);
    }

    public function getCpuCriticalThreshold(): int
    {
        return config('server-monitor.monitoring.cpu.critical_threshold', 90);
    }

    public function runAllChecks(): array
    {
        return [
            'disk' => $this->checkDiskSpace(),
            'memory' => $this->checkMemoryUsage(),
            'cpu' => $this->checkCpuLoad(),
            'mysql' => $this->checkMysqlService(),
        ];
    }

    public function checkDiskSpace(): array
    {
        $usage = $this->getDiskUsage();

        return [
            'metric' => 'disk_space',
            'value' => $usage,
            'unit' => '%',
            'status' => $this->getDiskStatus($usage),
            'message' => "Disk space usage is {$usage}%",
        ];
    }

    public function checkMemoryUsage(): array
    {
        $usage = $this->getMemoryUsage();

        return [
            'metric' => 'memory_usage',
            'value' => $usage,
            'unit' => '%',
            'status' => $this->getMemoryStatus($usage),
            'message' => "Memory usage is {$usage}%",
        ];
    }

    public function checkCpuLoad(): array
    {
        $load = $this->getCpuLoad();

        return [
            'metric' => 'cpu_load',
            'value' => $load,
            'unit' => '',
            'status' => $this->getCpuStatus($load),
            'message' => "CPU load is {$load}",
        ];
    }

    public function checkMysqlService(): array
    {
        $isRunning = $this->getMysqlStatus();

        return [
            'metric' => 'mysql_service',
            'value' => $isRunning ? 1 : 0,
            'unit' => '',
            'status' => $isRunning ? 'ok' : 'critical',
            'message' => $isRunning ? 'MySQL service is running' : 'MySQL service is not running',
        ];
    }

    public function getAlerts(array $checks): array
    {
        $alerts = [];

        foreach ($checks as $check) {
            if ($check['status'] === 'critical') {
                $alerts[] = [
                    'type' => 'CRITICAL',
                    'details' => "🔴 {$check['message']}",
                    'metric' => $check['metric'],
                    'value' => $check['value'],
                ];
            } elseif ($check['status'] === 'warning') {
                $alerts[] = [
                    'type' => 'WARNING',
                    'details' => "🟡 {$check['message']}",
                    'metric' => $check['metric'],
                    'value' => $check['value'],
                ];
            }
        }

        return $alerts;
    }

    protected function getDiskUsage(): int
    {
        $output = shell_exec('df /');

        if (empty($output)) {
            return 0;
        }

        if (preg_match('/(\d+)%/', $output, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    protected function getMemoryUsage(): int
    {
        $output = shell_exec('free | grep Mem | awk "{ printf(\"%.0f\", \$3/\$2 * 100.0) }"');

        return (int) trim($output);
    }

    protected function getCpuLoad(): float
    {
        $output = shell_exec('uptime | awk -F"load average:" "{ print \$2 }" | awk "{ print \$1 }" | sed "s/,//"');

        return (float) trim($output);
    }

    protected function getMysqlStatus(): bool
    {
        $output = shell_exec('pgrep mysqld');

        return ! empty(trim($output));
    }

    protected function getDiskStatus(int $usage): string
    {
        if ($usage >= $this->getDiskCriticalThreshold()) {
            return 'critical';
        }
        if ($usage >= $this->getDiskWarningThreshold()) {
            return 'warning';
        }

        return 'ok';
    }

    protected function getMemoryStatus(int $usage): string
    {
        if ($usage >= $this->getMemoryCriticalThreshold()) {
            return 'critical';
        }
        if ($usage >= $this->getMemoryWarningThreshold()) {
            return 'warning';
        }

        return 'ok';
    }

    protected function getCpuStatus(float $load): string
    {
        if ($load >= $this->getCpuCriticalThreshold()) {
            return 'critical';
        }
        if ($load >= $this->getCpuWarningThreshold()) {
            return 'warning';
        }

        return 'ok';
    }
}