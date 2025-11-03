<?php

namespace SoyCristianRico\LaravelServerMonitor\Services;

class ServerMonitoringService
{
    public function getDiskWarningThreshold(): int
    {
        return config('server-monitor.monitoring.disk.warning_threshold') ?? 80;
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

    public function getSwapWarningThreshold(): int
    {
        return config('server-monitor.monitoring.swap.warning_threshold', 20);
    }

    public function getSwapCriticalThreshold(): int
    {
        return config('server-monitor.monitoring.swap.critical_threshold', 50);
    }

    public function runAllChecks(): array
    {
        return [
            'disk' => $this->checkDiskSpace(),
            'memory' => $this->checkMemoryUsage(),
            'cpu' => $this->checkCpuLoad(),
            'swap' => $this->checkSwapUsage(),
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

    public function checkSwapUsage(): array
    {
        $swapData = $this->getSwapData();
        $status = $this->getSwapStatus($swapData);

        return [
            'metric' => 'swap_usage',
            'value' => $swapData['swap_percentage'],
            'unit' => '%',
            'status' => $status,
            'message' => $this->getSwapMessage($swapData, $status),
            'details' => $swapData,
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

        // Sort alerts by priority (critical first)
        usort($alerts, function ($a, $b) {
            if ($a['type'] === 'CRITICAL' && $b['type'] === 'WARNING') {
                return -1;
            }
            if ($a['type'] === 'WARNING' && $b['type'] === 'CRITICAL') {
                return 1;
            }
            return 0;
        });

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

    protected function getSwapData(): array
    {
        $output = shell_exec('free -b');

        if (empty($output)) {
            return [
                'swap_percentage' => 0,
                'swap_used_mb' => 0,
                'swap_total_mb' => 0,
                'memory_available_mb' => 0,
                'memory_total_mb' => 0,
                'memory_pressure' => false,
            ];
        }

        $lines = explode("\n", trim($output));
        $memoryLine = null;
        $swapLine = null;

        foreach ($lines as $line) {
            if (strpos($line, 'Mem:') === 0) {
                $memoryLine = preg_split('/\s+/', $line);
            } elseif (strpos($line, 'Swap:') === 0) {
                $swapLine = preg_split('/\s+/', $line);
            }
        }

        if (!$memoryLine || !$swapLine) {
            return [
                'swap_percentage' => 0,
                'swap_used_mb' => 0,
                'swap_total_mb' => 0,
                'memory_available_mb' => 0,
                'memory_total_mb' => 0,
                'memory_pressure' => false,
            ];
        }

        $memoryTotal = (int) $memoryLine[1];
        $memoryAvailable = (int) $memoryLine[6]; // Available column
        $swapTotal = (int) $swapLine[1];
        $swapUsed = (int) $swapLine[2];

        $swapPercentage = $swapTotal > 0 ? round(($swapUsed / $swapTotal) * 100) : 0;
        $memoryAvailablePercentage = $memoryTotal > 0 ? round(($memoryAvailable / $memoryTotal) * 100) : 0;

        // Consider memory pressure if less than 15% available RAM
        $memoryPressure = $memoryAvailablePercentage < 15;

        return [
            'swap_percentage' => $swapPercentage,
            'swap_used_mb' => round($swapUsed / 1024 / 1024, 1),
            'swap_total_mb' => round($swapTotal / 1024 / 1024, 1),
            'memory_available_mb' => round($memoryAvailable / 1024 / 1024, 1),
            'memory_total_mb' => round($memoryTotal / 1024 / 1024, 1),
            'memory_available_percentage' => $memoryAvailablePercentage,
            'memory_pressure' => $memoryPressure,
        ];
    }

    protected function getSwapUsage(): int
    {
        $swapData = $this->getSwapData();
        return $swapData['swap_percentage'];
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

    protected function getSwapStatus(array $swapData): string
    {
        $swapPercentage = $swapData['swap_percentage'];
        $memoryPressure = $swapData['memory_pressure'];
        $memoryAvailablePercentage = $swapData['memory_available_percentage'];

        // No swap configured or no swap used
        if ($swapData['swap_total_mb'] == 0 || $swapPercentage == 0) {
            return 'ok';
        }

        // Critical: High swap usage AND memory pressure (low available RAM)
        if ($swapPercentage >= $this->getSwapCriticalThreshold() && $memoryPressure) {
            return 'critical';
        }

        // Critical: Extremely high swap usage (>80%) regardless of available RAM
        if ($swapPercentage >= 80) {
            return 'critical';
        }

        // Warning: Moderate swap usage WITH memory pressure
        if ($swapPercentage >= $this->getSwapWarningThreshold() && $memoryPressure) {
            return 'warning';
        }

        // Warning: Very high swap usage (>60%) even without memory pressure
        if ($swapPercentage >= 60) {
            return 'warning';
        }

        // Normal: Swap usage without memory pressure is optimization, not a problem
        return 'ok';
    }

    protected function getSwapMessage(array $swapData, string $status): string
    {
        $swapPercentage = $swapData['swap_percentage'];
        $swapUsedMb = $swapData['swap_used_mb'];
        $memoryAvailableMb = $swapData['memory_available_mb'];
        $memoryAvailablePercentage = $swapData['memory_available_percentage'];

        if ($status === 'ok') {
            if ($swapPercentage > 0) {
                return "Swap usage {$swapPercentage}% ({$swapUsedMb}MB) is normal with {$memoryAvailableMb}MB ({$memoryAvailablePercentage}%) RAM available";
            }
            return "No swap usage detected";
        }

        if ($status === 'warning') {
            return "Swap usage {$swapPercentage}% ({$swapUsedMb}MB) with {$memoryAvailableMb}MB ({$memoryAvailablePercentage}%) RAM available";
        }

        return "High swap usage {$swapPercentage}% ({$swapUsedMb}MB) with low available RAM {$memoryAvailableMb}MB ({$memoryAvailablePercentage}%)";
    }
}