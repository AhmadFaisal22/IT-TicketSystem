<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLevel;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\TicketHistory;
use App\Models\User;
use App\Notifications\TicketApprovalRequested;
use App\Notifications\TicketCreated;
use App\Notifications\TicketStatusChanged;
use App\Notifications\TicketAssigned;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $f = $request->validate([
            'status'        => 'nullable|in:open,in_progress,pending,pending_approval,resolved,closed,rejected',
            'priority'      => 'nullable|in:low,medium,high,critical',
            'department_id' => 'nullable|integer|exists:departments,id',
            'assigned_to'   => 'nullable|integer|exists:users,id',
            'search'        => 'nullable|string|max:255',
            'sla_breached'  => 'nullable|boolean',
            'per_page'      => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $query = Ticket::with(['creator', 'assignee', 'department'])
            ->orderByDesc('created_at');

        if (!$user->isItStaff()) {
            // Regular users see their own tickets OR tickets they are an approver for
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('approvals', fn ($a) => $a->where('approver_id', $user->id));
            });
        }

        if (!empty($f['status'])) {
            $query->where('status', $f['status']);
        }
        if (!empty($f['priority'])) {
            $query->where('priority', $f['priority']);
        }
        if (!empty($f['department_id'])) {
            $query->where('department_id', $f['department_id']);
        }
        if (!empty($f['assigned_to'])) {
            $query->where('assigned_to', $f['assigned_to']);
        }
        if (!empty($f['search'])) {
            $search = $f['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }
        if (!empty($f['sla_breached'])) {
            $query->where(function ($q) {
                $q->where('sla_response_breached', true)
                  ->orWhere('sla_resolution_breached', true);
            });
        }

        return response()->json($query->paginate((int) ($f['per_page'] ?? 20)));
    }

    public function store(Request $request): JsonResponse
    {
        // Tickets must be assigned to a member of the IT department on creation.
        $itDeptId = Department::where('name', 'IT')->value('id');

        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'required|string|max:10000',
            'priority'       => 'required|in:low,medium,high,critical',
            'category'       => 'nullable|string|max:100',
            'subcategory'    => 'nullable|string|max:100',
            'department_id'  => 'required|exists:departments,id',
            'assigned_to'    => ['required', Rule::exists('users', 'id')->where('active', true)->where('department_id', $itDeptId)],
            'attachments'    => 'nullable|array|max:5',
            'attachments.*'  => 'file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
        ]);

        $ticket = DB::transaction(function () use ($data, $request) {
            $ticket = Ticket::create(array_merge(
                Arr::except($data, ['attachments']),
                ['created_by' => $request->user()->id]
            ));
            $ticket->setSlaDeadlines();
            $ticket->save();

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('ticket-attachments', 'local');
                    $ticket->attachments()->create([
                        'user_id'       => $request->user()->id,
                        'filename'      => basename($path),
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type'     => $file->getMimeType(),
                        'size'          => $file->getSize(),
                        'path'          => $path,
                    ]);
                }
            }

            TicketHistory::create([
                'ticket_id'  => $ticket->id,
                'user_id'    => $request->user()->id,
                'action'     => 'created',
                'created_at' => now(),
            ]);

            return $ticket;
        });

        // Check if this department has active approval levels
        $approvalLevels = ApprovalLevel::where('department_id', $ticket->department_id)
            ->where('is_active', true)
            ->orderBy('level_order')
            ->get();

        if ($approvalLevels->isNotEmpty()) {
            // Route through approval workflow
            $ticket->update(['status' => 'pending_approval']);

            foreach ($approvalLevels as $level) {
                TicketApproval::create([
                    'ticket_id'         => $ticket->id,
                    'approval_level_id' => $level->id,
                    'level_order'       => $level->level_order,
                    'approver_id'       => $level->approver_id,
                    'status'            => 'pending',
                ]);
            }

            // Notify first-level approver
            $firstApprover = User::find($approvalLevels->first()->approver_id);
            $firstApprover?->notify(new TicketApprovalRequested($ticket, $approvalLevels->first()));
        } else {
            // No approvals needed — notify only the assigned IT staff member
            $ticket->assignee?->notify(new TicketCreated($ticket));
        }

        return response()->json($ticket->load(['creator', 'department', 'attachments']), 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);
        return response()->json(
            $ticket->load(['creator', 'assignee', 'department', 'comments.user', 'histories.user', 'attachments', 'approvals.approver', 'approvals.responder'])
        );
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('update', $ticket);

        $data = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:10000',
            'priority'    => 'sometimes|in:low,medium,high,critical',
            'category'    => 'nullable|string|max:100',
            'subcategory' => 'nullable|string|max:100',
        ]);

        $ticket->update($data);
        return response()->json($ticket->load(['creator', 'assignee', 'department']));
    }

    public function updateStatus(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('updateStatus', $ticket);

        $data = $request->validate(['status' => 'required|in:open,in_progress,pending,resolved,closed']);

        $oldStatus = $ticket->status;
        $ticket->status = $data['status'];

        if ($data['status'] === 'resolved' && !$ticket->resolved_at) {
            $ticket->resolved_at = now();
        }
        if ($data['status'] === 'closed' && !$ticket->closed_at) {
            $ticket->closed_at = now();
        }
        $ticket->save();

        TicketHistory::create([
            'ticket_id'  => $ticket->id,
            'user_id'    => $request->user()->id,
            'action'     => 'status_changed',
            'field'      => 'status',
            'old_value'  => $oldStatus,
            'new_value'  => $data['status'],
            'created_at' => now(),
        ]);

        $ticket->creator->notify(new TicketStatusChanged($ticket, $oldStatus));

        return response()->json($ticket);
    }

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('assign', $ticket);

        $itDeptId = Department::where('name', 'IT')->value('id');
        $data = $request->validate([
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('active', true)->where('department_id', $itDeptId)],
        ]);
        $ticket->update(['assigned_to' => $data['assigned_to']]);

        if ($data['assigned_to']) {
            $ticket->assignee->notify(new TicketAssigned($ticket));

            if (!$ticket->first_response_at) {
                $ticket->update(['first_response_at' => now()]);
            }
        }

        return response()->json($ticket->load('assignee'));
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $this->authorize('delete', $ticket);
        DatabaseNotification::where('data->ticket_id', $ticket->id)->delete();
        $ticket->delete();
        return response()->json(null, 204);
    }
}
