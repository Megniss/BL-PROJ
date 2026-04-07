# Project Research Summary

**Project:** BookLoop — Ratings & Reviews Milestone
**Domain:** Post-exchange rating system on a brownfield Laravel 13 + Vue 3 SPA
**Researched:** 2026-03-26
**Confidence:** HIGH

## Executive Summary

BookLoop's ratings feature is a well-scoped, low-ambiguity milestone. The technical patterns are fully established (Laravel `withAvg`, Eloquent relationships, Options API Vue components) and the existing codebase provides clear conventions to follow. No new packages are needed. The entire feature is implementable as one migration, one controller, three query changes, one new Vue component, and additive template edits to three existing components.

The recommended approach is backend-first, strictly following the dependency chain: migration and model, then the rating controller with its authorization logic, then query changes to existing endpoints, then the frontend modal, then profile wiring, then browse display. The most dangerous work is the authorization logic in `RatingController::store` — the post-swap ownership transfer means `book->user_id` cannot be used to identify the receiver. Authorization must be anchored to the `swap_requests` record, and the receiver identity must be derived from the swap at rating time.

Two pre-existing bugs (REQ-005: Swapped books appearing in browse; REQ-006: silent errors in Messages.vue) must be fixed before or alongside ratings. REQ-005 directly breaks ratings display on the browse page. REQ-006 is an active requirement and the natural moment to extract the duplicated `handleLogout` logic. Deferring these creates a demo that looks broken even if the ratings code is correct.

---

## Key Findings

### Recommended Stack

No new packages. The full ratings feature is built with what is already in the project: Laravel 13 Eloquent (`withAvg`, `withCount`, `hasMany`, `hasOne`, `belongsTo`), Sanctum middleware, Axios, Bootstrap 5.3, and Vue 3 Options API. The star picker is 5 Unicode characters in a `v-for` — adding `vue-star-rating` or any similar package would be inconsistent with the zero-package-UI codebase and creates a bundle/style conflict surface for no gain.

**Core technologies:**
- Laravel Eloquent `withAvg` / `withCount` — aggregate rating data in browse and profile queries — available since Laravel 8, stable in Laravel 13, single subquery per request
- `unique(['swap_request_id', 'book_id'])` DB constraint — one rating per book per swap — exact expression of the business rule at the DB level
- Unicode `★` / `☆` in a Vue `v-for` — interactive star picker — zero dependencies, dark-mode safe, readable in code review
- `RatingController` (new, standalone) — rating creation + authorization — keeps SwapRequestController (183 lines, 5 actions) from growing further

**New API surface:**
- `POST /api/ratings` — create a rating (auth required)
- `GET /api/books/{book}/ratings` — paginated reviews (public)
- Three existing endpoints get additive query changes: `GET /api/browse`, `GET /api/users/{user}`, `GET /api/profile/history`

### Expected Features

**Must have (table stakes):**
- 1–5 integer star rating input — universal convention; half-stars add complexity for no demo value
- One rating per swap per book — unique constraint + controller guard
- Rating only by the receiver of that specific book — authorization anchored to swap record
- Rating only after swap status = 'accepted' — controller gate
- Average star rating on browse book cards — the visible payoff; `withAvg` query
- Average rating on user profile books — same query, scoped to user
- Rating visible immediately after submission — local state update in Vue after POST 201

**Should have (differentiators, add if time allows):**
- Optional text review alongside stars — `nullable text` column, display when present
- Review list on book detail / profile — context behind the score; low effort, high visual payoff
- Rating count alongside average — "4.2 (7 ratings)" is more trustworthy than "4.2"
- "Rated" badge on swap history rows — visual confirmation for the user

**Defer (out of scope for this milestone):**
- Half-star ratings, helpful votes, review moderation, spoiler tags, pagination of reviews, anonymous reviews, mood/pace tags

### Architecture Approach

The `ratings` table is the single new database artifact. It links `swap_request_id` + `book_id` (unique together) + `rater_id` + `stars` + optional `review`. The `book_id` is deliberately denormalized from the swap for query performance — it allows `AVG(stars) WHERE book_id = ?` without joining through `swap_requests`. The unique constraint is `(swap_request_id, book_id)`, not `(user_id, book_id)`, because the business rule is one rating per swap per book, not one rating per user per book.

