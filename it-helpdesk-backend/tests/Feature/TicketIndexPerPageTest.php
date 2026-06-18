<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketIndexPerPageTest extends TestCase
{
    use RefreshDatabase;

    private function seedTickets(int $count): User
    {
        $dept  = Department::create(['name' => 'IT', 'name_zh' => 'IT部']);
        $staff = User::factory()->create(['role' => 'it_staff', 'department_id' => $dept->id]);

        for ($i = 0; $i < $count; $i++) {
            Ticket::create([
                'title'         => "Ticket {$i}",
                'description'   => 'desc',
                'status'        => 'open',
                'priority'      => 'medium',
                'department_id' => $dept->id,
                'created_by'    => $staff->id,
                'assigned_to'   => $staff->id,
            ]);
        }

        return $staff;
    }

    public function test_per_page_controls_page_size(): void
    {
        $staff = $this->seedTickets(25);
        Sanctum::actingAs($staff);

        $this->getJson('/api/tickets?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('per_page', 5);
    }

    public function test_defaults_to_twenty_per_page(): void
    {
        $staff = $this->seedTickets(25);
        Sanctum::actingAs($staff);

        $this->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('per_page', 20);
    }

    public function test_rejects_invalid_per_page(): void
    {
        $staff = $this->seedTickets(1);
        Sanctum::actingAs($staff);

        $this->getJson('/api/tickets?per_page=0')->assertStatus(422);
        $this->getJson('/api/tickets?per_page=101')->assertStatus(422);
        $this->getJson('/api/tickets?per_page=abc')->assertStatus(422);
    }

    public function test_accepts_upper_bound_per_page(): void
    {
        $staff = $this->seedTickets(25);
        Sanctum::actingAs($staff);

        $this->getJson('/api/tickets?per_page=100')
            ->assertOk()
            ->assertJsonPath('per_page', 100)
            ->assertJsonCount(25, 'data');
    }
}
