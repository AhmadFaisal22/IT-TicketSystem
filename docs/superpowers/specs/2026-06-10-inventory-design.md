# IT Asset Management (Inventory) — Design Spec

- **Date:** 2026-06-10
- **Branch:** `feature/inventory`
- **Status:** Approved design, pending implementation plan

## 1. Goal

Add a site-wide **IT asset register** to the existing IT HelpDesk system. Each asset is an
individually tracked item (laptop, monitor, network gear, etc.) with a serial number, a
lifecycle status, an owner, and a department. The module integrates with the existing
ticket, user, and department data and reuses the existing polymorphic attachment system.

The module follows the conventions of the existing **Ticket** module (model + `apiResource`
controller + dedicated action endpoints + history/audit table + Pinia store + nested SPA
routes + bilingual i18n).

## 2. Scope

### In scope (v1)

- Asset CRUD
- Assign / return (set & clear current holder)
- Status lifecycle: in_stock → assigned → in_repair → retired (+ lost)
- Movement/audit log per asset (mirrors `ticket_histories`)
- Asset ↔ User (assigned holder)
- Asset ↔ Department (owning department)
- Ticket ↔ Asset (a ticket can reference the asset it concerns)
- File attachments per asset (PDF / photos) — reuse polymorphic `Attachment`
- Excel import / export (`maatwebsite/excel`)
- QR code label per asset (front-end generated, encodes the asset detail deep link)
- IT-only visibility (it_staff / admin); regular users have no access
- Fixed category list defined in code, bilingual labels in i18n
- PHPUnit feature tests for the critical paths

### Out of scope (v1)

- Warranty-expiry alerts/notifications (the `warranty_expiry` field is stored, but no
  automated reminder is built)
- Quantity-based consumable/stock management (this module tracks individual items only)
- End-user "my devices" view (module is IT-only)
- Admin-managed (DB) category table — categories are a fixed code list

## 3. Data Model

### 3.1 New table `assets`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `asset_tag` | string, unique | Auto-generated `AST-00001` via `booted()` `creating` hook (mirrors `Ticket::generateNumber`) |
| `name` | string | e.g. "Dell Latitude 5440" |
| `category` | string | One of the fixed category keys (§3.4) |
| `manufacturer` | string, nullable | |
| `model` | string, nullable | |
| `serial_number` | string, nullable, unique | Null allowed; unique when present |
| `status` | string | One of the status keys (§4.1); default `in_stock` |
| `assigned_to` | FK `users.id`, nullable | `nullOnDelete`. Current holder |
| `department_id` | FK `departments.id`, nullable | `nullOnDelete`. Owning department |
| `location` | string, nullable | Physical location / room |
| `purchase_date` | date, nullable | |
| `purchase_cost` | decimal(12,2), nullable | |
| `warranty_expiry` | date, nullable | Stored only; no alert in v1 |
| `notes` | text, nullable | |
| `created_at`, `updated_at` | timestamps | |

Casts: `purchase_date`, `warranty_expiry` → `date`; `purchase_cost` → `decimal:2`.

### 3.2 New table `asset_histories` (mirrors `ticket_histories`)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `asset_id` | FK `assets.id`, `cascadeOnDelete` | |
| `user_id` | FK `users.id`, constrained | Actor who made the change |
| `action` | string | `created` / `updated` / `assigned` / `returned` / `status_changed` |
| `field` | string, nullable | Field name when relevant (e.g. `status`) |
| `old_value` | text, nullable | |
| `new_value` | text, nullable | |
| `created_at` | timestamp | No `updated_at` (matches `ticket_histories`) |

### 3.3 Change to existing `tickets` table

- Add `asset_id` — FK `assets.id`, nullable, `nullOnDelete`. A new migration adds the column
  (existing tickets table is created by an earlier migration; this is an additive migration,
  consistent with `add_subcategory_to_tickets_table`).
- Add `asset_id` to `Ticket::$fillable` and an `asset()` BelongsTo relation.

### 3.4 Categories (fixed code list)

Keys (stored in `assets.category`): `laptop`, `desktop`, `monitor`, `printer`, `network`,
`phone`, `peripheral`, `software_license`, `other`.

- Backend: a single source of truth (e.g. `App\Support\AssetCategories` constant array) used
  for validation (`in:...`).
- Frontend: bilingual labels resolved through i18n (`asset.category.<key>`).

### 3.5 Relationships

- `Asset`: `belongsTo` assignee (`User`, `assigned_to`), `belongsTo` department,
  `hasMany` histories, `morphMany` attachments (`attachable`), `hasMany` tickets.
