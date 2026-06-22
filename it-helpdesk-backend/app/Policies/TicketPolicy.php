<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isItStaff()) return true;
        if ($ticket->created_by === $user->id) return true;
        // Approvers must be able to view tickets assigned to them for approval
        return $ticket->approvals()->where('approver_id', $user->id)->exists();
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $user->isAdmin()
            || ($user->isItStaff())
            || ($ticket->created_by === $user->id && $ticket->status === 'open');
    }

    public function updateStatus(User $user, Ticket $ticket): bool
    {
        // Cannot manually change status while ticket is in approval workflow
        if ($ticket->status === 'pending_approval' && !$user->isAdmin()) {
            return false;
        }
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
