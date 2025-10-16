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
    */
    'scheduling' => [
        'server_monitor' => 'everyTenMinutes',
        'security_check' => 'everyThirtyMinutes',
        'malware_check' => 'hourly',
        'crontab_monitor' => 'everyFiveMinutes',
    ],
];