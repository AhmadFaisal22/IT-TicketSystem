<?php

namespace Tests\Feature;

use App\Exports\AssetsExport;
use App\Models\Asset;
use App\Models\Department;
use App\Models\User;
use App\Support\AssetCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_and_status_rules_are_built_from_constants(): void
    {
        $this->assertSame('exists:asset_categories,name', AssetCategories::categoryRule());
        $this->assertSame('in:in_stock,assigned,in_repair,retired,lost', AssetCategories::statusRule());
    }

    public function test_asset_tag_is_auto_generated_sequentially(): void
    {
        $a = Asset::factory()->create();
        $b = Asset::factory()->create();

        $this->assertSame('AST-00001', $a->asset_tag);
        $this->assertSame('AST-00002', $b->asset_tag);
    }

    public function test_asset_belongs_to_assignee_and_user_has_assigned_assets(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create(['assigned_to' => $user->id, 'status' => 'assigned']);

        $this->assertTrue($asset->assignee->is($user));
        $this->assertTrue($user->assignedAssets->first()->is($asset));
    }

    private function itStaff(): User
    {
        return User::factory()->create(['role' => 'it_staff']);
    }

    public function test_regular_user_is_forbidden_from_listing_assets(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/assets')->assertStatus(403);
    }

    public function test_it_staff_can_search_assignable_active_users_for_assets(): void
    {
        Sanctum::actingAs($this->itStaff());

        $selected = User::factory()->create([
            'name' => 'Zoe Selected',
            'email' => 'zoe.selected@example.test',
            'role' => 'user',
            'active' => true,
        ]);
        $match = User::factory()->create([
            'name' => 'Alice Holder',
            'email' => 'alice.holder@example.test',
            'role' => 'user',
            'active' => true,
        ]);
        User::factory()->create([
            'name' => 'Inactive Alice',
            'email' => 'inactive.alice@example.test',
            'active' => false,
        ]);

        $this->getJson("/api/users/assignable?search=alice&selected_id={$selected->id}&limit=1")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $selected->id, 'name' => 'Zoe Selected'])
            ->assertJsonFragment([
                'id' => $match->id,
                'name' => 'Alice Holder',
                'email' => 'alice.holder@example.test',
                'role' => 'user',
            ])
            ->assertJsonMissing(['name' => 'Inactive Alice']);
    }

    public function test_it_staff_can_list_assets_with_filters(): void
    {
        Sanctum::actingAs($this->itStaff());
        Asset::factory()->create(['category' => 'Laptop', 'status' => 'in_stock']);
        Asset::factory()->create(['category' => 'Monitor', 'status' => 'assigned']);

        $this->getJson('/api/assets')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/assets?category=Laptop')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/assets?status=assigned')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_it_staff_can_filter_assets_by_department(): void
    {
        Sanctum::actingAs($this->itStaff());
        $finance = Department::create(['name' => 'Finance', 'name_zh' => 'FN']);
        $sales = Department::create(['name' => 'Sales', 'name_zh' => 'SL']);
        Asset::factory()->count(2)->create(['department_id' => $finance->id]);
        Asset::factory()->create(['department_id' => $sales->id]);

        $this->getJson("/api/assets?department_id={$finance->id}")
            ->assertOk()->assertJsonPath('total', 2);

        // An unknown department id is rejected by validation.
        $this->getJson('/api/assets?department_id=999999')->assertStatus(422);
    }

    public function test_search_matches_owner_full_name_in_either_order(): void
    {
        Sanctum::actingAs($this->itStaff());
        Asset::factory()->create(['first_name' => 'Kai', 'last_name' => 'Zhuang']);
        Asset::factory()->create(['first_name' => 'Tiger', 'last_name' => 'Wang']);

        // "first last" as a single query matches across the two name columns.
        $this->getJson('/api/assets?search=' . urlencode('Kai Zhuang'))
            ->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.last_name', 'Zhuang');

        // "last first" order matches too.
        $this->getJson('/api/assets?search=' . urlencode('Zhuang Kai'))
            ->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.first_name', 'Kai');

        // A single name term still matches.
        $this->getJson('/api/assets?search=Tiger')
            ->assertOk()->assertJsonPath('total', 1);
    }

    public function test_it_staff_can_view_an_asset(): void
    {
        Sanctum::actingAs($this->itStaff());
        $asset = Asset::factory()->create();

        $this->getJson("/api/assets/{$asset->id}")
            ->assertOk()
            ->assertJsonPath('asset_tag', $asset->asset_tag);
    }

    public function test_it_staff_can_create_an_asset_and_logs_created_history(): void
    {
        $staff = $this->itStaff();
        Sanctum::actingAs($staff);

        $res = $this->postJson('/api/assets', [
            'asset_tag' => 'US02-ADOM001-011',
            'name'      => 'Dell Latitude 5440',
            'category'  => 'Laptop',
        ])->assertCreated();

        // Tag is taken verbatim from input - no auto-increment.
        $this->assertSame('US02-ADOM001-011', $res->json('asset_tag'));

        // A missing tag is rejected; a duplicate tag is rejected.
        $this->postJson('/api/assets', ['category' => 'Laptop'])->assertStatus(422);
        $this->postJson('/api/assets', ['asset_tag' => 'US02-ADOM001-011', 'category' => 'Laptop'])->assertStatus(422);

        $id = $res->json('id');
        $this->assertDatabaseHas('assets', ['id' => $id, 'name' => 'Dell Latitude 5440', 'status' => 'in_stock']);
        $this->assertDatabaseHas('asset_histories', ['asset_id' => $id, 'action' => 'created', 'user_id' => $staff->id]);
    }

    public function test_create_rejects_invalid_category(): void
    {
        Sanctum::actingAs($this->itStaff());
        $this->postJson('/api/assets', ['asset_tag' => 'T-1', 'name' => 'X', 'category' => 'spaceship'])
            ->assertStatus(422);
    }

    public function test_it_staff_can_update_an_asset(): void
    {
        Sanctum::actingAs($this->itStaff());
        $asset = Asset::factory()->create(['name' => 'Old']);

        \App\Models\AssetLocation::create(['name' => 'HQ-3F']);
        $this->putJson("/api/assets/{$asset->id}", ['name' => 'New', 'location' => 'HQ-3F', 'version' => 1])
            ->assertOk()->assertJsonPath('name', 'New');
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'name' => 'New', 'location' => 'HQ-3F']);

        // A location that is not in the admin-managed list is rejected.
        $this->putJson("/api/assets/{$asset->id}", ['location' => 'Nowhere', 'version' => 2])->assertStatus(422);
    }

    public function test_update_requires_a_version_field(): void
    {
        Sanctum::actingAs($this->itStaff());
        $asset = Asset::factory()->create(['name' => 'Old']);

        $this->putJson("/api/assets/{$asset->id}", ['name' => 'New'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('version');
    }

    public function test_update_with_matching_version_succeeds_and_bumps_version(): void
    {
        Sanctum::actingAs($this->itStaff());
        $asset = Asset::factory()->create(['name' => 'Old']);

        $this->putJson("/api/assets/{$asset->id}", ['name' => 'New', 'version' => 1])
            ->assertOk()
            ->assertJsonPath('name', 'New')
            ->assertJsonPath('version', 2);

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'name' => 'New', 'version' => 2]);
    }

    public function test_update_with_stale_version_is_rejected_with_409(): void
    {
        Sanctum::actingAs($this->itStaff());
        $asset = Asset::factory()->create(['name' => 'Old']);

        // First writer holds version 1 and wins (1 -> 2).
        $this->putJson("/api/assets/{$asset->id}", ['name' => 'First', 'version' => 1])->assertOk();

        // Second writer still holds the stale version 1 -> conflict, no overwrite.
        $this->putJson("/api/assets/{$asset->id}", ['name' => 'Second', 'version' => 1])
            ->assertStatus(409);

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'name' => 'First', 'version' => 2]);
    }

    public function test_only_admin_can_delete_an_asset(): void
    {
        $asset = Asset::factory()->create();

        Sanctum::actingAs($this->itStaff());
        $this->deleteJson("/api/assets/{$asset->id}")->assertStatus(403);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->deleteJson("/api/assets/{$asset->id}")->assertNoContent();
        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
    }

    public function test_assign_sets_holder_status_and_logs_history(): void
    {
        $staff = $this->itStaff();
        Sanctum::actingAs($staff);
        Carbon::setTestNow('2026-06-11 10:00:00');
        $holder = User::factory()->create();
        $asset = Asset::factory()->create(['status' => 'in_stock', 'assign_date' => now()->subMonth()->toDateString()]);

        $this->patchJson("/api/assets/{$asset->id}/assign", ['assigned_to' => $holder->id])
            ->assertOk()
            ->assertJsonPath('status', 'assigned')
            ->assertJsonPath('assigned_to', $holder->id);

        $this->assertDatabaseHas('asset_histories', [
            'asset_id' => $asset->id, 'action' => 'assigned',
            'old_value' => null, 'new_value' => $holder->name,
        ]);
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'assign_date' => now()->toDateString(),
        ]);
        Carbon::setTestNow();

        // Re-assigning the same holder is a no-op and must not add history noise.
        $this->patchJson("/api/assets/{$asset->id}/assign", ['assigned_to' => $holder->id])->assertOk();
        $this->assertSame(1, $asset->histories()->where('action', 'assigned')->count());
    }

    public function test_returning_clears_holder_and_sets_in_stock(): void
    {
        Sanctum::actingAs($this->itStaff());
        $holder = User::factory()->create();
        $asset = Asset::factory()->create(['status' => 'assigned', 'assigned_to' => $holder->id]);

        $this->patchJson("/api/assets/{$asset->id}/assign", ['assigned_to' => null])
            ->assertOk()
            ->assertJsonPath('status', 'in_stock')
            ->assertJsonPath('assigned_to', null);

        $this->assertDatabaseHas('asset_histories', [
            'asset_id' => $asset->id, 'action' => 'returned',
            'old_value' => $holder->name, 'new_value' => null,
        ]);
    }

    public function test_status_change_logs_status_changed_history(): void
    {
        Sanctum::actingAs($this->itStaff());
        $asset = Asset::factory()->create(['status' => 'in_stock']);

        $this->patchJson("/api/assets/{$asset->id}/status", ['status' => 'in_repair'])
            ->assertOk()
            ->assertJsonPath('status', 'in_repair');

        $this->assertDatabaseHas('asset_histories', [
            'asset_id' => $asset->id, 'action' => 'status_changed', 'field' => 'status',
            'old_value' => 'in_stock', 'new_value' => 'in_repair',
        ]);
    }

    public function test_it_staff_can_upload_and_delete_an_asset_attachment(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->itStaff());
        $asset = Asset::factory()->create();

        $res = $this->postJson("/api/assets/{$asset->id}/attachments", [
            'attachments' => [UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf')],
        ])->assertCreated();

        $attachmentId = $res->json('0.id');
        $this->assertDatabaseHas('attachments', [
            'id' => $attachmentId, 'attachable_type' => \App\Models\Asset::class, 'attachable_id' => $asset->id,
        ]);

        $this->deleteJson("/api/assets/{$asset->id}/attachments/{$attachmentId}")->assertNoContent();
        $this->assertDatabaseMissing('attachments', ['id' => $attachmentId]);
    }

    public function test_meta_returns_categories_and_status_counts(): void
    {
        Sanctum::actingAs($this->itStaff());
        Asset::factory()->create(['status' => 'in_stock']);
        Asset::factory()->create(['status' => 'in_stock']);
        Asset::factory()->create(['status' => 'assigned', 'assigned_to' => User::factory()->create()->id]);

        $this->getJson('/api/assets/meta')
            ->assertOk()
            ->assertJsonCount(count(AssetCategories::DEFAULTS), 'categories')
            ->assertJsonPath('status_counts.in_stock', 2)
            ->assertJsonPath('status_counts.assigned', 1);
    }

    public function test_export_downloads_a_file(): void
    {
        Excel::fake();
        Sanctum::actingAs($this->itStaff());
        Asset::factory()->count(3)->create();

        $this->get('/api/assets/export')->assertOk();
        Excel::assertDownloaded('assets.xlsx');
    }

    public function test_export_respects_department_filter(): void
    {
        Excel::fake();
        Sanctum::actingAs($this->itStaff());
        $finance = Department::create(['name' => 'Finance', 'name_zh' => 'FN']);
        $sales = Department::create(['name' => 'Sales', 'name_zh' => 'SL']);
        Asset::factory()->count(2)->create(['department_id' => $finance->id]);
        Asset::factory()->create(['department_id' => $sales->id]);

        $this->get("/api/assets/export?department_id={$finance->id}")->assertOk();

        Excel::assertDownloaded('assets.xlsx', fn (AssetsExport $export) => $export->query()->count() === 2);
    }

    public function test_import_creates_assets_from_rows(): void
    {
        $rows = collect([
            collect(['name' => 'Imported Laptop', 'category' => 'Laptop', 'serial_number' => 'IMP-1']),
            collect(['name' => 'Imported Monitor', 'category' => 'Monitor', 'serial_number' => 'IMP-2']),
            collect(['name' => '', 'category' => 'Laptop']), // name optional -> created
            collect(['name' => 'Bad Category', 'category' => 'spaceship']), // invalid -> rejected
        ]);

        $import = new \App\Imports\AssetsImport();
        $import->collection($rows);

        $this->assertSame(3, $import->created);
        $this->assertCount(1, $import->rejected);
        $this->assertDatabaseHas('assets', ['name' => 'Imported Laptop', 'category' => 'Laptop']);
        $this->assertDatabaseHas('assets', ['name' => 'Imported Monitor', 'category' => 'Monitor']);
    }

    public function test_ticket_can_reference_an_asset_and_asset_exposes_related_tickets(): void
    {
        $asset = Asset::factory()->create();
        $department = \App\Models\Department::create(['name' => 'IT', 'name_zh' => 'IT閮ㄩ棬']);
        $ticket = \App\Models\Ticket::create([
            'title'         => 'Screen flickers',
            'description'   => 'Monitor flickers intermittently',
            'status'        => 'open',
            'priority'      => 'medium',
            'department_id' => $department->id,
            'created_by'    => $this->itStaff()->id,
            'asset_id'      => $asset->id,
        ]);

        $this->assertTrue($ticket->asset->is($asset));
        $this->assertTrue($asset->fresh()->tickets->first()->is($ticket));
    }
}
