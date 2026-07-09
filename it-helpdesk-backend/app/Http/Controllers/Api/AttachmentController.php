<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorizeDownload($request->user(), $attachment);

        // Legacy rows may still point at the public disk until the
        // attachments:make-private command has been run.
        $disk = collect(['local', 'public'])
            ->first(fn ($d) => Storage::disk($d)->exists($attachment->path));
        abort_if($disk === null, 404);

        return Storage::disk($disk)->download(
            $attachment->path,
            $attachment->original_name,
            ['X-Content-Type-Options' => 'nosniff']
        );
    }

    private function authorizeDownload(User $user, Attachment $attachment): void
    {
        $parent = $attachment->attachable;
        if ($parent instanceof Comment) {
            // Internal notes are IT-only; their attachments must not leak to
            // the ticket creator even if the attachment id is guessed.
            abort_if($parent->is_internal && !$user->isItStaff(), 403);
            $parent = $parent->ticket;
        }

        if ($parent instanceof Ticket) {
            abort_unless($user->can('view', $parent), 403);
            return;
        }

        // Assets (and any future attachable) are IT-only
        abort_unless($user->isItStaff(), 403);
    }
}
