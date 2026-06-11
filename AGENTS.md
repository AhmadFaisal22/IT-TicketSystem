# IT HelpDesk — Internal IT Ticketing System

A bilingual (English/Chinese) internal IT ticketing system built with:
- **Backend**: Laravel 12 + PostgreSQL + Laravel Sanctum + Laravel Socialite
- **Frontend**: Vue 3 + Vite + TypeScript + Tailwind CSS + Pinia + vue-i18n

## Project Structure

```
Agentic Workflow/
├── it-helpdesk-backend/   Laravel 12 REST API
└── it-helpdesk-frontend/  Vue 3 SPA
```

## Quick Start

### Backend

```bash
cd it-helpdesk-backend

# 1. Install dependencies
php composer.phar install

# 2. Copy .env and configure
copy .env.example .env

# 3. Edit .env:
#    - Set DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
#    - Set GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET
#    - Set AZURE_CLIENT_ID / AZURE_CLIENT_SECRET
#    - Set FRONTEND_URL=http://localhost:5173

# 4. Generate app key
php artisan key:generate

# 5. Run migrations + seed
php artisan migrate
php artisan db:seed

# 6. Run dev server
php artisan serve
```

### Frontend

```bash
cd it-helpdesk-frontend
npm install
npm run dev
```

Frontend runs at http://localhost:5173
Backend runs at http://localhost:8000

## Key Features

| Feature | Description |
|---------|-------------|
| OAuth Login | Google Workspace & Microsoft 365 SSO |
| Bilingual | EN/中文 toggle per user, persisted |
| Ticket Workflow | Open → In Progress → Pending → Resolved → Closed |
| SLA Tracking | Per-department + per-priority response/resolution deadlines |
| Department Chat | Threaded comments on tickets (with IT internal notes) |
| Notifications | In-app bell + email on ticket events |
| Dashboard | Charts: trends, status, priority, department, IT workload |
| RBAC | Admin / IT Staff / End User roles |

## Database Schema

- `users` — id, name, email, google_id, microsoft_id, role, department_id, locale
- `departments` — id, name, name_zh (bilingual)
- `sla_policies` — id, department_id (nullable=default), priority, response_hours, resolution_hours
- `tickets` — id, ticket_number (TKT-00001), title, description, status, priority, department_id, sla deadlines
- `comments` — id, ticket_id, user_id, body, is_internal
- `ticket_histories` — audit trail of changes
- `notifications` — Laravel DB notifications (type, data, read_at)

## API Endpoints

```
GET  /api/auth/redirect/google       → OAuth redirect URL
GET  /api/auth/callback/google       → Exchange code for token
GET  /api/auth/me                    → Current user
POST /api/auth/logout

GET  /api/tickets                    → List (paginated, filterable)
POST /api/tickets                    → Create
GET  /api/tickets/:id                → Detail + comments + history
PATCH /api/tickets/:id/status        → Change status (IT staff)
PATCH /api/tickets/:id/assign        → Assign (IT staff)

GET  /api/tickets/:id/comments       → Thread
POST /api/tickets/:id/comments       → Reply (is_internal flag)

GET  /api/dashboard/stats            → KPIs + charts data
GET  /api/dashboard/sla              → SLA compliance data

GET  /api/departments                → Department list
GET  /api/users/it-staff             → IT staff for assignment
GET  /api/notifications              → User notifications
```

## OAuth Setup

### Google
1. https://console.cloud.google.com → APIs & Services → Credentials
2. Create OAuth 2.0 Client ID (Web)
3. Add redirect URI: `http://localhost:8000/api/auth/callback/google`
4. Copy Client ID + Secret to `.env`

### Microsoft Azure
1. https://portal.azure.com → Azure Active Directory → App registrations
2. New registration, add redirect URI: `http://localhost:8000/api/auth/callback/microsoft`
3. Copy Client ID, create Client Secret
4. Set `AZURE_TENANT_ID=common` (or your tenant ID for single-org)

## Roles

| Role | Can Do |
|------|--------|
| `user` | Create tickets for their department, view own tickets, comment |
| `it_staff` | View all tickets, change status, assign, internal notes |
| `admin` | Everything + manage users, departments, SLA policies |

> First user to log in gets `user` role by default.
> An admin must manually promote them via Admin → Users.
> To bootstrap: directly UPDATE the first user in DB to role='admin'.
