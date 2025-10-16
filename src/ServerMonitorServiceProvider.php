<?php

namespace CristianDev\LaravelServerMonitor;

use CristianDev\LaravelServerMonitor\Console\Commands\Security\SecurityCheckCommand;
use CristianDev\LaravelServerMonitor\Console\Commands\Security\SecurityCheckMalwareCommand;
use CristianDev\LaravelServerMonitor\Console\Commands\Security\SecurityMonitorCrontabsCommand;
use CristianDev\LaravelServerMonitor\Console\Commands\ServerMonitorCommand;
use CristianDev\LaravelServerMonitor\Services\Security\SecurityNotificationService;
use CristianDev\LaravelServerMonitor\Services\Security\SecurityScannerService;
use CristianDev\LaravelServerMonitor\Services\ServerMonitoringService;
use Illuminate\Support\ServiceProvider;

class ServerMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/server-monitor.php', 'server-monitor');

        $this->app->singleton(ServerMonitoringService::class);
        $this->app->singleton(SecurityNotificationService::class);
        $this->app->singleton(SecurityScannerService::class);
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