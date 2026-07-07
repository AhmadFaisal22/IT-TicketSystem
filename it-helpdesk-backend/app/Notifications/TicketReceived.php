<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReceived extends Notification implements ShouldQueue
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
            ->subject("Ticket Received: {$this->ticket->ticket_number}")
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("We have received your ticket. Our IT team will get back to you soon.")
            ->line("**{$this->ticket->title}**")
            ->line("Ticket Number: {$this->ticket->ticket_number}")
            ->line("Priority: {$this->ticket->priority} | Status: {$this->ticket->status}")
            ->action('View Ticket', config('app.frontend_url') . "/tickets/{$this->ticket->id}");
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'ticket_received',
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'title' => $this->ticket->title,
            'status' => $this->ticket->status,
        ];
    }
}
