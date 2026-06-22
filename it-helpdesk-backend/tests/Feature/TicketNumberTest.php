<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketNumberTest extends TestCase
{
    use RefreshDatabase;

    private function makeTicket(): Ticket
    {
        $department = Department::firstOrCreate(['name' => 'IT'], ['name_zh' => 'IT部']);
        $creator = User::factory()->create();

        return Ticket::create([
            'title'         => 'Test',
            'description'   => 'Test',
            'status'        => 'open',
            'priority'      => 'medium',
            'department_id' => $department->id,
            'created_by'    => $creator->id,
        ]);
    }

    public function test_ticket_number_is_derived_from_id(): void
    {
        $a = $this->makeTicket();
        $b = $this->makeTicket();

        $this->assertSame('TKT-00001', $a->ticket_number);
        $this->assertSame('TKT-00002', $b->ticket_number);
        // Stored value matches the in-memory value (no stale temp placeholder).
        $this->assertDatabaseHas('tickets', ['id' => $b->id, 'ticket_number' => 'TKT-00002']);
    }

    public function test_ticket_number_is_not_reused_after_the_latest_is_deleted(): void
    {
        $a = $this->makeTicket();   // id 1 -> TKT-00001
        $b = $this->makeTicket();   // id 2 -> TKT-00002
        $b->delete();               // max(id) drops back to 1

        $c = $this->makeTicket();   // id 3 -> must be TKT-00003, NOT TKT-00002

        $this->assertSame('TKT-00003', $c->ticket_number);
    }
}
