# SLA Breach Notifications — Design

**Date:** 2026-06-22
**Status:** Approved (design)

## Goal

When a ticket breaches its SLA, the system currently flips a boolean flag silently
(`routes/console.php` scheduler) — nobody is told. Make breaches notify the right people
so they can act, and consolidate the breach logic into one testable place.

## Decisions

- **Recipients:** the assigned IT staff member (if any) **plus all admins**, deduplicated by
  user id. Admins are the closest thing to an escalation tier without a schema change.
- **Action:** notify only (in-app + email). No auto-bump priority, no auto-reassign.
- **One notification class** (`SlaBreached`) parameterised by breach type, not two classes.

## Background / Current State

`routes/console.php` runs every 15 minutes and bulk-updates flags:

- Response breach: `sla_response_breached = false` AND `sla_response_due_at < now` AND
  `first_response_at IS NULL`.
- Resolution breach: `sla_resolution_breached = false` AND `sla_resolution_due_at < now` AND
  status NOT IN (`resolved`, `closed`).

It uses bulk `update()` (no per-row work), so it cannot notify. The `breached = false` filter
means each ticket transitions to breached exactly once — the natural place to fire a one-time
notification.

There is no manager / escalation-contact concept (departments have only name fields; SLA
policies hold only hours). Admins = users with role `admin`. IT staff = role `admin` or
`it_staff`.

Notification infrastructure: classes implement `ShouldQueue`, channels `['database', 'mail']`.
A test (`QueuedNotificationTest`) asserts **every** notification class is queued — the new
class must implement `ShouldQueue`.

## Architecture

Move the breach check out of the closure into an Artisan command so it is unit-testable and the
SLA-overdue logic lives in one place.

- **`app/Console/Commands/CheckSlaBreaches.php`** (signature `sla:check-breaches`):
  1. Fetch tickets newly past **response** SLA (conditions below). For each: set
     `sla_response_breached = true`, then notify recipients with `SlaBreached(ticket, 'response')`.
  2. Fetch tickets newly past **resolution** SLA. For each: set `sla_resolution_breached = true`,
     then notify recipients with `SlaBreached(ticket, 'resolution')`.
  3. Return the count handled (useful for logging/tests).
- **`routes/console.php`:** replace the inline `Schedule::call(...)` breach block with
  `Schedule::command('sla:check-breaches')->everyFifteenMinutes()->name('sla:check-breaches')`.
  The `sanctum:prune-expired` daily schedule is unchanged.

Set the flag **before** dispatching the (queued) notification, so a failure in queue dispatch
cannot cause a missed flag; queued notifications don't fail inline anyway.

## Breach Conditions

- **Response:** `sla_response_breached = false` AND `sla_response_due_at` not null AND
  `sla_response_due_at < now` AND `first_response_at IS NULL`.
- **Resolution:** `sla_resolution_breached = false` AND `sla_resolution_due_at` not null AND
  `sla_resolution_due_at < now` AND status NOT IN (`resolved`, `closed`, `rejected`).
  - **Correctness fix:** `rejected` is added to the exclusion (today it isn't, so a rejected
    ticket can be flagged as breached — pointless noise). `pending_approval` is intentionally
    left in scope (SLA pausing during approval is a separate, out-of-scope feature).

## Notification — `SlaBreached`

`app/Notifications/SlaBreached.php`, `implements ShouldQueue`, `use Queueable`.

- Constructor: `public Ticket $ticket, public string $type` where `$type` ∈ `response|resolution`.
- `via()` → `['database', 'mail']`.
- `toMail()` → subject `SLA Breach: {ticket_number}`; lines stating which SLA was missed
  (response vs resolution), the title, priority/department, and the deadline that passed;
  action button linking to `{frontend_url}/tickets/{id}`.
- `toArray()` →
  `['type' => 'sla_breached', 'breach_type' => $type, 'ticket_id', 'ticket_number', 'title', 'priority']`.

## Recipients

Build a unique `User` collection:
- `$ticket->assignee` if not null.
- All users with role `admin`.
- Deduplicate by `id` (an admin who is also the assignee is notified once).

Dispatch via `Notification::send($recipients, new SlaBreached($ticket, $type))`.

## Frontend

- **NotificationBell:** add rendering for `type === 'sla_breached'` — an icon/colour, a label
  built from `breach_type`, and click-through to `/tickets/{ticket_id}` (mirroring existing
  notification types).
- **i18n:** EN/ZH strings for the breach notification label (e.g. EN "SLA breached:
  {ticket}", ZH "SLA 已违约:{ticket}"), with response/resolution wording.

If the bell already falls back gracefully for unknown types, this is polish, not a hard
requirement — but it should display cleanly.

## Testing (TDD)

Feature test for the command using `Notification::fake()`:

- Response breach: a ticket past response-due with no first response gets
  `sla_response_breached = true` and a `SlaBreached` (type `response`) sent to the assignee and
  every admin, but **not** to a regular user.
- Resolution breach: analogous for resolution; type `resolution`.
- **No duplicate:** running the command twice notifies only once (flag already true on 2nd run).
- **Negative cases:** a ticket with `first_response_at` set is not response-breached; a
  `resolved`/`closed`/`rejected` ticket is not resolution-breached.
- Recipients deduped: an admin assignee receives exactly one notification.

## Out of Scope (v1)

- Business-hours / holiday-aware SLA (currently wall-clock `addHours`).
- Auto-bump priority or auto-reassign on breach.
- Configurable escalation contacts (per SLA policy / department).
- SLA pausing while `pending_approval`.
