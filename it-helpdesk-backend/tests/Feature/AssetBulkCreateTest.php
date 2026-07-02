<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssetBulkCreateTest extends TestCase
{
    use RefreshDatabase;

    private function itStaff(): User
    {
        return User::factory()->create(['role' => 'it_staff']);
    }

    // ── next-tag suggestion ──────────────────────────────────────────────

    public function test_next_tag_suggests_increment_of_latest_asset_prefix(): void
    {
        Sanctum::actingAs($this->itStaff());
        Asset::factory()->create(['asset_tag' => 'US02-ADOM001-020']);
        Asset::factory()->create(['asset_tag' => 'US02-ADOM001-038']);

        $this->getJson('/api/assets/next-tag')
            ->assertOk()
            ->assertJsonPath('suggested', 'US02-ADOM001-039');
    }

    public function test_next_tag_uses_highest_number_for_prefix_not_latest_row(): void
    {
        Sanctum::actingAs($this->itStaff());
        Asset::factory()->create(['asset_tag' => 'US02-ADOM001-038']);
        // Created later but numerically lower - suggestion must still be 039.
        Asset::factory()->create(['asset_tag' => 'US02-ADOM001-005']);

        $this->getJson('/api/assets/next-tag')
            ->assertOk()
            ->assertJsonPath('suggested', 'US02-ADOM001-039');
    }

    public function test_next_tag_with_no_assets_returns_null(): void
    {
        Sanctum::actingAs($this->itStaff());

        $this->getJson('/api/assets/next-tag')
            ->assertOk()
            ->assertJsonPath('suggested', null);
    }

    public function test_next_tag_pads_to_width_of_existing_suffix(): void
    {
        Sanctum::actingAs($this->itStaff());
        Asset::factory()->create(['asset_tag' => 'US02-ADOM001-008']);

        $this->getJson('/api/assets/next-tag')
            ->assertOk()
            ->assertJsonPath('suggested', 'US02-ADOM001-009');
    }

    // ── bulk create ──────────────────────────────────────────────────────

    public function test_bulk_creates_consecutive_assets_and_logs_history(): void
    {
        $staff = $this->itStaff();
        Sanctum::actingAs($staff);

        $res = $this->postJson('/api/assets/bulk', [
            'asset_tag' => 'US02-ADOM001-039',
            'quantity'  => 3,
            'category'  => 'Monitor',
            'model'     => 'Dell U2723QE',
        ])->assertCreated();

        $this->assertSame(3, $res->json('created'));
        foreach (['US02-ADOM001-039', 'US02-ADOM001-040', 'US02-ADOM001-041'] as $tag) {
            $this->assertDatabaseHas('assets', [
                'asset_tag' => $tag, 'category' => 'Monitor', 'model' => 'Dell U2723QE', 'status' => 'in_stock',
            ]);
        }
        $ids = Asset::pluck('id');
        foreach ($ids as $id) {
            $this->assertDatabaseHas('asset_histories', [
                'asset_id' => $id, 'action' => 'created', 'user_id' => $staff->id,
            ]);
        }
    }

    public function test_bulk_rejects_whole_batch_when_any_generated_tag_collides(): void
    {
        Sanctum::actingAs($this->itStaff());
        Asset::factory()->create(['asset_tag' => 'US02-ADOM001-040']);

        $this->postJson('/api/assets/bulk', [
            'asset_tag' => 'US02-ADOM001-039',
            'quantity'  => 3,
            'category'  => 'Monitor',
        ])->assertStatus(422)
          ->assertJsonValidationErrors('asset_tag');

        $this->assertDatabaseMissing('assets', ['asset_tag' => 'US02-ADOM001-039']);
        $this->assertDatabaseMissing('assets', ['asset_tag' => 'US02-ADOM001-041']);
        $this->assertSame(1, Asset::count());
    }

    public function test_bulk_rolls_over_past_padding_width(): void
    {
        Sanctum::actingAs($this->itStaff());

        $this->postJson('/api/assets/bulk', [
            'asset_tag' => 'US02-ADOM001-999',
            'quantity'  => 2,
            'category'  => 'Laptop',
        ])->assertCreated();

        $this->assertDatabaseHas('assets', ['asset_tag' => 'US02-ADOM001-999']);
        $this->assertDatabaseHas('assets', ['asset_tag' => 'US02-ADOM001-1000']);
    }

    public function test_bulk_requires_numeric_tag_suffix_and_sane_quantity(): void
    {
        Sanctum::actingAs($this->itStaff());

        // Tag without a trailing number cannot be incremented.
        $this->postJson('/api/assets/bulk', [
            'asset_tag' => 'NO-NUMBER-TAG-', 'quantity' => 2, 'category' => 'Laptop',
        ])->assertStatus(422)->assertJsonValidationErrors('asset_tag');

        $this->postJson('/api/assets/bulk', [
            'asset_tag' => 'US02-ADOM001-001', 'quantity' => 51, 'category' => 'Laptop',
        ])->assertStatus(422)->assertJsonValidationErrors('quantity');

        $this->postJson('/api/assets/bulk', [
            'asset_tag' => 'US02-ADOM001-001', 'quantity' => 0, 'category' => 'Laptop',
        ])->assertStatus(422)->assertJsonValidationErrors('quantity');
    }

    public function test_regular_user_is_forbidden_from_bulk_create_and_next_tag(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/assets/next-tag')->assertStatus(403);
        $this->postJson('/api/assets/bulk', [
            'asset_tag' => 'US02-ADOM001-001', 'quantity' => 2, 'category' => 'Laptop',
        ])->assertStatus(403);
    }
}
