# Technology Stack — Ratings & Reviews Milestone

**Project:** BookLoop
**Scope:** Adding 1-5 star rating + optional review text to an existing Laravel 13 + Vue 3 SPA
**Researched:** 2026-03-26
**Overall confidence:** HIGH (based on direct codebase inspection + established Laravel/Vue patterns)

---

## What Already Exists (Do Not Re-Implement)

| Layer | Already present |
|-------|----------------|
| Auth | Laravel Sanctum, Bearer tokens in localStorage, `auth:sanctum` middleware |
| HTTP client | Axios with global Authorization header set in `authStore.js` |
| State | Reactive stores (no Pinia/Vuex) — `authStore.js`, `themeStore.js`, `langStore.js` |
| UI | Bootstrap 5.3, custom CSS variables, `langMixin.js` for translations |
| Patterns | Options API throughout, direct `axios.get/post/patch/delete` calls in component methods |

---

## New Additions Required

### No New Packages Needed

Do not install any star-rating Vue package (e.g., `vue-star-rating`, `vue3-star-ratings`). Reasons:

1. A 1-5 star widget is 10 lines of template + a click handler. A package adds a dependency, bundle weight, and a style conflict surface area against Bootstrap.
2. The existing codebase has zero UI component packages — everything is hand-rolled Bootstrap markup. Adding one package for a trivial widget would be inconsistent and create a maintenance burden.
3. Unicode star characters (`★` / `☆`) rendered in a `v-for` loop over `[1,2,3,4,5]` are sufficient and match the existing emoji-heavy UI style (the codebase already uses `📚`, `🔔`, `⏳` inline).

**Verdict: build the star widget inline, no packages.**

---

## Migration Design

### New table: `ratings`

```php
Schema::create('ratings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('swap_request_id')->constrained('swap_requests')->cascadeOnDelete();
    $table->foreignId('rater_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
    $table->unsignedTinyInteger('stars');         // 1-5
    $table->text('review')->nullable();
    $table->timestamps();

    // one rating per swap, enforced at DB level
    $table->unique('swap_request_id');
});
```

**Why anchor on `swap_request_id` not `(rater_id, book_id)`:**

A `(rater_id, book_id)` unique constraint fails the business rule. If user A swaps book X away, then somehow gets book X again via a second swap, they should be allowed to rate that second exchange. The rule is "one rating per completed swap", not "one rating per book per user". The `swap_request_id` unique constraint is the exact expression of that rule.

**Why store `book_id` alongside `swap_request_id`:**

`book_id` is denormalized for query performance. Fetching "average stars for a book" requires either: (a) joining `ratings → swap_requests → books`, or (b) a direct `WHERE book_id = ?`. Option (b) is simpler and faster. The value is derivable from the swap, so it is not normalisation-breaking — it is a deliberate read optimisation.

**Why `cascadeOnDelete` on `swap_request_id`:**

If a swap is deleted (which can happen via the dismiss/cancel flow), the rating should disappear with it. Orphaned ratings on deleted swaps have no meaningful display context.

### No schema changes to existing tables

Do not add `average_rating` to the `books` table. Computing the average via `AVG()` on the `ratings` table is correct and avoids a stale-cache problem. SQLite handles `AVG()` on a small dataset (school project scale) with zero performance concern.

---

## Backend: New Controller

**`RatingController`** — single responsibility: create a rating.

```
POST /api/ratings
middleware: auth:sanctum
body: { swap_request_id, stars, review? }
```

**Authorization logic (all in the controller, no separate Policy needed for this scale):**

1. Load the `SwapRequest` — 404 if not found.
2. Verify `$swap->status === 'accepted'` — 422 if not.
3. Determine who received which book:
   - The requester received `wanted_book_id`
   - The book owner (original `wantedBook->user_id` before the swap) received `offered_book_id`
   - After the swap, ownership has transferred — so "who received what" must be inferred from the swap record, not current `user_id` on the book.
