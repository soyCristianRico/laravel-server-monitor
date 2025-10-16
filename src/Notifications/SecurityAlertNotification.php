<?php

namespace CristianDev\LaravelServerMonitor\Notifications;

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
        $subject = count($this->alerts) > 0
            ? 'Security Alert: ' . count($this->alerts) . ' issue(s) detected'
            : 'Security Report';

        $mailMessage = (new MailMessage)
            ->subject($subject)
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
    }

    public function toArray($notifiable): array
    {
        return [
            'alerts_count' => count($this->alerts),
            'alerts' => $this->alerts,
            'report' => $this->report,
        ];
    }
}