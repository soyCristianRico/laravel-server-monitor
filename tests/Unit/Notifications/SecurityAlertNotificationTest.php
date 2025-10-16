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
            ->and($mailMessage->greeting)->toBe('Security Alert');

        // Check that it contains alert information in the intro lines
        $introText = implode(' ', $mailMessage->introLines);
        expect($introText)->toContain('Suspicious Processes')
            ->and($introText)->toContain('High Disk Usage');
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

        expect($notification->getAlerts())->toBe($alerts);
    });

    it('provides report through accessor', function () {
        $report = 'Test security report';
        $notification = new SecurityAlertNotification([], $report);

        expect($notification->getReport())->toBe($report);
    });

    it('uses queueable trait', function () {
        $reflection = new ReflectionClass(SecurityAlertNotification::class);

        expect($reflection->getTraitNames())
            ->toContain('Illuminate\Bus\Queueable');
    });

    it('alerts property is protected', function () {
        $reflection = new ReflectionClass(SecurityAlertNotification::class);
        $property = $reflection->getProperty('alerts');

        expect($property->isProtected())->toBeTrue();
    });

    it('report property is protected', function () {
        $reflection = new ReflectionClass(SecurityAlertNotification::class);
        $property = $reflection->getProperty('report');

        expect($property->isProtected())->toBeTrue();
    });

    it('handles multiple alert types correctly', function () {
        $alerts = [
            ['type' => 'Suspicious Processes', 'details' => 'wget script.sh'],
            ['type' => 'Network Ports', 'details' => 'Port 4444 open'],
            ['type' => 'Disk Usage', 'details' => '95% full on /var'],
        ];
        $notification = new SecurityAlertNotification($alerts);
        $mailMessage = $notification->toMail(new User());

        // Check that all alerts are included in the intro lines
        $introText = implode(' ', $mailMessage->introLines);
        expect($introText)->toContain('Suspicious Processes')
            ->and($introText)->toContain('Network Ports')
            ->and($introText)->toContain('Disk Usage');
    });

    it('constructs notification with different parameter combinations', function () {
        $alerts = [['type' => 'Test', 'details' => 'Details']];
        $report = 'Test report';

        $alertsOnly = new SecurityAlertNotification($alerts);
        $reportOnly = new SecurityAlertNotification([], $report);
        $bothParams = new SecurityAlertNotification($alerts, $report);

        expect($alertsOnly->getAlerts())->toBe($alerts)
            ->and($alertsOnly->getReport())->toBeNull()
            ->and($reportOnly->getAlerts())->toBe([])
            ->and($reportOnly->getReport())->toBe($report)
            ->and($bothParams->getAlerts())->toBe($alerts)
            ->and($bothParams->getReport())->toBe($report);
    });
});