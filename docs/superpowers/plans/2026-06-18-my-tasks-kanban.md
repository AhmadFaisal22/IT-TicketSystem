# My Tasks Kanban Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a dedicated "My Tasks" Kanban page where IT staff and admins see and manage the tickets currently assigned to them.

**Architecture:** A new Vue view (`MyTasksView.vue`) fetches the current user's assigned tickets via the existing `GET /api/tickets?assigned_to=...` endpoint and groups them into status columns. Status changes go through the existing `PATCH /api/tickets/{id}/status` endpoint (which already logs history and notifies the creator) via a per-card dropdown (all devices) plus native HTML5 drag-and-drop (desktop enhancement). The only backend change is an optional `per_page` query parameter so the board can load a full workload in one request.

**Tech Stack:** Laravel 12 + PHPUnit (backend); Vue 3 + TypeScript + Tailwind + vue-i18n + vue-router + Pinia (frontend). No new dependencies.

## Global Constraints

- **Bilingual:** every user-facing string must have a key in BOTH `src/locales/en.ts` and `src/locales/zh.ts`.
- **No new dependencies:** drag-and-drop uses native HTML5 DnD (no library); `package.json` is not modified.
- **Backend default unchanged:** ticket index still paginates at 20/page when `per_page` is absent.
- **Access:** the page is gated to `isItStaff` (role `it_staff` or `admin`); `user`-role accounts never see it.
- **Reuse existing endpoints:** no new write endpoints, no new notifications. Status changes use `PATCH /api/tickets/{id}/status`.
- **Status set:** the only statuses used on the board / in the dropdown are `open`, `in_progress`, `pending`, `resolved`, `closed`. `pending_approval` and `rejected` tickets are not shown.
- **PHP:** not on PATH — run artisan via `"$HOME/php-8.3/php.exe"` (local SQLite test DB). Frontend verified via `npm run build`.
- **Commits:** no `Co-Authored-By` trailer.

## File Structure

- `it-helpdesk-backend/app/Http/Controllers/Api/TicketController.php` — MODIFY `index()`: add `per_page` validation + use it in `paginate()`.
- `it-helpdesk-backend/tests/Feature/TicketIndexPerPageTest.php` — CREATE: feature test for `per_page`.
- `it-helpdesk-frontend/src/locales/en.ts` / `zh.ts` — MODIFY: add `nav.myTasks` + `myTasks.*` strings.
- `it-helpdesk-frontend/src/views/tickets/MyTasksView.vue` — CREATE: the Kanban board.
- `it-helpdesk-frontend/src/router/index.ts` — MODIFY: add `/my-tasks` route.
- `it-helpdesk-frontend/src/components/layout/AppLayout.vue` — MODIFY: add sidebar link + page title + icon import.

---

## Task 1: Backend — optional `per_page` on ticket index

**Files:**
- Modify: `it-helpdesk-backend/app/Http/Controllers/Api/TicketController.php` (the `index` method, ~lines 30-77)
- Test: `it-helpdesk-backend/tests/Feature/TicketIndexPerPageTest.php`

**Interfaces:**
- Consumes: existing `GET /api/tickets` route and `Ticket` / `Department` / `User` models.
- Produces: `GET /api/tickets?per_page=N` returns `N` items/page (1–100); absent → 20; invalid → 422. The frontend (Task 3) relies on `per_page=100`.

- [ ] **Step 1: Write the failing test**

