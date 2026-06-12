<?php

use App\Models\Ticket;
use Illuminate\Support\Facades\Schedule;

// Check and mark SLA breaches every 15 minutes
Schedule::call(function () {
    $now = now();

    // Mark response SLA breached
    Ticket::where('sla_response_breached', false)
        ->whereNotNull('sla_response_due_at')
        ->where('sla_response_due_at', '<', $now)
        ->whereNull('first_response_at')
        ->update(['sla_response_breached' => true]);

    // Mark resolution SLA breached
    Ticket::where('sla_resolution_breached', false)
        ->whereNotNull('sla_resolution_due_at')
        ->where('sla_resolution_due_at', '<', $now)
        ->whereNotIn('status', ['resolved', 'closed'])
        ->update(['sla_resolution_breached' => true]);

})->everyFifteenMinutes()->name('sla:check-breaches');

// Delete tokens that expired more than 24h ago
Schedule::command('sanctum:prune-expired --hours=24')->daily();
