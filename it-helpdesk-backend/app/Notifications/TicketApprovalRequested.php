<?php

namespace App\Notifications;

use App\Models\ApprovalLevel;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket,
        public ApprovalLevel $level
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Approval Required: {$this->ticket->ticket_number}")
            ->line("A ticket requires your approval.")
            ->line("**{$this->ticket->title}**")
            ->line("Priority: {$this->ticket->priority} | Department: {$this->ticket->department->name}")
            ->line("Approval level: {$this->level->name}")
            ->action('Review Ticket', config('app.frontend_url') . "/tickets/{$this->ticket->id}");
    }

    public function toArray($notifiable): array
    {
        return [
            'type'             => 'approval_requested',
            'ticket_id'        => $this->ticket->id,
            'ticket_number'    => $this->ticket->ticket_number,
            'title'            => $this->ticket->title,
            'approval_level'   => $this->level->name,
        ];
    }
}
