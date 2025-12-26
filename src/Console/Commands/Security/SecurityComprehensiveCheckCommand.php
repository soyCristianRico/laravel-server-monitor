<?php

namespace SoyCristianRico\LaravelServerMonitor\Console\Commands\Security;

use SoyCristianRico\LaravelServerMonitor\Services\Security\SecurityScannerService;
use SoyCristianRico\LaravelServerMonitor\Traits\NotifiesSecurityAlerts;
use Illuminate\Console\Command;

class SecurityComprehensiveCheckCommand extends Command
{
    use NotifiesSecurityAlerts;

    protected $signature = 'security:comprehensive-check';

    protected $description = 'Run comprehensive daily security audit';

    public function handle(): int
    {
        $this->initializeNotificationService();
        $scanner = app(SecurityScannerService::class);

        $this->info('Starting comprehensive security check...');
        $alerts = [];

        // 1. Check for modified system files
        $this->info('Checking system files...');
        if ($alert = $scanner->checkModifiedSystemFiles()) {
            $alerts[] = $alert;
        }

        // 2. Check for unauthorized SSH keys
        $this->info('Checking SSH keys...');
        if ($alert = $scanner->checkUnauthorizedSSHKeys()) {
            $alerts[] = $alert;
        }

        // 3. Check disk usage
        $this->info('Checking disk usage...');
        if ($alert = $scanner->checkDiskUsage()) {
            $alerts[] = $alert;
        }

        // 4. Check for failed login attempts
        $this->info('Checking failed logins...');
        if ($alert = $scanner->checkFailedLogins()) {
            $alerts[] = $alert;
        }

        // 5. Check for suspicious users
        $this->info('Checking for new users...');
        if ($alert = $scanner->checkNewUsers()) {
            $alerts[] = $alert;
        }

        // 6. Get open ports for report
        $this->info('Checking open ports...');
        $openPorts = $scanner->getListeningPorts();

        // 7. Check for large files (possible data dumps)
        $this->info('Checking for suspicious large files...');
        if ($alert = $scanner->checkLargeFiles()) {
            $alerts[] = $alert;
        }

        // Run additional security checks
        $this->info('Running additional security checks...');
        $this->call('security:check');
        $this->call('security:check-malware');

        // Generate report
        $report = $this->generateReport($alerts, $openPorts);

        // Send comprehensive report
        if (! empty($alerts)) {
            $this->sendSecurityAlerts($alerts, 'Security issues found! Comprehensive report sent.');
        } else {
            $this->info('No critical security issues found.');
            // Still send a daily summary
            $this->sendSecurityReport([], $report);
        }

        return 0;
    }

    private function generateReport(array $alerts, string $openPorts): string
    {
        $appUrl = config('app.url', 'Laravel Application');

        $report = 'Daily Security Report for ' . $appUrl . "\n";
        $report .= 'Generated at: ' . now()->format('Y-m-d H:i:s') . "\n\n";

        if (empty($alerts)) {
            $report .= "✅ No critical security issues detected.\n\n";
        } else {
            $report .= '⚠️ ' . count($alerts) . " security issues detected.\n\n";
        }

        $report .= "Open Ports:\n" . $openPorts . "\n";

        return $report;
    }
}