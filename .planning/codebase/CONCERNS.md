# CONCERNS.md
# BookLoop — Code Review Concerns

Codebase reviewed: 2026-03-26
Reviewer: Claude Code (claude-sonnet-4-6)

Severity scale: HIGH | MEDIUM | LOW

---

## 1. SECURITY

### 1.1 [HIGH] Sanctum token stored in localStorage
File: resources/js/authStore.js, lines 4–10, 19
Sanctum bearer tokens are written to localStorage and read back on page load.
localStorage is accessible to any JavaScript running on the page, making the
token vulnerable to XSS theft. HttpOnly cookies are the standard mitigation for
SPA token storage.

### 1.2 [HIGH] /api/browse and /api/users/{user} are fully public with no rate limiting
File: routes/api.php, lines 12–13
These two routes sit outside the throttle middleware that guards auth endpoints.
A malicious actor can enumerate all books and all user profiles (name, join date,
book count, full library) without authentication and at unlimited speed.

### 1.3 [HIGH] Stale user data cached in localStorage after profile update
File: resources/js/authStore.js, lines 33–35; resources/js/components/Profile.vue, line 233
updateUser() writes the new user object to localStorage but the cached object
only contains id/name/email — not the server's full user model. If a token is
revoked server-side (e.g. password changed on another device), the client still
reads a "logged-in" state from localStorage until the next 401 is received.

### 1.4 [MEDIUM] No CSRF protection documented for SPA cookie flow
File: config/sanctum.php; resources/js/app.js
The app uses Bearer tokens (not cookies), so standard CSRF is not an issue here.
However, axios has no X-XSRF-TOKEN header setup (resources/js/bootstrap.js is
imported but never sets the header). If the project ever switches to cookie-based
auth this will silently break.

### 1.5 [MEDIUM] BookPolicy exists but is never used in controllers
File: app/Policies/BookPolicy.php; app/Http/Controllers/BookController.php, lines 67–69, 87–89
Authorization in BookController is done via manual id comparisons rather than
$this->authorize(). The BookPolicy is fully defined but completely unused,
creating a misleading false sense of policy-based authorization. viewAny, view,
and create all return false, which would break things if authorize() were ever
wired up.

### 1.6 [MEDIUM] Message body has no server-side throttle per conversation
File: routes/api.php, line 40; app/Http/Controllers/MessageController.php
POST /api/messages has no throttle middleware. An authenticated user can flood
another user's inbox with thousands of messages per minute. The general Sanctum
rate limit is not applied here.

### 1.7 [LOW] Password reset invalidation: existing Sanctum tokens not revoked on reset
File: app/Http/Controllers/PasswordResetController.php, lines 37–41
When a user resets their password, the handler calls forceFill + save but does
not call $user->tokens()->delete(). Any previously stolen Bearer tokens remain
valid after a password reset, defeating one of the main purposes of the feature.

---

## 2. DATA INTEGRITY / RACE CONDITIONS

### 2.1 [HIGH] Swap acceptance does not lock rows — TOCTOU race condition
File: app/Http/Controllers/SwapRequestController.php, lines 117–161
The accept() method checks swap->status === 'pending' and then opens a DB
transaction, but it does not use SELECT ... FOR UPDATE (lockForUpdate()) on the
swap_requests row. Two simultaneous accept requests for the same swap can both
pass the status check before either commits, resulting in double-acceptance.

### 2.2 [HIGH] SwapRequest store() checks book availability outside the transaction
File: app/Http/Controllers/SwapRequestController.php, lines 34–56
The status check for both books (lines 34–35) happens before the DB transaction
opens (line 47). Between the check and the transaction, another request could
mark either book as Pending. The transaction does not re-verify status, so two
overlapping swap requests can both succeed for the same book.

### 2.3 [MEDIUM] Conflicting swap cleanup on accept does not notify declined requesters
File: app/Http/Controllers/SwapRequestController.php, lines 135–155
When a swap is accepted, conflicting pending swaps are bulk-declined via
whereIn('id', ...)->update([...]), which skips Eloquent events and thus fires no
SwapDeclined notification to those requesters. They will only discover the
cancellation by polling.

