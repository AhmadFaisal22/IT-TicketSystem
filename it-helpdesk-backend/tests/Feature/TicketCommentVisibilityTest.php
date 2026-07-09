<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketCommentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeTicketWithComments(User $creator): Ticket
    {
        $department = Department::create(['name' => 'IT', 'name_zh' => 'IT部']);
        $staff = User::factory()->create(['role' => 'it_staff']);

        $ticket = Ticket::create([
            'title' => 'VPN issue',
            'description' => 'Cannot connect',
            'status' => 'open',
            'priority' => 'medium',
            'department_id' => $department->id,
            'created_by' => $creator->id,
        ]);

        Comment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'body' => 'Public update',
            'is_internal' => false,
        ]);
        Comment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $staff->id,
            'body' => 'Internal diagnosis',
            'is_internal' => true,
        ]);

        return $ticket;
    }

    public function test_ticket_creator_does_not_receive_internal_comments_in_ticket_detail(): void
    {
        $creator = User::factory()->create(['role' => 'user']);
        $ticket = $this->makeTicketWithComments($creator);

        Sanctum::actingAs($creator);

        $response = $this->getJson("/api/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonPath('comments.0.body', 'Public update')
            ->assertJsonCount(1, 'comments');

        $this->assertStringNotContainsString('Internal diagnosis', $response->getContent());
    }

    public function test_it_staff_receive_internal_comments_in_ticket_detail(): void
    {
        $creator = User::factory()->create(['role' => 'user']);
        $ticket = $this->makeTicketWithComments($creator);
        $viewer = User::factory()->create(['role' => 'it_staff']);

        Sanctum::actingAs($viewer);

        $this->getJson("/api/tickets/{$ticket->id}")
            ->assertOk()
            ->assertJsonCount(2, 'comments')
            ->assertJsonFragment(['body' => 'Public update'])
            ->assertJsonFragment(['body' => 'Internal diagnosis']);
    }
}
