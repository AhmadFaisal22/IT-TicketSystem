# IT Asset Management — Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the backend REST API for the IT asset register (assets, history, ticket link, attachments, Excel import/export), IT-only, with PHPUnit feature tests.

**Architecture:** Mirror the existing Ticket module. An `Asset` Eloquent model + `AssetController` (`apiResource` + assign/status/attachments/import/export/meta endpoints) + an `AssetHistory` audit table. Every controller method enforces IT-only access via `abort_unless($request->user()->isItStaff(), 403)`, matching `DepartmentController`. Attachments reuse the polymorphic `Attachment` model. Excel via `maatwebsite/excel`.

**Tech Stack:** Laravel 12, PHP 8.2, PostgreSQL (prod) / SQLite `:memory:` (tests), Sanctum, `maatwebsite/excel`.

**Scope note:** This plan is backend only. The Vue frontend (views, store, router, nav, i18n, ticket UI integration) is a separate plan: `2026-06-10-inventory-frontend.md`. Spec: `docs/superpowers/specs/2026-06-10-inventory-design.md`.

**Conventions for every task:** run commands from `it-helpdesk-backend/`. Run a single test with `php artisan test --filter <name>`. Composer is invoked as `php composer.phar`.

---

## File Structure

**Create:**
- `app/Support/AssetCategories.php` — single source of truth for category keys + validation rule
- `database/migrations/2026_06_10_100001_create_assets_table.php`
- `database/migrations/2026_06_10_100002_create_asset_histories_table.php`
- `database/migrations/2026_06_10_100003_add_asset_id_to_tickets_table.php`
- `app/Models/Asset.php`
- `app/Models/AssetHistory.php`
- `database/factories/AssetFactory.php`
- `app/Http/Controllers/Api/AssetController.php`
- `app/Exports/AssetsExport.php`
- `app/Imports/AssetsImport.php`
- `database/seeders/AssetSeeder.php`
- `tests/Feature/AssetTest.php`

**Modify:**
- `app/Models/Ticket.php` — add `asset_id` to `$fillable` + `asset()` relation
- `app/Models/User.php` — add `assignedAssets()` relation
- `routes/api.php` — register asset routes
- `database/seeders/DatabaseSeeder.php` — call `AssetSeeder`
- `composer.json` / `composer.lock` — add `maatwebsite/excel`

---

## Task 1: Add Excel dependency + category source of truth

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create: `app/Support/AssetCategories.php`
- Test: `tests/Feature/AssetTest.php`

- [ ] **Step 1: Install maatwebsite/excel**

Run: `php composer.phar require maatwebsite/excel`
Expected: package added, `config/excel.php` auto-discovered. If memory errors occur, prefix with `php -d memory_limit=-1 composer.phar require maatwebsite/excel`.

- [ ] **Step 2: Create the category source of truth**

Create `app/Support/AssetCategories.php`:

```php
<?php

namespace App\Support;

class AssetCategories
{
    /** Fixed category keys. Bilingual labels live in the frontend i18n (asset.category.<key>). */
    public const KEYS = [
        'laptop',
        'desktop',
        'monitor',
        'printer',
        'network',
        'phone',
        'peripheral',
        'software_license',
        'other',
    ];

    public const STATUSES = [
        'in_stock',
        'assigned',
        'in_repair',
        'retired',
        'lost',
    ];

    /** Laravel validation rule fragment, e.g. "in:laptop,desktop,...". */
    public static function categoryRule(): string
    {
        return 'in:' . implode(',', self::KEYS);
    }

    public static function statusRule(): string
    {
        return 'in:' . implode(',', self::STATUSES);
    }
}
```

- [ ] **Step 3: Write a test asserting the constants are wired**

