# BookLoop — Architecture Overview

## Summary

BookLoop is a Laravel SPA (Single Page Application). Laravel serves a single Blade view that mounts a Vue 3 frontend. All page navigation is handled client-side by Vue Router. The backend exposes a JSON REST API consumed by the frontend via Axios.

---

## Request Flow

```
Browser
  └── GET any URL
        └── Laravel web.php catch-all route
              └── resources/views/welcome.blade.php
                    └── Vue 3 app mounts on #app
                          └── Vue Router handles client-side routing
                                └── Components call Laravel API (/api/...)
                                      └── Laravel API routes (routes/api.php)
                                            └── Controllers → Models → SQLite DB
```

---

## Backend (Laravel)

### Routing

- `routes/web.php` — single catch-all route returns the `welcome` Blade view for every URL, letting Vue Router take over client-side.
- `routes/api.php` — all REST API endpoints, prefixed `/api/` by default.

### Authentication

Laravel Sanctum is used for token-based auth. On login/register, a personal access token is issued. Protected routes use `middleware('auth:sanctum')`. An Axios interceptor on the frontend automatically redirects to `/login` on any 401 response.

### Controllers

| Controller | Responsibility |
|---|---|
| `AuthController` | Register, login, logout, current user (`/me`) |
| `BookController` | CRUD for the auth user's books; public browse with search/filter/sort |
| `ProfileController` | View/update profile, swap history, notifications |
| `SwapRequestController` | Create/accept/decline/cancel swap requests; handles book ownership transfer atomically |
| `MessageController` | Conversation list, message thread view, send message, unread count |
| `UserController` | Public user profile view |
| `PasswordResetController` | Forgot-password email link and reset |

### Models & Relationships

```
User
 ├── hasMany Books
 ├── hasMany SwapRequests (as requester)
 ├── hasMany Messages (as sender / recipient)
 └── Notifiable (SwapAccepted, SwapDeclined, NewMessage)

Book
 └── belongsTo User
     Fields: title, author, genre, language, condition, status, description
     Status values: Available | Pending | Swapped

SwapRequest
 ├── belongsTo User (requester)
 ├── belongsTo Book (offeredBook)
 └── belongsTo Book (wantedBook)
     Status values: pending | accepted | declined
     Dismiss flags: requester_dismissed, owner_dismissed

Message
 ├── belongsTo User (sender — from_user_id)
 └── belongsTo User (recipient — to_user_id)
     Fields: body, read_at
```

### Swap Logic

When a swap request is created, both books are atomically set to `Pending`. On accept, book ownership is transferred between users (both books swap `user_id`) and any other pending swap requests involving those books are auto-declined. On cancel/decline, both books revert to `Available`.

### Notifications

Database-stored notifications (Laravel's built-in notification system):
- `SwapAccepted` — sent to requester when their swap is accepted
- `SwapDeclined` — sent to requester when their swap is declined
- `NewMessage` — sent to recipient on first unread message in a conversation thread

### Policies

- `BookPolicy` — authorizes book operations to the owning user

---

## Frontend (Vue 3)

### Entry Point

`resources/js/app.js` — imports Bootstrap CSS/JS, creates the Vue app, registers Vue Router, sets up the Axios 401 interceptor, initializes the theme store, and mounts to `#app`.

### Routing (`resources/js/router/router.js`)

Navigation guards enforce auth:
- `meta: { requiresAuth: true }` — redirects unauthenticated users to `/login`
- `meta: { guestOnly: true }` — redirects logged-in users to `/dashboard`

Dashboard, Profile, Messages, and UserProfile are lazy-loaded (code-split).

| Route | Component | Access |
|---|---|---|
| `/` | Home.vue | Public |
| `/about` | About.vue | Public |
| `/login` | Login.vue | Guest only |
| `/register` | Register.vue | Guest only |
| `/forgot-password` | ForgotPassword.vue | Guest only |
| `/reset-password` | ResetPassword.vue | Guest only |
| `/dashboard` | Dashboard.vue | Auth required |
| `/profile` | Profile.vue | Auth required |
| `/messages` | Messages.vue | Auth required |
| `/users/:id` | UserProfile.vue | Public |
| `/*` | NotFound.vue | Public |

### State Management

No Vuex/Pinia. State is managed via lightweight reactive stores:

- **`authStore.js`** — reactive object holding `user` and `token`. Persisted to `localStorage` (`bookloop_user`, `bookloop_token`). Exports `setAuth`, `clearAuth`, `updateUser`, `isLoggedIn`. Sets `axios.defaults.headers.common['Authorization']` on init and login.
- **`themeStore.js`** — reactive `dark` flag. Toggles `data-theme` attribute on `<html>`. Persisted to `localStorage` (`theme`). Initialized at app startup.
- **`langStore.js`** + **`translations.js`** — reactive `locale` (`en` | `lv`). Persisted to `localStorage` (`bookloop_locale`). `t(key)` function looks up translation strings with English fallback.
- **`langMixin.js`** — Vue mixin exposing `t()` and `setLocale()` methods to components.

### Shared Utilities

- **`coverColor.js`** — deterministic gradient color for book cover display, based on `book.id % 6`.
- **`homeLogic.js`** — extracted logic for the Home page (browse/search).

### Components

| Component | Purpose |
|---|---|
| `App.vue` | Root — just a `<router-view>` wrapper |
| `AppNavbar.vue` | Shared nav bar with logo, dark mode toggle, EN/LV switcher |
| `Home.vue` | Public landing page with hero, book browse, search/filter |
| `About.vue` | Static about page |
| `Login.vue` | Login form |
| `Register.vue` | Registration form |
| `ForgotPassword.vue` | Password reset request form |
| `ResetPassword.vue` | Password reset form (with token) |
| `Dashboard.vue` | Authenticated home: user's books, incoming/outgoing swap requests, notifications |
| `Profile.vue` | Edit profile, swap history |
| `Messages.vue` | Conversation list + message thread view |
| `UserProfile.vue` | Public view of another user's library; initiate swap from here |
| `SwapModal.vue` | Modal for selecting which book to offer in a swap request |
| `NotFound.vue` | 404 page |

---

## Build & Tooling

- **Vite** with `laravel-vite-plugin` and `@vitejs/plugin-vue`
- Entry points: `resources/js/app.js`, `resources/css/app.css`
- **Bootstrap 5** for UI (imported via JS in `app.js`)
- **Axios** for HTTP requests
- **PHP**: Laravel 11+, Laravel Sanctum, Laravel Pint (linting)
- **Database**: SQLite
