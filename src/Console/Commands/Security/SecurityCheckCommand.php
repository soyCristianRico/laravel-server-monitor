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

        // NEW: Check for suspicious PHP processes
        if ($phpProcessAlerts = $this->scanner->checkSuspiciousPhpProcesses()) {
            $alerts = array_merge($alerts, $phpProcessAlerts);
            $this->error('Suspicious PHP processes detected!');
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

        $this->info('Running enhanced security checks...');

        $alerts = array_merge($alerts, $this->checkForSuspiciousUploads());
        $alerts = array_merge($alerts, $this->checkForSuspiciousHtaccessFiles());
        $alerts = array_merge($alerts, $this->checkForFakeImageFiles());
        $alerts = array_merge($alerts, $this->checkForFileIntegrityViolations());

        if (! empty($alerts)) {
            $this->sendSecurityAlerts($alerts);

            return 1;
        }

        $this->info('✅ No security issues detected');

        return 0;
    }

    private function checkForSuspiciousUploads(): array
    {
        if ($uploadAlerts = $this->scanner->checkSuspiciousUploads()) {
            $this->error('Suspicious file uploads detected!');
            return $uploadAlerts;
        }

        return [];
    }

    private function checkForSuspiciousHtaccessFiles(): array
    {
        if ($htaccessAlerts = $this->scanner->checkSuspiciousHtaccess()) {
            $this->error('Suspicious .htaccess files detected!');
            return $htaccessAlerts;
        }

        return [];
    }

    private function checkForFakeImageFiles(): array
    {
        if ($fakeImageAlerts = $this->scanner->checkFakeImageFiles()) {
            $this->error('Fake image files with PHP code detected!');
            return $fakeImageAlerts;
        }

        return [];
    }

    private function checkForFileIntegrityViolations(): array
    {
        if ($integrityAlerts = $this->scanner->checkFileIntegrity()) {
            $this->error('Critical file modifications detected!');
            return $integrityAlerts;
        }

        return [];
    }
}