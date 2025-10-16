<?php

namespace CristianDev\LaravelServerMonitor\Traits;

use CristianDev\LaravelServerMonitor\Services\Security\SecurityNotificationService;

trait NotifiesSecurityAlerts
{
    protected SecurityNotificationService $notificationService;

    protected function initializeNotificationService(): void
    {
        $this->notificationService = app(SecurityNotificationService::class);
    }

    protected function sendSecurityAlerts(array $alerts, ?string $successMessage = null, ?string $errorMessage = null): bool
    {
        if (empty($alerts)) {
            return false;
        }

        $successMessage = $successMessage ?? 'Security issues found! Alerts sent.';
        $errorMessage = $errorMessage ?? 'Security issues found! No admin users found to notify.';

        $sent = $this->notificationService->sendAlerts($alerts, $successMessage, $errorMessage);

        if ($sent) {
            $this->error($successMessage);
        } else {
            $this->error($errorMessage);
        }

        $this->notificationService->logAlerts($alerts, $this->signature ?? 'security_check');

        return $sent;
    }

    protected function sendSecurityReport(array $alerts = [], ?string $report = null): bool
    {
        return $this->notificationService->sendReport($alerts, $report);
    }
}