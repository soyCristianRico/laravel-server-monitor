<?php

use CristianDev\LaravelServerMonitor\Notifications\SecurityAlertNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\Fixtures\User;

describe('SecurityAlertNotification', function () {
    it('uses mail channel', function () {
        $alerts = [['type' => 'Test Alert', 'details' => 'Test details']];
        $notification = new SecurityAlertNotification($alerts);

        expect($notification->via(new User()))->toBe(['mail']);
    });

    it('builds mail message with alerts correctly', function () {
        $alerts = [
            ['type' => 'Suspicious Processes', 'details' => 'wget detected'],
            ['type' => 'High Disk Usage', 'details' => '95% full'],
        ];
        $user = User::factory()->create();
        $notification = new SecurityAlertNotification($alerts);

        $mailMessage = $notification->toMail($user);

        expect($mailMessage)
            ->toBeInstanceOf(MailMessage::class)
            ->and($mailMessage->level)->toBe('error')
            ->and($mailMessage->subject)->toBe('[Security Monitor] Security Alert - Immediate Attention Required')
            ->and($mailMessage->markdown)->toBe('laravel-server-monitor::emails.security-alert')
            ->and($mailMessage->viewData)->toHaveKeys(['alerts'])
            ->and($mailMessage->viewData['alerts'])->toBe($alerts);
    });

    it('builds mail message for daily report when no alerts', function () {
        $report = 'Daily security scan completed. All systems normal.';
        $user = User::factory()->create();
        $notification = new SecurityAlertNotification([], $report);

        $mailMessage = $notification->toMail($user);

        expect($mailMessage)
            ->toBeInstanceOf(MailMessage::class)
            ->and($mailMessage->level)->toBe('info') // Default level for reports
            ->and($mailMessage->subject)->toBe('[Security Monitor] Daily Security Report - All Clear');
    });

    it('builds mail message for daily report with empty alerts', function () {
        $report = 'Security check completed successfully';
        $user = User::factory()->create();
        $notification = new SecurityAlertNotification([], $report);

        $mailMessage = $notification->toMail($user);
        $lines = $mailMessage->introLines;

        expect($lines)
            ->toContain('Daily security scan completed successfully.')
            ->toContain('No critical security issues were detected.')
            ->toContain('Report details:')
            ->toContain($report);
    });

    it('builds mail message for daily report with null report', function () {
        $user = User::factory()->create();
        $notification = new SecurityAlertNotification([]);

        $mailMessage = $notification->toMail($user);
        $lines = $mailMessage->introLines;

        expect($lines)
            ->toContain('Security check completed without issues.');
    });

    it('provides alerts through accessor', function () {
        $alerts = [['type' => 'Test', 'details' => 'Details']];
        $notification = new SecurityAlertNotification($alerts);

        expect($notification->alerts)->toBe($alerts);
    });

    it('provides report through accessor', function () {
        $report = 'Test security report';
        $notification = new SecurityAlertNotification([], $report);

        expect($notification->report)->toBe($report);
    });

    it('uses queueable trait', function () {
        $reflection = new ReflectionClass(SecurityAlertNotification::class);

        expect($reflection->getTraitNames())
            ->toContain('Illuminate\Bus\Queueable');
    });

    it('marks alerts property as readonly', function () {
        $reflection = new ReflectionClass(SecurityAlertNotification::class);
        $property = $reflection->getProperty('alerts');

        expect($property->isReadOnly())->toBeTrue();
    });

    it('marks report property as readonly', function () {
        $reflection = new ReflectionClass(SecurityAlertNotification::class);
        $property = $reflection->getProperty('report');

        expect($property->isReadOnly())->toBeTrue();
    });

    it('handles multiple alert types correctly', function () {
        $alerts = [
            ['type' => 'Suspicious Processes', 'details' => 'wget script.sh'],
            ['type' => 'Network Ports', 'details' => 'Port 4444 open'],
            ['type' => 'Disk Usage', 'details' => '95% full on /var'],
        ];
        $notification = new SecurityAlertNotification($alerts);
        $mailMessage = $notification->toMail(new User());

        expect($mailMessage->viewData['alerts'])
            ->toHaveCount(3)
            ->and($mailMessage->viewData['alerts'][0]['type'])->toBe('Suspicious Processes')
            ->and($mailMessage->viewData['alerts'][1]['type'])->toBe('Network Ports')
            ->and($mailMessage->viewData['alerts'][2]['type'])->toBe('Disk Usage');
    });

    it('constructs notification with different parameter combinations', function () {
        $alerts = [['type' => 'Test', 'details' => 'Details']];
        $report = 'Test report';

        $alertsOnly = new SecurityAlertNotification($alerts);
        $reportOnly = new SecurityAlertNotification([], $report);
        $bothParams = new SecurityAlertNotification($alerts, $report);

        expect($alertsOnly->alerts)->toBe($alerts)
            ->and($alertsOnly->report)->toBeNull()
            ->and($reportOnly->alerts)->toBe([])
            ->and($reportOnly->report)->toBe($report)
            ->and($bothParams->alerts)->toBe($alerts)
            ->and($bothParams->report)->toBe($report);
    });
});