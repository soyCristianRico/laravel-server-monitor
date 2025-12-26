<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Server Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for server monitoring and security
    | checks. You can customize thresholds, notification settings, and
    | security scanning options.
    |
    */

    'monitoring' => [
        /*
        |--------------------------------------------------------------------------
        | Disk Space Monitoring
        |--------------------------------------------------------------------------
        */
        'disk' => [
            'warning_threshold' => env('SERVER_MONITOR_DISK_WARNING', 80),
            'critical_threshold' => env('SERVER_MONITOR_DISK_CRITICAL', 90),
        ],

        /*
        |--------------------------------------------------------------------------
        | Memory Usage Monitoring
        |--------------------------------------------------------------------------
        */
        'memory' => [
            'warning_threshold' => env('SERVER_MONITOR_MEMORY_WARNING', 80),
            'critical_threshold' => env('SERVER_MONITOR_MEMORY_CRITICAL', 90),
        ],

        /*
        |--------------------------------------------------------------------------
        | CPU Load Monitoring
        |--------------------------------------------------------------------------
        */
        'cpu' => [
            'warning_threshold' => env('SERVER_MONITOR_CPU_WARNING', 70),
            'critical_threshold' => env('SERVER_MONITOR_CPU_CRITICAL', 90),
        ],

        /*
        |--------------------------------------------------------------------------
        | Swap Usage Monitoring
        |--------------------------------------------------------------------------
        |
        | Smart swap monitoring that considers both swap percentage and available RAM.
        |
        | The system uses intelligent logic to avoid false positives:
        | - Normal swap usage (0-60%) with plenty of available RAM is considered OK
        | - Only alerts when swap usage indicates real memory pressure
        | - Memory pressure is detected when less than 15% RAM is available
        |
        | Thresholds work as follows:
        | - WARNING: threshold% swap usage WITH memory pressure, OR >60% swap usage
        | - CRITICAL: threshold% swap usage WITH memory pressure, OR >80% swap usage
        |
        */
        'swap' => [
            'warning_threshold' => env('SERVER_MONITOR_SWAP_WARNING', 20),
            'critical_threshold' => env('SERVER_MONITOR_SWAP_CRITICAL', 50),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        /*
        |--------------------------------------------------------------------------
        | Admin Role
        |--------------------------------------------------------------------------
        |
        | The role that should receive security and monitoring notifications.
        | Users with this role will receive email alerts when issues are detected.
        |
        */
        'admin_role' => env('SERVER_MONITOR_ADMIN_ROLE', 'admin'),

        /*
        |--------------------------------------------------------------------------
        | User Model
        |--------------------------------------------------------------------------
        |
        | The fully qualified class name of your User model.
        |
        */
        'user_model' => env('SERVER_MONITOR_USER_MODEL', 'App\\Models\\User'),

        /*
        |--------------------------------------------------------------------------
        | Notification Channels
        |--------------------------------------------------------------------------
        |
        | Available channels: mail, slack, teams, etc.
        | Currently only mail is implemented.
        |
        */
        'channels' => ['mail'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */
    'security' => [
        /*
        |--------------------------------------------------------------------------
        | Whitelisted Users
        |--------------------------------------------------------------------------
        |
        | System users that should be ignored during security scans.
        |
        */
        'whitelisted_users' => [
            'forge',
            'root',
            'www-data',
            'mysql',
            'redis',
            'nobody',
        ],

        /*
        |--------------------------------------------------------------------------
        | Whitelisted Directories
        |--------------------------------------------------------------------------
        |
        | User directories that should be ignored during security scans.
        |
        */
        'whitelisted_directories' => [
            '/home/forge',
            '/home/root',
            '/var/www',
        ],

        /*
        |--------------------------------------------------------------------------
        | Excluded Paths for Malware Scanning
        |--------------------------------------------------------------------------
        |
        | Directories to exclude from malware pattern scanning.
        |
        */
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
        ],

        /*
        |--------------------------------------------------------------------------
        | Whitelisted Security Files
        |--------------------------------------------------------------------------
        |
        | Files that may contain legitimate security-related patterns and should
        | be excluded from malware pattern detection.
        |
        */
        'whitelisted_security_files' => [
            // Add your security-related files here
            // Example: 'app/Services/Security/SecurityService.php',
            'src/Services/Security/SecurityScannerService.php',
        ],

        /*
        |--------------------------------------------------------------------------
        | Whitelisted Processes
        |--------------------------------------------------------------------------
        |
        | Process names that should be ignored during security scans.
        | These are legitimate system processes.
        |
        */
        'whitelisted_processes' => [
            'php-fpm',
            'php-fpm: master',
            'php-fpm: pool',
        ],

        /*
        |--------------------------------------------------------------------------
        | Excluded Vendor Paths
        |--------------------------------------------------------------------------
        |
        | Vendor directory patterns that are legitimate and should not trigger alerts.
        | These are common in framework packages.
        |
        */
        'excluded_vendor_patterns' => [
            '/Resources/assets',
            '/Resources/views',
            '/Resources/lang',
            '/tests/fixtures',
            '/tests/stubs',
        ],

        /*
        |--------------------------------------------------------------------------
        | Enhanced Security Detection Options
        |--------------------------------------------------------------------------
        |
        | Enable/disable specific security detection features.
        |
        */
        'detections' => [
            'suspicious_processes' => env('SERVER_MONITOR_CHECK_PROCESSES', true),
            'suspicious_uploads' => env('SERVER_MONITOR_CHECK_UPLOADS', true),
            'suspicious_htaccess' => env('SERVER_MONITOR_CHECK_HTACCESS', true),
            'fake_image_files' => env('SERVER_MONITOR_CHECK_FAKE_IMAGES', true),
            'file_integrity' => env('SERVER_MONITOR_CHECK_INTEGRITY', true),
            'malware_patterns' => env('SERVER_MONITOR_CHECK_MALWARE', true),
        ],

        /*
        |--------------------------------------------------------------------------
        | Detection Frequency Strategy
        |--------------------------------------------------------------------------
        |
        | How often to run different security checks for optimal threat detection.
        |
        | CRITICAL (every 15 min via security:check):
        | - PHP processes in /tmp/
        | - PHP files in storage/uploads/
        | - Suspicious files in public/
        | - File integrity (index.php, artisan)
        | - Malicious .htaccess files
        | - Fake image files with PHP
        |
        | IMPORTANT (every 30 min):
        | - Malware pattern scanning
        | - Crontab modifications
        |
        | ROUTINE (daily):
        | - Failed login analysis
        | - Large file detection
        | - SSH key modifications
        | - System file changes
        |
        */
        'frequencies' => [
            'critical_attack_detection' => env('SERVER_MONITOR_FREQ_CRITICAL', 15),   // security:check
            'malware_patterns' => env('SERVER_MONITOR_FREQ_MALWARE', 30),           // malware:check
            'crontab_monitoring' => env('SERVER_MONITOR_FREQ_CRONTAB', 5),          // crontab:monitor
            'comprehensive_scan' => env('SERVER_MONITOR_FREQ_COMPREHENSIVE', 1440), // daily (1440 min)
        ],

        /*
        |--------------------------------------------------------------------------
        | Critical File Monitoring
        |--------------------------------------------------------------------------
        |
        | Laravel files that should be monitored for unauthorized changes.
        |
        */
        'critical_files' => [
            'public/index.php',
            'bootstrap/app.php',
            'artisan',
            'composer.json',
            'composer.lock',
            '.env',
        ],

        /*
        |--------------------------------------------------------------------------
        | Upload Directory Protection
        |--------------------------------------------------------------------------
        |
        | Directories where PHP files should never be allowed.
        |
        */
        'protected_upload_dirs' => [
            'storage/app/public',
            'public/uploads',
            'public/images',
            'public/files',
            'public/documents',
            'public/media',
        ],

        /*
        |--------------------------------------------------------------------------
        | Scan Cache Duration
        |--------------------------------------------------------------------------
        |
        | Duration in minutes to cache scan results to avoid duplicate alerts.
        |
        */
        'scan_cache_duration' => env('SERVER_MONITOR_CACHE_DURATION', 60),

        /*
        |--------------------------------------------------------------------------
        | Alert Cooldown
        |--------------------------------------------------------------------------
        |
        | Minimum time in minutes between alerts for the same files.
        |
        */
        'alert_cooldown' => env('SERVER_MONITOR_ALERT_COOLDOWN', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how often various monitoring tasks should run.
    | These are suggestions - you can customize in your console routes.
    |
    | IMPORTANT: security:check now includes critical attack detections
    | and should run frequently (every 15 minutes) for fast threat response.
    |
    */
    'scheduling' => [
        'server_monitor' => 'everyTenMinutes',
        'security_check' => 'everyFifteenMinutes',        // CRITICAL: includes upload/process detection
        'malware_check' => 'everyThirtyMinutes',
        'crontab_monitor' => 'everyFiveMinutes',
        'comprehensive_check' => 'dailyAt("04:30")',      // Daily summary + non-critical checks
    ],
];