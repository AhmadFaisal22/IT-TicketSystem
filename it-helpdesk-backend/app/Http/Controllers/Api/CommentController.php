<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Notifications\NewComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $query = $ticket->comments()->with('user')->orderBy('created_at');

        if (!request()->user()->isItStaff()) {
            $query->where('is_internal', false);
        }

        return response()->json($query->get());
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'is_internal' => 'boolean',
        ]);

        if (($data['is_internal'] ?? false) && !$request->user()->isItStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $comment = $ticket->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'is_internal' => $data['is_internal'] ?? false,
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'action' => 'commented',
            'created_at' => now(),
        ]);

        // Record first response time if IT staff commenting
        if ($request->user()->isItStaff() && !$ticket->first_response_at) {
            $ticket->update(['first_response_at' => now()]);
        }

        // Notify relevant parties (skip if internal note)
        if (!($data['is_internal'] ?? false)) {
            $notifyUsers = collect([$ticket->creator]);
            if ($ticket->assignee && $ticket->assignee->id !== $request->user()->id) {
                $notifyUsers->push($ticket->assignee);
            }
            foreach ($notifyUsers->unique('id') as $user) {
                if ($user->id !== $request->user()->id) {
                    $user->notify(new NewComment($ticket, $comment));
                }
            }
        }

        return response()->json($comment->load('user'), 201);
    }

    public function destroy(Request $request, Ticket $ticket, Comment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $comment->delete();
        return response()->json(null, 204);
    }
}
