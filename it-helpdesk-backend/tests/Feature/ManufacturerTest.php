<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Manufacturer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManufacturerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function itStaff(): User
    {
        return User::factory()->create(['role' => 'it_staff']);
    }

    public function test_it_staff_can_list_manufacturers(): void
    {
        Manufacturer::create(['name' => 'Lenovo']);
        Sanctum::actingAs($this->itStaff());

        $this->getJson('/api/asset-manufacturers')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Lenovo']);
    }

    public function test_admin_can_create_a_manufacturer_with_full_fields(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/asset-manufacturers', [
            'name'              => 'Dell Technologies',
            'short_name'        => 'Dell',
            'contact'           => 'Jane Roe',
            'support_phone'     => '+1-800-000-0000',
            'support_email'     => 'support@dell.test',
            'country_of_origin' => 'USA',
            'notes'             => 'Primary laptop vendor',
            'status'            => 'active',
        ])->assertCreated()
          ->assertJsonPath('name', 'Dell Technologies')
          ->assertJsonPath('short_name', 'Dell');

        $this->assertDatabaseHas('manufacturers', [
            'name' => 'Dell Technologies', 'support_email' => 'support@dell.test', 'status' => 'active',
        ]);
    }

    public function test_status_defaults_to_active_when_omitted(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/asset-manufacturers', ['name' => 'HP'])
            ->assertCreated()
            ->assertJsonPath('status', 'active');
    }

    public function test_create_rejects_duplicate_name_and_invalid_email(): void
    {
        Sanctum::actingAs($this->admin());
        Manufacturer::create(['name' => 'Apple']);

        $this->postJson('/api/asset-manufacturers', ['name' => 'Apple'])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $this->postJson('/api/asset-manufacturers', ['name' => 'Acme', 'support_email' => 'not-an-email'])
            ->assertStatus(422)->assertJsonValidationErrors('support_email');
    }

    public function test_admin_can_update_and_delete_a_manufacturer(): void
    {
        Sanctum::actingAs($this->admin());
        $m = Manufacturer::create(['name' => 'Old Name']);

        $this->putJson("/api/asset-manufacturers/{$m->id}", ['name' => 'New Name', 'status' => 'inactive'])
            ->assertOk()->assertJsonPath('name', 'New Name')->assertJsonPath('status', 'inactive');

        $this->deleteJson("/api/asset-manufacturers/{$m->id}")->assertNoContent();
        $this->assertDatabaseMissing('manufacturers', ['id' => $m->id]);
    }

    public function test_non_admin_cannot_write_manufacturers(): void
    {
        Sanctum::actingAs($this->itStaff());

        $this->postJson('/api/asset-manufacturers', ['name' => 'Nope'])->assertStatus(403);

        Sanctum::actingAs(User::factory()->create(['role' => 'user']));
        $this->getJson('/api/asset-manufacturers')->assertStatus(403);
    }

    public function test_asset_accepts_managed_manufacturer_and_rejects_unknown(): void
    {
        Sanctum::actingAs($this->itStaff());
        Manufacturer::create(['name' => 'Lenovo']);

        $this->postJson('/api/assets', [
            'asset_tag' => 'MFR-1', 'category' => 'Laptop', 'manufacturer' => 'Lenovo',
        ])->assertCreated()->assertJsonPath('manufacturer', 'Lenovo');

        $this->postJson('/api/assets', [
            'asset_tag' => 'MFR-2', 'category' => 'Laptop', 'manufacturer' => 'Ghost Brand',
        ])->assertStatus(422)->assertJsonValidationErrors('manufacturer');
    }

    public function test_backfill_creates_rows_from_distinct_existing_asset_values(): void
    {
        Asset::factory()->create(['manufacturer' => 'LENOVO']);
        Asset::factory()->create(['manufacturer' => 'LENOVO']); // duplicate -> one row
        Asset::factory()->create(['manufacturer' => 'DELL']);
        Asset::factory()->create(['manufacturer' => null]);     // skipped
        Manufacturer::create(['name' => 'DELL']);               // already exists -> not duplicated

        $created = Manufacturer::backfillFromAssets();

        $this->assertSame(1, $created); // only LENOVO is new
        $this->assertDatabaseHas('manufacturers', ['name' => 'LENOVO', 'status' => 'active']);
        $this->assertSame(1, Manufacturer::where('name', 'DELL')->count());
    }
}