### 2.4 [MEDIUM] ProfileController::show() swap count is inaccurate after ownership transfer
File: app/Http/Controllers/ProfileController.php, lines 16–21
The query counts accepted swaps where requester_id = user OR offeredBook.user_id
= user. After a swap, the offered book changes owner. The orWhereHas clause will
now match the new owner's id on books that were exchanged before they owned them,
inflating the swap count over time.

### 2.5 [LOW] SwapRequest status enum uses lowercase, Book status uses Title Case
File: database/migrations/2026_03_24_102913_create_swap_requests_table.php, line 19;
      database/migrations/2026_03_24_101056_create_books_table.php, lines 21–22
swap_requests.status values are 'pending'/'accepted'/'declined' (lowercase) while
books.status values are 'Available'/'Pending'/'Swapped' (Title Case). Frontend
code checks both formats in different places, increasing the chance of a missed
comparison (e.g. Dashboard.vue line 118 checks req.status === 'pending').

---

## 3. FRONTEND / UX

### 3.1 [HIGH] Messages.vue silently swallows all fetch errors
File: resources/js/components/Messages.vue, lines 155–158, 187–190
Both fetchConversations() and fetchThread() have empty catch blocks with only a
comment. If the API is down or returns an error, the user sees a blank panel or a
stale list with no feedback. This makes debugging in production very difficult.

### 3.2 [MEDIUM] Polling in Messages.vue is never cleared when navigating away mid-open
File: resources/js/components/Messages.vue, lines 165–172, 145–147
clearInterval(this.pollTimer) is called in beforeUnmount and at the start of
openConversation. However, if the component unmounts while a fetchThread() call
is in-flight, the async callback can still try to mutate this.messages and
this.conversations after unmount, causing potential Vue warnings or state leaks.

### 3.3 [MEDIUM] Dashboard.vue fetches all 5 endpoints in parallel on every swap accept/decline
File: resources/js/components/Dashboard.vue, lines 378–395
acceptSwap() and declineSwap() both call this.fetchAll() after the action, which
fires 5 parallel API requests (books, incoming, outgoing, notifications,
unread-count). For a simple swap action a targeted state update would be more
efficient and less likely to cause flicker.

### 3.4 [MEDIUM] Home.vue "Message owner" button is not translated
File: resources/js/components/Home.vue, line 127
The button label "Message owner" is a hardcoded English string while every other
button on the page uses t(). The same string in Messages.vue (lines 21, 25) is
also hardcoded. This breaks the LV locale for a visible UI element.

### 3.5 [MEDIUM] UserProfile.vue shows all books including "Swapped" status
File: app/Http/Controllers/UserController.php, line 14; resources/js/components/UserProfile.vue, line 68
The API filters out Pending books but returns Swapped ones. The frontend then
renders a status tag for Swapped books without filtering them, showing books the
user no longer owns alongside an active "Request Swap" button (which will be
rejected server-side, confusing the user).

### 3.6 [LOW] cancelSwap uses browser confirm() dialog
File: resources/js/components/Dashboard.vue, line 398
confirm() is a blocking browser dialog that cannot be styled, does not respect
the app's dark mode, and behaves differently across browsers (some mobile
browsers suppress it). The app already has a custom modal pattern used for book
deletion; the same approach should be used for swap cancellation.

### 3.7 [LOW] sendMessage uses alert() on error
File: resources/js/components/Messages.vue, line 222
alert() has the same drawbacks as confirm() above. Other parts of the app use
inline error state variables; the message send error should follow that pattern.

### 3.8 [LOW] Dashboard unreadNotifCount only counts the first page of notifications
File: resources/js/components/Dashboard.vue, lines 312
unreadNotifCount is calculated from notifs.data.data.filter(n => !n.read_at).length,
which only covers the first 15 notifications returned by the paginated endpoint.
Users with more than 15 unread notifications will see an undercount in the bell
badge.

---

## 4. ROUTING / NAVIGATION

