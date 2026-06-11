<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use App\Support\AssetCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_and_status_rules_are_built_from_constants(): void
    {
        $this->assertSame('in:laptop,desktop,monitor,printer,network,phone,peripheral,software_license,other', AssetCategories::categoryRule());
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

    public function test_it_staff_can_list_assets_with_filters(): void
    {
        Sanctum::actingAs($this->itStaff());
        Asset::factory()->create(['category' => 'laptop', 'status' => 'in_stock']);
        Asset::factory()->create(['category' => 'monitor', 'status' => 'assigned']);

        $this->getJson('/api/assets')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/assets?category=laptop')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/assets?status=assigned')->assertOk()->assertJsonPath('total', 1);
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
            'name'     => 'Dell Latitude 5440',
            'category' => 'laptop',
        ])->assertCreated();

        $id = $res->json('id');
        $this->assertDatabaseHas('assets', ['id' => $id, 'name' => 'Dell Latitude 5440', 'status' => 'in_stock']);
        $this->assertDatabaseHas('asset_histories', ['asset_id' => $id, 'action' => 'created', 'user_id' => $staff->id]);
    }

    public function test_create_rejects_invalid_category(): void
    {
        Sanctum::actingAs($this->itStaff());
        $this->postJson('/api/assets', ['name' => 'X', 'category' => 'spaceship'])
            ->assertStatus(422);
    }

    public function test_it_staff_can_update_an_asset(): void
    {
        Sanctum::actingAs($this->itStaff());
        $asset = Asset::factory()->create(['name' => 'Old']);

        $this->putJson("/api/assets/{$asset->id}", ['name' => 'New', 'location' => 'HQ-3F'])
            ->assertOk()->assertJsonPath('name', 'New');
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'name' => 'New', 'location' => 'HQ-3F']);
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
        $holder = User::factory()->create();
        $asset = Asset::factory()->create(['status' => 'in_stock']);

        $this->patchJson("/api/assets/{$asset->id}/assign", ['assigned_to' => $holder->id])
            ->assertOk()
            ->assertJsonPath('status', 'assigned')
            ->assertJsonPath('assigned_to', $holder->id);

        $this->assertDatabaseHas('asset_histories', ['asset_id' => $asset->id, 'action' => 'assigned']);
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

        $this->assertDatabaseHas('asset_histories', ['asset_id' => $asset->id, 'action' => 'returned']);
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
}
