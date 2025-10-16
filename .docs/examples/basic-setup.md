# Basic Setup Example

Real-world example of setting up Laravel Server Monitor in a typical Laravel application.

## Scenario

You have a Laravel application running on a VPS with:
- Ubuntu 20.04 LTS
- Laravel 11.x
- MySQL database
- Nginx web server
- Small team (2-3 developers with admin access)

## Step-by-Step Setup

### 1. Install Package

```bash
cd /var/www/your-laravel-app
composer require cristian-dev/laravel-server-monitor
```

### 2. Install Dependencies

```bash
# Install spatie/laravel-permission if not already installed
composer require spatie/laravel-permission

# Publish and migrate
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

### 3. Publish Configuration

```bash
php artisan vendor:publish --provider="CristianDev\LaravelServerMonitor\ServerMonitorServiceProvider"
```

### 4. Configure Environment Variables

Add to your `.env`:

```env
# Server Monitor Settings
SERVER_MONITOR_DISK_WARNING=85
SERVER_MONITOR_DISK_CRITICAL=95
SERVER_MONITOR_MEMORY_WARNING=80
SERVER_MONITOR_MEMORY_CRITICAL=90
SERVER_MONITOR_CPU_WARNING=75
SERVER_MONITOR_CPU_CRITICAL=90

# Notification Settings
SERVER_MONITOR_ADMIN_ROLE=admin
SERVER_MONITOR_USER_MODEL="App\\Models\\User"

# Mail settings (if not already configured)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=alerts@yourapp.com
MAIL_FROM_NAME="Your App Monitoring"
```

### 5. Create Admin Users

```php
// In database/seeders/AdminSeeder.php
use App\Models\User;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run()
    {
        // Create admin role
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        // Create or find admin users
        $adminEmails = [
            'admin@yourapp.com',
            'dev@yourapp.com',
            'alerts@yourapp.com',
        ];

        foreach ($adminEmails as $email) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => 'Admin User',
                    'password' => bcrypt('secure-password'),
                    'email_verified_at' => now(),
                ]
            );

            $user->assignRole($adminRole);
        }
    }
}
```

Run the seeder:

```bash
php artisan db:seed --class=AdminSeeder
```

### 6. Customize Configuration

Edit `config/server-monitor.php`:

```php
<?php

return [
    'monitoring' => [
        'disk' => [
            'warning_threshold' => env('SERVER_MONITOR_DISK_WARNING', 85),
            'critical_threshold' => env('SERVER_MONITOR_DISK_CRITICAL', 95),
        ],
        'memory' => [
            'warning_threshold' => env('SERVER_MONITOR_MEMORY_WARNING', 80),
            'critical_threshold' => env('SERVER_MONITOR_MEMORY_CRITICAL', 90),
        ],
        'cpu' => [
            'warning_threshold' => env('SERVER_MONITOR_CPU_WARNING', 75),
            'critical_threshold' => env('SERVER_MONITOR_CPU_CRITICAL', 90),
        ],
    ],

    'notifications' => [
        'admin_role' => env('SERVER_MONITOR_ADMIN_ROLE', 'admin'),
        'user_model' => env('SERVER_MONITOR_USER_MODEL', 'App\\Models\\User'),
        'channels' => ['mail'],
    ],

    'security' => [
        'whitelisted_users' => [
            'forge',
            'root',
            'www-data',
            'ubuntu',      // Add your server user
            'deploy',      // Add your deployment user
        ],

        'whitelisted_directories' => [
            '/home/forge',
            '/home/ubuntu',     // Add your user directories
            '/home/deploy',
        ],

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
            'public/uploads',   // Add your upload directories
        ],

        'whitelisted_security_files' => [
            // Add your security files if any
            'app/Http/Middleware/CustomSecurityMiddleware.php',
        ],
    ],
];
```

### 7. Setup Scheduling

Add to `routes/console.php`:

```php
<?php

use Illuminate\Support\Facades\Schedule;

// Existing schedules...

// Server monitoring every 10 minutes
Schedule::command('server:monitor')->everyTenMinutes();

// Security checks every 30 minutes
Schedule::command('security:check')->everyThirtyMinutes();

// Malware scanning every 2 hours
Schedule::command('security:check-malware')->cron('0 */2 * * *');

// Crontab monitoring every 5 minutes
Schedule::command('security:monitor-crontabs')->everyFiveMinutes();
```

Make sure your cron is configured:

```bash
# Add to crontab (crontab -e)
* * * * * cd /var/www/your-laravel-app && php artisan schedule:run >> /dev/null 2>&1
```

### 8. Test the Setup

```bash
# Test server monitoring
php artisan server:monitor

# Test security checks
php artisan security:check

# Test malware scanning
php artisan security:check-malware

# Check scheduled tasks
php artisan schedule:list
```

### 9. Verify Email Alerts

Force an alert to test email delivery:

```bash
# Temporarily lower disk threshold to trigger alert
php artisan tinker
config(['server-monitor.monitoring.disk.warning_threshold' => 10]);
exit

# Run monitoring (should trigger alert if disk > 10%)
php artisan server:monitor

# Check logs
tail -f storage/logs/laravel.log
```

## Expected Results

After setup, you should have:

1. **Automated Monitoring**: Commands running automatically via cron
2. **Email Alerts**: Admin users receive emails when issues are detected
3. **Logging**: All events logged to `storage/logs/laravel.log`
4. **Dashboard Ready**: Ready to add monitoring dashboard if needed

## Sample Alert Email

When issues are detected, admin users receive emails like:

```
Subject: Security Alert: 2 issue(s) detected

Security Alert

The following security issues have been detected on your server:

**Suspicious Network Ports**
Port 4444 listening on 0.0.0.0:4444
---

**High Disk Usage**
Disk usage: 92% on /dev/sda1
---

Please review these alerts immediately and take appropriate action.

This is an automated security monitoring message.
```

## Monitoring Dashboard (Optional)

You can check monitoring status programmatically:

```php
// In a controller or command
use CristianDev\LaravelServerMonitor\Services\ServerMonitoringService;

$monitor = app(ServerMonitoringService::class);
$checks = $monitor->runAllChecks();

foreach ($checks as $type => $check) {
    echo "{$type}: {$check['status']} - {$check['message']}\n";
}
```

## Next Steps

- [Advanced Setup](advanced-setup.md) - Production optimization
- [Custom Integrations](integrations.md) - Slack, dashboards, etc.
- [Troubleshooting](../troubleshooting/common-issues.md) - Common issues