### 4.1 [MEDIUM] Duplicate router file — resources/js/router/index.js is dead code
File: resources/js/router/index.js
This file defines a bare-bones router with only the home route. The app actually
imports from resources/js/router/router.js (app.js line 6). index.js is never
imported anywhere and will mislead future contributors.

### 4.2 [LOW] userId passed via query string leaks user identity in browser history
File: resources/js/components/Messages.vue, lines 138–142; Home.vue, line 291
Opening a conversation pushes ?userId=X&userName=Y into the URL. The name appears
in the browser history and can be read by any script that inspects
window.location. Route params or component state would be safer.

---

## 5. BACKEND / API DESIGN

### 5.1 [MEDIUM] /api/browse returns all Swapped books alongside Available ones
File: app/Http/Controllers/BookController.php, line 13
The browse query excludes only 'Pending' books (where status != 'Pending'). This
means Swapped books are returned in search results and shown on the home page
with a non-green status tag and a disabled swap button. These books should be
excluded (status = 'Available' only).

### 5.2 [MEDIUM] Book store() does not enforce a per-user book limit
File: app/Http/Controllers/BookController.php, lines 49–63
There is no cap on how many books a single user can add. Combined with the absent
message throttle (concern 1.6) this could be used to spam the public browse
listing.

### 5.3 [MEDIUM] ProfileController::history() uses the same broken swap count query as show()
File: app/Http/Controllers/ProfileController.php, lines 65–74
The history query uses orWhereHas('offeredBook', ...) — the same post-ownership-
transfer issue described in concern 2.4. After a swap the offered book now belongs
to the other party, so the original owner's history will show someone else's
accepted swaps.

### 5.4 [LOW] GET /api/profile/history is paginated but GET /api/notifications uses
      a different page size with no documented consistency
File: app/Http/Controllers/ProfileController.php, lines 72, 79
history() paginates at 10 items; notifications() paginates at 15. This is not
inherently wrong but is inconsistent and undocumented, making frontend pagination
logic harder to reason about.

### 5.5 [LOW] UserController::show() duplicates the swap count logic from ProfileController
File: app/Http/Controllers/UserController.php, lines 18–23;
      app/Http/Controllers/ProfileController.php, lines 16–21
The same three-clause SwapRequest query is written twice. If one is fixed (see
concern 2.4) the other will silently remain broken.

---

## 6. DATABASE / MIGRATIONS

### 6.1 [MEDIUM] books table has no index on user_id
File: database/migrations/2026_03_24_101056_create_books_table.php
foreignId() creates a foreign key constraint but not a query index in SQLite
(Laravel adds an index automatically on MySQL via foreignId, but not always on
SQLite migrations without explicit $table->index('user_id')). Queries like
$user->books()->get() will do a full table scan as the library grows.

### 6.2 [MEDIUM] swap_requests has no index on requester_id or status
File: database/migrations/2026_03_24_102913_create_swap_requests_table.php
The outgoing() and incoming() queries filter heavily on requester_id and status
but there are no explicit indexes for these columns. For a read-heavy browse
pattern this will degrade as the table grows.

### 6.3 [LOW] The 'bookloop' SQLite file in the project root is committed to the repo
File: bookloop (project root); .gitignore (not present / does not exclude it)
CLAUDE.md notes this as a "leftover artifact." A live SQLite database file with
potentially real user data should not be in version control. It should be added
to .gitignore immediately.

### 6.4 [LOW] migrations use cascadeOnDelete on swap_requests -> books
File: database/migrations/2026_03_24_102913_create_swap_requests_table.php, lines 17–18
If a book is force-deleted (e.g. by a future admin feature), all swap requests
referencing it cascade-delete silently. Given that the app currently prevents
deletion of Pending books, this is low risk now, but the behaviour should be
intentional (restrictOnDelete might be safer).

---

## 7. CODE QUALITY / MAINTAINABILITY

### 7.1 [MEDIUM] handleLogout is copy-pasted into Dashboard.vue, Messages.vue, and Profile.vue
File: resources/js/components/Dashboard.vue, lines 479–486;
      resources/js/components/Messages.vue, lines 239–246;
      resources/js/components/Profile.vue, lines 249–254
