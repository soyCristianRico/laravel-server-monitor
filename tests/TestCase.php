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
    }

    protected function defineDatabaseMigrations()
    {
        // Load default Laravel migrations (users table)
        $this->loadLaravelMigrations();

        // Create the permission tables manually for testing
        $this->createPermissionTables();
    }

    protected function createPermissionTables()
    {
        \Illuminate\Support\Facades\Schema::create('permissions', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        \Illuminate\Support\Facades\Schema::create('roles', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        \Illuminate\Support\Facades\Schema::create('model_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        \Illuminate\Support\Facades\Schema::create('model_has_roles', function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        \Illuminate\Support\Facades\Schema::create('role_has_permissions', function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
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

        // Configure Spatie Permission defaults
        config()->set('permission.models.permission', \Spatie\Permission\Models\Permission::class);
        config()->set('permission.models.role', \Spatie\Permission\Models\Role::class);
        config()->set('permission.table_names.roles', 'roles');
        config()->set('permission.table_names.permissions', 'permissions');
        config()->set('permission.table_names.model_has_permissions', 'model_has_permissions');
        config()->set('permission.table_names.model_has_roles', 'model_has_roles');
        config()->set('permission.table_names.role_has_permissions', 'role_has_permissions');

        // Set up auth guard for permissions
        config()->set('auth.defaults.guard', 'web');
        config()->set('auth.guards.web.driver', 'session');
        config()->set('auth.guards.web.provider', 'users');
        config()->set('auth.providers.users.driver', 'eloquent');
        config()->set('auth.providers.users.model', 'Tests\\Fixtures\\User');
    }
}