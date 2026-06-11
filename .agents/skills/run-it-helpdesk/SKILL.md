---
name: run-it-helpdesk
description: Run, start, launch, screenshot, verify, test, or drive the IT HelpDesk app (Vue 3 frontend + Laravel backend). Use when asked to run the app, take a screenshot, confirm a feature works, or check the live UI.
---

# run-it-helpdesk

Bilingual IT ticketing SPA — Vue 3 frontend on Vite + Laravel 12 REST API on PHP-FPM.
Driven via a **Chrome CDP smoke driver** (`smoke.mjs`) — no extra packages required beyond Node.js and the Chrome installation already on this machine.

---

## Prerequisites

Already installed on this machine:
- PHP 8.2 at `php` in PATH
- Node.js 20 at `node` in PATH
- Chrome at `C:/Program Files/Google/Chrome/Application/chrome.exe`
- Frontend deps: `it-helpdesk-frontend/node_modules/` present
- Backend deps: `it-helpdesk-backend/vendor/` present
- SQLite DB with migrations run: `it-helpdesk-backend/database/database.sqlite`

No `apt-get` or `npm install` needed to run the smoke driver — it uses only Node built-ins.

---

## Start the servers

Open two terminals (or use `start-helpdesk.bat` from the repo root on Windows):

```powershell
# Terminal 1 — backend (port 8000)
cd it-helpdesk-backend
php artisan serve --port=8000

# Terminal 2 — frontend (port 5173; auto-bumps to 5174 if 5173 is taken)
cd it-helpdesk-frontend
npm run dev
```

Verify:
```bash
curl -s http://localhost:8000/api/auth/me   # → {"message":"Unauthenticated."}
curl -s http://localhost:5173/              # → <!doctype html> ...  (or 5174)
```

---

## Create a test token (once)

The app uses Google/Microsoft OAuth — no password login. For local testing, create a Sanctum token directly:

```bash
cd it-helpdesk-backend
php artisan tinker --execute "echo App\Models\User::find(1)->createToken('smoke')->plainTextToken;"
# Output: 50|<random-string>
```

`User::find(1)` is `admin@helpdesk.local` (Administrator SEG, role=admin). For IT staff use `find(2)`, for a regular user use `find(3)`.

---

## Run: agent path (smoke driver)

The driver at `.Codex/skills/run-it-helpdesk/smoke.mjs` does:
1. API smoke tests (unauthenticated 401, then authenticated endpoints)
2. Launches Chrome with `--remote-debugging-port=9222`
3. Injects the Sanctum token into `localStorage`
4. Navigates to 5 pages and saves PNG screenshots

```bash
cd "c:/Users/LENOVO/Agentic Workflow"

SANCTUM_TOKEN="50|<your-token>" \
FRONTEND_URL="http://localhost:5174" \
node .Codex/skills/run-it-helpdesk/smoke.mjs /tmp/helpdesk-screenshots
```

Expected output:
```
── API smoke ────────────────────────────────────────────
  ✓ Unauthenticated → 401
  ✓ /api/auth/me → Administrator SEG (admin)
  ✓ /api/departments → 12 depts
  ✓ /api/tickets → total=3
  ✓ /api/dashboard/stats → 200

── Screenshots ──────────────────────────────────────────
  ✓ 01-login.png
  ✓ 02-dashboard.png
  ✓ 03-tickets.png
  ✓ 04-create-ticket.png
  ✓ 05-admin-users.png

✓ All checks passed. Screenshots → /tmp/helpdesk-screenshots/
```

Screenshots land in the directory you pass as the first argument (default `/tmp/helpdesk-screenshots`).

### Environment variables

| Variable | Default | Notes |
|---|---|---|
| `SANCTUM_TOKEN` | _(none)_ | Required for auth tests + screenshots past login |
| `FRONTEND_URL` | `http://localhost:5174` | Match whatever port Vite picked |
| `BACKEND_URL` | `http://localhost:8000` | Standard Laravel serve port |
| `CHROME_EXE` | `C:/Program Files/Google/Chrome/Application/chrome.exe` | Override if Chrome is elsewhere |

### Add more pages

In `smoke.mjs`, add calls after the existing `cdp.navigate(...)` + `cdp.screenshot(...)` pairs:

```js
await cdp.navigate(`${FRONTEND_URL}/tickets/1`, 2500);
await cdp.screenshot(path.join(SCREENSHOT_DIR, '06-ticket-detail.png'));
```

---

## Run: human path

```powershell
start-helpdesk.bat    # Windows — opens two cmd windows, opens browser at localhost:5173
```

Then log in via Google or Microsoft OAuth (requires configured `.env` credentials).

---

## Test the build

```bash
cd it-helpdesk-frontend
npm run build         # vue-tsc + vite build — must exit 0 with no TS errors
```

---

## Gotchas

- **Vite port auto-bumps.** If port 5173 is already in use, Vite picks 5174 (or 5175...). Always check the Vite startup output and pass the actual port in `FRONTEND_URL`.

- **Chrome CDP frames MUST be masked.** RFC 6455 requires client→server WS frames to carry a 4-byte mask key. Chrome silently drops unmasked frames — no error, just no response. The `smoke.mjs` driver masks all sends correctly. If you extend it or write your own CDP client, this is the first thing to check if commands seem to vanish.

- **OAuth login can't be driven headlessly.** The app has no username/password form — only Google/Microsoft OAuth buttons. Use `php artisan tinker` to create Sanctum tokens for all automated testing.

- **Sanctum token is single-use for this test user.** If the token gets revoked (user logs out, or `php artisan sanctum:prune-expired` runs), create a new one with the tinker command above.

- **SQLite (dev) vs PostgreSQL (prod).** The `.env` uses `DB_CONNECTION=sqlite` locally. The Docker deploy uses PostgreSQL. Any raw SQL in controllers must be database-agnostic — `DashboardController` uses PHP Carbon instead of SQLite-only `JULIANDAY()`.

- **Admin bootstrap.** Seed data has `admin@helpdesk.local` (User id=1) as admin. If seeding from scratch on a fresh DB, the first Google-OAuth user gets role=`user` — promote via tinker: `User::find(1)->update(['role'=>'admin'])`.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| `Chrome CDP not ready on port 9222` | Previous Chrome process still holds the port. Run `taskkill /F /IM chrome.exe /T` |
| Screenshots are blank white | Vite dev server not running, or `FRONTEND_URL` points to wrong port |
| `401` on all API calls even with token | Token was revoked — create a new one via tinker |
| `php artisan serve` fails | `DB_DATABASE` path in `.env` is wrong, or SQLite file missing — run `php artisan migrate` |
| Dashboard stats 500 | Typically a missing department or SLA policy — re-run `php artisan db:seed` |
