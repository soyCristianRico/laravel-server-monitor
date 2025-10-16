# Configuration Guide

Complete configuration reference for Laravel Server Monitor.

## Configuration File Structure

The main configuration file is located at `config/server-monitor.php` after publishing.

```php
return [
    'monitoring' => [...],      // Server resource thresholds
    'notifications' => [...],   // Alert settings
    'security' => [...],       // Security scan settings
    'scheduling' => [...],     // Suggested schedules
];
```

## Monitoring Configuration

### Disk Space Monitoring

```php
'monitoring' => [
    'disk' => [
        'warning_threshold' => 80,   // Alert at 80% usage
        'critical_threshold' => 90,  // Critical at 90% usage
    ],
],
```

**Environment variables:**
```env
SERVER_MONITOR_DISK_WARNING=80
SERVER_MONITOR_DISK_CRITICAL=90
```

### Memory Monitoring

```php
'memory' => [
    'warning_threshold' => 80,   // Alert at 80% memory usage
    'critical_threshold' => 90,  // Critical at 90% memory usage
],
```

**Environment variables:**
```env
SERVER_MONITOR_MEMORY_WARNING=80
SERVER_MONITOR_MEMORY_CRITICAL=90
```

### CPU Load Monitoring

```php
'cpu' => [
    'warning_threshold' => 70,   // Alert at 70% CPU load
    'critical_threshold' => 90,  // Critical at 90% CPU load
],
```

**Environment variables:**
```env
SERVER_MONITOR_CPU_WARNING=70
SERVER_MONITOR_CPU_CRITICAL=90
```

## Notification Configuration

### Basic Settings

```php
'notifications' => [
    'admin_role' => 'admin',              // Role that receives alerts
    'user_model' => 'App\\Models\\User',  // Your User model
    'channels' => ['mail'],               // Available: mail, slack, teams
],
```

**Environment variables:**
```env
SERVER_MONITOR_ADMIN_ROLE=admin
SERVER_MONITOR_USER_MODEL="App\\Models\\User"
```

### Custom User Model

If you use a different User model:

```php
'notifications' => [
    'user_model' => 'App\\Models\\Administrator',
],
```

### Custom Role Names

```php
'notifications' => [
    'admin_role' => 'super-admin',  // Your custom role name
],
```

## Security Configuration

### Whitelisted Users

System users to ignore during security scans:

```php
'security' => [
    'whitelisted_users' => [
        'forge',
        'root',
        'www-data',
        'mysql',
        'redis',
        'your-app-user',  // Add your application user
    ],
],
```

### Whitelisted Directories

User directories to ignore:

```php
'whitelisted_directories' => [
    '/home/forge',
    '/home/root',
    '/var/www',
    '/home/your-user',  // Add your specific paths
],
```

### Malware Scanning Exclusions

Directories to exclude from malware scanning:

```php
'excluded_paths' => [
    'vendor',
    'node_modules',
    'storage/framework/cache',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
    '.git',
    'public/storage',
    'tests',           // Add if needed
    'database/seeds',  // Add if needed
],
```

### Whitelisted Security Files

Files that may contain legitimate security patterns:

```php
'whitelisted_security_files' => [
    'app/Services/Security/SecurityService.php',
    'app/Http/Middleware/SecurityMiddleware.php',
    'app/Console/Commands/SecurityCheck.php',
    // Add your security-related files
],
```

### Cache and Cooldown Settings

```php
'security' => [
    'scan_cache_duration' => 60,    // Cache scan results for 60 minutes
    'alert_cooldown' => 120,        // 2 hours between duplicate alerts
],
```

**Environment variables:**
```env
SERVER_MONITOR_CACHE_DURATION=60
SERVER_MONITOR_ALERT_COOLDOWN=120
```

## Scheduling Configuration

Suggested scheduling frequencies:

```php
'scheduling' => [
    'server_monitor' => 'everyTenMinutes',
    'security_check' => 'everyThirtyMinutes',
    'malware_check' => 'hourly',
    'crontab_monitor' => 'everyFiveMinutes',
],
```

## Advanced Configuration

### Multiple Admin Roles

```php
// In your AppServiceProvider boot method
$this->app['config']->set('server-monitor.notifications.admin_roles', [
    'admin',
    'super-admin',
    'security-team'
]);
```

### Custom Notification Channels

Extend the SecurityNotificationService:

```php
// In your service provider
$this->app->extend(SecurityNotificationService::class, function ($service, $app) {
    return new YourCustomSecurityNotificationService();
});
```

### Environment-Specific Settings

#### Production Settings

```env
# Stricter thresholds for production
SERVER_MONITOR_DISK_WARNING=75
SERVER_MONITOR_DISK_CRITICAL=85
SERVER_MONITOR_MEMORY_WARNING=75
SERVER_MONITOR_MEMORY_CRITICAL=85

# More frequent monitoring
SERVER_MONITOR_CACHE_DURATION=30
SERVER_MONITOR_ALERT_COOLDOWN=60
```

#### Development Settings

```env
# Relaxed thresholds for development
SERVER_MONITOR_DISK_WARNING=90
SERVER_MONITOR_DISK_CRITICAL=95
SERVER_MONITOR_MEMORY_WARNING=90
SERVER_MONITOR_MEMORY_CRITICAL=95

# Less frequent alerts
SERVER_MONITOR_CACHE_DURATION=120
SERVER_MONITOR_ALERT_COOLDOWN=240
```

## Configuration Validation

Validate your configuration:

```bash
# Show current configuration
php artisan config:show server-monitor

# Test with current settings
php artisan server:monitor --dry-run
php artisan security:check --dry-run
```

## Next Steps

- [Usage Examples](../examples/basic-setup.md)
- [Environment Variables](environment.md)
- [Advanced Configuration](advanced.md)