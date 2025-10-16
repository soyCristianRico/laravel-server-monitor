<?php

namespace Tests;

use CristianDev\LaravelServerMonitor\ServerMonitorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup code if needed
    }

    protected function getPackageProviders($app)
    {
        return [
            ServerMonitorServiceProvider::class,
            PermissionServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up server monitor configuration for testing
        config()->set('server-monitor.monitoring.disk.warning_threshold', 80);
        config()->set('server-monitor.monitoring.disk.critical_threshold', 90);
        config()->set('server-monitor.monitoring.memory.warning_threshold', 80);
        config()->set('server-monitor.monitoring.memory.critical_threshold', 90);
        config()->set('server-monitor.monitoring.cpu.warning_threshold', 70);
        config()->set('server-monitor.monitoring.cpu.critical_threshold', 90);

        config()->set('server-monitor.notifications.admin_role', 'admin');
        config()->set('server-monitor.notifications.user_model', 'Tests\\Fixtures\\User');

        config()->set('server-monitor.security.whitelisted_users', ['forge', 'root', 'www-data']);
        config()->set('server-monitor.security.excluded_paths', ['vendor', 'node_modules', 'tests']);
        config()->set('server-monitor.security.whitelisted_security_files', []);
    }
}