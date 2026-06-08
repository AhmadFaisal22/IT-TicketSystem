<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewDashboard(User $user): bool
    {
        return $user->isItStaff();
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isItStaff() || $ticket->created_by === $user->id;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin()
            || ($user->isItStaff())
            || ($ticket->created_by === $user->id && $ticket->status === 'open');
    }

    public function updateStatus(User $user, Ticket $ticket): bool
    {
        return $user->isItStaff();
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->isItStaff();
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin();
    }
}
