# Common Issues and Solutions

Solutions to frequently encountered problems with Laravel Server Monitor.

## Installation Issues

### Commands Not Found After Installation

**Problem:**
```bash
php artisan server:monitor
# Command "server:monitor" is not defined.
```

**Solutions:**

1. **Clear caches:**
```bash
php artisan config:clear
php artisan cache:clear
composer dump-autoload
```

2. **Check service provider registration:**
```bash
php artisan list | grep -E "(server|security)"
```

3. **Verify composer installation:**
```bash
composer show soycristianrico/laravel-server-monitor
```

4. **Check Laravel version compatibility:**
```bash
php artisan --version
# Should be Laravel 10.x or 11.x
```

### Package Auto-Discovery Failed

**Problem:**
Service provider not automatically registered.

**Solution:**
Manually register in `config/app.php`:

```php
'providers' => [
    // Other providers...
    SoyCristianRico\LaravelServerMonitor\ServerMonitorServiceProvider::class,
],
```

## Configuration Issues

### No Admin Users Receiving Alerts

**Problem:**
Commands run successfully but no emails are sent.

**Solutions:**

1. **Check admin users exist:**
```bash
php artisan tinker
```
```php
use App\Models\User;
use Spatie\Permission\Models\Role;

// Check if admin role exists
Role::where('name', 'admin')->exists();

// Check users with admin role
User::role('admin')->get();
```

2. **Verify mail configuration:**
```bash
php artisan config:show mail
```

3. **Test email sending:**
```bash
php artisan tinker
```
```php
Mail::raw('Test email', function($message) {
    $message->to('your@email.com')->subject('Test');
});
```

4. **Check notification service:**
```php
// In tinker
$service = app(\SoyCristianRico\LaravelServerMonitor\Services\Security\SecurityNotificationService::class);
$service->sendAlerts([['type' => 'Test', 'details' => 'Test alert']]);
```

### Wrong User Model Configuration

**Problem:**
```
Class 'App\Models\User' not found
```

**Solution:**
Update configuration in `config/server-monitor.php`:

```php
'notifications' => [
    'user_model' => 'App\\User',  // Laravel 8 and below
    // or
    'user_model' => 'Your\\Custom\\UserModel',
],
```

## Permission Issues

### System Commands Fail

**Problem:**
```bash
php artisan server:monitor
# Shows 0% disk usage or empty results
```

**Solutions:**

1. **Check web server user permissions:**
```bash
# Test as web server user
sudo -u www-data df -h
sudo -u www-data free
sudo -u www-data uptime
```

2. **Verify commands are available:**
```bash
which df free uptime netstat pgrep
```

3. **Check PATH environment:**
```bash
# In your Laravel app
php artisan tinker
```
```php
echo getenv('PATH');
```

4. **Test specific commands:**
```bash
php -r "echo shell_exec('df -h');"
```

### Cache Directory Permissions

**Problem:**
```
Permission denied: storage/security_cache
```

**Solution:**
```bash
# Create directory with correct permissions
mkdir -p storage/security_cache
chmod 755 storage/security_cache
chown www-data:www-data storage/security_cache
```

## Monitoring Issues

### False Positive Disk Alerts

**Problem:**
Getting disk alerts for mounted filesystems or temporary mounts.

**Solution:**
Customize disk checking in your own service extension:

```php
// Create app/Services/CustomServerMonitoringService.php
use SoyCristianRico\LaravelServerMonitor\Services\ServerMonitoringService;

class CustomServerMonitoringService extends ServerMonitoringService
{
    protected function getDiskUsage(): int
    {
        // Only check root filesystem
        $output = shell_exec('df / | tail -1');

        if (preg_match('/(\d+)%/', $output, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }
}
```

Register in service provider:
```php
$this->app->bind(ServerMonitoringService::class, CustomServerMonitoringService::class);
```

### MySQL Service Check Fails

**Problem:**
MySQL shows as "not running" when it is running.

**Solutions:**

1. **Check process name:**
```bash
ps aux | grep mysql
# Look for process name: mysqld, mysql, mariadb
```

2. **Customize MySQL check:**
```php
protected function getMysqlStatus(): bool
{
    // Try multiple process names
    $processes = ['mysqld', 'mysql', 'mariadb'];

    foreach ($processes as $process) {
        $output = shell_exec("pgrep {$process}");
        if (!empty(trim($output))) {
            return true;
        }
    }

    return false;
}
```

## Security Scanning Issues

### Too Many False Positives

**Problem:**
Malware scanner reports legitimate files as suspicious.

**Solution:**
Update whitelist in `config/server-monitor.php`:

```php
'security' => [
    'whitelisted_security_files' => [
        'app/Services/PaymentService.php',         // Contains base64_decode
        'app/Http/Controllers/ApiController.php',  // Contains eval for API
        'vendor/package/SecurityHelper.php',       // Third-party security code
        'app/Helpers/CryptoHelper.php',           // Legitimate crypto functions
    ],

    'excluded_paths' => [
        'vendor',
        'node_modules',
        'tests',                    // Add if test files trigger alerts
        'database/seeders',         // Add if seeders contain test data
        'storage/app/backups',      // Add backup directories
    ],
],
```

### Scan Performance Issues

**Problem:**
Malware scanning takes too long or times out.

**Solutions:**

1. **Increase timeout (if using queue):**
```php
// In config/queue.php
'timeout' => 300,  // 5 minutes
```

2. **Exclude large directories:**
```php
'excluded_paths' => [
    'storage/app/uploads',
    'public/media',
    'storage/backups',
    'var/log',  // System logs
],
```

3. **Run during off-peak hours:**
```php
Schedule::command('security:check-malware')
    ->hourly()
    ->between('02:00', '06:00');
```

## Scheduling Issues

### Cron Jobs Not Running

**Problem:**
Scheduled commands don't execute automatically.

**Solutions:**

1. **Verify cron is configured:**
```bash
crontab -l
# Should show: * * * * * cd /path/to/project && php artisan schedule:run
```

2. **Check cron logs:**
```bash
tail -f /var/log/cron.log
grep CRON /var/log/syslog
```

3. **Test schedule manually:**
```bash
php artisan schedule:run
php artisan schedule:list
```

4. **Check user permissions:**
```bash
# Make sure cron runs as correct user
sudo crontab -u www-data -l
```

### Commands Running Too Frequently

**Problem:**
Getting too many alerts or high server load.

**Solution:**
Adjust scheduling in `routes/console.php`:

```php
// Reduce frequency
Schedule::command('server:monitor')->everyFifteenMinutes();  // Instead of every 10
Schedule::command('security:check')->hourly();               // Instead of every 30 min

// Add conditions
Schedule::command('security:check-malware')
    ->hourly()
    ->when(function () {
        // Only run if server load is low
        $load = sys_getloadavg()[0];
        return $load < 2.0;
    });
```

## Email Issues

### Emails Going to Spam

**Problem:**
Alert emails are marked as spam.

**Solutions:**

1. **Configure proper mail headers:**
```php
// In config/mail.php
'from' => [
    'address' => 'monitoring@yourdomain.com',  // Use your domain
    'name' => 'Server Monitor',
],
```

2. **Use authenticated SMTP:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourdomain.com
MAIL_USERNAME=monitoring@yourdomain.com
MAIL_PASSWORD=your-password
```

3. **Add SPF/DKIM records** to your domain DNS.

### No Emails Received

**Problem:**
Commands run successfully but no emails arrive.

**Debug steps:**

1. **Check Laravel logs:**
```bash
tail -f storage/logs/laravel.log | grep -i mail
```

2. **Test mail configuration:**
```bash
php artisan tinker
```
```php
Mail::raw('Test', function($message) {
    $message->to('test@example.com')->subject('Test');
});
```

3. **Check queue if using:**
```bash
php artisan queue:work --once
```

## Performance Issues

### High Memory Usage

**Problem:**
Monitoring commands consume too much memory.

**Solutions:**

1. **Reduce scan scope:**
```php
'excluded_paths' => [
    'storage/app/large-files',
    'public/uploads',
    'var/cache',
],
```

2. **Process files in chunks** (custom implementation):
```php
// In custom scanner service
public function scanDirectory($directory)
{
    $files = glob("$directory/*.php");

    foreach (array_chunk($files, 100) as $chunk) {
        $this->processChunk($chunk);
        // Free memory between chunks
        gc_collect_cycles();
    }
}
```

## Getting Help

### Enable Debug Mode

```bash
# Run with maximum verbosity
php artisan server:monitor -vvv

# Check detailed logs
tail -f storage/logs/laravel.log
```

### Collect System Information

```bash
# System info
uname -a
php --version
df -h
free -h
ps aux | head -20

# Laravel info
php artisan --version
php artisan config:show server-monitor
```

### Report Issues

When reporting issues, include:

1. Laravel version
2. PHP version
3. Server OS
4. Complete error messages
5. Configuration (remove sensitive data)
6. Steps to reproduce

## Next Steps

- [Performance Optimization](performance.md)
- [Security Considerations](security.md)
- [Advanced Configuration](../configuration/advanced.md)