Create `it-helpdesk-backend/tests/Feature/TicketIndexPerPageTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketIndexPerPageTest extends TestCase
{
    use RefreshDatabase;

    private function seedTickets(int $count): User
    {
        $dept  = Department::create(['name' => 'IT', 'name_zh' => 'IT部']);
        $staff = User::factory()->create(['role' => 'it_staff', 'department_id' => $dept->id]);

        for ($i = 0; $i < $count; $i++) {
            Ticket::create([
                'title'         => "Ticket {$i}",
                'description'   => 'desc',
                'status'        => 'open',
                'priority'      => 'medium',
                'department_id' => $dept->id,
                'created_by'    => $staff->id,
                'assigned_to'   => $staff->id,
            ]);
        }

        return $staff;
    }

    public function test_per_page_controls_page_size(): void
    {
        $staff = $this->seedTickets(25);
        Sanctum::actingAs($staff);

        $this->getJson('/api/tickets?per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('per_page', 5);
    }

    public function test_defaults_to_twenty_per_page(): void
    {
        $staff = $this->seedTickets(25);
        Sanctum::actingAs($staff);

        $this->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('per_page', 20);
    }

    public function test_rejects_invalid_per_page(): void
    {
        $staff = $this->seedTickets(1);
        Sanctum::actingAs($staff);

        $this->getJson('/api/tickets?per_page=0')->assertStatus(422);
        $this->getJson('/api/tickets?per_page=101')->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run (from `it-helpdesk-backend`):
```bash
"$HOME/php-8.3/php.exe" artisan test --filter=TicketIndexPerPageTest
```
Expected: `test_per_page_controls_page_size` and `test_rejects_invalid_per_page` FAIL — without the new param, `per_page=5` is ignored (returns 20 items, asserts 5) and `per_page=0/101` return 200 instead of 422. (`test_defaults_to_twenty_per_page` already passes.)

- [ ] **Step 3: Add `per_page` to validation and pagination**

In `TicketController::index()`, add the `per_page` rule to the `$f` validation array (after the `sla_breached` line):

```php
            'sla_breached'  => 'nullable|boolean',
            'per_page'      => 'nullable|integer|min:1|max:100',
```

Then change the final return from:

```php
        return response()->json($query->paginate(20));
```

to:

```php
        return response()->json($query->paginate((int) ($f['per_page'] ?? 20)));
```

- [ ] **Step 4: Run test to verify it passes**

Run:
```bash
"$HOME/php-8.3/php.exe" artisan test --filter=TicketIndexPerPageTest
```
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add it-helpdesk-backend/app/Http/Controllers/Api/TicketController.php it-helpdesk-backend/tests/Feature/TicketIndexPerPageTest.php
git commit -m "feat(api): support per_page on ticket index"
```

---

## Task 2: Frontend — i18n strings

**Files:**
- Modify: `it-helpdesk-frontend/src/locales/en.ts`
- Modify: `it-helpdesk-frontend/src/locales/zh.ts`

**Interfaces:**
- Produces: i18n keys `nav.myTasks`, `myTasks.subtitle`, `myTasks.showCompleted`, `myTasks.done`, `myTasks.empty` in both locales. Task 3 consumes these via `t(...)`.

- [ ] **Step 1: Add the English strings**

In `src/locales/en.ts`, add `myTasks` to the `nav` block (after the `tickets:` line):

```ts
    tickets: 'Tickets',
    myTasks: 'My Tasks',
    assets: 'Inventory',
```

Then add a new top-level `myTasks` section immediately after the `nav` block closes (between `},` of `nav` and `auth: {`):

```ts
    logout: 'Logout'
  },
  myTasks: {
    subtitle: 'Tickets assigned to you',
    showCompleted: 'Show completed',
    done: 'Done',
    empty: 'You have no assigned tickets.'
  },
  auth: {
```

- [ ] **Step 2: Add the Chinese strings**

In `src/locales/zh.ts`, add `myTasks` to the `nav` block (after the `tickets:` line):

```ts
    tickets: '工单列表',
    myTasks: '我的任务',
    assets: '资产',
```

Then add the matching top-level `myTasks` section after the `nav` block closes:

```ts
    logout: '退出登录'
  },
  myTasks: {
    subtitle: '指派给你的工单',
    showCompleted: '显示已完成',
    done: '已完成',
    empty: '你当前没有被指派的工单。'
  },
  auth: {
```

- [ ] **Step 3: Verify the locale files compile**

Run (from `it-helpdesk-frontend`):
```bash
npm run build
```
Expected: build succeeds (no TypeScript errors in the locale object literals).

- [ ] **Step 4: Commit**

```bash
git add it-helpdesk-frontend/src/locales/en.ts it-helpdesk-frontend/src/locales/zh.ts
git commit -m "feat(i18n): add My Tasks strings"
```

---

## Task 3: Frontend — My Tasks board (baseline: columns + per-card status dropdown), route, and sidebar link

**Files:**
- Create: `it-helpdesk-frontend/src/views/tickets/MyTasksView.vue`
- Modify: `it-helpdesk-frontend/src/router/index.ts`
- Modify: `it-helpdesk-frontend/src/components/layout/AppLayout.vue`