**Major components:**
1. `ratings` migration + `Rating` model — the data foundation; defines all relationships
2. `RatingController::store` — authorization hub; derives receiver identity from the swap record at creation time
3. `RatingModal.vue` (new component) — star picker + optional review textarea; same modal pattern as `SwapModal.vue`
4. `Profile.vue` swap history section — rating entry point; shows rate/rated state per swap
5. `Home.vue` + `UserProfile.vue` book cards — rating display; additive template changes, data rides in existing responses

**Build order (strict dependency chain):**
Step 1: migration + models → Step 2: RatingController + routes → Step 3: query changes to browse/profile/history endpoints → Step 4: RatingModal.vue → Step 5: Profile.vue wiring → Step 6: browse card display

### Critical Pitfalls

1. **Authorization anchored to book ownership instead of swap record** — After accept(), `book->user_id` has already been rewritten. The receiver identity must be derived from `swap.requester_id` and `swap.wanted_book_id`/`offered_book_id` at rating time, not from current book ownership. An examiner will probe this directly.

2. **Wrong unique constraint allows rating the book you gave** — `unique(['user_id', 'book_id'])` without `swap_request_id` allows a user to rate any book they once touched. Use `unique(['swap_request_id', 'book_id'])` and additionally verify the user is the receiver in the controller.

3. **Swapped books visible in browse (REQ-005) undermines ratings display** — A rated book card with a disabled swap button looks like a broken feature. Fix `BookController` status filter to `= 'Available'` before or in the same phase as ratings display.

4. **Broken ProfileController history query propagates to rating eligibility** — The existing `orWhereHas('offeredBook', ...)` query is broken post-transfer (CONCERNS.md 2.4). Copying this pattern for "which swaps can I rate?" produces wrong eligibility results. Fix the underlying query when building rating entry in Profile.vue.

5. **`withAvg` vs `->with('ratings')` confusion** — `$book->ratings->avg('stars')` loads all rating rows into PHP memory (N+1 for a browse page). Always use `withAvg('ratings', 'stars')` on the query builder. Detection: grep for `->with('ratings')` on any browse or index query.

---

## Implications for Roadmap

### Phase 1: Fixes + Backend Foundation

**Rationale:** Two pre-existing bugs (REQ-005, REQ-006) are active requirements that interact with ratings. Fixing them first means the ratings feature ships onto a clean surface. The backend foundation (migration, models, controller) must exist before any frontend work is possible.

**Delivers:**
- REQ-005: Browse shows only Available books (`BookController` one-line fix)
- REQ-006: Messages.vue shows errors instead of silently failing
- `ratings` migration + `Rating` model with all relationships
- `RatingController::store` with full authorization logic
- `POST /api/ratings` route registered

**Addresses:** Table stakes #1–4 (creation gate, receiver-only, one-per-swap, status check)

**Avoids:** Pitfalls 1, 2, 3, 4 — all authorization and schema problems are solved here before any frontend code can depend on them

**Research flag:** None — these are well-documented Laravel patterns. No phase research needed.

---

### Phase 2: Backend Exposure + Frontend Rating Entry

**Rationale:** Once the controller exists and is tested, extend existing endpoints to carry rating data, then build the Vue rating entry flow. Profile.vue is the primary user-facing entry point. The broken history query must be fixed as part of this phase.

**Delivers:**
- `withAvg` / `withCount` added to browse and user profile queries
- `with('ratings')` added to ProfileController history query (with the broken ownership query fixed)
- `RatingModal.vue` component (star picker + review textarea, Options API, no packages)
- `Profile.vue` wiring: shows "Rate" button or submitted stars per accepted swap
- Both EN and LV translation keys added for new strings

**Addresses:** Table stakes #5, 6, 7 (average display, immediate feedback); differentiator: optional review text; differentiator: "Rated" badge

**Avoids:** Pitfalls 5 (withAvg), 6 (history query), 8 (CSS star hack), 9 (fetchAll cascade), 11 (missing LV translations)

**Research flag:** None — Options API modal pattern matches SwapModal.vue exactly. Standard patterns apply.

---

### Phase 3: Browse + Profile Display Polish