Create `tests/Feature/AssetTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Support\AssetCategories;
use Tests\TestCase;

class AssetTest extends TestCase
{
    public function test_category_and_status_rules_are_built_from_constants(): void
    {
        $this->assertSame('in:laptop,desktop,monitor,printer,network,phone,peripheral,software_license,other', AssetCategories::categoryRule());
        $this->assertSame('in:in_stock,assigned,in_repair,retired,lost', AssetCategories::statusRule());
    }
}
```

- [ ] **Step 4: Run the test**

Run: `php artisan test --filter test_category_and_status_rules_are_built_from_constants`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock app/Support/AssetCategories.php tests/Feature/AssetTest.php config/excel.php
git commit -m "feat(inventory): add Excel dep and asset category constants"
```

---

## Task 2: Migrations (assets, asset_histories, tickets.asset_id)

**Files:**
- Create: `database/migrations/2026_06_10_100001_create_assets_table.php`
- Create: `database/migrations/2026_06_10_100002_create_asset_histories_table.php`
- Create: `database/migrations/2026_06_10_100003_add_asset_id_to_tickets_table.php`

- [ ] **Step 1: Create the assets migration**

Create `database/migrations/2026_06_10_100001_create_assets_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable()->unique();
            $table->string('status')->default('in_stock');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('location')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
```

- [ ] **Step 2: Create the asset_histories migration**

Create `database/migrations/2026_06_10_100002_create_asset_histories_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('action');
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_histories');
    }
};
```

- [ ] **Step 3: Create the tickets.asset_id migration**

Create `database/migrations/2026_06_10_100003_add_asset_id_to_tickets_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->after('department_id')->constrained('assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_id');
        });
    }
};
```

- [ ] **Step 4: Run migrations against a scratch SQLite DB to verify they apply**

Run: `php artisan test --filter test_category_and_status_rules_are_built_from_constants`
(The test suite boots with `RefreshDatabase`-style in-memory migration once tests in later tasks use it; for now verify the migrations are syntactically valid by running:)
Run: `php artisan migrate:status`
Expected: the three new migrations listed as `Pending` with no PHP errors.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_10_100001_create_assets_table.php database/migrations/2026_06_10_100002_create_asset_histories_table.php database/migrations/2026_06_10_100003_add_asset_id_to_tickets_table.php
git commit -m "feat(inventory): add assets, asset_histories, tickets.asset_id migrations"
```

---

## Task 3: Models + factory + relations

**Files:**
- Create: `app/Models/Asset.php`, `app/Models/AssetHistory.php`, `database/factories/AssetFactory.php`
- Modify: `app/Models/Ticket.php`, `app/Models/User.php`
- Test: `tests/Feature/AssetTest.php`

- [ ] **Step 1: Write a failing test for asset_tag auto-generation and relations**