- `Ticket`: `belongsTo` asset.
- `User`: `hasMany` assignedAssets (`Asset`, `assigned_to`).
- `Attachment`: already polymorphic — no change.

## 4. Status Lifecycle & History

### 4.1 Statuses

`in_stock` (default), `assigned`, `in_repair`, `retired`, `lost`.

### 4.2 Transitions and history logging (all inside a DB transaction)

| Action | Effect | History entry |
|---|---|---|
| Create | persist asset (status defaults `in_stock`) | `created` |
| Update (edit) | persist changed fields | `updated` (a single row per edit; no per-field rows) |
| Assign | set `assigned_to` (+ optional `department_id`), `status` → `assigned` | `assigned` (new_value = holder) |
| Return | clear `assigned_to`, `status` → `in_stock` | `returned` (old_value = prior holder) |
| Change status | set `status` to `in_repair` / `retired` / `lost` / `in_stock` | `status_changed` (field=`status`, old/new) |

The asset detail page renders the history as a timeline, identical in shape to the ticket
history timeline.

## 5. API

All routes are registered in `routes/api.php` under the existing `auth:sanctum` group.
Every controller method (including `index`/`show`) begins with
`abort_unless($request->user()->isItStaff(), 403);` — this is the IT-only gate, consistent
with the existing `DepartmentController` pattern. `destroy` additionally requires admin
(`abort_unless($request->user()->isAdmin(), 403);`).

```
GET    /api/assets                       List (paginated; filters: status, category,
                                         department_id, assigned_to, search on
                                         name/asset_tag/serial_number)
POST   /api/assets                       Create
GET    /api/assets/{asset}               Detail (loads assignee, department, histories.user,
                                         attachments, tickets)
PUT    /api/assets/{asset}               Update
DELETE /api/assets/{asset}               Delete (admin only)
PATCH  /api/assets/{asset}/assign        Assign to user (+ optional department)
PATCH  /api/assets/{asset}/status        Change status (incl. return to stock)
POST   /api/assets/{asset}/attachments         Upload file(s)
DELETE /api/assets/{asset}/attachments/{id}    Delete a file
GET    /api/assets/export                Excel (.xlsx) export honoring current filters
POST   /api/assets/import                Excel import (row-level validation)
GET    /api/assets/meta                  Category keys + per-status counts (for filters/forms)
```

Registered via `Route::apiResource('assets', AssetController::class)` plus the explicit
extra routes (mirrors how tickets register `apiResource` + `status`/`assign`).

### Validation highlights

- `category` → `required|in:<fixed list>`
- `serial_number` → `nullable|string|unique:assets,serial_number` (ignore self on update)
- `status` → `in:in_stock,assigned,in_repair,retired,lost`
- `assigned_to` → `nullable|exists:users,id`
- `department_id` → `nullable|exists:departments,id`
- attachments → `file|mimes:jpeg,jpg,png,gif,webp,pdf|max:10240`, `max:5` per request
  (same rule as `TicketController::store`)

## 6. Frontend (Vue 3 SPA)

### 6.1 Routes (nested under `AppLayout`, `src/router/index.ts`)

- `/assets` → `views/assets/AssetsView.vue` (list)
- `/assets/create` → `views/assets/CreateAssetView.vue`
- `/assets/:id` → `views/assets/AssetDetailView.vue`
- `/assets/:id/edit` → `views/assets/EditAssetView.vue`

Add a `requiresItStaff` route-meta guard. `router.beforeEach` currently handles
`requiresAuth`, `guest`, `requiresAdmin`; add a `requiresItStaff` branch that redirects
non-IT users to the dashboard.

### 6.2 Navigation (`components/layout/AppLayout.vue`)

Add an "Assets" entry inside the existing `v-if="auth.isItStaff"` section (the IT/admin
nav block), with its own icon. Add `pageTitle` map entries for the new route names.

### 6.3 Views

- **AssetsView** — filterable/paginated table (status, category, department, assigned-to,
  search); per-row status badge; **Import** and **Export** buttons; "+ New Asset".
- **CreateAssetView / EditAssetView** — asset form with a category picker (fixed list),
  status select, assignee + department selects, purchase/warranty fields, notes, file upload.
- **AssetDetailView** — info card, Assign / Return / Change-status actions, history timeline,
  attachments list (view/download/delete), related tickets list, and a printable **QR label**.

### 6.4 Store & API

- `src/stores/assets.ts` (Pinia) — list/detail state, filters, CRUD + assign/status actions.
- `src/api/index.ts` — add asset endpoints.

### 6.5 Ticket integration