**Rationale:** Adding star display to book cards in `Home.vue` and `UserProfile.vue` is the final visible payoff — the feature now completes the full loop from earning a rating to seeing it surface on the book. This phase is purely additive template changes.

**Delivers:**
- Star display on book cards in `Home.vue` (average + count, null-guarded)
- Star display on book cards in `UserProfile.vue`
- `handleLogout` extraction to `authStore.js` (code quality cleanup, natural here since we are already touching multiple components)
- SQLite `PRAGMA foreign_keys = ON` in `AppServiceProvider::boot()` if not already present

**Addresses:** Table stakes #5, 6 (average visible on browse and user profile); differentiator: rating count alongside average

**Avoids:** Pitfalls 4 (SQLite FK pragma), 10 (duplicate handleLogout), 12 (null avg display)

**Research flag:** None — purely additive template and config changes.

---

### Phase Ordering Rationale

- Bugs must precede ratings display: REQ-005 breaks browse, REQ-006 is a required fix — neither can be deferred
- Backend must precede frontend: the Vue components depend on API responses that include rating data
- Authorization must be correct before UI exists: once the modal is built it will be used; getting the auth logic wrong after this point is a rewrite
- Browse display is last because it is purely additive and depends on query changes from Phase 2 already being in place

### Research Flags

Phases with standard patterns (skip research-phase):
- **Phase 1:** Laravel migration + controller patterns are fully documented; direct codebase inspection was sufficient
- **Phase 2:** Vue Options API modal pattern matches existing code exactly; no novel patterns
- **Phase 3:** Additive template changes; no architectural decisions remaining

No phases require `/gsd:research-phase` — all patterns are resolved and documented in the research files.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Direct codebase inspection; zero ambiguity on package decisions |
| Features | HIGH | Requirements from PROJECT.md are authoritative; table stakes derived from clear UX conventions |
| Architecture | HIGH | Direct inspection of SwapRequestController accept logic, existing migrations, and controller patterns |
| Pitfalls | HIGH | Most pitfalls identified from direct code reading (CONCERNS.md, SwapRequestController, ProfileController); SQLite FK pragma is documented behavior |

**Overall confidence:** HIGH

### Gaps to Address

- **"Who received what" identity derivation:** The logic for determining which user received which book after a swap is the most implementation-sensitive piece. STACK.md and ARCHITECTURE.md both describe the correct approach, but this needs careful integration testing. Detection: write a test where both participants try to rate and verify each can only rate the book they received.

- **`ratings_avg_stars` null guard:** All display code must handle `null` (no ratings) vs a numeric value. Display nothing or a "no ratings" indicator — never display 0 filled stars. This is a minor implementation detail but visually obvious during a demo.

- **Broken ProfileController history query (CONCERNS.md 2.4, 5.3):** The fix for this query is not fully specified in the research — PITFALLS.md notes it is broken but the correct replacement query requires knowing whether an `owner_id` column will be added to `swap_requests` or whether the fix accepts requester-perspective-only history. This needs a decision before Phase 2 planning.

---

## Sources

### Primary (HIGH confidence)
- Direct inspection: `app/Http/Controllers/SwapRequestController.php` — accept logic, ownership transfer
- Direct inspection: `app/Http/Controllers/BookController.php`, `ProfileController.php`, `UserController.php`
- Direct inspection: `database/migrations/2026_03_24_102913_create_swap_requests_table.php`
- Direct inspection: `app/Models/SwapRequest.php`, `app/Models/Book.php`
- Direct inspection: `resources/js/components/Dashboard.vue`, `Profile.vue`, `Home.vue`
- Direct inspection: `.planning/CONCERNS.md` — pre-existing bugs and code quality issues
- Direct inspection: `.planning/PROJECT.md` — authoritative requirements (REQ-001 through REQ-006)
- Laravel 13 Eloquent `withAvg` / `withCount` — standard aggregate methods, available since Laravel 8

### Secondary (MEDIUM confidence)
- Training knowledge: Goodreads, LibraryThing, StoryGraph feature sets — platforms are stable as of knowledge cutoff
- SQLite `PRAGMA foreign_keys` behavior — documented SQLite specification

---
*Research completed: 2026-03-26*
*Ready for roadmap: yes*
