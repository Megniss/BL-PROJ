# Conventions

Coding conventions for the BookLoop project. This covers both the Laravel backend and the Vue 3 frontend.

---

## PHP / Laravel

### General style
- Follow PSR-12. Run Laravel Pint before committing: `./vendor/bin/pint`
- One blank line between methods inside a class
- No blank line after the opening brace of a class or method
- Short closures (`fn`) are fine for simple callbacks

### Controllers
- Thin controllers — no business logic beyond validation, authorization, and delegation to models
- Authorization is done inline with a simple `if` check and an early `return response()->json([...], 403)` — no separate Policy classes currently
- Always validate with `$request->validate([...])` using array syntax for rules: `['required', 'string', 'max:255']`
- Return `response()->json($data)` for success, `response()->json(['message' => '...'], $code)` for errors
- Use `201` for created resources, `422` for validation/business rule errors, `403` for authorization failures

### Models
- Use PHP 8 attributes for `#[Fillable([...])]` and `#[Hidden([...])]` instead of `$fillable`/`$hidden` properties
- Define relationships as typed methods returning `HasMany`, `BelongsTo`, etc.
- Keep models thin — no computed logic beyond relationships and casts

### Routes (routes/api.php)
- Group authenticated routes under `Route::middleware('auth:sanctum')->group(...)`
- Apply `throttle:` middleware on public-facing auth endpoints (register, login, forgot-password)
- Name specific sub-routes before wildcard routes (e.g. `/messages/unread-count` before `/messages/{user}`) — add a comment explaining why if it is not obvious
- Use resourceful HTTP verbs: `GET` list/show, `POST` create, `PUT`/`PATCH` update, `DELETE` destroy

### Database
- SQLite for local development (`database/database.sqlite`)
- Migration filenames use the format `YYYY_MM_DD_HHMMSS_description.php`
- Factories live in `database/factories/` — always use `fake()` helpers and set sensible defaults
- Enum-like string columns (status, condition, etc.) use Title Case values: `Available`, `Pending`, `Swapped`, `New`, `Good`, `Fair`, `Worn`

---

## JavaScript / Vue

### Component structure
- Options API (`export default { name, components, mixins, data, computed, mounted, beforeUnmount, methods }`)
- Component name matches filename (PascalCase): `Dashboard.vue` exports `name: 'Dashboard'`
- Template first, then `<script>`, no `<style>` block (styles live in `resources/css/`)
- No `<script setup>` — stick with Options API for consistency

### State management
- Global auth state lives in `resources/js/authStore.js` as a `reactive()` object
- Exported helpers: `setAuth`, `clearAuth`, `updateUser`, `isLoggedIn`
- No Vuex/Pinia — keep state local to components unless it needs to be shared (auth is the only shared state)

### API calls
- Use `axios` directly — no wrapper service
- All API calls go to `/api/...`
- Use `async/await` with `try/catch` in methods
- On error: read `err.response?.data?.message` or `err.response?.data?.errors` for user-facing messages
- Loading and error states are tracked as `data()` properties (`loading`, `fetchError`, `actionError`, `formError`, `saving`)

### i18n
- All user-visible strings go through the `t('key')` helper (from `langMixin`)
- Add the mixin with `mixins: [langMixin]`
- Translation keys follow dot notation: `'dash.title'`, `'modal.cancel'`, `'notif.empty'`
- Add new keys to both `lv` and `en` locales in `langStore.js`

### Routing (resources/js/router/router.js)
- Add all routes in `router.js`
- Eager-load public/lightweight components; lazy-load authenticated pages with `() => import(...)`
- Use `meta: { requiresAuth: true }` for protected routes, `meta: { guestOnly: true }` for auth-only pages
- Navigate programmatically with `this.$router.push({ name: 'routeName' })`

### CSS
- Tailwind CSS v4 via `@tailwindcss/vite`
- Bootstrap is also available for utility classes (`btn`, `form-control`, `d-flex`, etc.)
- Custom CSS goes in `resources/css/app.css`
- Prefer Bootstrap utility classes; use custom classes when Bootstrap does not cover the case
- Custom class names use kebab-case: `notif-wrap`, `dash-card-title`, `modal-overlay`

---

## Git

- Commit messages in Latvian are fine (this is a school project)
- Commits are small and focused
- Branch: all work on `main`
