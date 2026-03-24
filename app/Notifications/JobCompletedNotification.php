<?php

namespace App\Notifications;

use App\Models\ReportingJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JobCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public ReportingJob $job)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $status = ucfirst($this->job->status);
        $success = $this->job->counts['success'];
        $total = $this->job->counts['total'];

        return (new MailMessage)
                    ->subject("NAPExpress: Reporting Job #{$this->job->id} {$status}")
                    ->greeting("Hello {$notifiable->name},")
                    ->line("Your reporting job for **{$this->job->form_type}** has finished.")
                    ->line("Status: **{$status}**")
                    ->line("Progress: **{$success} / {$total}** records processed.")
                    ->action('View Job Details', route('jobs.show', $this->job->id))
                    ->line('Thank you for using NAPExpress for your automation needs!');
    }
}