- Ticket create form (`CreateTicketView`) and detail (`TicketDetailView`) gain an optional
  "Related asset" selector / display. The selector lists assets (searchable). On the asset
  detail page, the "related tickets" list shows tickets pointing at that asset.

## 7. Value-add Features

### 7.1 Excel import / export — `maatwebsite/excel`

- **Dependency:** add `maatwebsite/excel` via Composer.
- **Export:** `GET /api/assets/export` streams an `.xlsx` of the current filtered result set
  (columns: asset_tag, name, category, manufacturer, model, serial_number, status, assignee,
  department, location, purchase_date, purchase_cost, warranty_expiry, notes).
- **Import:** `POST /api/assets/import` accepts an `.xlsx`/`.csv`, validates each row
  (category in fixed list, serial_number uniqueness, status validity), creates assets, and
  returns a per-row result summary (created count + list of rejected rows with reasons).
  Imported assets get an auto `asset_tag` unless one is supplied and unique.

### 7.2 QR code label — front-end

- **Dependency:** add `qrcode` (npm) to the frontend.
- The asset detail page renders a QR code encoding the asset detail URL
  (`<FRONTEND_URL>/assets/:id`). A "Print label" action prints the tag (asset_tag + name +
  QR). No backend work required.

### 7.3 Attachments — reuse existing polymorphic system

- `Asset morphMany Attachment` (`attachable`). Upload stores to `asset-attachments/` on the
  `public` disk and creates an `Attachment` row (same fields/flow as
  `TicketController::store`). Same mime/size limits.

## 8. Permissions

- **Visibility:** IT-only. it_staff and admin can access the entire module; regular `user`
  role gets 403 from every asset endpoint and the SPA route guard redirects them away.
- **Enforcement (backend):** `abort_unless($request->user()->isItStaff(), 403)` at the top of
  every `AssetController` method.
- **Delete:** admin only (`isAdmin()`), to avoid accidental record loss; all other mutations
  allowed for it_staff.
- **Enforcement (frontend):** `requiresItStaff` route meta + nav entry gated on
  `auth.isItStaff`.

## 9. Internationalization

Add an `asset.*` namespace to `src/locales/en.ts` and `src/locales/zh.ts`:

- `asset.category.<key>` for all nine categories
- `asset.status.<key>` for all five statuses
- field labels, action labels (assign/return/change-status/import/export/print-label),
  table headers, and detail/timeline strings

All user-facing strings go through `t()`.

## 10. Testing

The project currently has only the default example tests. This module establishes a
PHPUnit feature-test pattern. Cover the critical paths:

- Asset create / update / delete (happy path + validation failures)
- Assign sets holder + status and writes an `assigned` history row
- Return clears holder + status and writes a `returned` history row
- Status change writes a `status_changed` history row
- Ticket can be created/updated with an `asset_id`, and the asset exposes related tickets
- A regular `user` receives 403 from list/detail/mutating endpoints
- `destroy` is forbidden for it_staff but allowed for admin
- Import: valid rows created, invalid rows reported; Export: returns a downloadable file

## 11. New Dependencies

| Dependency | Where | Purpose |
|---|---|---|
| `maatwebsite/excel` | Composer (backend) | Excel import/export |
| `qrcode` | npm (frontend) | QR label generation |

## 12. New / Changed Files (anticipated)

**Backend**
- `database/migrations/*_create_assets_table.php`
- `database/migrations/*_create_asset_histories_table.php`
- `database/migrations/*_add_asset_id_to_tickets_table.php`
- `app/Models/Asset.php`, `app/Models/AssetHistory.php`
- `app/Http/Controllers/Api/AssetController.php`
- `app/Support/AssetCategories.php` (or equivalent constant source)
- `app/Imports/AssetsImport.php`, `app/Exports/AssetsExport.php` (maatwebsite)
- Edits: `app/Models/Ticket.php`, `app/Models/User.php`, `routes/api.php`
- `database/seeders/AssetSeeder.php` (sample data)
- `tests/Feature/AssetTest.php`

**Frontend**
- `src/views/assets/{AssetsView,CreateAssetView,EditAssetView,AssetDetailView}.vue`
- `src/stores/assets.ts`
- Edits: `src/router/index.ts`, `src/components/layout/AppLayout.vue`,
  `src/api/index.ts`, `src/locales/en.ts`, `src/locales/zh.ts`,
  `src/views/tickets/CreateTicketView.vue`, `src/views/tickets/TicketDetailView.vue`

## 13. Open Items

None blocking. Warranty-expiry reminders and a consumables/quantity module are explicitly
deferred to a future iteration.
