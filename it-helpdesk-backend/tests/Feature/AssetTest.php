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
}
