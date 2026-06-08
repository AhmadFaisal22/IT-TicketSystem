# IT HelpDesk — Internal IT Ticketing System

Bilingual (English / 中文) internal IT ticketing system built for **SEG Solar Manufaktur Indonesia**.

![Laravel](https://img.shields.io/badge/Laravel-12-red?logo=laravel)
![Vue](https://img.shields.io/badge/Vue-3-brightgreen?logo=vue.js)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-14+-blue?logo=postgresql)
![TypeScript](https://img.shields.io/badge/TypeScript-5-blue?logo=typescript)
![License](https://img.shields.io/badge/license-MIT-green)

---

## Features

- **OAuth Login** — Google Workspace & Microsoft 365 SSO
- **Bilingual** — EN / 中文 toggle per user, persisted to DB
- **Ticket Workflow** — Open → In Progress → Pending → Resolved → Closed
- **SLA Tracking** — Per-department & per-priority response/resolution deadlines
- **Department Chat** — Threaded comments with IT-internal notes
- **Notifications** — In-app bell + email on ticket events
- **Dashboard** — Charts: trends, status, priority, department, IT workload
- **RBAC** — Admin / IT Staff / End User roles

---

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12 · PHP 8.2+ · Sanctum · Socialite |
| Frontend | Vue 3 · Vite · TypeScript · Tailwind CSS · Pinia · vue-i18n |
| Database | PostgreSQL 14+ |
| Auth | Google OAuth 2.0 · Microsoft Azure AD |

---

## Project Structure

```
it-helpdesk/
├── it-helpdesk-backend/   # Laravel 12 REST API  (port 8000)
└── it-helpdesk-frontend/  # Vue 3 SPA            (port 5173)
```

---

## Quick Start (Local Development)

### 1 — Backend

```bash
cd it-helpdesk-backend

# Install dependencies
php composer.phar install

# Copy and configure environment
cp .env.example .env
# Edit .env — set DB_*, GOOGLE_*, AZURE_*, FRONTEND_URL

# Generate app key
php artisan key:generate

# Run migrations + seed demo data
php artisan migrate
php artisan db:seed

# Start dev server
php artisan serve
# → http://localhost:8000
```

### 2 — Frontend

```bash
cd it-helpdesk-frontend

# Install dependencies
npm install

# Copy environment file
cp .env.example .env
# Edit .env if your backend runs on a different port

# Start dev server
npm run dev
# → http://localhost:5173
```

### 3 — Bootstrap Admin

After first login, promote your account to admin directly in the database:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your@email.com';
```

---

## Environment Variables

### Backend (`it-helpdesk-backend/.env`)

| Variable | Description |
|----------|-------------|
| `APP_URL` | Backend URL (e.g. `http://localhost:8000`) |
| `FRONTEND_URL` | Frontend URL (e.g. `http://localhost:5173`) |
| `DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD` | PostgreSQL connection |
| `GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET` | Google OAuth credentials |
| `AZURE_CLIENT_ID / AZURE_CLIENT_SECRET / AZURE_TENANT_ID` | Microsoft OAuth credentials |
| `MAIL_MAILER / MAIL_HOST / MAIL_PORT` | Email delivery (use `log` for dev) |

### Frontend (`it-helpdesk-frontend/.env`)

| Variable | Description |
|----------|-------------|
| `VITE_API_TARGET` | Backend URL for dev proxy (default: `http://localhost:8000`) |

---

## Production Deployment (VPS + Nginx)

### Backend

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache

# Queue worker (use Supervisor)
php artisan queue:work --sleep=3 --tries=3
```

Set in production `.env`:
```
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error
```

### Frontend

```bash
npm ci
npm run build
# Serve the dist/ folder via Nginx
```

### Nginx (minimal)

```nginx
server {
    listen 443 ssl http2;
    server_name helpdesk.yourdomain.com;
    root /path/to/it-helpdesk-frontend/dist;

    location / { try_files $uri /index.html; }

    location ~ ^/(api|storage)/ {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto https;
    }
}
```

---

## OAuth Setup

### Google
1. [Google Cloud Console](https://console.cloud.google.com) → APIs & Services → Credentials
2. Create OAuth 2.0 Client ID (Web application)
3. Add redirect URI: `https://yourdomain.com/api/auth/callback/google`
4. Copy Client ID + Secret → `.env`

### Microsoft Azure
1. [Azure Portal](https://portal.azure.com) → Azure Active Directory → App registrations
2. New registration, add redirect URI: `https://yourdomain.com/api/auth/callback/microsoft`
3. Copy Client ID, create Client Secret → `.env`
4. Set `AZURE_TENANT_ID=common` (or your tenant ID)

---

## Roles

| Role | Permissions |
|------|-------------|
| `user` | Create tickets for own department, view own tickets, comment |
| `it_staff` | View all tickets, change status, assign, write internal notes |
| `admin` | Everything + manage users, departments, SLA policies |

---

## License

MIT
