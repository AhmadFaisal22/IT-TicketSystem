<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SlaBreached;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SlaBreachNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Department $dept;
    private User $admin1;
    private User $admin2;
    private User $staff;
    private User $endUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dept = Department::create(['name' => 'IT', 'name_zh' => 'IT部']);
        $this->admin1 = User::factory()->create(['role' => 'admin']);
        $this->admin2 = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'it_staff']);
        $this->endUser = User::factory()->create(['role' => 'user']);
    }

    private function ticket(array $attrs = []): Ticket
    {
        return Ticket::create(array_merge([
            'title'         => 'T',
            'description'   => 'D',
            'status'        => 'open',
            'priority'      => 'high',
            'department_id' => $this->dept->id,
            'created_by'    => $this->endUser->id,
            'assigned_to'   => $this->staff->id,
        ], $attrs));
    }

    public function test_response_breach_flags_and_notifies_assignee_and_admins_only(): void
    {
        Notification::fake();
        $ticket = $this->ticket(['sla_response_due_at' => now()->subHour(), 'first_response_at' => null]);

        $this->artisan('sla:check-breaches')->assertExitCode(0);

        $this->assertTrue($ticket->fresh()->sla_response_breached);
        Notification::assertSentTo($this->staff, SlaBreached::class, fn ($n) => $n->type === 'response');
        Notification::assertSentTo($this->admin1, SlaBreached::class);
        Notification::assertSentTo($this->admin2, SlaBreached::class);
        Notification::assertNotSentTo($this->endUser, SlaBreached::class);
    }

    public function test_resolution_breach_flags_and_notifies(): void
    {
        Notification::fake();
        $ticket = $this->ticket(['sla_resolution_due_at' => now()->subHour(), 'status' => 'in_progress']);

        $this->artisan('sla:check-breaches')->assertExitCode(0);

        $this->assertTrue($ticket->fresh()->sla_resolution_breached);
        Notification::assertSentTo($this->staff, SlaBreached::class, fn ($n) => $n->type === 'resolution');
        Notification::assertSentTo($this->admin1, SlaBreached::class);
    }

    public function test_breach_notifies_only_once_across_runs(): void
    {
        Notification::fake();
        $this->ticket(['sla_response_due_at' => now()->subHour()]);

        $this->artisan('sla:check-breaches');
        $this->artisan('sla:check-breaches');

        Notification::assertSentToTimes($this->staff, SlaBreached::class, 1);
    }

    public function test_admin_assignee_is_notified_only_once(): void
    {
        Notification::fake();
        $this->ticket(['assigned_to' => $this->admin1->id, 'sla_response_due_at' => now()->subHour()]);

        $this->artisan('sla:check-breaches');

        Notification::assertSentToTimes($this->admin1, SlaBreached::class, 1);
    }

    public function test_does_not_breach_responded_resolved_closed_or_rejected_tickets(): void
    {
        Notification::fake();
        $responded = $this->ticket(['sla_response_due_at' => now()->subHour(), 'first_response_at' => now()->subMinutes(30)]);
        $resolved  = $this->ticket(['sla_resolution_due_at' => now()->subHour(), 'status' => 'resolved']);
        $closed    = $this->ticket(['sla_resolution_due_at' => now()->subHour(), 'status' => 'closed']);
        $rejected  = $this->ticket(['sla_resolution_due_at' => now()->subHour(), 'status' => 'rejected']);

        $this->artisan('sla:check-breaches');

        $this->assertFalse($responded->fresh()->sla_response_breached);
        $this->assertFalse($resolved->fresh()->sla_resolution_breached);
        $this->assertFalse($closed->fresh()->sla_resolution_breached);
        $this->assertFalse($rejected->fresh()->sla_resolution_breached);
        Notification::assertNothingSent();
    }
}
