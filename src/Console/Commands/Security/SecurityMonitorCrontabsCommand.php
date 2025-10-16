<?php

namespace CristianDev\LaravelServerMonitor\Console\Commands\Security;

use CristianDev\LaravelServerMonitor\Services\Security\SecurityScannerService;
use CristianDev\LaravelServerMonitor\Traits\NotifiesSecurityAlerts;
use Illuminate\Console\Command;

class SecurityMonitorCrontabsCommand extends Command
{
    use NotifiesSecurityAlerts;

    protected $signature = 'security:monitor-crontabs';

    protected $description = 'Monitor crontab modifications for security threats';

    public function handle(): int
    {
        $this->initializeNotificationService();
        $scanner = app(SecurityScannerService::class);

        $this->info('Monitoring crontab modifications...');

        $alerts = [];

        if ($alert = $scanner->checkCrontabModifications()) {
            $alerts[] = $alert;
            $this->error('Recent crontab modifications detected!');
            $this->line($alert['details']);
        }

        if (! empty($alerts)) {
            $this->sendSecurityAlerts($alerts,
                'Crontab modifications detected! Security team notified.',
                'Crontab modifications detected but no admin users found to notify.'
            );

            return 1;
        }

        $this->info('✅ No recent crontab modifications detected');

        return 0;
    }
}