<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SettlementReportGenerated extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $zipPath,
        private readonly array $dateRange
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Settlement Reports Generated')
            ->greeting('Hello!')
            ->line('Settlement reports have been generated successfully.')
            ->line("Period: {$this->dateRange['start']} to {$this->dateRange['end']}")
            ->line("You can download the reports from: {$this->zipPath}")
            ->action('Download Reports', $this->zipPath)
            ->line('Thank you for using our application!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'zip_path' => $this->zipPath,
            'date_range' => $this->dateRange,
        ];
    }
}