**Interfaces:**
- Consumes: `ticketApi.list` and `ticketApi.updateStatus` from `@/api`; `useAuthStore` (`auth.user.id`); `Ticket` type from `@/stores/tickets`; i18n keys from Task 2; `per_page` from Task 1.
- Produces: a reachable `/my-tasks` route (name `my-tasks`) and a `MyTasksView.vue` whose `changeStatus(ticket, newStatus)` function (optimistic update + revert-on-error) is extended by Task 4.

- [ ] **Step 1: Create the board component**

Create `it-helpdesk-frontend/src/views/tickets/MyTasksView.vue`:

```vue
<template>
  <div>
    <!-- Header / toggle -->
    <div class="flex items-center justify-between mb-4">
      <p class="text-sm text-gray-500">{{ t('myTasks.subtitle') }}</p>
      <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
        <input type="checkbox" v-model="showCompleted"
          class="rounded border-gray-300 text-red-600 focus:ring-red-500" />
        {{ t('myTasks.showCompleted') }}
      </label>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="p-8 text-center text-gray-400">
      <div class="w-8 h-8 border-4 border-red-600 border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      {{ t('common.loading') }}
    </div>

    <template v-else>
      <!-- Error -->
      <div v-if="error" class="mb-3 p-3 bg-red-50 border border-red-200 text-red-600 rounded-lg text-sm">
        {{ error }}
      </div>

      <!-- Empty -->
      <div v-if="!tickets.length" class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
        <p class="text-gray-400">{{ t('myTasks.empty') }}</p>
      </div>

      <!-- Board -->
      <div v-else class="flex gap-4 overflow-x-auto pb-2">
        <div v-for="col in columns" :key="col.key"
          class="flex-shrink-0 w-72 bg-gray-50 rounded-xl border border-gray-100">
          <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="text-sm font-semibold text-gray-700">{{ t(col.labelKey) }}</span>
            <span class="text-xs text-gray-400">{{ col.tickets.length }}</span>
          </div>
          <div class="p-2 space-y-2 min-h-[60px]">
            <div v-for="ticket in col.tickets" :key="ticket.id"
              @click="router.push(`/tickets/${ticket.id}`)"
              class="bg-white rounded-lg border border-gray-200 p-3 cursor-pointer hover:border-red-300 hover:shadow-sm transition">
              <div class="flex items-start justify-between gap-2 mb-1.5">
                <span class="text-xs font-mono text-red-600">{{ ticket.ticket_number }}</span>
                <div class="flex items-center gap-1">
                  <span v-if="ticket.sla_resolution_breached"
                    class="px-1.5 py-0.5 text-xs bg-red-100 text-red-700 rounded font-medium">SLA</span>
                  <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="priorityClass(ticket.priority)">
                    {{ t(`ticket.${ticket.priority}`) }}
                  </span>
                </div>
              </div>
              <p class="text-sm font-medium text-gray-800 mb-2 leading-snug">{{ ticket.title }}</p>
              <select :value="ticket.status" @click.stop @change="onStatusSelect(ticket, $event)"
                class="w-full px-2 py-1 border border-gray-200 rounded text-xs text-gray-600 focus:outline-none focus:ring-2 focus:ring-red-500">
                <option v-for="s in statusOptions" :key="s" :value="s">{{ t(`ticket.${s}`) }}</option>
              </select>
            </div>
            <p v-if="!col.tickets.length" class="text-xs text-gray-300 text-center py-4">—</p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { ticketApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import type { Ticket } from '@/stores/tickets'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const tickets = ref<Ticket[]>([])
const loading = ref(false)
const error = ref('')
const showCompleted = ref(false)

const statusOptions = ['open', 'in_progress', 'pending', 'resolved', 'closed'] as const

const columns = computed(() => {
  const cols = [
    { key: 'open', labelKey: 'ticket.open', tickets: tickets.value.filter(tk => tk.status === 'open') },
    { key: 'in_progress', labelKey: 'ticket.in_progress', tickets: tickets.value.filter(tk => tk.status === 'in_progress') },
    { key: 'pending', labelKey: 'ticket.pending', tickets: tickets.value.filter(tk => tk.status === 'pending') },
  ]
  if (showCompleted.value) {
    cols.push({
      key: 'done',
      labelKey: 'myTasks.done',
      tickets: tickets.value.filter(tk => tk.status === 'resolved' || tk.status === 'closed'),
    })
  }
  return cols
})

function priorityClass(p: string) {
  const map: Record<string, string> = {
    critical: 'bg-red-100 text-red-700',
    high: 'bg-orange-100 text-orange-700',
    medium: 'bg-yellow-100 text-yellow-700',
    low: 'bg-gray-100 text-gray-600',
  }
  return map[p] || 'bg-gray-100 text-gray-600'
}

function onStatusSelect(ticket: Ticket, e: Event) {
  changeStatus(ticket, (e.target as HTMLSelectElement).value)
}

async function changeStatus(ticket: Ticket, newStatus: string) {
  if (ticket.status === newStatus) return
  const old = ticket.status
  ticket.status = newStatus as Ticket['status'] // optimistic
  error.value = ''
  try {
    await ticketApi.updateStatus(ticket.id, newStatus)
  } catch (err: any) {
    ticket.status = old // revert
    error.value = err?.response?.data?.message || t('common.error')
  }
}

async function fetchTasks() {
  if (!auth.user?.id) return
  loading.value = true
  error.value = ''
  try {
    const { data } = await ticketApi.list({ assigned_to: auth.user.id, per_page: 100 })
    tickets.value = data.data
  } catch (err: any) {
    error.value = err?.response?.data?.message || t('common.error')
  } finally {
    loading.value = false
  }
}

onMounted(fetchTasks)
</script>
```

