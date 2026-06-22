<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewComment;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\SlaBreached;
use App\Notifications\TicketApprovalRequested;
use App\Notifications\TicketApproved;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketCreated;
use App\Notifications\TicketRejected;
use App\Notifications\TicketStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QueuedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_notification_class_is_queued(): void
    {
        $classes = [
            NewComment::class,
            ResetPasswordNotification::class,
            SlaBreached::class,
            TicketApprovalRequested::class,
            TicketApproved::class,
            TicketAssigned::class,
            TicketCreated::class,
            TicketRejected::class,
            TicketStatusChanged::class,
        ];

        foreach ($classes as $class) {
            $this->assertContains(
                ShouldQueue::class,
                class_implements($class),
                "{$class} must implement ShouldQueue so mail is sent by the queue worker, not inside the HTTP request"
            );
        }
    }

    public function test_status_change_pushes_notification_to_queue_instead_of_sending_inline(): void
    {
        config(['queue.default' => 'database']);

        $creator = User::factory()->create(['role' => 'user']);
        $staff = User::factory()->create(['role' => 'it_staff']);
        $department = Department::create(['name' => 'IT', 'name_zh' => 'IT部']);
        $ticket = Ticket::create([
            'title'         => 'Broken laptop',
            'description'   => 'It will not boot',
            'status'        => 'open',
            'priority'      => 'medium',
            'department_id' => $department->id,
            'created_by'    => $creator->id,
        ]);

        Sanctum::actingAs($staff);
        $this->patchJson("/api/tickets/{$ticket->id}/status", ['status' => 'in_progress'])
            ->assertOk();

        // One queued job per channel: database + mail
        $this->assertSame(2, DB::table('jobs')->count(), 'notification should be queued, not sent inline');
    }
}
