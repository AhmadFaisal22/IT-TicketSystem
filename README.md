# IT HelpDesk — Internal IT Ticketing System

Bilingual (English / 中文) internal IT ticketing system built for **SEG Solar Manufaktur Indonesia**.

![Laravel](https://img.shields.io/badge/Laravel-12-red?logo=laravel)
![Vue](https://img.shields.io/badge/Vue-3-brightgreen?logo=vue.js)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14+-blue?logo=postgresql)
![TypeScript](https://img.shields.io/badge/TypeScript-5-blue?logo=typescript)
![License](https://img.shields.io/badge/license-MIT-green)

---

## Clone & Run in 5 Minutes (Windows)

> **Prerequisites:** [Git](https://git-scm.com/downloads) · [PHP 8.2+](https://windows.php.net/download/) · [Composer](https://getcomposer.org/download/) · [Node.js 18+](https://nodejs.org/)

### Step 1 — Clone the repository

```bash
git clone https://github.com/AhmadFaisal22/IT-TicketSystem.git
cd IT-TicketSystem
```

### Step 2 — Set up the backend

```bash
cd it-helpdesk-backend

# Install PHP dependencies
composer install

# Create environment file
copy .env.example .env

# Generate app key
php artisan key:generate

# (Optional) Use SQLite for zero-config local dev — edit .env:
#   DB_CONNECTION=sqlite
#   DB_DATABASE=database/database.sqlite
echo "" > database\database.sqlite

# Run database migrations
php artisan migrate

# (Optional) Seed demo users and departments
php artisan db:seed

cd ..
```

### Step 3 — Set up the frontend

```bash
cd it-helpdesk-frontend
npm install
cd ..
```

### Step 4 — Start everything with one double-click

Double-click **`start-helpdesk.bat`** in the root folder.

It will open two terminal windows and launch your browser automatically:

```
start-helpdesk.bat
  ├── Opens "Laravel Backend"  window → php artisan serve  (http://localhost:8000)
  └── Opens "Vite Frontend"    window → npm run dev        (http://localhost:5173)
  └── Opens browser at http://localhost:5173  after 4 seconds
```

Or run it from a terminal:

```cmd
start-helpdesk.bat
```

### Step 5 — Bootstrap your admin account

After your first login, promote your account to admin:

```bash
cd it-helpdesk-backend
php artisan tinker
>>> \App\Models\User::where('email','your@email.com')->update(['role'=>'admin']);
```

---

## Table of Contents

1. [Features](#features)
2. [Tech Stack](#tech-stack)
3. [Project Structure](#project-structure)
4. [Database Schema](#database-schema)
5. [Ticket Lifecycle](#ticket-lifecycle)
6. [Approval Chain Flow](#approval-chain-flow)
7. [API Reference](#api-reference)
8. [Quick Start (Local)](#quick-start-local)
9. [Environment Variables](#environment-variables)
10. [Email Setup (Office 365)](#email-setup-office-365)
11. [OAuth Setup](#oauth-setup)
12. [Role Permissions](#role-permissions)
13. [Production Deployment](#production-deployment)

---

## Features

| Feature | Description |
|---------|-------------|
| **Email + OAuth Login** | Email/password login + Google Workspace & Microsoft 365 SSO |
| **Forgot Password** | Email-based reset link flow (like Google's), token expires in 60 min |
| **Bilingual** | EN / 中文 toggle per user, persisted to DB |
| **Ticket Workflow** | Open → In Progress → Pending → Resolved → Closed |
| **Approval Chain** | Department-level multi-step approval before ticket reaches IT |
| **SLA Tracking** | Per-department & per-priority response/resolution deadlines |
| **Department Chat** | Threaded comments with IT-internal notes (hidden from end users) |
| **Notifications** | In-app bell + email on ticket events (create, assign, comment, approve/reject) |
| **Dashboard** | Charts: trends, status distribution, priority, department, IT workload |
| **RBAC** | Admin / IT Staff / End User roles |

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12 · PHP 8.2+ · Sanctum · Socialite |
| Frontend | Vue 3 · Vite · TypeScript · Tailwind CSS · Pinia · vue-i18n |
| Database | PostgreSQL 14+ (SQLite for local dev) |
| Auth | Email/Password · Google OAuth 2.0 · Microsoft Azure AD |
| Mail | SMTP (Office 365 / Gmail / any provider) |

---

## Project Structure

```
IT-TicketSystem/
├── it-helpdesk-backend/          # Laravel 12 REST API
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   │   ├── AuthController.php          # Login, OAuth, logout, locale
│   │   │   ├── ForgotPasswordController.php # Forgot + reset password
│   │   │   ├── TicketController.php        # Ticket CRUD + status/assign
│   │   │   ├── TicketApprovalController.php # Approve / reject tickets
│   │   │   ├── ApprovalLevelController.php  # Admin: manage approval chains
│   │   │   ├── CommentController.php        # Ticket comments
│   │   │   ├── DashboardController.php      # KPI stats + charts
│   │   │   ├── DepartmentController.php
│   │   │   ├── NotificationController.php
│   │   │   ├── SlaController.php
│   │   │   └── UserController.php
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Ticket.php
│   │   │   ├── ApprovalLevel.php
│   │   │   ├── TicketApproval.php
│   │   │   ├── Comment.php
│   │   │   ├── Department.php
│   │   │   └── SlaPolicy.php
│   │   ├── Notifications/
│   │   │   ├── ResetPasswordNotification.php
│   │   │   ├── TicketApprovalRequested.php
│   │   │   ├── TicketApproved.php
│   │   │   └── TicketRejected.php
│   │   └── Policies/
│   │       └── TicketPolicy.php
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   └── routes/api.php
│
└── it-helpdesk-frontend/         # Vue 3 SPA
    └── src/
        ├── api/index.ts            # Axios + all API calls
        ├── stores/
        │   ├── auth.ts             # Pinia: current user + token
        │   └── tickets.ts          # Pinia: tickets + types
        ├── router/index.ts         # Routes + guards
        ├── locales/
        │   ├── en.ts               # English strings
        │   └── zh.ts               # Chinese strings
        └── views/
            ├── auth/
            │   ├── LoginView.vue
            │   ├── ForgotPasswordView.vue  # Email form → "Check email" state
            │   └── ResetPasswordView.vue   # Token + new password form
            ├── tickets/
            │   ├── TicketsView.vue
            │   ├── CreateTicketView.vue
            │   ├── EditTicketView.vue
            │   └── TicketDetailView.vue    # Includes approval card
            ├── admin/
            │   ├── UsersView.vue
            │   ├── DepartmentsView.vue
            │   ├── SlaView.vue
            │   └── ApprovalLevelsView.vue  # JIRA-style approval chain builder
            └── dashboard/
                └── DashboardView.vue
```

---

## Database Schema

```
┌─────────────────────────────────────────────────────────────────┐
│                         users                                    │
├──────────────────┬──────────────────────────────────────────────┤
│ id               │ bigint PK                                     │
│ name             │ varchar                                       │
│ email            │ varchar UNIQUE                                │
│ password         │ varchar nullable (null for OAuth-only users)  │
│ google_id        │ varchar nullable                              │
│ microsoft_id     │ varchar nullable                              │
│ avatar           │ varchar nullable                              │
│ role             │ enum: admin | it_staff | user                 │
│ department_id    │ FK → departments.id nullable                  │
│ locale           │ enum: en | zh  default 'en'                   │
│ active           │ boolean default true                          │
│ created_at       │ timestamp                                     │
└──────────────────┴──────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                       departments                                │
├──────────────────┬──────────────────────────────────────────────┤
│ id               │ bigint PK                                     │
│ name             │ varchar  (English)                            │
│ name_zh          │ varchar  (Chinese)                            │
│ description      │ text nullable                                 │
└──────────────────┴──────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                         tickets                                  │
├──────────────────┬──────────────────────────────────────────────┤
│ id               │ bigint PK                                     │
│ ticket_number    │ varchar UNIQUE  (e.g. TKT-00001)              │
│ title            │ varchar                                       │
│ description      │ text                                          │
│ status           │ enum: open | in_progress | pending |          │
│                  │       pending_approval | resolved |           │
│                  │       closed | rejected                       │
│ priority         │ enum: low | medium | high | critical          │
│ category         │ varchar nullable                              │
│ subcategory      │ varchar nullable                              │
│ department_id    │ FK → departments.id                           │
│ created_by       │ FK → users.id                                 │
│ assigned_to      │ FK → users.id nullable                        │
│ response_due_at  │ timestamp nullable  (SLA deadline)            │
│ resolution_due_at│ timestamp nullable  (SLA deadline)            │
│ resolved_at      │ timestamp nullable                            │
│ closed_at        │ timestamp nullable                            │
│ created_at       │ timestamp                                     │
└──────────────────┴──────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                      approval_levels                             │
│         (admin-configured approval chains per department)        │
├──────────────────┬──────────────────────────────────────────────┤
│ id               │ bigint PK                                     │
│ department_id    │ FK → departments.id                           │
│ name             │ varchar  (e.g. "Department Head Approval")    │
│ approver_id      │ FK → users.id                                 │
│ level_order      │ integer  (1 = first in chain)                 │
│ is_active        │ boolean  (inactive = skipped)                 │
│ created_at       │ timestamp                                     │
└──────────────────┴──────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                      ticket_approvals                            │
│          (one row per approval step, per ticket)                 │
├──────────────────┬──────────────────────────────────────────────┤
│ id               │ bigint PK                                     │
│ ticket_id        │ FK → tickets.id                               │
│ approver_id      │ FK → users.id                                 │
│ level_order      │ integer                                       │
│ status           │ enum: pending | approved | rejected |         │
│                  │       cancelled                               │
│ notes            │ text nullable                                 │
│ acted_at         │ timestamp nullable                            │
│ created_at       │ timestamp                                     │
└──────────────────┴──────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                         comments                                 │
├──────────────────┬──────────────────────────────────────────────┤
│ id               │ bigint PK                                     │
│ ticket_id        │ FK → tickets.id                               │
│ user_id          │ FK → users.id                                 │
│ body             │ text                                          │
│ is_internal      │ boolean  (true = IT-staff-only note)          │
│ created_at       │ timestamp                                     │
└──────────────────┴──────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                      ticket_histories                            │
│                  (full audit trail of changes)                   │
├──────────────────┬──────────────────────────────────────────────┤
│ id               │ bigint PK                                     │
│ ticket_id        │ FK → tickets.id                               │
│ user_id          │ FK → users.id nullable                        │
│ action           │ varchar  (e.g. "status_changed", "assigned")  │
│ old_value        │ text nullable                                  │
│ new_value        │ text nullable                                  │
│ created_at       │ timestamp                                     │
└──────────────────┴──────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                       sla_policies                               │
├──────────────────┬──────────────────────────────────────────────┤
│ id               │ bigint PK                                     │
│ department_id    │ FK → departments.id nullable (null = default) │
│ priority         │ enum: low | medium | high | critical          │
│ response_hours   │ integer                                       │
│ resolution_hours │ integer                                       │
└──────────────────┴──────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                       notifications                              │
│               (Laravel DB notification table)                    │
├──────────────────┬──────────────────────────────────────────────┤
│ id               │ uuid PK                                       │
│ type             │ varchar  (fully qualified notification class)  │
│ notifiable_type  │ varchar  (e.g. "App\Models\User")             │
│ notifiable_id    │ bigint   FK → users.id                        │
│ data             │ json     (notification payload)               │
│ read_at          │ timestamp nullable                            │
│ created_at       │ timestamp                                     │
└──────────────────┴──────────────────────────────────────────────┘
```

---

## Ticket Lifecycle

```
                         ┌─────────────────────┐
                         │   User submits ticket│
                         └──────────┬──────────┘
                                    │
                    ┌───────────────▼───────────────┐
                    │ Department has approval levels?│
                    └──────┬────────────────┬───────┘
                           │ YES            │ NO
                           ▼                ▼
                  ┌──────────────┐   ┌──────────────┐
                  │pending_appro-│   │     open     │
                  │    val       │   │  (IT queue)  │
                  └──────┬───────┘   └──────┬───────┘
                         │                  │
          ┌──────────────▼──────┐           │
          │ Approver 1 reviews  │           │
          └──────┬──────────────┘           │
                 │                          │
        ┌────────▼────────┐                 │
        │ Approved?       │                 │
        └──┬───────────┬──┘                 │
           │ YES       │ NO                 │
           ▼           ▼                    │
    Next level?   ┌─────────┐              │
    ┌──┴────┐     │rejected │              │
    │YES│NO │     └─────────┘              │
    ▼   ▼   │                              │
 loop  open─┘                              │
           │                               │
           └───────────────────────────────┘
                           │
                    ┌──────▼──────┐
                    │   open      │  ← IT staff sees it
                    └──────┬──────┘
                           │ IT Staff picks up
                    ┌──────▼──────┐
                    │ in_progress │
                    └──────┬──────┘
                           │ Waiting on user
                    ┌──────▼──────┐
                    │   pending   │  (optional waiting state)
                    └──────┬──────┘
                           │ Issue fixed
                    ┌──────▼──────┐
                    │  resolved   │
                    └──────┬──────┘
                           │ After confirmation / auto
                    ┌──────▼──────┐
                    │   closed    │
                    └─────────────┘
```

### Status Descriptions

| Status | Who Can Set | Meaning |
|--------|-------------|---------|
| `open` | System (auto on create / after approval) | Ticket is in IT queue, waiting to be picked up |
| `in_progress` | IT Staff | IT is actively working on it |
| `pending` | IT Staff | Waiting for user response / external action |
| `pending_approval` | System (auto on create if dept has levels) | Waiting for dept approval chain |
| `resolved` | IT Staff | Issue resolved, awaiting closure |
| `closed` | IT Staff / Admin | Ticket closed |
| `rejected` | Approver (during approval chain) | Ticket was rejected at approval stage |

---

## Approval Chain Flow

The admin configures approval levels per department in **Admin → Approval Levels**.

```
Department: Finance
─────────────────────────────────────────────────────────────────
 Submitter  →  [Step 1: Dept Head]  →  [Step 2: Finance Manager]  →  IT Staff
              Ahmad (approver_id=5)     Budi (approver_id=8)

ticket_approvals rows created when ticket is submitted:
  { ticket_id: 42, approver_id: 5, level_order: 1, status: 'pending' }
  { ticket_id: 42, approver_id: 8, level_order: 2, status: 'pending' }

Sequence:
  1. Ticket created → status = 'pending_approval'
     → email sent to Ahmad (level 1)
  2. Ahmad approves → level 1 row: status = 'approved'
     → email sent to Budi (level 2)
  3. Budi approves → level 2 row: status = 'approved'
     → ticket status = 'open' → email sent to IT team
  4. If any approver rejects → ticket status = 'rejected'
     → all remaining approvals = 'cancelled'
     → email sent to submitter

If department has NO approval levels:
  → ticket status = 'open' immediately → IT is notified
```

---

## API Reference

All endpoints are prefixed with `/api`. Protected routes require `Authorization: Bearer <token>`.

### Authentication

```
POST /api/auth/login                  Email + password login
POST /api/auth/forgot-password        Send password reset link
POST /api/auth/reset-password         Reset password with token
GET  /api/auth/redirect/google        Get Google OAuth URL
GET  /api/auth/redirect/microsoft     Get Microsoft OAuth URL
GET  /api/auth/callback/google        Exchange OAuth code → token
GET  /api/auth/callback/microsoft     Exchange OAuth code → token

GET  /api/auth/me          [auth]     Current user
POST /api/auth/logout      [auth]     Invalidate token
PATCH /api/auth/locale     [auth]     Update UI language (en/zh)
```

### Tickets

```
GET  /api/tickets              [auth]  List tickets (paginated, filterable)
POST /api/tickets              [auth]  Create ticket
GET  /api/tickets/:id          [auth]  Detail + comments + history + approvals
PATCH /api/tickets/:id/status  [auth]  Change status (IT staff)
PATCH /api/tickets/:id/assign  [auth]  Assign to IT staff
POST /api/tickets/:id/approve  [auth]  Approve current approval step
POST /api/tickets/:id/reject   [auth]  Reject current approval step
```

Query params for `GET /api/tickets`:
- `status`, `priority`, `department_id`, `assigned_to`, `search`, `page`

### Comments

```
GET  /api/tickets/:id/comments          [auth]  List comments
POST /api/tickets/:id/comments          [auth]  Add comment (is_internal flag)
DELETE /api/tickets/:id/comments/:cid   [auth]  Delete comment
```

### Admin

```
GET  /api/users                    [admin]  List all users (paginated)
POST /api/users                    [admin]  Create user
PUT  /api/users/:id                [admin]  Update user
DELETE /api/users/:id              [admin]  Delete user
PATCH /api/users/:id/role          [admin]  Change role
PATCH /api/users/:id/department    [admin]  Change department
PATCH /api/users/:id/toggle-active [admin]  Enable / disable account

GET    /api/departments            [auth]   List departments
POST   /api/departments            [admin]  Create
PUT    /api/departments/:id        [admin]  Update
DELETE /api/departments/:id        [admin]  Delete

GET  /api/sla-policies             [auth]   List SLA policies
POST /api/sla-policies             [admin]  Create
DELETE /api/sla-policies/:id       [admin]  Delete

GET    /api/approval-levels        [admin]  List all approval levels
POST   /api/approval-levels        [admin]  Create level
PUT    /api/approval-levels/:id    [admin]  Update level
DELETE /api/approval-levels/:id    [admin]  Delete level
POST   /api/approval-levels/reorder [admin] Reorder levels
```

### Dashboard & Notifications

```
GET /api/dashboard/stats           [auth]  KPIs + chart data
GET /api/dashboard/sla             [auth]  SLA compliance data

GET  /api/notifications            [auth]  List notifications
GET  /api/notifications/unread-count [auth] Unread count
PATCH /api/notifications/:id/read  [auth]  Mark one as read
PATCH /api/notifications/mark-all-read [auth] Mark all read
```

---

## Quick Start (Local)

### Prerequisites

- PHP 8.2+ with extensions: `pdo_pgsql`, `pdo_sqlite`, `mbstring`, `openssl`, `xml`
- Composer
- Node.js 18+
- PostgreSQL 14+ (or SQLite for quick local dev)

### 1 — Backend

```bash
cd it-helpdesk-backend

# Install PHP dependencies
php composer.phar install
# or: composer install

# Copy environment file
cp .env.example .env

# Generate app key
php artisan key:generate

# For SQLite (zero-config local dev):
# Set DB_CONNECTION=sqlite and DB_DATABASE=database/database.sqlite in .env
touch database/database.sqlite

# Run migrations + seed
php artisan migrate
php artisan db:seed

# Start dev server
php artisan serve
# → http://localhost:8000
```

### 2 — Frontend

```bash
cd it-helpdesk-frontend

npm install
npm run dev
# → http://localhost:5173
```

### 3 — Bootstrap Admin

After first login, promote your account to admin in the DB:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your@email.com';
```

Or via Laravel Tinker:

```bash
php artisan tinker
>>> \App\Models\User::where('email','your@email.com')->update(['role'=>'admin']);
```

---

## Environment Variables

### Backend (`it-helpdesk-backend/.env`)

Copy from `.env.example`. Key variables:

```env
APP_NAME="IT HelpDesk"
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173        # Used in reset-password email links

# Database — PostgreSQL (production)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=it_helpdesk
DB_USERNAME=postgres
DB_PASSWORD=secret

# Database — SQLite (local dev, zero-config)
# DB_CONNECTION=sqlite
# DB_DATABASE=database/database.sqlite

# Email
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com             # or smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=helpdesk@yourcompany.com   # Sender account
MAIL_PASSWORD=                           # Account password or App Password
MAIL_FROM_ADDRESS=helpdesk@yourcompany.com
MAIL_FROM_NAME="${APP_NAME}"

# Use MAIL_MAILER=log during development (emails go to storage/logs/laravel.log)

# Google OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/api/auth/callback/google"

# Microsoft OAuth
AZURE_CLIENT_ID=
AZURE_CLIENT_SECRET=
AZURE_REDIRECT_URI="${APP_URL}/api/auth/callback/microsoft"
AZURE_TENANT_ID=common
```

### Frontend (`it-helpdesk-frontend/.env`)

No required variables for local dev — the Vite proxy points to `http://localhost:8000` by default.

---

## Email Setup (Office 365)

The forgot-password and ticket notification emails are sent via SMTP.

### Microsoft 365 (Office 365 SMTP)

1. **Enable SMTP AUTH** for the sending account:
   - Microsoft 365 Admin Center → **Users → Active users**
   - Click the sending account → **Mail** tab → **Manage email apps**
   - Tick **Authenticated SMTP** → Save

2. **App Password** (if MFA is enabled on the account):
   - Go to [account.microsoft.com/security](https://account.microsoft.com/security)
   - Security → App passwords → Create new
   - Use the generated password as `MAIL_PASSWORD`

3. **`.env` settings:**
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.office365.com
   MAIL_PORT=587
   MAIL_USERNAME=helpdesk@yourcompany.com
   MAIL_PASSWORD=your_password_or_app_password
   MAIL_FROM_ADDRESS=helpdesk@yourcompany.com
   ```

### Gmail Alternative

```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=youraddress@gmail.com
MAIL_PASSWORD=your_gmail_app_password   # Requires 2FA + App Password
```

### Dev / Testing

```env
MAIL_MAILER=log   # Emails written to storage/logs/laravel.log
```

---

## OAuth Setup

### Google Workspace

1. [Google Cloud Console](https://console.cloud.google.com) → APIs & Services → Credentials
2. Create **OAuth 2.0 Client ID** (Web application)
3. Authorised redirect URI: `https://yourdomain.com/api/auth/callback/google`
4. Copy Client ID + Secret to `.env`

### Microsoft Azure AD

1. [Azure Portal](https://portal.azure.com) → Azure Active Directory → App registrations
2. New registration → Redirect URI: `https://yourdomain.com/api/auth/callback/microsoft`
3. Certificates & secrets → New client secret
4. Copy Application (client) ID + Secret to `.env`
5. `AZURE_TENANT_ID=common` (multi-tenant) or your tenant ID (single org)

> **Note:** OAuth login is optional. Users can also log in with email + password.

---

## Role Permissions

| Action | `user` | `it_staff` | `admin` |
|--------|--------|-----------|---------|
| Create ticket (own dept) | ✅ | ✅ | ✅ |
| View own tickets | ✅ | ✅ | ✅ |
| View all tickets | ❌ | ✅ | ✅ |
| Change ticket status | ❌ | ✅ | ✅ |
| Assign ticket to IT staff | ❌ | ✅ | ✅ |
| Write internal notes | ❌ | ✅ | ✅ |
| Approve / reject (as approver) | ✅ | ✅ | ✅ |
| Manage users | ❌ | ❌ | ✅ |
| Manage departments | ❌ | ❌ | ✅ |
| Manage SLA policies | ❌ | ❌ | ✅ |
| Manage approval levels | ❌ | ❌ | ✅ |
| View dashboard | ✅ | ✅ | ✅ |

---

## Production Deployment

### Server Requirements

- Ubuntu 22.04 / Debian 12
- Nginx
- PHP 8.2 + FPM
- PostgreSQL 14+
- Node.js 18+ (build only)

### Backend

```bash
cd it-helpdesk-backend

composer install --no-dev --optimize-autoloader
cp .env.example .env
# Fill in APP_KEY, DB_*, MAIL_*, GOOGLE_*, AZURE_*
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Production `.env` additions:

```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error
```

### Frontend

```bash
cd it-helpdesk-frontend

npm ci
npm run build
# Serve dist/ via Nginx
```

### Nginx Config

```nginx
server {
    listen 443 ssl http2;
    server_name helpdesk.yourcompany.com;

    # Frontend SPA
    root /var/www/it-helpdesk-frontend/dist;
    index index.html;

    location / {
        try_files $uri /index.html;
    }

    # Proxy API + storage to Laravel
    location ~ ^/(api|storage)/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    ssl_certificate     /etc/letsencrypt/live/helpdesk.yourcompany.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/helpdesk.yourcompany.com/privkey.pem;
}
```

### Queue Worker (Supervisor)

Email notifications use queued jobs. Run a worker:

```bash
# /etc/supervisor/conf.d/it-helpdesk.conf
[program:it-helpdesk-worker]
command=php /var/www/it-helpdesk-backend/artisan queue:work --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/it-helpdesk-worker.log
```

### Docker (Synology NAS)

```bash
# At repo root
docker-compose up -d
```

See `docker-compose.yml` for port configuration.

---

## License

MIT
