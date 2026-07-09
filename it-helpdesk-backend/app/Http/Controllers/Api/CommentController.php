<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Notifications\NewComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CommentController extends Controller
{
    public function index(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $query = $ticket->comments()->with(['user', 'attachments'])->orderBy('created_at');

        if (!request()->user()->isItStaff()) {
            $query->where('is_internal', false);
        }

        return response()->json($query->get());
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $data = $request->validate([
            'body' => 'required_without:attachments|nullable|string|max:5000',
            'is_internal' => 'boolean',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
        ]);

        if (($data['is_internal'] ?? false) && !$request->user()->isItStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Attachment upload is an IT tool; end users comment with text only.
        if ($request->hasFile('attachments') && !$request->user()->isItStaff()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $comment = DB::transaction(function () use ($data, $request, $ticket) {
            $comment = $ticket->comments()->create([
                'user_id' => $request->user()->id,
                'body' => $data['body'] ?? '',
                'is_internal' => $data['is_internal'] ?? false,
            ]);

            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store('comment-attachments', 'local');
                $comment->attachments()->create([
                    'user_id'       => $request->user()->id,
                    'filename'      => basename($path),
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type'     => $file->getMimeType(),
                    'size'          => $file->getSize(),
                    'path'          => $path,
                ]);
            }

            return $comment;
        });

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

        return response()->json($comment->load(['user', 'attachments']), 201);
    }

    public function destroy(Request $request, Ticket $ticket, Comment $comment): JsonResponse
    {
        if ($comment->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Attachments are polymorphic (no FK cascade) — remove files and rows explicitly.
        foreach ($comment->attachments as $attachment) {
            foreach (['local', 'public'] as $disk) {
                Storage::disk($disk)->delete($attachment->path);
            }
            $attachment->delete();
        }

        $comment->delete();
        return response()->json(null, 204);
    }
}
