<?php

use CristianDev\LaravelServerMonitor\Services\Security\SecurityNotificationService;
use CristianDev\LaravelServerMonitor\Traits\NotifiesSecurityAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\User;

// Test command class that uses the trait
class TestSecurityCommand extends Command
{
    use NotifiesSecurityAlerts;

    protected $signature = 'test:security';

    protected $description = 'Test command for testing the trait';

    public function testSendAlerts($alerts)
    {
        $this->initializeNotificationService();

        return $this->sendSecurityAlerts($alerts);
    }

    public function testSendReport($alerts = [], $report = null)
    {
        $this->initializeNotificationService();

        return $this->sendSecurityReport($alerts, $report);
    }

    // Override error method to prevent output issues in tests
    public function error($string, $verbosity = null)
    {
        // Do nothing in tests to avoid writeln() on null errors
        return $this;
    }
}

describe('NotifiesSecurityAlerts Trait', function () {
    beforeEach(function () {
        Notification::fake();
    });

    describe('sendSecurityAlerts', function () {
        it('returns false when no alerts provided', function () {
            $command = new TestSecurityCommand();
            $result = $command->testSendAlerts([]);

            expect($result)->toBeFalse();
        });

        it('sends alerts when admin users exist', function () {
            // Create admin role and user
            $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $alerts = [
                [
                    'type' => 'Test Alert',
                    'details' => 'Test details',
                ],
            ];

            $command = new TestSecurityCommand();
            $result = $command->testSendAlerts($alerts);

            expect($result)->toBeTrue();
        });

        it('returns false when no admin users exist', function () {
            // Ensure no admin users exist
            User::whereHas('roles', function ($query) {
                $query->where('name', 'admin');
            })->delete();

            $alerts = [
                [
                    'type' => 'Test Alert',
                    'details' => 'Test details',
                ],
            ];

            $command = new TestSecurityCommand();
            $result = $command->testSendAlerts($alerts);

            expect($result)->toBeFalse();
        });
    });

    describe('sendSecurityReport', function () {
        it('sends reports to admin users', function () {
            // Create admin role and user
            $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $command = new TestSecurityCommand();
            $result = $command->testSendReport([], 'Test report');

            expect($result)->toBeTrue();
        });

        it('returns false when no admin users exist', function () {
            // Ensure no admin users exist
            User::whereHas('roles', function ($query) {
                $query->where('name', 'admin');
            })->delete();

            $command = new TestSecurityCommand();
            $result = $command->testSendReport([], 'Test report');

            expect($result)->toBeFalse();
        });
    });

    describe('initializeNotificationService', function () {
        it('initializes notification service correctly', function () {
            // Mock the app container to return a mock service
            $mockService = Mockery::mock(SecurityNotificationService::class);
            app()->instance(SecurityNotificationService::class, $mockService);

            // This should not throw any errors - just call it and verify it works
            $command = new TestSecurityCommand();
            $result = $command->testSendAlerts([]);

            // Should return false for empty alerts array
            expect($result)->toBeFalse();
        });
    });
});