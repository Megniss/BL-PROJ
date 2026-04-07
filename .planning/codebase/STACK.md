# Technology Stack

**Analysis Date:** 2026-03-26

## Languages

**Primary:**
- PHP 8.3+ (required by `composer.json`; runtime detected as PHP 8.5.4) — all backend logic
- JavaScript (ES Modules, `"type": "module"` in `package.json`) — all frontend logic

**Secondary:**
- CSS — custom styles in `resources/css/app.css` (no preprocessor; plain CSS with custom properties)

## Runtime

**Environment:**
- PHP 8.3+ (minimum declared in `composer.json`; actual runtime 8.5.4)
- Node.js v23.7.0 (detected on dev machine)

**Package Manager:**
- Composer (PHP) — lockfile: `composer.lock` (present)
- npm (JS) — lockfile: `package-lock.json` (present)

## Frameworks

**Core:**
- Laravel 13.x (`laravel/framework: ^13.0`) — HTTP routing, Eloquent ORM, queues, mail, sessions
- Vue 3.5.x (`vue: ^3.5.30`) — SPA frontend, component model
- Vue Router 5.0.x (`vue-router: ^5.0.4`) — client-side routing via `createWebHistory()`

**Auth:**
- Laravel Sanctum 4.3.x (`laravel/sanctum: ^4.3`) — token-based API auth (Bearer tokens stored in `localStorage`)

**Build/Dev:**
- Vite 8.0.x (`vite: ^8.0.0`) — asset bundling and dev server
- `laravel-vite-plugin` 3.0.x — integrates Vite into Laravel's asset pipeline
- `@vitejs/plugin-vue` 6.0.x — Vue SFC support in Vite
- `concurrently` 9.0.x — runs Laravel, Vite, queue worker, and log watcher in parallel during dev

**CSS:**
- Bootstrap 5.3.x (`bootstrap: ^5.3.8`) — utility classes and component base
- Custom CSS variables layered on top of Bootstrap via `resources/css/app.css`
- Note: CLAUDE.md mentions Tailwind v4 was planned but Bootstrap is what is actually installed and used.

**Testing:**
- PHPUnit 12.5.x (`phpunit/phpunit: ^12.5.12`) — PHP unit/feature tests

**Dev Utilities:**
- Laravel Pint 1.27.x (`laravel/pint: ^1.27`) — PHP code style fixer (runs via `./vendor/bin/pint`)
- Laravel Pail 1.2.x (`laravel/pail: ^1.2.5`) — real-time log streaming in terminal
- Faker 1.23.x (`fakerphp/faker: ^1.23`) — test data generation
- Mockery 1.6.x (`mockery/mockery: ^1.6`) — PHP mocking library
- Collision 8.6.x (`nunomaduro/collision: ^8.6`) — improved CLI error output for tests
- Laravel Tinker 3.0.x (`laravel/tinker: ^3.0`) — REPL for Artisan

## Key Dependencies

**Critical:**
- `laravel/sanctum` — all authenticated API routes use `auth:sanctum` middleware; tokens are Bearer tokens managed in `resources/js/authStore.js`
- `axios` 1.11.x — all frontend HTTP calls to the Laravel API; Authorization header is set globally from `authStore.js`
- `vue-router` — entire navigation model depends on this; routes defined in `resources/js/router/router.js`
- `bootstrap` — UI components and layout throughout all Vue components

**Infrastructure:**
- Laravel queue system (database driver) — used for background jobs; started via `php artisan queue:listen` in `composer run dev`
- Laravel password reset (built-in `Password` facade) — used by `PasswordResetController`

## Configuration

**Environment:**
- Configured via `.env` (copied from `.env.example` on setup)
- Key variables: `APP_KEY`, `APP_URL`, `DB_CONNECTION`, `SESSION_DRIVER`, `QUEUE_CONNECTION`, `MAIL_MAILER`, `VITE_APP_NAME`
- Database defaults to SQLite (`DB_CONNECTION=sqlite`), file at `database/database.sqlite`
- Session, cache, and queue all default to `database` driver

**Build:**
- `vite.config.js` — entry points are `resources/js/app.js` and `resources/css/app.css`; hot reload enabled (`refresh: true`)
- Output lands in `public/build/` (standard Laravel Vite convention)

## Platform Requirements

**Development:**
- PHP 8.3+
- Node.js (v23 used in dev; no `.nvmrc` or `.node-version` pinning)
- Composer
- npm

**Production:**
- Any PHP 8.3+ host with SQLite support
- No special services required beyond the PHP process (queue worker needed for background jobs)
- Static assets must be built with `npm run build` before deployment

---

*Stack analysis: 2026-03-26*
