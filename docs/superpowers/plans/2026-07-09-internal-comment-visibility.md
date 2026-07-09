# Internal Comment Visibility Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Prevent non-IT ticket viewers from receiving internal comments in the ticket detail API while preserving full visibility for IT staff and administrators.

**Architecture:** Keep the existing ticket detail endpoint and JSON shape. Constrain the eager-loaded `comments` relationship according to the authenticated user's role, matching the filtering already used by `CommentController::index`.

**Tech Stack:** Laravel 12, Eloquent constrained eager loading, Sanctum, PHPUnit feature tests

## Global Constraints

- Keep `GET /api/tickets/{ticket}` backward-compatible.
- Non-IT users receive only comments where `is_internal=false`.
- IT staff and administrators receive public and internal comments.
- Do not change the separate comments endpoint.

---

### Task 1: Add regression coverage and filter ticket-detail comments

**Files:**
- Create: `it-helpdesk-backend/tests/Feature/TicketCommentVisibilityTest.php`
- Modify: `it-helpdesk-backend/app/Http/Controllers/Api/TicketController.php:200-205`

**Interfaces:**
- Consumes: `GET /api/tickets/{ticket}` authenticated through `Sanctum::actingAs(User $user)`.
- Produces: the existing ticket JSON with a role-filtered `comments` array.

- [ ] **Step 1: Write the failing tests**

Create `TicketCommentVisibilityTest.php` with two tests. Build a ticket containing one public and one internal comment. Assert that its creator receives only the public comment, while IT staff receive both.

```php
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
```

- [ ] **Step 2: Run the test and verify RED**

Run:

```powershell
php artisan test tests/Feature/TicketCommentVisibilityTest.php
```

Expected: the creator test fails because the current response contains two comments and includes `Internal diagnosis`; the IT staff test passes.

- [ ] **Step 3: Implement the minimal role-based constrained eager load**

In `TicketController::show`, build the comments relation constraint from the authenticated user and preserve all other eager-loaded relationships:

```php
public function show(Request $request, Ticket $ticket): JsonResponse
{
    $this->authorize('view', $ticket);

    $relations = [
        'creator',
        'assignee',
        'department',
        'comments' => function ($query) use ($request) {
            if (!$request->user()->isItStaff()) {
                $query->where('is_internal', false);
            }
            $query->with('user');
        },
        'histories.user',
        'attachments',
        'approvals.approver',
        'approvals.responder',
    ];

    return response()->json($ticket->load($relations));
}
```

- [ ] **Step 4: Run the focused test and verify GREEN**

Run:

```powershell
php artisan test tests/Feature/TicketCommentVisibilityTest.php
```

Expected: both tests pass.

- [ ] **Step 5: Run the complete backend test suite**

Run:

```powershell
php artisan test
```

Expected: all backend tests pass with no new warnings or errors.
