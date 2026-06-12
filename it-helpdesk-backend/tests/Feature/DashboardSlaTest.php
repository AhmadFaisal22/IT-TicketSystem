<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardSlaTest extends TestCase
{
    use RefreshDatabase;

    private function makeTicket(User $creator, Department $department, bool $breached): Ticket
    {
        return Ticket::create([
            'title'                   => 'SLA test ticket',
            'description'             => 'body',
            'status'                  => 'open',
            'priority'                => 'medium',
            'department_id'           => $department->id,
            'created_by'              => $creator->id,
            'sla_response_breached'   => $breached,
            'sla_resolution_breached' => $breached,
        ]);
    }

    public function test_sla_dashboard_counts_breached_tickets(): void
    {
        $staff      = User::factory()->create(['role' => 'it_staff']);
        $department = Department::create(['name' => 'IT', 'name_zh' => 'IT部']);

        $this->makeTicket($staff, $department, breached: true);
        $this->makeTicket($staff, $department, breached: false);

        Sanctum::actingAs($staff);

        $this->getJson('/api/dashboard/sla?range=30')
            ->assertStatus(200)
            ->assertJson([
                'total_tickets'       => 2,
                'response_breached'   => 1,
                'resolution_breached' => 1,
                'overall_compliance'  => 50.0,
            ]);
    }

    public function test_regular_user_cannot_view_sla_dashboard(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/dashboard/sla')->assertStatus(403);
    }
}
