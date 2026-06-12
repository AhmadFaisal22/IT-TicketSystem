<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Ticket $ticket) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Ticket Assigned: {$this->ticket->ticket_number}")
            ->line("A ticket has been assigned to you.")
            ->line("**{$this->ticket->title}**")
            ->line("Priority: {$this->ticket->priority}")
            ->action('View Ticket', config('app.frontend_url') . "/tickets/{$this->ticket->id}");
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'ticket_assigned',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'title' => $this->ticket->title,
        ];
    }
}
