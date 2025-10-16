<?php

use CristianDev\LaravelServerMonitor\Notifications\SecurityAlertNotification;
use CristianDev\LaravelServerMonitor\Services\Security\SecurityNotificationService;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\User;

describe('SecurityNotificationService', function () {
    beforeEach(function () {
        $this->service = new SecurityNotificationService();
        Notification::fake();

        // Ensure we have clean state - create basic roles if they don't exist
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'user']);
    });

    describe('sendAlerts', function () {
        it('returns false when alerts array is empty', function () {
            $result = $this->service->sendAlerts([]);

            expect($result)->toBeFalse();
            Notification::assertNothingSent();
        });

        it('sends notifications to admin users when alerts exist', function () {
            // Create admin user (role already exists from beforeEach)
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $alerts = [
                [
                    'type' => 'Test Alert',
                    'details' => 'Test details',
                ],
            ];

            $result = $this->service->sendAlerts($alerts);

            expect($result)->toBeTrue();
            Notification::assertSentTo($admin, SecurityAlertNotification::class);
        });

        it('returns false when no admin users exist', function () {
            // Ensure no admin users exist - just remove users, keep roles
            User::query()->delete();

            $alerts = [
                [
                    'type' => 'Test Alert',
                    'details' => 'Test details',
                ],
            ];

            $result = $this->service->sendAlerts($alerts);

            expect($result)->toBeFalse();
            Notification::assertNothingSent();
        });

        it('uses custom success and error messages', function () {
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $alerts = [['type' => 'Test', 'details' => 'Details']];
            $customSuccess = 'Custom success message';
            $customError = 'Custom error message';

            $result = $this->service->sendAlerts($alerts, $customSuccess, $customError);

            expect($result)->toBeTrue();
        });
    });

    describe('sendReport', function () {
        it('sends reports to admin users', function () {
            // Create admin user (role already exists from beforeEach)
            $admin = User::factory()->create();
            $admin->assignRole('admin');

            $alerts = [];
            $report = 'Daily security report';

            $result = $this->service->sendReport($alerts, $report);

            expect($result)->toBeTrue();
            Notification::assertSentTo($admin, SecurityAlertNotification::class);
        });

        it('returns false when no admin users exist', function () {
            // Ensure no admin users exist
            User::whereHas('roles', function ($query) {
                $query->where('name', 'admin');
            })->delete();

            $result = $this->service->sendReport([], 'Test report');

            expect($result)->toBeFalse();
            Notification::assertNothingSent();
        });
    });

    describe('logAlerts', function () {
        it('logs alerts without throwing errors', function () {
            $alerts = [
                ['type' => 'Test Alert', 'details' => 'Test details'],
            ];

            // This should not throw an exception
            $this->service->logAlerts($alerts, 'test_context');

            // If we get here, no exception was thrown
            expect(true)->toBeTrue();
        });
    });
});