Add to `tests/Feature/AssetTest.php` (add the imports and the `RefreshDatabase` trait at the top of the class):

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Asset;
use App\Models\User;
```

```php
    use RefreshDatabase;

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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter test_asset_tag_is_auto_generated_sequentially`
Expected: FAIL ("Class Asset not found" or factory missing)

- [ ] **Step 3: Create the Asset model**

Create `app/Models/Asset.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_tag', 'name', 'category', 'manufacturer', 'model',
        'serial_number', 'status', 'assigned_to', 'department_id',
        'location', 'purchase_date', 'purchase_cost', 'warranty_expiry', 'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'purchase_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Asset $asset) {
            if (empty($asset->asset_tag)) {
                $asset->asset_tag = static::generateTag();
            }
        });
    }

    private static function generateTag(): string
    {
        $last = static::max('id') ?? 0;
        return 'AST-' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AssetHistory::class)->orderByDesc('created_at');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function logHistory(int $userId, string $action, ?string $field = null, ?string $old = null, ?string $new = null): void
    {
        $this->histories()->create([
            'user_id'    => $userId,
            'action'     => $action,
            'field'      => $field,
            'old_value'  => $old,
            'new_value'  => $new,
            'created_at' => now(),
        ]);
    }
}
```

- [ ] **Step 4: Create the AssetHistory model**

Create `app/Models/AssetHistory.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['asset_id', 'user_id', 'action', 'field', 'old_value', 'new_value', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
```

- [ ] **Step 5: Create the AssetFactory**

Create `database/factories/AssetFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Support\AssetCategories;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'          => $this->faker->words(2, true),
            'category'      => $this->faker->randomElement(AssetCategories::KEYS),
            'manufacturer'  => $this->faker->company(),
            'model'         => $this->faker->bothify('Model-####'),
            'serial_number' => $this->faker->unique()->bothify('SN-########'),
            'status'        => 'in_stock',
            'location'      => $this->faker->city(),
            'purchase_date' => $this->faker->date(),
        ];
    }
}
```

- [ ] **Step 6: Add the asset relation/fillable to Ticket and the relation to User**

In `app/Models/Ticket.php`, add `'asset_id'` to the `$fillable` array (after `'department_id'`), and add this relation method (after `department()`):

```php
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
```

In `app/Models/User.php`, add this relation (after `assignedTickets()`):

```php
    public function assignedAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'assigned_to');
    }
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter "test_asset_tag_is_auto_generated_sequentially|test_asset_belongs_to_assignee_and_user_has_assigned_assets"`
Expected: PASS (2 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Models/Asset.php app/Models/AssetHistory.php database/factories/AssetFactory.php app/Models/Ticket.php app/Models/User.php tests/Feature/AssetTest.php
git commit -m "feat(inventory): add Asset/AssetHistory models, factory, ticket+user relations"
```

---

## Task 4: AssetController index + show + routes (IT-only gate)

**Files:**
- Create: `app/Http/Controllers/Api/AssetController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/AssetTest.php`

- [ ] **Step 1: Write failing tests for listing, access control, and filtering**

