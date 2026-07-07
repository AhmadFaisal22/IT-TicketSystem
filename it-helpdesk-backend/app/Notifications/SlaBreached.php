<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaBreached extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param string $type 'response' | 'resolution' */
    public function __construct(public Ticket $ticket, public string $type) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $due = $this->type === 'response'
            ? $this->ticket->sla_response_due_at
            : $this->ticket->sla_resolution_due_at;

        return (new MailMessage)
            ->subject("SLA Breach: {$this->ticket->ticket_number}")
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line("The {$this->type} SLA for a ticket has been breached.")
            ->line("**{$this->ticket->title}**")
            ->line("Priority: {$this->ticket->priority} | Department: {$this->ticket->department->name}")
            ->line('Deadline was: ' . optional($due)->toDayDateTimeString())
            ->action('View Ticket', config('app.frontend_url') . "/tickets/{$this->ticket->id}");
    }

    public function toArray($notifiable): array
    {
        return [
            'type'          => 'sla_breached',
            'breach_type'   => $this->type,
            'ticket_id'     => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'title'         => $this->ticket->title,
            'priority'      => $this->ticket->priority,
        ];
    }
}
