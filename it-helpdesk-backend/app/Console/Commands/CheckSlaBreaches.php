<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SlaBreached;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class CheckSlaBreaches extends Command
{
    protected $signature = 'sla:check-breaches';

    protected $description = 'Flag tickets that breached their response/resolution SLA and notify the assignee + admins.';

    public function handle(): int
    {
        $now = now();
        $admins = User::where('role', 'admin')->get();

        // Response SLA: past due with no first response yet.
        $responseBreached = Ticket::where('sla_response_breached', false)
            ->whereNotNull('sla_response_due_at')
            ->where('sla_response_due_at', '<', $now)
            ->whereNull('first_response_at')
            ->with('assignee')
            ->get();

        foreach ($responseBreached as $ticket) {
            $ticket->update(['sla_response_breached' => true]);
            $this->notifyBreach($ticket, 'response', $admins);
        }

        // Resolution SLA: past due and not finished (resolved/closed/rejected).
        $resolutionBreached = Ticket::where('sla_resolution_breached', false)
            ->whereNotNull('sla_resolution_due_at')
            ->where('sla_resolution_due_at', '<', $now)
            ->whereNotIn('status', ['resolved', 'closed', 'rejected'])
            ->with('assignee')
            ->get();

        foreach ($resolutionBreached as $ticket) {
            $ticket->update(['sla_resolution_breached' => true]);
            $this->notifyBreach($ticket, 'resolution', $admins);
        }

        $count = $responseBreached->count() + $resolutionBreached->count();
        $this->info("SLA breaches processed: {$count}");

        return self::SUCCESS;
    }

    /** Notify the assignee (if any) plus all admins, deduplicated by id. */
    private function notifyBreach(Ticket $ticket, string $type, Collection $admins): void
    {
        $recipients = $admins->concat([$ticket->assignee])
            ->filter()
            ->unique('id')
            ->values();

        Notification::send($recipients, new SlaBreached($ticket, $type));
    }
}