4. Verify `$request->user()->id` is one of the two swap participants — 403 if not.
5. Verify no rating exists for this `swap_request_id` yet — 422 if already rated.
6. Determine `book_id`: the book the authenticated user *received* (not gave).
7. Create the rating.

**Why a dedicated `RatingController` rather than adding to `SwapRequestController`:**

`SwapRequestController` is already 183 lines handling five distinct swap lifecycle actions. Ratings are a separate concern triggered after the lifecycle ends. Keeping them separate follows the existing pattern where each concern has its own controller.

---

## "Who Received What" — Key Logic

After a swap is accepted, book ownership transfers. The `swap_requests` table preserves the original intent:

- `requester_id` — the user who initiated the swap (they wanted `wanted_book_id`, they gave `offered_book_id`)
- After accept: requester now owns what was `wanted_book_id`, owner now owns what was `offered_book_id`

So when creating a rating:
- If `auth user == swap.requester_id` → they received `swap.wanted_book_id` → `book_id = swap.wanted_book_id`
- If `auth user == original owner` → they received `swap.offered_book_id` → `book_id = swap.offered_book_id`

The original owner is recoverable because before the swap `wantedBook.user_id` was the owner. After the swap that has changed. Use the swap's `wantedBook` relationship to get the book record, then check the *current* `user_id` — which after the swap is the requester. Therefore: original owner = whoever is NOT the requester and IS a participant.

Simpler approach: load both books with their current owners. One of them has `user_id == requester_id` (the book requester now owns). The other's `user_id` is the original owner. This avoids storing pre-swap user IDs.

---

## Backend: Model

```php
// app/Models/Rating.php
#[Fillable(['swap_request_id', 'rater_id', 'book_id', 'stars', 'review'])]
class Rating extends Model
{
    public function swapRequest(): BelongsTo { ... }
    public function rater(): BelongsTo { ... }
    public function book(): BelongsTo { ... }
}
```

Add to `Book` model:

```php
public function ratings(): HasMany
{
    return $this->hasMany(Rating::class);
}
```

---

## Backend: Exposing Average Rating

Add average star data to two existing API responses:

**1. `GET /api/browse` (BookController::browse)**

Add `withAvg('ratings', 'stars')` to the query. Laravel's `withAvg` produces a `ratings_avg_stars` attribute on each book. No new endpoint needed.

**2. `GET /api/users/{user}` (UserController::show)**

Same treatment — add `withAvg('ratings', 'stars')` to the books relationship load.

**3. `GET /api/profile/history` (ProfileController::history)**

For the swap history view where the "rate this swap" button appears — include whether a rating exists for each swap. Add `with('rating')` to the history query (once the `SwapRequest` model has `hasOne Rating` defined).

Add to `SwapRequest` model:

```php
public function rating(): HasOne
{
    return $this->hasOne(Rating::class, 'swap_request_id');
}
```

---

## Frontend: Star Widget

Build inline in whichever component needs it. No extraction to a separate component file is needed for this feature scope — the widget is small enough to inline in the modal/card. If it appears in more than two places, extract to `StarRating.vue`.

Pattern (Options API, matching existing codebase):

```javascript
// in data()
hoverStar: 0,
selectedStar: 0,

// in methods
setHover(n) { this.hoverStar = n; },
clearHover() { this.hoverStar = 0; },
selectStar(n) { this.selectedStar = n; },
```

```html
<!-- template — uses Unicode stars, no package -->
<span
  v-for="n in 5"
  :key="n"
  @mouseover="setHover(n)"
  @mouseleave="clearHover"
  @click="selectStar(n)"
  style="cursor:pointer; font-size:1.4rem"
>
  {{ (hoverStar || selectedStar) >= n ? '★' : '☆' }}
</span>
```

This renders correctly in all browsers, supports hover preview, requires no CSS beyond font-size, and is readable by teachers reviewing the code.

---

## Frontend: Where the Rating UI Lives

**Rate a swap: inside Profile.vue's swap history section.**

