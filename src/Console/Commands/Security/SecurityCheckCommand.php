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

        // NEW ENHANCED SECURITY CHECKS
        $this->info('Running enhanced security checks...');

        // Check suspicious uploads (PHP files in storage/uploads)
        if ($uploadAlerts = $this->scanner->checkSuspiciousUploads()) {
            $alerts = array_merge($alerts, $uploadAlerts);
            $this->error('Suspicious file uploads detected!');
        }

        // Check suspicious .htaccess files
        if ($htaccessAlerts = $this->scanner->checkSuspiciousHtaccess()) {
            $alerts = array_merge($alerts, $htaccessAlerts);
            $this->error('Suspicious .htaccess files detected!');
        }

        // Check fake image files containing PHP
        if ($fakeImageAlerts = $this->scanner->checkFakeImageFiles()) {
            $alerts = array_merge($alerts, $fakeImageAlerts);
            $this->error('Fake image files with PHP code detected!');
        }

        // Check file integrity of critical Laravel files
        if ($integrityAlerts = $this->scanner->checkFileIntegrity()) {
            $alerts = array_merge($alerts, $integrityAlerts);
            $this->error('Critical file modifications detected!');
        }

        if (! empty($alerts)) {
            $this->sendSecurityAlerts($alerts);

            return 1;
        }

        $this->info('✅ No security issues detected');

        return 0;
    }
}