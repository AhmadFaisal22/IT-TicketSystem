# My Tasks Kanban — Design Spec

- **Date:** 2026-06-18
- **Status:** Approved (pending implementation plan)
- **Author:** Di Zhou (with Claude)

## Goal

Give IT staff and admins a dedicated "My Tasks" page that shows, at a glance, every
ticket currently assigned to them, organized as a Kanban board. Today the only way to
find your assigned work is to know that tickets exist and dig through the Tickets list;
there is no per-person task view. Assignment already triggers an email notification —
this feature adds the missing place to *see and manage* that work.

## Background — current state (verified in code)

- **Assignment is by IT department, not by role.** `UserController::itStaff()`
  (`it-helpdesk-backend/app/Http/Controllers/Api/UserController.php:37`) returns every
  active user whose `department_id` is the "IT" department, regardless of role
  (`admin` / `it_staff` / `user`). So whether someone can be *assigned* a ticket depends
  on IT-department membership, not their role.
- **Permissions are by role.** `isItStaff()` = role is `admin` or `it_staff`
  (`User::isItStaff()` backend; `auth.isItStaff` frontend store). This is what gates the
  IT navigation and the status-change policy.
- **The list endpoint already supports filtering by assignee.**
  `GET /api/tickets?assigned_to={id}` is validated and applied in
  `TicketController::index()`. For `isItStaff` users the query returns all tickets, then
  the `assigned_to` filter narrows it. Results are paginated at a fixed 20/page.
- **Status changes already do the right side effects.**
  `PATCH /api/tickets/{id}/status` (`TicketController::updateStatus`) writes a
  `TicketHistory` row and notifies the ticket creator. It accepts only
  `open, in_progress, pending, resolved, closed`. `TicketPolicy::updateStatus` allows
  `isItStaff` users and blocks manual changes while a ticket is `pending_approval`
  (unless admin).
- **No drag-and-drop library is installed** (`package.json` has Vue 3, Pinia, vue-router,
  vue-i18n, apexcharts, heroicons, vueuse — nothing for DnD).

## Scope

**In scope**
- A new "My Tasks" Kanban page for `isItStaff` users (it_staff + admin).
- Each user sees only tickets assigned to themselves (admins included — no team-wide view).
- Drag-to-change-status on desktop + a per-card status dropdown that works everywhere.
- One small backend addition: optional `per_page` on the ticket index.

**Out of scope**
- Team-wide / all-IT workload view.
- The approval workflow (`pending_approval` / `rejected`) — not in active use; these
  tickets are simply not shown on the board.
- Any new notifications or email. Existing assignment email and status-change
  notification are reused as-is.
- Sub-tasks / personal checklist items inside a ticket. "Task" = one assigned ticket.

## Design

### Routing & navigation
- New view: `it-helpdesk-frontend/src/views/tickets/MyTasksView.vue`.
- Route: path `/my-tasks`, name `my-tasks`, `meta: { requiresItStaff: true }`, added as a
  child of the authenticated `AppLayout` route in `src/router/index.ts`. The existing
  router guard already redirects non-IT users away from `requiresItStaff` routes.
- Sidebar (`AppLayout.vue`): add a "My Tasks" link inside the `auth.isItStaff` nav set,
  positioned between Dashboard and Tickets. Uses an existing heroicon (e.g.
  `ClipboardDocumentCheckIcon`) for consistency with the other icon-based nav items.

### Data fetching
- The view owns its own state (does **not** use the shared `ticketStore`, which backs the
  Tickets list — keeping them separate avoids cross-page interference).
- On mount, call `ticketApi.list({ assigned_to: auth.user.id, per_page: 100 })` and group
  the returned tickets into columns by `status` in local component state.
- Provide a manual refresh and re-fetch after a status change resolves (to pick up SLA
  fields recomputed server-side). Loading and empty states mirror the existing
  TicketsView styling.

### Kanban columns