The history list already exists and shows each completed swap. Add a "Rate" button (or inline star widget) on swaps where `status === 'accepted'` and `rating === null`. Submits to `POST /api/ratings` via axios.

Do not create a separate `/rate` route. The history section is the natural context — users review past swaps and rate from there. A dedicated page would require navigation away from context users already have.

**Display average on book cards: inline in the card template.**

Book cards appear in `Home.vue` (browse), `UserProfile.vue`, and `Dashboard.vue`. Add a small star display line:

```html
<span v-if="book.ratings_avg_stars" class="text-warning small">
  {{ '★'.repeat(Math.round(book.ratings_avg_stars)) }}
  <span class="text-muted">({{ Number(book.ratings_avg_stars).toFixed(1) }})</span>
</span>
```

Display only if `ratings_avg_stars` is non-null (i.e., at least one rating exists).

---

## Alternatives Considered

| Option | Rejected Because |
|--------|-----------------|
| `overtrue/laravel-follow` or other rating packages | Overkill; adds dependency for a feature implementable in one migration + one controller |
| `spatie/laravel-rating` | Does not exist as a maintained package; hand-rolling is the correct choice at this scale |
| Store `average_rating` on `books` table | Creates stale-data risk; SQLite AVG on < 1000 rows is instant; denormalization adds sync complexity |
| Separate `/rate/:swapId` route + page | Extra navigation step with no benefit; history section already has the context |
| `vue-star-rating` npm package | Unnecessary dependency; 5 Unicode characters in a v-for are sufficient |
| Pinia store for rating state | Overkill; ratings are submitted once and never need cross-component reactivity |
| Allow rating the book you gave | Contradicts the "rate what you received" semantics; the giver's experience is irrelevant to the book's quality |

---

## API Surface Summary

| Method | Route | Auth | Purpose |
|--------|-------|------|---------|
| POST | `/api/ratings` | Required | Submit a rating for a completed swap |

Existing routes that need query changes (no new routes):

| Route | Change |
|-------|--------|
| GET `/api/browse` | Add `withAvg('ratings', 'stars')` |
| GET `/api/users/{user}` | Add `withAvg('ratings', 'stars')` to books |
| GET `/api/profile/history` | Add `with('rating')` to swap query |

---

## Validation Rules (RatingController)

```php
$request->validate([
    'swap_request_id' => ['required', 'integer', 'exists:swap_requests,id'],
    'stars'           => ['required', 'integer', 'min:1', 'max:5'],
    'review'          => ['nullable', 'string', 'max:1000'],
]);
```

`max:1000` on review matches the existing `description` field on books, keeping conventions consistent.

---

## Confidence Assessment

| Area | Confidence | Basis |
|------|------------|-------|
| Migration design | HIGH | Direct inspection of existing migrations; `unique('swap_request_id')` constraint is exact expression of the business rule |
| Controller pattern | HIGH | Matches existing controller code style; same validation/response conventions |
| No packages needed | HIGH | Widget complexity vs. dependency cost is clear-cut |
| `withAvg` approach | HIGH | Standard Laravel Eloquent aggregate; available since Laravel 8, stable in Laravel 13 |
| Frontend widget pattern | HIGH | Matches Options API + Bootstrap conventions throughout the codebase |
| "Who received what" logic | MEDIUM | Requires careful implementation — the post-swap ownership transfer means book.user_id no longer reflects original owner; logic described above is correct but needs integration testing |

---

## Sources

- Direct inspection: `database/migrations/2026_03_24_102913_create_swap_requests_table.php`
- Direct inspection: `app/Models/SwapRequest.php`, `app/Models/Book.php`
- Direct inspection: `app/Http/Controllers/SwapRequestController.php` (accept logic, ownership transfer)
- Direct inspection: `app/Http/Controllers/BookController.php` (browse query pattern)
- Direct inspection: `routes/api.php` (route conventions)
- Direct inspection: `resources/js/components/Dashboard.vue`, `Profile.vue` (Options API patterns)
- Laravel 13 Eloquent `withAvg` — standard aggregate relationship method, available since Laravel 8