Add to `tests/Feature/AssetTest.php` (add `use Laravel\Sanctum\Sanctum;` at top):

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter "test_regular_user_is_forbidden_from_listing_assets|test_it_staff_can_list_assets_with_filters|test_it_staff_can_view_an_asset"`
Expected: FAIL (404 — route not defined)

- [ ] **Step 3: Create the controller with index + show**

Create `app/Http/Controllers/Api/AssetController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Support\AssetCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    /** Reject anyone who is not IT staff/admin. Called at the top of every action. */
    private function authorizeItStaff(Request $request): void
    {
        abort_unless($request->user()->isItStaff(), 403);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeItStaff($request);

        $f = $request->validate([
            'status'        => 'nullable|' . AssetCategories::statusRule(),
            'category'      => 'nullable|' . AssetCategories::categoryRule(),
            'department_id' => 'nullable|integer|exists:departments,id',
            'assigned_to'   => 'nullable|integer|exists:users,id',
            'search'        => 'nullable|string|max:255',
        ]);

        $query = Asset::with(['assignee', 'department'])->orderByDesc('created_at');

        if (!empty($f['status'])) {
            $query->where('status', $f['status']);
        }
        if (!empty($f['category'])) {
            $query->where('category', $f['category']);
        }
        if (!empty($f['department_id'])) {
            $query->where('department_id', $f['department_id']);
        }
        if (!empty($f['assigned_to'])) {
            $query->where('assigned_to', $f['assigned_to']);
        }
        if (!empty($f['search'])) {
            $search = $f['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('asset_tag', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(20));
    }

    public function show(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeItStaff($request);

        return response()->json(
            $asset->load(['assignee', 'department', 'histories.user', 'attachments', 'tickets'])
        );
    }
}
```

- [ ] **Step 4: Register the asset routes**

In `routes/api.php`, add `use App\Http\Controllers\Api\AssetController;` to the imports, and inside the `Route::middleware('auth:sanctum')->group(...)` block add:

```php
    // Assets (IT only — enforced in controller)
    Route::get('assets', [AssetController::class, 'index']);
    Route::get('assets/{asset}', [AssetController::class, 'show']);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter "test_regular_user_is_forbidden_from_listing_assets|test_it_staff_can_list_assets_with_filters|test_it_staff_can_view_an_asset"`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AssetController.php routes/api.php tests/Feature/AssetTest.php
git commit -m "feat(inventory): asset index/show endpoints with IT-only gate"
```

---

## Task 5: Create / update / delete

**Files:**
- Modify: `app/Http/Controllers/Api/AssetController.php`, `routes/api.php`
- Test: `tests/Feature/AssetTest.php`

- [ ] **Step 1: Write failing tests for store/update/destroy + history + admin-only delete**

Add to `tests/Feature/AssetTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter "test_it_staff_can_create_an_asset_and_logs_created_history|test_create_rejects_invalid_category|test_it_staff_can_update_an_asset|test_only_admin_can_delete_an_asset"`
Expected: FAIL (405/404 — methods/routes not defined)

- [ ] **Step 3: Add store/update/destroy to the controller**

Add to `app/Http/Controllers/Api/AssetController.php` (add `use Illuminate\Support\Facades\DB;` at top):

```php
    public function store(Request $request): JsonResponse
    {
        $this->authorizeItStaff($request);

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'category'        => 'required|' . AssetCategories::categoryRule(),
            'manufacturer'    => 'nullable|string|max:255',
            'model'           => 'nullable|string|max:255',
            'serial_number'   => 'nullable|string|max:255|unique:assets,serial_number',
            'status'          => 'nullable|' . AssetCategories::statusRule(),
            'assigned_to'     => 'nullable|exists:users,id',
            'department_id'   => 'nullable|exists:departments,id',
            'location'        => 'nullable|string|max:255',
            'purchase_date'   => 'nullable|date',
            'purchase_cost'   => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'notes'           => 'nullable|string|max:10000',
        ]);

        $asset = DB::transaction(function () use ($data, $request) {
            $asset = Asset::create($data);
            $asset->logHistory($request->user()->id, 'created');
            return $asset;
        });

        return response()->json($asset->load(['assignee', 'department']), 201);
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeItStaff($request);

        $data = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'category'        => 'sometimes|' . AssetCategories::categoryRule(),
            'manufacturer'    => 'nullable|string|max:255',
            'model'           => 'nullable|string|max:255',
            'serial_number'   => 'nullable|string|max:255|unique:assets,serial_number,' . $asset->id,
            'location'        => 'nullable|string|max:255',
            'purchase_date'   => 'nullable|date',
            'purchase_cost'   => 'nullable|numeric|min:0',
            'warranty_expiry' => 'nullable|date',
            'notes'           => 'nullable|string|max:10000',
        ]);

        DB::transaction(function () use ($asset, $data, $request) {
            $asset->update($data);
            $asset->logHistory($request->user()->id, 'updated');
        });

        return response()->json($asset->load(['assignee', 'department']));
    }

    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $asset->delete();
        return response()->json(null, 204);
    }
```

Note: `update` intentionally does NOT accept `status`, `assigned_to`, or `department_id` — those change only through the dedicated assign/status endpoints (Task 6) so history stays meaningful.

- [ ] **Step 4: Register the routes**

In `routes/api.php`, add under the existing asset routes:

```php
    Route::post('assets', [AssetController::class, 'store']);
    Route::put('assets/{asset}', [AssetController::class, 'update']);
    Route::delete('assets/{asset}', [AssetController::class, 'destroy']);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter "test_it_staff_can_create_an_asset_and_logs_created_history|test_create_rejects_invalid_category|test_it_staff_can_update_an_asset|test_only_admin_can_delete_an_asset"`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AssetController.php routes/api.php tests/Feature/AssetTest.php
git commit -m "feat(inventory): asset create/update/delete endpoints"
```

---

## Task 6: Assign / return + status change

**Files:**
- Modify: `app/Http/Controllers/Api/AssetController.php`, `routes/api.php`
- Test: `tests/Feature/AssetTest.php`

- [ ] **Step 1: Write failing tests for assign, return, and status change**

Add to `tests/Feature/AssetTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter "test_assign_sets_holder_status_and_logs_history|test_returning_clears_holder_and_sets_in_stock|test_status_change_logs_status_changed_history"`
Expected: FAIL (404 — routes not defined)

- [ ] **Step 3: Add assign + updateStatus to the controller**

Add to `app/Http/Controllers/Api/AssetController.php`:

```php
    public function assign(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeItStaff($request);

        $data = $request->validate([
            'assigned_to'   => 'nullable|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        DB::transaction(function () use ($asset, $data, $request) {
            $previousHolder = $asset->assigned_to;

            if (!empty($data['assigned_to'])) {
                $asset->assigned_to = $data['assigned_to'];
                if (array_key_exists('department_id', $data)) {
                    $asset->department_id = $data['department_id'];
                }
                $asset->status = 'assigned';
                $asset->save();
                $asset->logHistory($request->user()->id, 'assigned', 'assigned_to', (string) $previousHolder, (string) $data['assigned_to']);
            } else {
                $asset->assigned_to = null;
                $asset->status = 'in_stock';
                $asset->save();
                $asset->logHistory($request->user()->id, 'returned', 'assigned_to', (string) $previousHolder, null);
            }
        });

        return response()->json($asset->fresh()->load(['assignee', 'department']));
    }

    public function updateStatus(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeItStaff($request);

        $data = $request->validate(['status' => 'required|' . AssetCategories::statusRule()]);
        $old = $asset->status;

        DB::transaction(function () use ($asset, $data, $old, $request) {
            $asset->status = $data['status'];
            // Returning to stock via status also clears the holder.
            if ($data['status'] === 'in_stock') {
                $asset->assigned_to = null;
            }
            $asset->save();
            $asset->logHistory($request->user()->id, 'status_changed', 'status', $old, $data['status']);
        });

        return response()->json($asset->fresh()->load(['assignee', 'department']));
    }
```

- [ ] **Step 4: Register the routes**

In `routes/api.php`, add under the existing asset routes:

```php
    Route::patch('assets/{asset}/assign', [AssetController::class, 'assign']);
    Route::patch('assets/{asset}/status', [AssetController::class, 'updateStatus']);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter "test_assign_sets_holder_status_and_logs_history|test_returning_clears_holder_and_sets_in_stock|test_status_change_logs_status_changed_history"`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AssetController.php routes/api.php tests/Feature/AssetTest.php
git commit -m "feat(inventory): asset assign/return and status-change endpoints"
```

---

## Task 7: Attachments (upload / delete)

**Files:**
- Modify: `app/Http/Controllers/Api/AssetController.php`, `routes/api.php`
- Test: `tests/Feature/AssetTest.php`

- [ ] **Step 1: Write failing tests for upload + delete**

Add to `tests/Feature/AssetTest.php` (add `use Illuminate\Http\UploadedFile;` and `use Illuminate\Support\Facades\Storage;`):

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter test_it_staff_can_upload_and_delete_an_asset_attachment`
Expected: FAIL (404 — routes not defined)

- [ ] **Step 3: Add attachment methods to the controller**

Add to `app/Http/Controllers/Api/AssetController.php` (add `use App\Models\Attachment;` and `use Illuminate\Support\Facades\Storage;`):

```php
    public function storeAttachments(Request $request, Asset $asset): JsonResponse
    {
        $this->authorizeItStaff($request);

        $request->validate([
            'attachments'   => 'required|array|max:5',
            'attachments.*' => 'file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240',
        ]);

        $created = [];
        foreach ($request->file('attachments') as $file) {
            $path = $file->store('asset-attachments', 'public');
            $created[] = $asset->attachments()->create([
                'user_id'       => $request->user()->id,
                'filename'      => basename($path),
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'size'          => $file->getSize(),
                'path'          => $path,
            ]);
        }

        return response()->json($created, 201);
    }

    public function destroyAttachment(Request $request, Asset $asset, Attachment $attachment): JsonResponse
    {
        $this->authorizeItStaff($request);
        abort_unless(
            $attachment->attachable_type === Asset::class && $attachment->attachable_id === $asset->id,
            404
        );

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return response()->json(null, 204);
    }
```

- [ ] **Step 4: Register the routes**

In `routes/api.php`, add under the existing asset routes:

```php
    Route::post('assets/{asset}/attachments', [AssetController::class, 'storeAttachments']);
    Route::delete('assets/{asset}/attachments/{attachment}', [AssetController::class, 'destroyAttachment']);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter test_it_staff_can_upload_and_delete_an_asset_attachment`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AssetController.php routes/api.php tests/Feature/AssetTest.php
git commit -m "feat(inventory): asset attachment upload/delete"
```

---

## Task 8: Excel export + import + meta endpoint

**Files:**
- Create: `app/Exports/AssetsExport.php`, `app/Imports/AssetsImport.php`
- Modify: `app/Http/Controllers/Api/AssetController.php`, `routes/api.php`
- Test: `tests/Feature/AssetTest.php`

- [ ] **Step 1: Write failing tests for export, import, and meta**

Add to `tests/Feature/AssetTest.php` (add `use Maatwebsite\Excel\Facades\Excel;`):

```php
    public function test_meta_returns_categories_and_status_counts(): void
    {
        Sanctum::actingAs($this->itStaff());
        Asset::factory()->create(['status' => 'in_stock']);
        Asset::factory()->create(['status' => 'in_stock']);
        Asset::factory()->create(['status' => 'assigned', 'assigned_to' => User::factory()->create()->id]);

        $this->getJson('/api/assets/meta')
            ->assertOk()
            ->assertJsonPath('categories', \App\Support\AssetCategories::KEYS)
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

    public function test_import_creates_assets_from_rows(): void
    {
        $rows = collect([
            collect(['name' => 'Imported Laptop', 'category' => 'laptop', 'serial_number' => 'IMP-1']),
            collect(['name' => 'Imported Monitor', 'category' => 'monitor', 'serial_number' => 'IMP-2']),
            collect(['name' => '', 'category' => 'laptop']), // invalid -> rejected
        ]);

        $import = new \App\Imports\AssetsImport();
        $import->collection($rows);

        $this->assertSame(2, $import->created);
        $this->assertCount(1, $import->rejected);
        $this->assertDatabaseHas('assets', ['name' => 'Imported Laptop', 'category' => 'laptop']);
        $this->assertDatabaseHas('assets', ['name' => 'Imported Monitor', 'category' => 'monitor']);
    }
```

Note: the import test exercises the `AssetsImport::collection()` logic directly (no fixture file needed). The HTTP `import` endpoint is thin glue over `Excel::import` and is covered by manual verification in the final task.

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter "test_meta_returns_categories_and_status_counts|test_export_downloads_a_file|test_import_creates_assets_from_rows"`
Expected: FAIL (404 / class not found)

- [ ] **Step 3: Create the export class**

Create `app/Exports/AssetsExport.php`:

```php
<?php

namespace App\Exports;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AssetsExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private Builder $query) {}

    public function query(): Builder
    {
        return $this->query->with(['assignee', 'department']);
    }

    public function headings(): array
    {
        return ['Asset Tag', 'Name', 'Category', 'Manufacturer', 'Model', 'Serial Number',
            'Status', 'Assignee', 'Department', 'Location', 'Purchase Date', 'Purchase Cost',
            'Warranty Expiry', 'Notes'];
    }

    public function map($asset): array
    {
        return [
            $asset->asset_tag,
            $asset->name,
            $asset->category,
            $asset->manufacturer,
            $asset->model,
            $asset->serial_number,
            $asset->status,
            $asset->assignee?->name,
            $asset->department?->name,
            $asset->location,
            optional($asset->purchase_date)->format('Y-m-d'),
            $asset->purchase_cost,
            optional($asset->warranty_expiry)->format('Y-m-d'),
            $asset->notes,
        ];
    }
}
```

- [ ] **Step 4: Create the import class**

Create `app/Imports/AssetsImport.php`:

```php
<?php

