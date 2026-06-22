# Manufacturers + Inventory Options Sub-pages — Design

**Date:** 2026-06-22
**Status:** Approved (design)

## Goal

Two related changes to the asset/inventory admin area:

1. **Add a managed Manufacturer entity** with a rich field set, and wire it into the
   "Add Asset" form so the manufacturer field becomes a dropdown sourced from the
   managed list.
2. **Restructure "Inventory Options"** (currently one long admin page with two stacked
   sections — Categories and Locations) into an **expandable sidebar group** with three
   separate sub-pages: Categories, Locations, Manufacturers.

## Background / Current State

- Admin nav has a single flat item **"Asset Options"** (`admin-asset-options`, route
  `/admin/asset-options`) rendering `AssetOptionsView.vue`. That page stacks two CRUD
  sections: **Categories** (`asset_categories`) and **Locations** (`asset_locations`).
  Each option row is just `name` + `name_zh`.
- Backend CRUD lives in `AssetOptionController` (read = IT staff, write = admin).
- An asset stores the **name string** of its category/location (e.g. `assets.category`,
  `assets.location`), validated against the options table via `exists:asset_categories,name` /
  `exists:asset_locations,name`. **No foreign-key IDs are used** for these soft references.
- The manufacturer field in `AssetForm.vue` is currently a plain free-text `<input>` bound
  to `form.manufacturer`, stored in the `assets.manufacturer` string column.
- The sidebar (`AppLayout.vue`) currently has **no expandable/submenu pattern**; admin items
  are a flat `v-for` of `router-link`s. It also supports a desktop icon-only "collapsed" rail.

## Decisions

- **Manufacturer ↔ Asset linkage:** dropdown that **stores the manufacturer name** in the
  existing `assets.manufacturer` string column — consistent with how Category and Location
  already work. **No schema change to `assets`**; no FK; no asset-row data migration.
- The canonical value stored on the asset is the manufacturer's **official `name`**.

## Data Model — new `manufacturers` table

| Column | Type | Constraints / Notes |
|---|---|---|
| `id` | bigint PK | auto-increment |
| `name` | string | **required, unique** — official name; stored on the asset |
| `short_name` | string | nullable — abbreviation |
| `contact` | string | nullable |
| `support_phone` | string | nullable |
| `support_email` | string | nullable, validated as email on write |
| `country_of_origin` | string | nullable |
| `notes` | text | nullable |
| `status` | string | `active` \| `inactive`, default `active` |
| `created_at` | timestamp | = "Created Date" |
| `updated_at` | timestamp | = "Modified Date" |

`Manufacturer` Eloquent model: fillable for all writable fields; cast nothing special.

## Backend API

Extend `AssetOptionController` (same RBAC helpers: `authorizeItStaff` for read,
`authorizeAdmin` for write), mirroring the categories/locations methods:

```
GET    /api/asset-options/manufacturers          → list (IT staff), ordered by name
POST   /api/asset-options/manufacturers          → create (admin)
PATCH  /api/asset-options/manufacturers/:id       → update (admin)
DELETE /api/asset-options/manufacturers/:id       → delete (admin)
```

Validation on create/update:
- `name`: required (create) / sometimes (update), string, max 255, unique (ignoring self on update)
- `short_name`, `contact`, `support_phone`, `country_of_origin`: nullable string, max 255
- `support_email`: nullable, email, max 255
- `notes`: nullable string (text)
- `status`: nullable, `in:active,inactive` (defaults to `active` when absent)

### Add/Edit Asset manufacturer validation

In `AssetController@store` and `@update`, change the `manufacturer` rule from free string to:
`nullable|exists:manufacturers,name`.

Rationale: matches the category/location pattern. Seeding (below) ensures existing values
remain valid.

**Bulk import stays lenient:** `AssetsImport` writes `manufacturer` as free text and is **not**
validated against the `manufacturers` table (consistent with how it already handles other
fields). An imported manufacturer name that isn't in the managed list is preserved on the asset;
when that asset is later edited in the form, the "keep showing existing value" rule below applies.

