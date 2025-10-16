# Commands Reference

Complete reference for all Laravel Server Monitor commands.

## Server Monitoring Commands

### `server:monitor`

Monitors server resources and sends alerts if thresholds are exceeded.

```bash
php artisan server:monitor
```

**What it checks:**
- Disk space usage (configurable thresholds)
- Memory usage (RAM)
- CPU load average (1-minute)
- MySQL service status

**Output example:**
```
Starting server monitoring...
✅ Disk space usage is 45%
✅ Memory usage is 67%
✅ CPU load is 0.34
🔴 MySQL service is not running
Server monitoring alerts sent successfully!
```

**Exit codes:**
- `0`: All checks passed
- `1`: One or more alerts triggered

**When alerts are sent:**
- Disk usage ≥ warning threshold
- Memory usage ≥ warning threshold
- CPU load ≥ warning threshold
- MySQL service not running

## Security Commands

### `security:check`

Comprehensive security scan covering multiple attack vectors.

```bash
php artisan security:check
```

**What it checks:**
- Suspicious processes (wget/curl scripts)
- Suspicious network ports (4444, 5555, 31337, 12345)
- Scrapyd service exposure (ports 6800, 6801)
- Recent crontab modifications
- High failed login attempts
- Recently created users
- Modified system files
- Recently modified SSH keys
- Large files (possible data dumps)

**Output example:**
```
Suspicious processes detected!
Recent crontab modifications detected!
✅ No other security issues detected
Security issues found! Alerts sent.
```

**Exit codes:**
- `0`: No security issues found
- `1`: Security issues detected and alerts sent

### `security:check-malware`

Specialized malware pattern detection in PHP files.

```bash
php artisan security:check-malware
```

**What it scans:**
- All PHP files in application directories
- Suspicious code patterns:
  - `eval()` with variables
  - `base64_decode()` with variables
  - Shell execution functions with variables
  - Superglobal access patterns

**Features:**
- Excludes configured directories (vendor, node_modules, etc.)
- Respects whitelisted security files
- Caches results to prevent duplicate alerts
- Tracks recently uploaded PHP files

**Output example:**
```
Starting malware pattern scan...
Suspicious patterns detected in 2 location(s)!
Type: Suspicious PHP Code Patterns
Directory: /var/www/html
Files:
/var/www/html/uploads/shell.php
/var/www/html/cache/backdoor.php
---
Malware patterns detected! Security team notified.
```

### `security:monitor-crontabs`

Monitors crontab files for unauthorized modifications.

```bash
php artisan security:monitor-crontabs
```

**What it monitors:**
- System crontab files (`/etc/cron*`)
- User crontab files (`/var/spool/cron/crontabs`)
- Files modified in the last day

**Use case:**
- Detect unauthorized scheduled tasks
- Monitor for persistence mechanisms
- Track administrative changes

## Command Options and Flags

### Global Options

All commands support standard Laravel command options:

```bash
# Show more detailed output
php artisan server:monitor --verbose

# Suppress output
php artisan server:monitor --quiet

# Show help
php artisan server:monitor --help
```

### Custom Thresholds (Server Monitor)

While not built-in, you can modify thresholds programmatically:

```php
// In a custom command or service
$service = app(ServerMonitoringService::class);
config(['server-monitor.monitoring.disk.warning_threshold' => 85]);
$checks = $service->runAllChecks();
```

## Scheduling Commands

### Recommended Schedule

Add to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

// Critical monitoring - every 10 minutes
Schedule::command('server:monitor')->everyTenMinutes();

// General security - every 30 minutes
Schedule::command('security:check')->everyThirtyMinutes();

// Intensive scanning - hourly
Schedule::command('security:check-malware')->hourly();

// Quick checks - every 5 minutes
Schedule::command('security:monitor-crontabs')->everyFiveMinutes();
```

### Alternative Schedules

```php
// High-frequency monitoring
Schedule::command('server:monitor')->everyFiveMinutes();
Schedule::command('security:check')->everyFifteenMinutes();

// Low-frequency monitoring
Schedule::command('server:monitor')->everyThirtyMinutes();
Schedule::command('security:check')->hourly();

// Peak hours only
Schedule::command('server:monitor')
    ->everyTenMinutes()
    ->between('09:00', '17:00');

// Off-hours intensive scanning
Schedule::command('security:check-malware')
    ->hourly()
    ->between('02:00', '06:00');
```

### Conditional Scheduling

```php
// Only on production
Schedule::command('server:monitor')
    ->everyTenMinutes()
    ->when(fn() => app()->environment('production'));

// Skip during maintenance
Schedule::command('security:check')
    ->everyThirtyMinutes()
    ->unless(fn() => app()->isDownForMaintenance());
```

## Command Integration

### Combining with Other Commands

```php
// Run cleanup before monitoring
Schedule::command('app:cleanup')->dailyAt('03:00');
Schedule::command('server:monitor')->dailyAt('03:15');

// Chain commands
Schedule::call(function () {
    Artisan::call('server:monitor');
    if (Artisan::output() !== 0) {
        Artisan::call('app:emergency-alert');
    }
})->everyTenMinutes();
```

### Custom Monitoring Logic

```php
// In your own command
use CristianDev\LaravelServerMonitor\Services\ServerMonitoringService;

public function handle()
{
    $monitor = app(ServerMonitoringService::class);
    $checks = $monitor->runAllChecks();

    // Your custom logic
    foreach ($checks as $check) {
        if ($check['status'] === 'critical') {
            $this->handleCriticalAlert($check);
        }
    }
}
```

## Troubleshooting Commands

### Check Command Registration

```bash
# List all server-monitor commands
php artisan list | grep -E "(server|security)"
```

### Test Configuration

```bash
# Show current configuration
php artisan config:show server-monitor

# Clear config cache
php artisan config:clear
```

### Debug Mode

```bash
# Run with maximum verbosity
php artisan server:monitor -vvv

# Check Laravel logs
tail -f storage/logs/laravel.log
```

## Next Steps

- [Scheduling Guide](scheduling.md)
- [Notification Setup](notifications.md)
- [API Reference](../api/commands.md)