namespace App\Imports;

use App\Models\Asset;
use App\Support\AssetCategories;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AssetsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    /** @var array<int,array{row:int,reason:string}> */
    public array $rejected = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $category = trim((string) ($row['category'] ?? ''));
            $serial = $row['serial_number'] ?? null;

            if ($name === '' || !in_array($category, AssetCategories::KEYS, true)) {
                $this->rejected[] = ['row' => $i + 2, 'reason' => 'Missing name or invalid category'];
                continue;
            }
            if (!empty($serial) && Asset::where('serial_number', $serial)->exists()) {
                $this->rejected[] = ['row' => $i + 2, 'reason' => "Duplicate serial_number {$serial}"];
                continue;
            }

            Asset::create([
                'name'          => $name,
                'category'      => $category,
                'manufacturer'  => $row['manufacturer'] ?? null,
                'model'         => $row['model'] ?? null,
                'serial_number' => $serial ?: null,
                'status'        => in_array($row['status'] ?? null, AssetCategories::STATUSES, true) ? $row['status'] : 'in_stock',
                'location'      => $row['location'] ?? null,
            ]);
            $this->created++;
        }
    }
}
```

- [ ] **Step 5: Add export/import/meta to the controller**

Add to `app/Http/Controllers/Api/AssetController.php` (add `use App\Exports\AssetsExport;`, `use App\Imports\AssetsImport;`, `use Maatwebsite\Excel\Facades\Excel;`):

```php
    public function meta(Request $request): JsonResponse
    {
        $this->authorizeItStaff($request);

        $counts = Asset::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $statusCounts = [];
        foreach (AssetCategories::STATUSES as $s) {
            $statusCounts[$s] = (int) ($counts[$s] ?? 0);
        }

        return response()->json([
            'categories'    => AssetCategories::KEYS,
            'statuses'      => AssetCategories::STATUSES,
            'status_counts' => $statusCounts,
        ]);
    }

    public function export(Request $request)
    {
        $this->authorizeItStaff($request);

        $query = Asset::query()->orderByDesc('created_at');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        return Excel::download(new AssetsExport($query), 'assets.xlsx');
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorizeItStaff($request);
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);

        $import = new AssetsImport();
        Excel::import($import, $request->file('file'));

        return response()->json([
            'created'  => $import->created,
            'rejected' => $import->rejected,
        ]);
    }