| Column (zh / en)      | Status             | Draggable |
|-----------------------|--------------------|-----------|
| 新建 / New            | `open`             | yes       |
| 进行中 / In Progress  | `in_progress`      | yes       |
| 待处理 / Pending      | `pending`          | yes       |
| (toggle) 已完成 / Done | `resolved`, `closed` | yes (into Done = resolve/close) |

- A "Show completed" toggle reveals the Done column (collapsed by default). Done groups
  both `resolved` and `closed`.
- Tickets with status `pending_approval` or `rejected` are not rendered on the board
  (approval flow is out of use). They remain visible via the regular Tickets list.
- Columns lay out side by side; on narrow screens the board scrolls horizontally
  (`overflow-x-auto`).

### Card
- Shows: `ticket_number`, `title`, priority color badge, and an `SLA` red badge when
  `sla_resolution_breached` (matching TicketsView treatment).
- Clicking the card body navigates to `/tickets/{id}` (ticket detail).
- Each card has a small status `<select>` (the cross-platform status-change control).

### Changing status — two mechanisms
1. **Desktop drag-and-drop (enhancement):** native HTML5 DnD (`draggable` attribute +
   `dragstart` / `dragover` / `drop`), no new dependency. On drop into a different column:
   optimistically move the card in local state, call `ticketApi.updateStatus(id, newStatus)`;
   on failure, revert the card and show an inline error. Allowed transitions are between
   `open` / `in_progress` / `pending` / `resolved` / `closed` — exactly what the backend
   accepts.
2. **Per-card status dropdown (baseline, all devices):** native HTML5 DnD does not fire on
   touch, so every card carries a `<select>` of the same statuses. Changing it runs the
   same optimistic update + revert-on-error path. This is the reliable mechanism on mobile
   and an accessibility fallback on desktop.

Both paths reuse `PATCH /api/tickets/{id}/status`, so history logging and creator
notification happen automatically. No new write endpoints are introduced.

### Backend change (the only one)
- `TicketController::index()`: add `'per_page' => 'nullable|integer|min:1|max:100'` to the
  validation, and use it for pagination: `$query->paginate($f['per_page'] ?? 20)`. Default
  behavior (20/page) is unchanged for all existing callers. This lets the board pull a
  user's full active workload in one request instead of being truncated at 20.

### i18n
- Add `nav.myTasks` and a "show completed" label to both `src/locales/en.ts` and
  `src/locales/zh.ts`. Column headers reuse the existing `ticket.open`,
  `ticket.in_progress`, `ticket.pending`, `ticket.resolved`, `ticket.closed` keys.

### Permissions
- Page/route gated by `isItStaff` (it_staff + admin). `user`-role accounts never see it,
  which is the intended normal case.
- Status changes continue to be authorized by `TicketPolicy::updateStatus` server-side.

## Edge cases & decisions
- **Role vs. department:** entry is gated by role (`isItStaff`). A hypothetical `user`-role
  account placed in the IT department could be assigned tickets but would not see this page.
  Accepted as a non-issue — IT personnel are it_staff/admin in practice. (Decided 2026-06-18.)
- **>20 assigned tickets:** handled by the `per_page` parameter (capped at 100).
- **Optimistic update conflict:** if the server rejects a status change (e.g. a race or a
  policy block), the card reverts to its prior column and an error message is shown.
- **Empty board:** show a friendly empty state when the user has no assigned tickets.

## Testing
- **Backend:** a feature test asserting `GET /api/tickets?per_page=N` honors the page size
  and that invalid values (0, >100, non-integer) are rejected; existing
  `assigned_to` filtering and `updateStatus` behavior already covered.
- **Frontend / manual:** using the `run-it-helpdesk` skill — verify the board loads only
  the current user's assigned tickets, drag a card between columns persists the status
  (and shows in ticket history), the per-card dropdown changes status, the "show completed"
  toggle reveals resolved/closed, and the page is hidden from `user`-role accounts.
