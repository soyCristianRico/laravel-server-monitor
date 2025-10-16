<?php

namespace SoyCristianRico\LaravelServerMonitor\Console\Commands\Security;

use SoyCristianRico\LaravelServerMonitor\Services\Security\SecurityScannerService;
use SoyCristianRico\LaravelServerMonitor\Traits\NotifiesSecurityAlerts;
use Illuminate\Console\Command;

class SecurityCheckCommand extends Command
{
    use NotifiesSecurityAlerts;

    protected SecurityScannerService $scanner;

    protected $signature = 'security:check';

    protected $description = 'Run security checks and send alerts if issues are found';

    public function handle()
    {
        $this->initializeNotificationService();
        $this->scanner = app(SecurityScannerService::class);

        $alerts = [];

        if ($alert = $this->scanner->checkSuspiciousProcesses()) {
            $alerts[] = $alert;
            $this->error('Suspicious processes detected!');
        }

        $portAlerts = $this->scanner->checkSuspiciousPorts();
        if (! empty($portAlerts)) {
            $alerts = array_merge($alerts, $portAlerts);
            $this->error('Suspicious network ports detected!');
        }

        if ($alert = $this->scanner->checkCrontabModifications()) {
            $alerts[] = $alert;
            $this->error('Recent crontab modifications detected!');
        }

        if ($alert = $this->scanner->checkFailedLogins()) {
            $alerts[] = $alert;
            $this->error('High number of failed login attempts detected!');
        }

        if ($alert = $this->scanner->checkNewUsers()) {
            $alerts[] = $alert;
            $this->error('Recently created users detected!');
        }

        if ($alert = $this->scanner->checkModifiedSystemFiles()) {
            $alerts[] = $alert;
            $this->error('Modified system files detected!');
        }

        if ($alert = $this->scanner->checkUnauthorizedSSHKeys()) {
            $alerts[] = $alert;
            $this->error('Recently modified SSH keys detected!');
        }

        if ($alert = $this->scanner->checkLargeFiles()) {
            $alerts[] = $alert;
            $this->error('Large files detected!');
        }

        if (! empty($alerts)) {
            $this->sendSecurityAlerts($alerts);

            return 1;
        }

        $this->info('✅ No security issues detected');

        return 0;
    }
}