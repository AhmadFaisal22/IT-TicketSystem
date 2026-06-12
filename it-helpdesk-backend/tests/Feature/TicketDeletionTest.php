<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketDeletionTest extends TestCase
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

    private function notifyAbout(User $user, int $ticketId): string
    {
        $id = (string) Str::uuid();

        $user->notifications()->create([
            'id'   => $id,
            'type' => TicketCreated::class,
            'data' => ['type' => 'ticket_created', 'ticket_id' => $ticketId, 'title' => 'Broken laptop'],
        ]);

        return $id;
    }

    public function test_deleting_a_ticket_deletes_its_notifications_but_keeps_others(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'it_staff']);

        $ticket = $this->makeTicket($staff);
        $other  = $this->makeTicket($staff);

        $doomed = $this->notifyAbout($staff, $ticket->id);
        $kept   = $this->notifyAbout($staff, $other->id);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/tickets/{$ticket->id}")->assertStatus(204);

        $this->assertDatabaseMissing('tickets', ['id' => $ticket->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $doomed]);
        $this->assertDatabaseHas('notifications', ['id' => $kept]);
    }
}
