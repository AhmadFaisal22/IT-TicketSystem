<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use App\Support\AssetCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