## Seeding / Data Backfill

A migration or seeder step backfills the `manufacturers` table so the dropdown is populated
and existing asset values stay valid:

- Insert one `manufacturers` row (status `active`, other fields null) for each **distinct,
  non-empty** value currently in `assets.manufacturer` (e.g. "LENOVO").
- Idempotent: skip names that already exist.

This runs as part of the same migration that creates the table (in the `up()` after table
creation) so a fresh `migrate` on existing data is self-consistent.

## Frontend — Nav restructure

In `AppLayout.vue`:

- Rename the nav label **"Asset Options" → "Inventory Options"** (i18n key `nav.inventoryOptions`,
  EN "Inventory Options" / ZH "库存选项"; keep/repoint the old `nav.assetOptions` usage).
- Replace the single admin nav item with an **expandable group**:
  - Parent row "Inventory Options" with a chevron that toggles an open/closed state.
  - When open, render three indented sub-links: Categories, Locations, Manufacturers.
  - The group **auto-expands** when the current route is one of its children.
  - **Collapsed (icon-rail) mode:** the parent icon links directly to the first child
    (`/admin/inventory/categories`) instead of toggling — keeps the icon rail simple. No
    flyout needed.
- Add the three child page titles to the `pageTitle` map.

## Frontend — Routing

Replace the single `asset-options` route with a parent + three children:

```
/admin/inventory/categories     → name: admin-inventory-categories
/admin/inventory/locations      → name: admin-inventory-locations
/admin/inventory/manufacturers  → name: admin-inventory-manufacturers
```

- Old `/admin/asset-options` **redirects** to `/admin/inventory/categories`.
- All under the existing `requiresAdmin` admin route group.

## Frontend — Page split (DRY)

- **Categories** and **Locations** are identical `name` + `name_zh` CRUD. Extract a single
  reusable `OptionCrudView.vue` (props: which option API + i18n key set) and mount it for both
  routes. This is refactored out of the current `AssetOptionsView.vue`.
- **Manufacturers** gets its own `ManufacturersView.vue` with the full field set (list table +
  add/edit modal containing name, short_name, contact, support_phone, support_email,
  country_of_origin, notes, status). List shows key columns; created/modified shown where useful.
- Delete or repurpose the old `AssetOptionsView.vue` once the split pages exist.

## Frontend — Add Asset form

In `AssetForm.vue`:
- Replace the manufacturer free-text `<input>` with a `<select>` populated from **active**
  manufacturers (fetched via the manufacturers list API).
- Bind to `form.manufacturer` storing the manufacturer **name** (unchanged payload shape).
- If the asset being edited already has a `manufacturer` value not in the active list (e.g. an
  inactive one), keep showing that value as a selected option so editing doesn't silently drop it.

## API client + i18n

- Add `assetManufacturerApi` (list/create/update/delete) alongside `assetCategoryApi` /
  `assetLocationApi`.
- Add EN/ZH i18n strings for the manufacturer fields, the three sub-page titles, and the
  renamed "Inventory Options" group.

## RBAC

Unchanged pattern: manufacturers read = IT staff, write = admin. All inventory sub-pages remain
under `requiresAdmin`.

## Testing

Backend feature tests (extend existing AssetOption / Asset test patterns):

- Manufacturer CRUD happy path (admin).
- Read allowed for IT staff; write forbidden (403) for non-admin.
- Create rejects duplicate `name`; rejects invalid `support_email`; defaults `status` to active.
- Asset create/update **accepts** a managed manufacturer name and **rejects** (422) an unknown one.
- Seeding: distinct existing `assets.manufacturer` values appear as manufacturer rows.

Frontend: manual verification via the run-it-helpdesk skill (nav expand, three pages, Add Asset
dropdown shows active manufacturers and saves the name).

## Out of Scope

- Converting `assets.manufacturer` to a foreign key.
- Manufacturer logos/attachments.
- Per-manufacturer asset reports.
