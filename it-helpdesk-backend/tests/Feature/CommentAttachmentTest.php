<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommentAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeTicket(User $creator): Ticket
    {
        $department = Department::create(['name' => 'IT', 'name_zh' => 'IT部']);

        return Ticket::create([
            'title'         => 'Broken laptop',
            'description'   => 'It will not boot',
            'status'        => 'open',
            'priority'      => 'medium',
            'department_id' => $department->id,
            'created_by'    => $creator->id,
        ]);
    }

    private function makeCommentWithAttachment(Ticket $ticket, User $author, bool $internal = false): Attachment
    {
        $comment = Comment::create([
            'ticket_id'   => $ticket->id,
            'user_id'     => $author->id,
            'body'        => 'See attached',
            'is_internal' => $internal,
        ]);

        $path = 'comment-attachments/diag.pdf';
        Storage::disk('local')->put($path, 'pdf-bytes');

        return $comment->attachments()->create([
            'user_id'       => $author->id,
            'filename'      => 'diag.pdf',
            'original_name' => 'diagnostics.pdf',
            'mime_type'     => 'application/pdf',
            'size'          => 9,
            'path'          => $path,
        ]);
    }

    public function test_it_staff_can_post_comment_with_attachments_stored_on_private_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $creator = User::factory()->create(['role' => 'user']);
        $ticket = $this->makeTicket($creator);

        Sanctum::actingAs(User::factory()->create(['role' => 'it_staff']));
        $response = $this->post("/api/tickets/{$ticket->id}/comments", [
            'body'        => 'Screenshot attached',
            'attachments' => [UploadedFile::fake()->image('screen.png')],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('body', 'Screenshot attached')
            ->assertJsonCount(1, 'attachments')
            ->assertJsonPath('attachments.0.original_name', 'screen.png');

        $path = Attachment::sole()->path;
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_it_staff_can_post_attachment_only_comment(): void
    {
        Storage::fake('local');
        $creator = User::factory()->create(['role' => 'user']);
        $ticket = $this->makeTicket($creator);

        Sanctum::actingAs(User::factory()->create(['role' => 'it_staff']));
        $this->post("/api/tickets/{$ticket->id}/comments", [
            'attachments' => [UploadedFile::fake()->create('log.pdf', 100, 'application/pdf')],
        ])->assertStatus(201)->assertJsonPath('body', '');
    }

    public function test_regular_user_cannot_post_comment_attachments(): void
    {
        Storage::fake('local');
        $creator = User::factory()->create(['role' => 'user']);
        $ticket = $this->makeTicket($creator);

        Sanctum::actingAs($creator);
        $this->post("/api/tickets/{$ticket->id}/comments", [
            'body'        => 'My screenshot',
            'attachments' => [UploadedFile::fake()->image('screen.png')],
        ])->assertStatus(403);

        $this->assertSame(0, Attachment::count());
    }

    public function test_comment_without_body_and_without_attachments_is_rejected(): void
    {
        $creator = User::factory()->create(['role' => 'user']);
        $ticket = $this->makeTicket($creator);

        Sanctum::actingAs(User::factory()->create(['role' => 'it_staff']));
        $this->postJson("/api/tickets/{$ticket->id}/comments", [])->assertStatus(422);
    }

    public function test_ticket_creator_can_download_public_comment_attachment(): void
    {
        Storage::fake('local');
        $creator = User::factory()->create(['role' => 'user']);
        $staff = User::factory()->create(['role' => 'it_staff']);
        $attachment = $this->makeCommentWithAttachment($this->makeTicket($creator), $staff);

        Sanctum::actingAs($creator);
        $response = $this->get("/api/attachments/{$attachment->id}/download");

        $response->assertOk();
        $this->assertSame('pdf-bytes', $response->streamedContent());
    }

    public function test_ticket_creator_cannot_download_internal_comment_attachment(): void
    {
        Storage::fake('local');
        $creator = User::factory()->create(['role' => 'user']);
        $staff = User::factory()->create(['role' => 'it_staff']);
        $attachment = $this->makeCommentWithAttachment($this->makeTicket($creator), $staff, internal: true);

        Sanctum::actingAs($creator);
        $this->getJson("/api/attachments/{$attachment->id}/download")->assertStatus(403);
    }

    public function test_it_staff_can_download_internal_comment_attachment(): void
    {
        Storage::fake('local');
        $creator = User::factory()->create(['role' => 'user']);
        $staff = User::factory()->create(['role' => 'it_staff']);
        $attachment = $this->makeCommentWithAttachment($this->makeTicket($creator), $staff, internal: true);

        Sanctum::actingAs($staff);
        $this->get("/api/attachments/{$attachment->id}/download")->assertOk();
    }

    public function test_comment_list_includes_attachments(): void
    {
        Storage::fake('local');
        $creator = User::factory()->create(['role' => 'user']);
        $ticket = $this->makeTicket($creator);
        $staff = User::factory()->create(['role' => 'it_staff']);
        $this->makeCommentWithAttachment($ticket, $staff);

        Sanctum::actingAs($creator);
        $this->getJson("/api/tickets/{$ticket->id}/comments")
            ->assertOk()
            ->assertJsonPath('0.attachments.0.original_name', 'diagnostics.pdf');
    }

    public function test_ticket_detail_includes_comment_attachments(): void
    {
        Storage::fake('local');
        $creator = User::factory()->create(['role' => 'user']);
        $ticket = $this->makeTicket($creator);
        $staff = User::factory()->create(['role' => 'it_staff']);
        $this->makeCommentWithAttachment($ticket, $staff);

        Sanctum::actingAs($creator);
        $this->getJson("/api/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('comments.0.attachments.0.original_name', 'diagnostics.pdf');
    }

    public function test_deleting_comment_removes_attachment_rows_and_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $creator = User::factory()->create(['role' => 'user']);
        $ticket = $this->makeTicket($creator);
        $staff = User::factory()->create(['role' => 'it_staff']);
        $attachment = $this->makeCommentWithAttachment($ticket, $staff);
        $commentId = $attachment->attachable_id;

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->delete("/api/tickets/{$ticket->id}/comments/{$commentId}")->assertStatus(204);

        $this->assertSame(0, Attachment::count());
        Storage::disk('local')->assertMissing($attachment->path);
    }
}
