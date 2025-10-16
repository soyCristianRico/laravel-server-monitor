# Quick Start Guide

Get Laravel Server Monitor running in 5 minutes.

## 🚀 5-Minute Setup

### 1. Install Package (1 minute)

```bash
composer require soycristianrico/laravel-server-monitor
```

### 2. Publish Configuration (1 minute)

```bash
php artisan vendor:publish --provider="SoyCristianRico\LaravelServerMonitor\ServerMonitorServiceProvider"
```

### 3. Setup Admin User (1 minute)

```bash
# If you don't have spatie/laravel-permission
composer require spatie/laravel-permission
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

```php
// In tinker or a seeder
use Spatie\Permission\Models\Role;

Role::firstOrCreate(['name' => 'admin']);
auth()->user()->assignRole('admin');
```

### 4. Test Commands (1 minute)

```bash
# Test server monitoring
php artisan server:monitor

# Test security scan
php artisan security:check
```

### 5. Setup Automation (1 minute)

Add to `routes/console.php`:

```php
Schedule::command('server:monitor')->everyTenMinutes();
Schedule::command('security:check')->everyThirtyMinutes();
```

## ✅ Done!

You now have:
- ✅ Server resource monitoring
- ✅ Security scanning
- ✅ Email alerts to admin users
- ✅ Automated scheduling

## 🎯 Quick Test

```bash
# Force a test alert (if disk > 80%)
df -h

# Check logs
tail -f storage/logs/laravel.log

# View configuration
php artisan config:show server-monitor
```

## 📧 Email Setup

Ensure your Laravel mail is configured in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=monitoring@yourapp.com
```

## 🔧 Basic Customization

Edit `config/server-monitor.php`:

```php
'monitoring' => [
    'disk' => [
        'warning_threshold' => 85,    // Your preference
        'critical_threshold' => 95,   // Your preference
    ],
],
```

## 🆘 Quick Troubleshooting

**No alerts received?**
- Check admin user has 'admin' role
- Verify mail configuration
- Check logs: `storage/logs/laravel.log`

**Commands not found?**
- Run: `composer dump-autoload`
- Check: `php artisan list | grep server`

**Permission errors?**
- Ensure web server can execute system commands
- Check file permissions on storage directory

## 📚 Next Steps

- [Full Configuration Guide](../configuration/README.md)
- [Usage Examples](../examples/basic-setup.md)
- [Advanced Setup](../examples/advanced-setup.md)