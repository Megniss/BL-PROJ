# External Integrations

**Analysis Date:** 2026-03-26

## APIs & External Services

No third-party external APIs are integrated. All API calls from the frontend go to the same Laravel backend (`/api/*`), handled by `routes/api.php`. There are no SDK imports for services like Stripe, Twilio, SendGrid, Google, etc.

## Data Storage

**Database:**
- SQLite (file-based)
  - Connection: `DB_CONNECTION=sqlite` (default in `.env.example`)
  - File path: `database/database.sqlite`
  - Client: Laravel Eloquent ORM
  - Tables: `users`, `books`, `swap_requests`, `messages`, `notifications`, `personal_access_tokens`, `cache`, `jobs`, `password_reset_tokens`, `sessions`

**File Storage:**
- Local filesystem only (`FILESYSTEM_DISK=local`)
- No S3 or cloud file storage active; AWS env vars present in `.env.example` but all empty and unused

**Caching:**
- Database driver (`CACHE_STORE=database`)
- Memcached and Redis entries exist in `.env.example` but are not the active driver

## Authentication & Identity

**Auth Provider:**
- Custom (no OAuth, no third-party identity provider)
- Implementation: Laravel Sanctum token auth
  - Tokens issued on login/register by `AuthController`, stored in `personal_access_tokens` table
  - Frontend stores token in `localStorage` (`bookloop_token`) via `resources/js/authStore.js`
  - All authenticated routes use `middleware('auth:sanctum')`
  - Axios `Authorization: Bearer <token>` header set globally in `resources/js/app.js`
  - Auto-logout on 401 response via Axios interceptor in `resources/js/app.js`

**Password Reset:**
- Laravel's built-in `Password` facade (`Illuminate\Support\Facades\Password`)
- Sends reset link via the configured mailer
- Handled by `app/Http/Controllers/PasswordResetController.php`

## Monitoring & Observability

**Error Tracking:**
- None (no Sentry, Bugsnag, or similar SDK detected)

**Logs:**
- Laravel's built-in logging (`LOG_CHANNEL=stack`, `LOG_STACK=single`)
- Dev log streaming via Laravel Pail (`php artisan pail`) — started automatically in `composer run dev`

## CI/CD & Deployment

**Hosting:**
- Not configured; no Dockerfile, `fly.toml`, `render.yaml`, Heroku `Procfile`, or similar detected

**CI Pipeline:**
- None (no `.github/workflows/`, `.gitlab-ci.yml`, etc. detected)

## Email / Notifications

**Mailer:**
- `MAIL_MAILER=log` by default (emails are written to the Laravel log, not actually sent)
- SMTP settings present in `.env.example` (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`) but all point to placeholder values
- Password reset emails use this mailer via Laravel's built-in `Password::sendResetLink()`
- No transactional email provider (Mailgun, Postmark, SES, etc.) is configured

**In-app Notifications:**
- Custom notification system stored in `notifications` database table
- Managed via `ProfileController` endpoints: `GET /api/notifications`, `POST /api/notifications/read-all`, `PATCH /api/notifications/{id}/read`
- No push notifications or websocket broadcasting (broadcast connection is `log`)

## Queue & Background Jobs

**Queue Driver:** `database` (`QUEUE_CONNECTION=database`, jobs table via migration)
- Worker started in dev with `php artisan queue:listen --tries=1 --timeout=0`
- No external queue broker (Redis, SQS, Beanstalkd) configured

## Webhooks & Callbacks

**Incoming:** None
**Outgoing:** None

## Environment Configuration

**Required env vars for full functionality:**
- `APP_KEY` — generated on setup (`php artisan key:generate`)
- `APP_URL` — used by Laravel for link generation (e.g., password reset emails)
- `DB_CONNECTION=sqlite` — database driver
- `MAIL_MAILER` — set to `log` by default; change to `smtp` or a provider for real emails
- `VITE_APP_NAME` — exposed to frontend via Vite

**Configured but inactive (present in `.env.example`, not in active use):**
- `REDIS_*` — Redis not used; cache/queue use database driver
- `MEMCACHED_HOST` — not used
- `AWS_*` — S3 not used; filesystem is local
- `BROADCAST_CONNECTION=log` — no real-time broadcasting active

---

*Integration audit: 2026-03-26*
