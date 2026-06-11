<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use App\Support\AssetCategories;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
