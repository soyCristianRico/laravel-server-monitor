<?php

namespace SoyCristianRico\LaravelServerMonitor;

use SoyCristianRico\LaravelServerMonitor\Console\Commands\Security\SecurityBackupCrontabsCommand;
use SoyCristianRico\LaravelServerMonitor\Console\Commands\Security\SecurityCheckCommand;
use SoyCristianRico\LaravelServerMonitor\Console\Commands\Security\SecurityCheckMalwareCommand;
use SoyCristianRico\LaravelServerMonitor\Console\Commands\Security\SecurityComprehensiveCheckCommand;
use SoyCristianRico\LaravelServerMonitor\Console\Commands\Security\SecurityMonitorCrontabsCommand;
use SoyCristianRico\LaravelServerMonitor\Console\Commands\ServerMonitorCommand;
use SoyCristianRico\LaravelServerMonitor\Services\Security\SecurityBackupService;
use SoyCristianRico\LaravelServerMonitor\Services\Security\SecurityNotificationService;
use SoyCristianRico\LaravelServerMonitor\Services\Security\SecurityScannerService;
use SoyCristianRico\LaravelServerMonitor\Services\ServerMonitoringService;
use Illuminate\Support\ServiceProvider;

class ServerMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/server-monitor.php', 'server-monitor');

        $this->app->singleton(ServerMonitoringService::class);
        $this->app->singleton(SecurityNotificationService::class);
        $this->app->singleton(SecurityScannerService::class);
        $this->app->singleton(SecurityBackupService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/Config/server-monitor.php' => config_path('server-monitor.php'),
            ], 'server-monitor-config');

            $this->commands([
                ServerMonitorCommand::class,
                SecurityCheckCommand::class,
                SecurityCheckMalwareCommand::class,
                SecurityMonitorCrontabsCommand::class,
                SecurityBackupCrontabsCommand::class,
                SecurityComprehensiveCheckCommand::class,
            ]);
        }

        $this->loadViewsFrom(__DIR__ . '/resources/views', 'server-monitor');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/resources/views' => resource_path('views/vendor/server-monitor'),
            ], 'server-monitor-views');
        }
    }
}