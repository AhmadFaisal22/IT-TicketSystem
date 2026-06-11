<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(private string $url) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset Your Password — IT HelpDesk')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('We received a password reset request for your account.')
            ->action('Reset Password', $this->url)
            ->line('This link expires in 60 minutes.')
            ->line('If you did not request a password reset, no action is required.');
    }
}
