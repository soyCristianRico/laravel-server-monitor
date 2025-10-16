# Services API Reference

Complete API documentation for Laravel Server Monitor services.

## ServerMonitoringService

Main service for server resource monitoring.

### Class: `CristianDev\LaravelServerMonitor\Services\ServerMonitoringService`

#### Public Methods

##### `runAllChecks(): array`

Executes all monitoring checks and returns results.

**Returns:**
```php
[
    'disk' => [
        'metric' => 'disk_space',
        'value' => 75,
        'unit' => '%',
        'status' => 'ok|warning|critical',
        'message' => 'Disk space usage is 75%'
    ],
    'memory' => [...],
    'cpu' => [...],
    'mysql' => [...]
]
```

**Example:**
```php
$service = app(ServerMonitoringService::class);
$checks = $service->runAllChecks();

foreach ($checks as $type => $check) {
    if ($check['status'] !== 'ok') {
        Log::warning("Alert: {$check['message']}");
    }
}
```

##### `checkDiskSpace(): array`

Checks disk space usage for root partition.

**Returns:**
```php
[
    'metric' => 'disk_space',
    'value' => 75,           // Percentage used
    'unit' => '%',
    'status' => 'ok',        // 'ok', 'warning', 'critical'
    'message' => 'Disk space usage is 75%'
]
```

##### `checkMemoryUsage(): array`

Checks RAM memory usage.

**Returns:**
```php
[
    'metric' => 'memory_usage',
    'value' => 68,           // Percentage used
    'unit' => '%',
    'status' => 'ok',
    'message' => 'Memory usage is 68%'
]
```

##### `checkCpuLoad(): array`

Checks CPU load average (1-minute).

**Returns:**
```php
[
    'metric' => 'cpu_load',
    'value' => 0.45,         // Load average
    'unit' => '',
    'status' => 'ok',
    'message' => 'CPU load is 0.45'
]
```

##### `checkMysqlService(): array`

Checks if MySQL service is running.

**Returns:**
```php
[
    'metric' => 'mysql_service',
    'value' => 1,            // 1 = running, 0 = not running
    'unit' => '',
    'status' => 'ok',        // 'ok' or 'critical'
    'message' => 'MySQL service is running'
]
```

##### `getAlerts(array $checks): array`

Processes check results and returns alerts for warning/critical statuses.

**Parameters:**
- `$checks` - Array from `runAllChecks()`

**Returns:**
```php
[
    [
        'type' => 'WARNING|CRITICAL',
        'details' => '🟡 Disk space usage is 85%',
        'metric' => 'disk_space',
        'value' => 85
    ],
    // ... more alerts
]
```

#### Configuration Methods

##### `getDiskWarningThreshold(): int`

Returns disk warning threshold from configuration.

##### `getDiskCriticalThreshold(): int`

Returns disk critical threshold from configuration.

##### `getMemoryWarningThreshold(): int`

Returns memory warning threshold from configuration.

##### `getMemoryCriticalThreshold(): int`

Returns memory critical threshold from configuration.

##### `getCpuWarningThreshold(): int`

Returns CPU warning threshold from configuration.

##### `getCpuCriticalThreshold(): int`

Returns CPU critical threshold from configuration.

## SecurityScannerService

Service for security monitoring and threat detection.

### Class: `CristianDev\LaravelServerMonitor\Services\Security\SecurityScannerService`

#### Public Methods

##### `checkSuspiciousProcesses(): ?array`

Scans for suspicious running processes.

**Returns:**
```php
// If threats found:
[
    'type' => 'Suspicious Processes',
    'details' => 'Process details...'
]

// If clean:
null
```

**Example:**
```php
$scanner = app(SecurityScannerService::class);
if ($alert = $scanner->checkSuspiciousProcesses()) {
    Log::warning('Suspicious process detected', $alert);
}
```

##### `checkSuspiciousPorts(): array`

Scans for suspicious network ports.

**Returns:**
```php
[
    [
        'type' => 'Suspicious Network Ports',
        'details' => 'Port details...'
    ],
    [
        'type' => 'Scrapyd Service Exposed',
        'details' => 'Scrapyd port details...'
    ]
]
```

##### `checkCrontabModifications(string $timeFrame = '-1'): ?array`

Checks for recent crontab modifications.

**Parameters:**
- `$timeFrame` - Time frame for find command (default: '-1' for 1 day)

**Returns:**
```php
// If modifications found:
[
    'type' => 'Recently Modified Crontabs',
    'details' => 'List of modified files...'
]

// If clean:
null
```

##### `checkFailedLogins(int $threshold = 20): ?array`

Checks for excessive failed login attempts.

**Parameters:**
- `$threshold` - Number of failed attempts to trigger alert

**Returns:**
```php
// If threshold exceeded:
[
    'type' => 'High Failed Login Attempts',
    'details' => 'Count: 25\nRecent attempts:\n...'
]

// If below threshold:
null
```

##### `checkNewUsers(int $days = 7): ?array`

Checks for recently created user accounts.

**Parameters:**
- `$days` - Number of days to look back

**Returns:**
```php
// If new users found (not whitelisted):
[
    'type' => 'Recently Created Users',
    'details' => 'List of new user directories...'
]

// If clean:
null
```