```

- [ ] **Step 6: Register the routes**

In `routes/api.php`, add the static asset routes **above** the `assets/{asset}` route so they are not captured as an `{asset}` parameter:

```php
    Route::get('assets/meta', [AssetController::class, 'meta']);
    Route::get('assets/export', [AssetController::class, 'export']);
    Route::post('assets/import', [AssetController::class, 'import']);
```

Verify final order in `routes/api.php` is: `assets` (index), `assets/meta`, `assets/export`, `assets/import`, `assets/{asset}` (show), then the rest. Laravel matches in declaration order, so the literal paths must precede the `{asset}` wildcard.

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test --filter "test_meta_returns_categories_and_status_counts|test_export_downloads_a_file|test_import_creates_assets_from_rows"`
Expected: PASS (3 tests)

- [ ] **Step 8: Commit**

```bash
git add app/Exports/AssetsExport.php app/Imports/AssetsImport.php app/Http/Controllers/Api/AssetController.php routes/api.php tests/Feature/AssetTest.php
git commit -m "feat(inventory): asset Excel export/import and meta endpoint"
```

---

## Task 9: Seeder + ticket-with-asset coverage + full suite

**Files:**
- Create: `database/seeders/AssetSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/AssetTest.php`

- [ ] **Step 1: Write a failing test that a ticket can reference an asset**

