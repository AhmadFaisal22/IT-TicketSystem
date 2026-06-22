<?php

use Illuminate\Support\Facades\Schedule;

// Flag SLA breaches and notify the assignee + admins every 15 minutes.
Schedule::command('sla:check-breaches')->everyFifteenMinutes()->name('sla:check-breaches');

// Delete tokens that expired more than 24h ago
Schedule::command('sanctum:prune-expired --hours=24')->daily();