- [ ] **Step 2: Register the route**

In `src/router/index.ts`, add a new child route immediately after the existing `tickets` route (the block with `name: 'tickets'`):

```ts
        {
          path: 'tickets',
          name: 'tickets',
          component: () => import('@/views/tickets/TicketsView.vue')
        },
        {
          path: 'my-tasks',
          name: 'my-tasks',
          component: () => import('@/views/tickets/MyTasksView.vue'),
          meta: { requiresItStaff: true }
        },
```

- [ ] **Step 3: Add the sidebar link, page title, and icon import**

In `src/components/layout/AppLayout.vue`:

(a) Extend the heroicons import to include the clipboard icon:

```ts
import { ComputerDesktopIcon, AdjustmentsHorizontalIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline'
```

(b) In the `navItems` computed, change the `if (auth.isItStaff)` block so Dashboard and My Tasks are both unshifted (My Tasks lands between Dashboard and Tickets):

```ts
  if (auth.isItStaff) {
    items.unshift(
      { name: 'dashboard', to: '/', label: 'nav.dashboard', iconImg: '/Dash.png' },
      { name: 'my-tasks', to: '/my-tasks', label: 'nav.myTasks', icon: ClipboardDocumentCheckIcon, prefix: 'my-task' }
    )
    items.push({ name: 'assets', to: '/assets', label: 'nav.assets', icon: ComputerDesktopIcon, prefix: 'asset' })
  }
```

(c) In the `pageTitle` computed map, add the My Tasks title after the `tickets:` line:

```ts
    tickets: t('nav.tickets'),
    'my-tasks': t('nav.myTasks'),
```

- [ ] **Step 4: Verify the build typechecks**

Run (from `it-helpdesk-frontend`):
```bash
npm run build
```
Expected: build succeeds with no TypeScript errors.

- [ ] **Step 5: Manual verification (run-it-helpdesk skill)**

Start the app (use the `run-it-helpdesk` skill). Log in as an `it_staff` or `admin` user who is in the IT department and has tickets assigned to them, then verify:
- The sidebar shows **My Tasks** between Dashboard and Tickets; clicking it opens `/my-tasks`.
- The board shows only tickets assigned to the logged-in user, grouped into New / In Progress / Pending.
- Changing a card's status dropdown moves it to the right column and persists after a page reload (and appears in that ticket's history on the detail page).
- Toggling **Show completed** reveals a Done column with resolved/closed tickets.
- Log in as a `user`-role account: the My Tasks link is absent and visiting `/my-tasks` redirects to dashboard.

- [ ] **Step 6: Commit**

```bash
git add it-helpdesk-frontend/src/views/tickets/MyTasksView.vue it-helpdesk-frontend/src/router/index.ts it-helpdesk-frontend/src/components/layout/AppLayout.vue
git commit -m "feat(frontend): add My Tasks board with status dropdown"
```

---

## Task 4: Frontend — drag-and-drop enhancement (desktop)

**Files:**
- Modify: `it-helpdesk-frontend/src/views/tickets/MyTasksView.vue`