All three components contain identical async handleLogout() implementations.
This should be extracted into a composable or the shared langMixin/authStore.

### 7.2 [MEDIUM] sendSwapRequest logic is duplicated in Home.vue and UserProfile.vue
File: resources/js/components/Home.vue, lines 313–329;
      resources/js/components/UserProfile.vue, lines 172–188
Both components implement requestSwap(), closeSwapModal(), and sendSwapRequest()
identically. This is the same duplication the recent refactor comment in
homeLogic.js aimed to address but did not fully solve.

### 7.3 [LOW] resources/js/router/index.js is imported by nothing but still shipped
See concern 4.1. The file adds dead weight to the build and will confuse future
developers about which router is authoritative.

### 7.4 [LOW] themeStore.init() must be called manually from app.js
File: resources/js/app.js, line 10; resources/js/themeStore.js, lines 14–18
The dark-mode preference is only applied if app.js calls themeStore.init().
If a future developer creates a second entry point or lazy-loads the store in a
test, the init call may be missed and the theme will default to light regardless
of the user's saved preference.

### 7.5 [LOW] langMixin exposes langStore as data but it is rarely needed directly in templates
File: resources/js/langMixin.js, lines 5–7
Every component that uses langMixin gets langStore injected into its data, but
most only need the t() method and setLocale(). The reactive langStore reference
in data causes unnecessary re-renders when the locale changes even in components
that do not display the current locale.

---

## 8. TESTING GAPS

### 8.1 [MEDIUM] No tests for MessageController
File: tests/Feature/MessageTest.php (file exists but is not shown — contents unknown);
      app/Http/Controllers/MessageController.php
The message index query (MessageController::index) uses a raw CASE expression in
groupByRaw which is SQLite-compatible but fragile. There should be tests covering
the conversation list, the self-message guard, and the unread count.

### 8.2 [MEDIUM] No test for the conflicting-swap auto-decline on accept
File: tests/Feature/SwapRequestTest.php
The most complex business logic path — accepting a swap while other pending swaps
reference the same books — has no test. This is the code path most likely to
break silently (concern 2.3 is directly related).

### 8.3 [LOW] No test for password reset flow (sendLink / reset)
File: tests/Feature/ (no PasswordResetTest)
The password reset endpoints exist and have throttle middleware but are entirely
untested. The missing token-revocation issue (concern 1.7) would be caught by a
test that checks token validity after reset.

### 8.4 [LOW] BookTest::test_browse_returns_available_books does not assert Swapped books are excluded
File: tests/Feature/BookTest.php, lines 124–136
The test creates Available and Pending books and confirms only Available ones
appear. It does not create any Swapped books, so the bug described in concern 5.1
(Swapped books appearing in browse results) is not caught by the test suite.

---

## 9. INFRASTRUCTURE / CONFIGURATION

### 9.1 [HIGH] 'bookloop' SQLite artifact may contain real user data in version control
File: bookloop (root); .gitignore
The root-level 'bookloop' file is a SQLite database that should not be in the
repo (confirmed by CLAUDE.md). If .gitignore also fails to exclude .env (standard
Laravel .gitignore does exclude it, but should be verified), real credentials
could be exposed.

### 9.2 [MEDIUM] No queue worker failure handling for notifications
File: app/Notifications/NewMessage.php; app/Notifications/SwapAccepted.php; app/Notifications/SwapDeclined.php
All notifications use the 'database' channel and are dispatched synchronously
(via()), so they run inline in the request. If notification storage fails (e.g.
disk full, DB locked on SQLite), the parent action (accept/decline/message) will
throw a 500 rather than gracefully degrading. Wrapping notification dispatch in a
try/catch or moving to queued notifications would improve resilience.

### 9.3 [LOW] No .gitignore entry explicitly excluding the root 'bookloop' SQLite file
File: bookloop (root)
git status shows 'M bookloop' — the file is tracked and has uncommitted changes.
It should be removed from tracking (git rm --cached bookloop) and added to
.gitignore to prevent real data from being committed.
