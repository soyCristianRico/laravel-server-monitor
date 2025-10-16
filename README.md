# Laravel Server Monitor

A comprehensive Laravel package for server monitoring and security checks. This package provides automated monitoring of server resources (CPU, memory, disk space) and security scanning capabilities.

## Features

- **Server Resource Monitoring**
  - CPU load monitoring with configurable thresholds
  - Memory usage tracking
  - Disk space monitoring
  - Swap usage monitoring with early warning detection
  - MySQL service status checking

- **Security Monitoring**
  - Malware pattern detection in PHP files
  - Suspicious process monitoring
  - Network port scanning
  - Crontab modification tracking
  - Failed login attempt monitoring
  - SSH key modification detection
  - System file change monitoring

- **Alert System**
  - Email notifications to admin users
  - Configurable alert thresholds
  - Alert cooldown to prevent spam
  - Detailed logging

## Installation

### From GitHub Repository

1. Add the repository to your project:

```bash
composer config repositories.laravel-server-monitor vcs https://github.com/soycristianrico/laravel-server-monitor
```

2. Install the package via Composer:

```bash
composer require soycristianrico/laravel-server-monitor:dev-main
```

### From Packagist (when published)

```bash
composer require soycristianrico/laravel-server-monitor
```

3. Publish the configuration file:

```bash
php artisan vendor:publish --provider="SoyCristianRico\LaravelServerMonitor\ServerMonitorServiceProvider" --tag="server-monitor-config"
```

4. Configure your settings in `config/server-monitor.php`

## Updating the Package

To update the package to the latest version:

```bash
composer update soycristianrico/laravel-server-monitor
```

If you need to republish the configuration after an update:

```bash
php artisan vendor:publish --provider="SoyCristianRico\LaravelServerMonitor\ServerMonitorServiceProvider" --tag="server-monitor-config" --force
```

## Configuration

### Basic Setup

Edit `config/server-monitor.php` to customize:

```php
return [
    'monitoring' => [
        'disk' => [
            'warning_threshold' => 80,  // Warning at 80% disk usage
            'critical_threshold' => 90, // Critical at 90% disk usage
        ],
        'memory' => [
            'warning_threshold' => 80,
            'critical_threshold' => 90,
        ],
        'cpu' => [
            'warning_threshold' => 70,
            'critical_threshold' => 90,
        ],
        'swap' => [
            'warning_threshold' => 20,  // Warning at 20% swap usage
            'critical_threshold' => 50, // Critical at 50% swap usage
        ],
    ],
    'notifications' => [
        'admin_role' => 'admin',
        'user_model' => 'App\\Models\\User',
    ],
];
```

### Environment Variables

Add these to your `.env` file:

```env
SERVER_MONITOR_DISK_WARNING=80
SERVER_MONITOR_DISK_CRITICAL=90
SERVER_MONITOR_MEMORY_WARNING=80
SERVER_MONITOR_MEMORY_CRITICAL=90
SERVER_MONITOR_CPU_WARNING=70
SERVER_MONITOR_CPU_CRITICAL=90
SERVER_MONITOR_SWAP_WARNING=20
SERVER_MONITOR_SWAP_CRITICAL=50
SERVER_MONITOR_ADMIN_ROLE=admin
SERVER_MONITOR_USER_MODEL="App\\Models\\User"
```

## Usage

### Manual Commands

Run monitoring checks manually:

```bash
# Check server resources
php artisan server:monitor

# Run security checks
php artisan security:check

# Scan for malware patterns
php artisan security:check-malware

# Monitor crontab changes
php artisan security:monitor-crontabs
```

### Automated Scheduling

Add to your `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

// Server monitoring every 10 minutes
Schedule::command('server:monitor')->everyTenMinutes();

// Security checks every 30 minutes
Schedule::command('security:check')->everyThirtyMinutes();

// Malware scanning hourly
Schedule::command('security:check-malware')->hourly();

// Crontab monitoring every 5 minutes
Schedule::command('security:monitor-crontabs')->everyFiveMinutes();
```

### User Roles

Ensure your admin users have the configured role (default: 'admin'). This package uses `spatie/laravel-permission` for role management:

```php
use Spatie\Permission\Models\Role;

// Create admin role if it doesn't exist
Role::firstOrCreate(['name' => 'admin']);

// Assign role to user
$user->assignRole('admin');
```

## Commands

### `server:monitor`

Monitors server resources and sends alerts if thresholds are exceeded.

**Exit codes:**
- `0`: All checks passed
- `1`: One or more alerts triggered

**Example output:**
```
Starting server monitoring...
✅ Disk space usage is 45%
✅ Memory usage is 67%
✅ CPU load is 0.34
🟡 Swap usage is 25%
🔴 MySQL service is not running
Server monitoring alerts sent successfully!
```

### `security:check`

Runs comprehensive security checks including:
- Suspicious processes
- Network ports
- Crontab modifications
- Failed login attempts
- New users
- System file changes
- SSH key modifications
- Large file detection

### `security:check-malware`

Specifically scans for malware patterns in PHP files:
- Evaluates suspicious code patterns
- Checks for recently uploaded files
- Excludes configured directories and whitelisted files
- Uses caching to prevent duplicate alerts

### `security:monitor-crontabs`

Monitors crontab files for recent modifications, useful for detecting unauthorized scheduled tasks.

## Requirements

- PHP 8.1+
- Laravel 10.x, 11.x or 12.x
- `spatie/laravel-permission` for role management
- Linux server with standard system utilities (df, free, uptime, netstat, etc.)

## Security Considerations

This package executes system commands to gather monitoring data. All commands are:
- Hardcoded (no user input)
- Read-only operations
- Limited to specific, safe system utilities

## Testing

Run the package tests:

```bash
vendor/bin/phpunit
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for more information on recent changes.