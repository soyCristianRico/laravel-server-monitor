<?php

namespace CristianDev\LaravelServerMonitor\Services\Security;

use CristianDev\LaravelServerMonitor\Notifications\SecurityAlertNotification;
use Illuminate\Support\Facades\Log;

class SecurityNotificationService
{
    public function sendAlerts(array $alerts, string $successMessage = 'Security issues found!', string $errorMessage = 'Security issues found but no admin users to notify!'): bool
    {
        if (empty($alerts)) {
            return false;
        }

        $notificationsSent = false;
        $adminRoleName = config('server-monitor.notifications.admin_role', 'admin');
        $userModel = config('server-monitor.notifications.user_model', \App\Models\User::class);

        if (class_exists($userModel) && method_exists($userModel, 'role')) {
            $userModel::role($adminRoleName)->each(function ($admin) use ($alerts, &$notificationsSent) {
                $admin->notify(new SecurityAlertNotification($alerts));
                $notificationsSent = true;
            });
        }

        if (! $notificationsSent) {
            Log::error($errorMessage, ['alerts_count' => count($alerts)]);
        }

        return $notificationsSent;
    }

    public function sendReport(array $alerts = [], ?string $report = null): bool
    {
        $notificationsSent = false;
        $adminRoleName = config('server-monitor.notifications.admin_role', 'admin');
        $userModel = config('server-monitor.notifications.user_model', \App\Models\User::class);

        if (class_exists($userModel) && method_exists($userModel, 'role')) {
            $userModel::role($adminRoleName)->each(function ($admin) use ($alerts, $report, &$notificationsSent) {
                $admin->notify(new SecurityAlertNotification($alerts, $report));
                $notificationsSent = true;
            });
        }

        return $notificationsSent;
    }

    public function logAlerts(array $alerts, string $context = 'security_check'): void
    {
        Log::info("Security check completed: $context", [
            'alerts_count' => count($alerts),
            'alerts' => $alerts,
            'timestamp' => now(),
        ]);
    }
}