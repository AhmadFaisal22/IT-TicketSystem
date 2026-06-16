<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\TicketHistory;
use App\Models\User;
use App\Notifications\TicketApprovalRequested;
use App\Notifications\TicketApproved;
use App\Notifications\TicketCreated;
use App\Notifications\TicketRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketApprovalController extends Controller
{
    public function approve(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        abort_if($ticket->status !== 'pending_approval', 422, 'Ticket is not awaiting approval.');

        // Find this user's pending approval record for the ticket
        $approval = TicketApproval::where('ticket_id', $ticket->id)
            ->where('approver_id', $user->id)
            ->where('status', 'pending')
            ->orderBy('level_order')
            ->first();

        abort_unless($approval, 403, 'You do not have a pending approval step for this ticket.');

        $now = now();
        $approval->update([
            'status'       => 'approved',
            'responded_by' => $user->id,
            'responded_at' => $now,
            'notes'        => $request->input('notes'),
        ]);

        TicketHistory::create([
            'ticket_id'  => $ticket->id,
            'user_id'    => $user->id,
            'action'     => 'approved',
            'field'      => 'approval',
            'old_value'  => 'pending',
            'new_value'  => 'approved',
            'created_at' => $now,
        ]);

        // Check if there is a next pending approval level
        $nextApproval = TicketApproval::where('ticket_id', $ticket->id)
            ->where('status', 'pending')
            ->orderBy('level_order')
            ->first();

        if ($nextApproval) {
            // Notify next approver
            $nextApprover = User::find($nextApproval->approver_id);
            $nextApprover?->notify(new TicketApprovalRequested($ticket, $nextApproval->approvalLevel));
        } else {
            // All levels approved → open the ticket
            $ticket->update(['status' => 'open']);

            TicketHistory::create([
                'ticket_id'  => $ticket->id,
                'user_id'    => $user->id,
                'action'     => 'status_changed',
                'field'      => 'status',
                'old_value'  => 'pending_approval',
                'new_value'  => 'open',
                'created_at' => $now,
            ]);

            // Notify ticket creator that it's been approved
            $ticket->creator->notify(new TicketApproved($ticket));

            // Notify only the assigned IT staff member
            $ticket->assignee?->notify(new TicketCreated($ticket));
        }

        return response()->json($ticket->fresh()->load(['creator', 'assignee', 'department', 'approvals.approver', 'approvals.responder', 'histories.user']));
    }

    public function reject(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        abort_if($ticket->status !== 'pending_approval', 422, 'Ticket is not awaiting approval.');

        $approval = TicketApproval::where('ticket_id', $ticket->id)
            ->where('approver_id', $user->id)
            ->where('status', 'pending')
            ->orderBy('level_order')
            ->first();

        abort_unless($approval, 403, 'You do not have a pending approval step for this ticket.');

        $data = $request->validate(['notes' => 'required|string|max:1000']);

        $now = now();
        // Reject current level
        $approval->update([
            'status'       => 'rejected',
            'responded_by' => $user->id,
            'responded_at' => $now,
            'notes'        => $data['notes'],
        ]);

        // Cancel all remaining pending approvals
        TicketApproval::where('ticket_id', $ticket->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        $ticket->update(['status' => 'rejected']);

        TicketHistory::create([
            'ticket_id'  => $ticket->id,
            'user_id'    => $user->id,
            'action'     => 'rejected',
            'field'      => 'approval',
            'old_value'  => 'pending_approval',
            'new_value'  => 'rejected',
            'created_at' => $now,
        ]);

        // Notify ticket creator
        $ticket->creator->notify(new TicketRejected($ticket, $data['notes']));

        return response()->json($ticket->fresh()->load(['creator', 'assignee', 'department', 'approvals.approver', 'approvals.responder', 'histories.user']));
    }
}
