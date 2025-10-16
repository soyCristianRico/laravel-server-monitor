<?php

namespace SoyCristianRico\LaravelServerMonitor\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected array $alerts,
        protected ?string $report = null
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        if (count($this->alerts) > 0) {
            // Security alert with issues
            $mailMessage = (new MailMessage)
                ->level('error')
                ->subject('[Security Monitor] Security Alert - Immediate Attention Required')
                ->greeting('Security Alert')
                ->line('The following security issues have been detected on your server:');

            foreach ($this->alerts as $alert) {
                $mailMessage->line("**{$alert['type']}**")
                           ->line($alert['details'] ?? 'No details available')
                           ->line('---');
            }

            if ($this->report) {
                $mailMessage->line('**Additional Report:**')
                           ->line($this->report);
            }

            $mailMessage->line('Please review these alerts immediately and take appropriate action.')
                       ->line('This is an automated security monitoring message.');

            return $mailMessage;
        } else {
            // Daily report without issues
            $mailMessage = (new MailMessage)
                ->level('info')
                ->subject('[Security Monitor] Daily Security Report - All Clear');

            if ($this->report) {
                $mailMessage->line('Daily security scan completed successfully.')
                           ->line('No critical security issues were detected.')
                           ->line('Report details:')
                           ->line($this->report);
            } else {
                $mailMessage->line('Security check completed without issues.');
            }

            return $mailMessage;
        }
    }

    public function toArray($notifiable): array
    {
        return [
            'alerts_count' => count($this->alerts),
            'alerts' => $this->alerts,
            'report' => $this->report,
        ];
    }

    public function getAlerts(): array
    {
        return $this->alerts;
    }

    public function getReport(): ?string
    {
        return $this->report;
    }
}