# Installation Guide

Complete installation guide for Laravel Server Monitor.

## Requirements

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- spatie/laravel-permission package
- Linux server environment
- Basic system utilities (df, free, uptime, netstat, pgrep)

## Step-by-Step Installation

### 1. Install via Composer

```bash
composer require soycristianrico/laravel-server-monitor
```

### 2. Install Dependencies

The package requires `spatie/laravel-permission` for role management:

```bash
# If not already installed
composer require spatie/laravel-permission

# Publish and run permission migrations
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

### 3. Publish Package Configuration

```bash
php artisan vendor:publish --provider="CristianDev\LaravelServerMonitor\ServerMonitorServiceProvider" --tag="server-monitor-config"
```

This creates `config/server-monitor.php` with default settings.

### 4. Create Admin Role and Users

```php
use Spatie\Permission\Models\Role;
use App\Models\User;

// Create admin role
$adminRole = Role::firstOrCreate(['name' => 'admin']);

// Assign role to user
$user = User::find(1); // Your admin user
$user->assignRole('admin');
```

### 5. Configure Environment Variables

Add to your `.env` file:

```env
# Server monitoring thresholds
SERVER_MONITOR_DISK_WARNING=80
SERVER_MONITOR_DISK_CRITICAL=90
SERVER_MONITOR_MEMORY_WARNING=80
SERVER_MONITOR_MEMORY_CRITICAL=90
SERVER_MONITOR_CPU_WARNING=70
SERVER_MONITOR_CPU_CRITICAL=90

# Notification settings
SERVER_MONITOR_ADMIN_ROLE=admin
SERVER_MONITOR_USER_MODEL="App\\Models\\User"
```

### 6. Test Installation

```bash
# Test server monitoring
php artisan server:monitor

# Test security checks
php artisan security:check

# Test malware scanning
php artisan security:check-malware
```

### 7. Setup Scheduling (Optional)

Add to `routes/console.php`:

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

Ensure your Laravel scheduler is running:

```bash
# Add to crontab
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Verification

### Check Commands Are Available

```bash
php artisan list | grep -E "(server|security)"
```

Should show:
- `server:monitor`
- `security:check`
- `security:check-malware`
- `security:monitor-crontabs`

### Check Configuration

```bash
php artisan config:show server-monitor
```

### Run Test Monitoring

```bash
php artisan server:monitor --verbose
```

## Next Steps

- [Configuration Guide](../configuration/README.md) - Customize settings
- [Quick Start](quickstart.md) - 5-minute setup
- [Usage Examples](../examples/basic-setup.md) - Real-world examples