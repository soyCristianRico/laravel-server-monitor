<?php

namespace SoyCristianRico\LaravelServerMonitor\Console\Commands;

use SoyCristianRico\LaravelServerMonitor\Services\ServerMonitoringService;
use SoyCristianRico\LaravelServerMonitor\Traits\NotifiesSecurityAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ServerMonitorCommand extends Command
{
    use NotifiesSecurityAlerts;

    protected $signature = 'server:monitor';

    protected $description = 'Monitor server resources (CPU, memory, disk space, MySQL)';

    public function __construct(
        protected ServerMonitoringService $monitoringService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting server monitoring...');

        $checks = $this->monitoringService->runAllChecks();
        $alerts = $this->monitoringService->getAlerts($checks);

        $this->displayResults($checks);

        if (! empty($alerts)) {
            $this->sendServerAlerts($alerts);
            Log::warning('Server monitoring alerts', ['alerts' => $alerts]);

            return 1;
        }

        $this->info('✅ All server checks passed');

        return 0;
    }

    protected function displayResults(array $checks): void
    {
        foreach ($checks as $name => $check) {
            // Skip if check is not a properly formatted array
            if (! is_array($check) || ! isset($check['status'], $check['message'])) {
                continue;
            }

            $icon = match ($check['status']) {
                'ok' => '✅',
                'warning' => '🟡',
                'critical' => '🔴',
                default => '❓'
            };

            if ($check['status'] === 'ok') {
                $this->info("{$icon} {$check['message']}");
            } else {
                $this->error("{$icon} {$check['message']}");
            }
        }
    }

    protected function sendServerAlerts(array $alerts): void
    {
        if (! isset($this->notificationService)) {
            $this->initializeNotificationService();
        }

        $this->sendSecurityAlerts($alerts,
            'Server monitoring alerts sent successfully!',
            'Server monitoring alerts detected but no admin users found to notify.'
        );
    }
}