<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewComment extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket, public Comment $comment) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Reply on {$this->ticket->ticket_number}")
            ->line("{$this->comment->user->name} replied on your ticket.")
            ->line("**{$this->ticket->title}**")
            ->action('View Ticket', config('app.frontend_url') . "/tickets/{$this->ticket->id}");
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'new_comment',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'comment_by' => $this->comment->user->name,
        ];
    }
}