**Interfaces:**
- Consumes: `changeStatus(ticket, newStatus)`, `tickets` ref, and the `columns`/card template from Task 3.
- Produces: native HTML5 drag-and-drop that calls the same `changeStatus`. Dropping on the Done column resolves the ticket (`resolved`).

- [ ] **Step 1: Add drag state and handlers**

In the `<script setup>` block of `MyTasksView.vue`, add a `draggingId` ref after the existing refs (after `const showCompleted = ref(false)`):

```ts
const draggingId = ref<number | null>(null)
```

Then add these two functions after `onStatusSelect`:

```ts
function onDragStart(ticket: Ticket) {
  draggingId.value = ticket.id
}

function onDrop(columnKey: string) {
  const ticket = tickets.value.find(tk => tk.id === draggingId.value)
  draggingId.value = null
  if (!ticket) return
  // The Done column groups resolved+closed; a drop there resolves the ticket.
  changeStatus(ticket, columnKey === 'done' ? 'resolved' : columnKey)
}
```

- [ ] **Step 2: Make columns drop targets**

In the template, add `@dragover.prevent` and `@drop` to the inner card container of each column. Change:

```html
          <div class="p-2 space-y-2 min-h-[60px]">
```

to:

```html
          <div class="p-2 space-y-2 min-h-[60px]"
            @dragover.prevent @drop="onDrop(col.key)">
```

- [ ] **Step 3: Make cards draggable**

In the template, make each card draggable and dim it while dragging. Change the card's opening `<div>`:

```html
            <div v-for="ticket in col.tickets" :key="ticket.id"
              @click="router.push(`/tickets/${ticket.id}`)"
              class="bg-white rounded-lg border border-gray-200 p-3 cursor-pointer hover:border-red-300 hover:shadow-sm transition">
```

to:

```html
            <div v-for="ticket in col.tickets" :key="ticket.id"
              draggable="true"
              @dragstart="onDragStart(ticket)"
              @dragend="draggingId = null"
              @click="router.push(`/tickets/${ticket.id}`)"
              :class="['bg-white rounded-lg border border-gray-200 p-3 cursor-pointer hover:border-red-300 hover:shadow-sm transition', draggingId === ticket.id ? 'opacity-50' : '']">
```

- [ ] **Step 4: Verify the build typechecks**

Run (from `it-helpdesk-frontend`):
```bash
npm run build
```
Expected: build succeeds with no TypeScript errors.

- [ ] **Step 5: Manual verification (run-it-helpdesk skill)**

With the app running and logged in as an IT user with assigned tickets:
- Drag a card from New to In Progress — it moves and the change persists after reload.
- Drag a card onto the Done column (with Show completed on) — the ticket becomes Resolved.
- Confirm the per-card dropdown still works (drag is additive, not a replacement).

- [ ] **Step 6: Commit**

```bash
git add it-helpdesk-frontend/src/views/tickets/MyTasksView.vue
git commit -m "feat(frontend): add drag-and-drop to My Tasks board"
```

---

## Self-Review

**Spec coverage:**
- Dedicated sidebar page → Task 3 (route + nav link). ✓
- Each user sees only their own assigned tickets → Task 3 (`assigned_to: auth.user.id`). ✓
- Kanban columns New/In Progress/Pending + collapsible Done → Task 3 (`columns` computed + `showCompleted`). ✓
- `pending_approval`/`rejected` not shown → Task 3 (no column filters them in). ✓
- Per-card status dropdown (all devices) → Task 3. ✓
- Desktop drag-and-drop, native HTML5, no dependency → Task 4. ✓
- Optimistic update + revert on error → Task 3 (`changeStatus`). ✓
- Reuse `PATCH /status`, no new notifications → Tasks 3 & 4 (`ticketApi.updateStatus`). ✓
- Backend `per_page` (default 20, max 100) → Task 1. ✓
- Bilingual strings → Task 2. ✓
- Gated to `isItStaff` → Task 3 (route meta + nav block). ✓

**Placeholder scan:** No TBD/TODO; all steps contain concrete code and exact commands. ✓

**Type consistency:** `changeStatus(ticket, newStatus)`, `tickets` ref, `columns[].key`/`.tickets`, and `draggingId` are used identically across Tasks 3 and 4. Status string set matches the backend `updateStatus` enum and the dropdown options. ✓
