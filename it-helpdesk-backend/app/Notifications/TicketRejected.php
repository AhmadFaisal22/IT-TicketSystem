<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketRejected extends Notification
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public string $reason
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Ticket Rejected: {$this->ticket->ticket_number}")
            ->line("Your ticket has been rejected.")
            ->line("**{$this->ticket->title}**")
            ->line("Reason: {$this->reason}")
            ->action('View Ticket', config('app.frontend_url') . "/tickets/{$this->ticket->id}");
    }

    public function toArray($notifiable): array
    {
        return [
            'type'          => 'ticket_rejected',
            'ticket_id'     => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'title'         => $this->ticket->title,
            'reason'        => $this->reason,
        ];
    }
}
