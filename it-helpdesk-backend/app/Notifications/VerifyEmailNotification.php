<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private string $url) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your Email — IT HelpDesk')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Thanks for registering for the SEG Solar IT HelpDesk.')
            ->line('Please confirm your email address to activate your account.')
            ->action('Verify Email', $this->url)
            ->line('This link expires in 60 minutes.')
            ->line('If you did not create an account, no action is required.');
    }
}