Add to `tests/Feature/AssetTest.php`:

```php
    public function test_ticket_can_reference_an_asset_and_asset_exposes_related_tickets(): void
    {
        $asset = Asset::factory()->create();
        $ticket = \App\Models\Ticket::create([
            'title'         => 'Screen flickers',
            'description'   => 'Monitor flickers intermittently',
            'status'        => 'open',
            'priority'      => 'medium',
            'department_id' => null,
            'created_by'    => $this->itStaff()->id,
            'asset_id'      => $asset->id,
        ]);

        $this->assertTrue($ticket->asset->is($asset));
        $this->assertTrue($asset->fresh()->tickets->first()->is($ticket));
    }
```

Note: `Ticket` auto-generates `ticket_number` via its `creating` hook, and `department_id` is nullable in the tickets schema, so this minimal create works.

- [ ] **Step 2: Run the test to verify it passes (relation already wired in Task 3)**

Run: `php artisan test --filter test_ticket_can_reference_an_asset_and_asset_exposes_related_tickets`
Expected: PASS (confirms the Task 3 relations + Task 2 column work end to end)

- [ ] **Step 3: Create the AssetSeeder**

Create `database/seeders/AssetSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        if (Asset::count() > 0) {
            return;
        }

        $holder = User::query()->inRandomOrder()->first();

        Asset::factory()->count(15)->create();
        Asset::factory()->count(5)->create([
            'status'      => 'assigned',
            'assigned_to' => $holder?->id,
        ]);
        Asset::factory()->count(2)->create(['status' => 'in_repair']);
    }
}
```

