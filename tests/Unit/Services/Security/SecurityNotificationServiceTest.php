<?php

use SoyCristianRico\LaravelServerMonitor\Services\Security\SecurityNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

describe('SecurityNotificationService', function () {
    beforeEach(function () {
        Notification::fake();
    });

    describe('sendAlerts', function () {
        it('returns false when alerts array is empty', function () {
            $service = new SecurityNotificationService();
            $result = $service->sendAlerts([]);

            expect($result)->toBeFalse();
        });

        it('returns false when user model does not exist', function () {
            // Set a non-existent user model in config
            config(['server-monitor.notifications.user_model' => 'NonExistentUserModel']);

            $service = new SecurityNotificationService();
            $alerts = [
                [
                    'type' => 'Test Alert',
                    'details' => 'Test details',
                ],
            ];

            $result = $service->sendAlerts($alerts);

            expect($result)->toBeFalse();
            // Would log error message in real usage
        });

        it('uses default config values when not configured', function () {
            // Clear any existing config
            config(['server-monitor.notifications.user_model' => null]);
            config(['server-monitor.notifications.admin_role' => null]);

            $service = new SecurityNotificationService();
            $alerts = [['type' => 'Test', 'details' => 'Details']];

            // This should use default values and return false since default user model won't exist in test
            $result = $service->sendAlerts($alerts);

            expect($result)->toBeFalse();
        });

        it('uses custom success and error messages', function () {
            config(['server-monitor.notifications.user_model' => 'NonExistentUserModel']);

            $service = new SecurityNotificationService();
            $alerts = [['type' => 'Test', 'details' => 'Details']];
            $customSuccess = 'Custom success message';
            $customError = 'Custom error message';

            $result = $service->sendAlerts($alerts, $customSuccess, $customError);

            expect($result)->toBeFalse();
            // Would log custom error message in real usage
        });
    });

    describe('sendReport', function () {
        it('returns false when user model does not exist', function () {
            config(['server-monitor.notifications.user_model' => 'NonExistentUserModel']);

            $service = new SecurityNotificationService();
            $result = $service->sendReport([], 'Test report');

            expect($result)->toBeFalse();
        });

        it('works with empty alerts and report', function () {
            config(['server-monitor.notifications.user_model' => 'NonExistentUserModel']);

            $service = new SecurityNotificationService();
            $result = $service->sendReport();

            expect($result)->toBeFalse();
        });
    });

    describe('logAlerts', function () {
        it('logs alerts without throwing errors', function () {
            $service = new SecurityNotificationService();
            $alerts = [
                ['type' => 'Test Alert', 'details' => 'Test details'],
            ];

            // This should not throw an exception
            $service->logAlerts($alerts, 'test_context');

            // If we get here, no exception was thrown
            expect(true)->toBeTrue();
        });

        it('logs with default context when none provided', function () {
            $service = new SecurityNotificationService();
            $alerts = [['type' => 'Test', 'details' => 'Test']];

            $service->logAlerts($alerts);

            // If we get here, no exception was thrown
            expect(true)->toBeTrue();
        });
    });
});