##### `checkModifiedSystemFiles(int $days = 1): ?array`

Checks for modifications to critical system files.

**Parameters:**
- `$days` - Number of days to look back

**Returns:**
```php
// If modifications found:
[
    'type' => 'Modified System Files',
    'details' => 'List of modified files...'
]

// If clean:
null
```

##### `checkUnauthorizedSSHKeys(int $days = 7): ?array`

Checks for recently modified SSH authorization files.

**Parameters:**
- `$days` - Number of days to look back

##### `checkLargeFiles(string $size = '100M', int $days = 1): ?array`

Checks for recently created large files.

**Parameters:**
- `$size` - Minimum file size (e.g., '100M', '1G')
- `$days` - Number of days to look back

##### `checkMalwarePatterns(): array`

Comprehensive malware pattern detection in PHP files.

**Returns:**
```php
[
    [
        'type' => 'Suspicious PHP Code Patterns',
        'details' => 'Directory: /var/www\nFiles:\n...'
    ],
    [
        'type' => 'Recently Uploaded PHP Files',
        'details' => 'Directory: /var/www\nFiles:\n...'
    ]
]
```

**Features:**
- Scans for dangerous PHP patterns
- Excludes configured directories
- Respects whitelisted files
- Caches results to prevent duplicate alerts
- Tracks file changes between scans

##### `getListeningPorts(): string`

Returns information about listening network ports.

**Returns:**
Raw netstat output of listening ports (excluding localhost).

## SecurityNotificationService

Service for sending security alerts and notifications.

### Class: `CristianDev\LaravelServerMonitor\Services\Security\SecurityNotificationService`

#### Public Methods

##### `sendAlerts(array $alerts, string $successMessage = '...', string $errorMessage = '...'): bool`

Sends security alerts to admin users.

**Parameters:**
- `$alerts` - Array of alert objects
- `$successMessage` - Message to log on success
- `$errorMessage` - Message to log on failure

**Returns:**
`true` if notifications were sent, `false` otherwise.

**Example:**
```php
$service = app(SecurityNotificationService::class);
$alerts = [
    [
        'type' => 'Security Issue',
        'details' => 'Issue description...'
    ]
];

if ($service->sendAlerts($alerts)) {
    echo "Alerts sent successfully!";
} else {
    echo "No admin users found to notify.";
}
```

##### `sendReport(array $alerts = [], ?string $report = null): bool`

Sends a security report (with optional alerts).

**Parameters:**
- `$alerts` - Optional array of alerts
- `$report` - Optional report text

**Returns:**
`true` if report was sent, `false` otherwise.

##### `logAlerts(array $alerts, string $context = 'security_check'): void`

Logs security alerts to Laravel log system.

**Parameters:**
- `$alerts` - Array of alerts to log
- `context` - Context identifier for the log entry

## Service Integration Examples

### Custom Monitoring Dashboard

```php
use CristianDev\LaravelServerMonitor\Services\ServerMonitoringService;
use CristianDev\LaravelServerMonitor\Services\Security\SecurityScannerService;

class MonitoringController extends Controller
{
    public function dashboard()
    {
        $monitor = app(ServerMonitoringService::class);
        $scanner = app(SecurityScannerService::class);

        $serverChecks = $monitor->runAllChecks();
        $securityAlerts = $scanner->checkMalwarePatterns();

        return view('monitoring.dashboard', [
            'server' => $serverChecks,
            'security' => $securityAlerts,
        ]);
    }
}
```

### Custom Alert Logic

```php
use CristianDev\LaravelServerMonitor\Services\ServerMonitoringService;

class CustomMonitoringService
{
    public function checkCriticalResources()
    {
        $monitor = app(ServerMonitoringService::class);
        $checks = $monitor->runAllChecks();

        foreach ($checks as $check) {
            if ($check['status'] === 'critical') {
                $this->handleCriticalAlert($check);
            }
        }
    }

    private function handleCriticalAlert(array $check)
    {
        // Custom logic: page admin, scale resources, etc.
        match($check['metric']) {
            'disk_space' => $this->cleanupDiskSpace(),
            'memory_usage' => $this->restartServices(),
            'mysql_service' => $this->restartMysql(),
            default => $this->sendUrgentAlert($check)
        };
    }
}
```

### Extending Services

```php
use CristianDev\LaravelServerMonitor\Services\Security\SecurityScannerService;

class ExtendedSecurityScanner extends SecurityScannerService
{
    public function checkCustomThreats(): array
    {
        $alerts = [];

        // Custom security checks
        if ($this->checkDockerContainers()) {
            $alerts[] = [
                'type' => 'Unauthorized Docker Containers',
                'details' => 'Unexpected containers detected...'
            ];
        }

        return array_merge(parent::checkMalwarePatterns(), $alerts);
    }

    private function checkDockerContainers(): bool
    {
        $containers = shell_exec('docker ps --format "table {{.Names}}"');
        $allowedContainers = config('monitoring.allowed_containers', []);

        // Your custom logic here
        return false;
    }
}
```

## Next Steps

- [Commands API Reference](commands.md)
- [Traits Reference](traits.md)
- [Usage Examples](../examples/basic-setup.md)