- [ ] **Step 4: Wire the seeder into DatabaseSeeder**

In `database/seeders/DatabaseSeeder.php`, add `AssetSeeder::class` to the `$this->call([...])` array (after the existing seeders). If the file calls seeders individually, add `$this->call(AssetSeeder::class);` at the end of `run()`.

- [ ] **Step 5: Run the entire asset test file**

Run: `php artisan test --filter AssetTest`
Expected: PASS (all asset tests green)

- [ ] **Step 6: Run the full suite to confirm nothing else broke**

Run: `php artisan test`
Expected: PASS (asset tests + existing example tests; no failures introduced by the tickets.asset_id migration)

- [ ] **Step 7: Commit**

```bash
git add database/seeders/AssetSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/AssetTest.php
git commit -m "feat(inventory): asset seeder and ticket-asset link coverage"
```

---

## Self-Review notes (already applied)

- **Spec coverage:** assets/asset_histories/tickets.asset_id (Task 2), models+relations (Task 3), index/show + IT-only gate (Task 4), CRUD + admin-only delete (Task 5), assign/return/status + history (Task 6), attachments reusing polymorphic Attachment (Task 7), Excel import/export + meta (Task 8), seeder + ticket link (Task 9). Categories fixed list (Task 1). Frontend, QR, i18n → separate frontend plan (out of scope here).
- **Deferred per spec:** warranty-expiry alerts (field stored, no notification); consumables; end-user view.
- **Type consistency:** `logHistory(userId, action, field, old, new)` defined in Task 3 and used identically in Tasks 5/6; `authorizeItStaff()` defined in Task 4 and reused in all later actions; route ordering note in Task 8 prevents `assets/{asset}` from shadowing `assets/meta|export|import`.

## Verification before done

- `php artisan test` green.
- `php artisan migrate:fresh --seed` on a local Postgres runs cleanly and produces sample assets (manual, since CI/prod uses Postgres while tests use SQLite).
```
