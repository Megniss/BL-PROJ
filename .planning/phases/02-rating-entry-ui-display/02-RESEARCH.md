# Phase 02: Rating Entry UI + Display - Research

**Researched:** 2026-03-27
**Domain:** Vue 3 Options API component authoring, i18n (custom translations.js), Laravel API response consumption
**Confidence:** HIGH — all findings drawn directly from the actual codebase files

## Summary

Phase 2 is a pure frontend phase. All backend work (RatingController, ratings migration, withAvg/withCount on browse and user-profile queries, ratings relation on history) was completed in Phase 1. The frontend only needs to consume data that is already in the API responses and post to a single existing endpoint.

The codebase uses a hand-rolled i18n system (no vue-i18n plugin): `translations.js` holds flat key→string maps for `en` and `lv`, exposed via `langStore.js`/`langMixin.js` using `t(key)` in templates. All new UI strings must be added to both locale objects in `translations.js`. No plugin registration is involved.

Component conventions are uniform throughout the codebase: Options API (`export default { name, components, mixins, props, emits, data(), computed, mounted, methods }`), `<Teleport to="body">` for modals, Bootstrap utility classes for layout, custom CSS classes (`btn-swap`, `tag`, `tag-green`, `modal-overlay`, `modal-card`) for visual styling. `SwapModal.vue` is the definitive pattern for `RatingModal.vue`.

**Primary recommendation:** Build RatingModal.vue as a near-copy of SwapModal.vue (same props/emits contract shape, same Teleport+overlay+modal-card structure), wire it into Profile.vue swap rows using the receiver-derivation logic already established in RatingController, and drop the star average display inline in both book-card templates.

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| REQ-001 (frontend) | Star input (1–5) in swap history entry for received books on Profile.vue | RatingModal pattern documented; receiver-derivation logic confirmed from RatingController and swap record fields |
| REQ-002 (frontend) | Optional review textarea (max 500 chars) alongside star input | Modal form pattern from SwapModal; max length validation is frontend-only (backend accepts 1000) |
| REQ-003 | Average rating visible on book cards in Home.vue and UserProfile.vue | `ratings_avg_stars` and `ratings_count` confirmed present in browse and user-library API responses |
| REQ-004 (frontend gate) | Rate button disappears after submission; rated state shown as read-only stars | Local state update pattern documented; history response includes ratings relation for initial state |
</phase_requirements>

## Project Constraints (from CLAUDE.md)

- **Framework:** Laravel SPA — Vue 3 mounted inside a single Blade view
- **Frontend entry:** `resources/js/app.js` → `components/App.vue` → Vue Router
- **Routing:** All routes in `resources/js/router/router.js`; Laravel has one catch-all web route
- **CSS:** Tailwind CSS v4 via `@tailwindcss/vite`; Bootstrap also imported in `app.js`
- **DB:** SQLite (`database/database.sqlite`)
- **Run tests:** `composer run test` or `php artisan test --filter TestName`
- **No new packages for star widget** (locked decision from STATE.md): implement with ~10 lines of Options API + Unicode stars

---

## Standard Stack

### Core (already installed — no new installs needed)

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Vue 3 | (project default) | Component framework | Already in use everywhere |
| axios | (project default) | HTTP client | Used in all components via `import axios from 'axios'` |
| Bootstrap | (project default) | Layout + utility classes | Already imported globally in `app.js` |
| translations.js (custom) | n/a | EN/LV i18n | All components use `langMixin` + `t(key)` |

### No New Packages

The locked decision from Phase 1 planning explicitly says: "No new packages for star widget — ~10 lines of Options API + Unicode." This is the only interactive widget needed for this phase. No vue-star-rating, no vue-i18n, no additional dependencies.

**Installation:** None required.

---

## Architecture Patterns

### Recommended Project Structure additions

```
resources/js/
├── components/
│   ├── RatingModal.vue      ← new (mirrors SwapModal.vue structure)
│   ├── Profile.vue          ← modified (add rating wiring to swap rows)
│   ├── Home.vue             ← modified (add star display to book cards)
│   └── UserProfile.vue      ← modified (add star display to book cards)
└── translations.js          ← modified (add rating keys to both en and lv)
```

### Pattern 1: Modal structure — follow SwapModal.vue exactly

SwapModal.vue is the reference pattern. Key characteristics:

```vue
<!-- SwapModal.vue structure — RatingModal.vue must match this -->
<template>
  <Teleport to="body">
    <div v-if="open" class="modal-overlay" @click.self="$emit('close')">
      <div class="modal-card">
        <!-- content -->
        <div class="d-flex justify-content-end gap-2">
          <button class="btn btn-outline-secondary" @click="$emit('close')">Cancel</button>
          <button class="btn btn-primary" :disabled="!canSubmit || sending" @click="$emit('send')">
            {{ sending ? 'Submitting…' : 'Submit Rating' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script>
export default {
  name: 'RatingModal',
  props: {
    open:    { type: Boolean, required: true },
    book:    { type: Object,  default: null },
    sending: { type: Boolean, default: false },
    error:   { type: String,  default: '' },
  },
  emits: ['close', 'send', 'update:stars', 'update:review'],
  // stars and review kept as v-model props or internal data — see notes
}
</script>
```

**Decision on modal state ownership:** SwapModal keeps `selectedBookId` in the parent (Profile.vue controls it). The same approach works for RatingModal — parent owns `stars` and `review` so it can read them on submit. Alternatively, since RatingModal is simpler (no list to pick from), stars+review can live inside RatingModal as internal `data()` and be emitted on send. Either works; keeping them internal is simpler.

### Pattern 2: Triggering a modal from a parent (Profile.vue pattern)

Profile.vue already uses this pattern for the edit modal:

```javascript
// Parent data shape for the rating modal
ratingModal: {
  open:        false,
  swapId:      null,   // swap_request_id to send to API
  bookTitle:   '',     // display only
  stars:       0,
  review:      '',
  sending:     false,
  error:       '',
},
```

Open it:
```javascript
openRatingModal(swap) {
  this.ratingModal.swapId     = swap.id
  this.ratingModal.bookTitle  = this.receivedBook(swap).title
  this.ratingModal.stars      = 0
  this.ratingModal.review     = ''
  this.ratingModal.error      = ''
  this.ratingModal.open       = true
},
```

### Pattern 3: Receiver derivation — which book did the current user receive?

This is the critical logic. From `RatingController::store()` (confirmed in codebase):

```
if userId === swap.requester_id  → received swap.wanted_book
if userId === swap.owner_id      → received swap.offered_book
```

Frontend equivalent (Profile.vue method):

```javascript
receivedBook(swap) {
  if (swap.requester.id === this.authStore.user.id) {
    return swap.wanted_book   // requester wanted this book and received it
  } else {
    return swap.offered_book  // owner received the offered book from requester
  }
},
```

This is already implicitly used in the existing Profile.vue swap history template (lines 53–57) for the "you gave / you received" display text — the new rating logic uses the exact same conditional.

### Pattern 4: Already-rated detection from history API response

`ProfileController::history()` already eager-loads `ratings` on each swap:

```php
->with(['requester:id,name', 'offeredBook', 'wantedBook', 'ratings'])
```

Each swap object in the history array has a `ratings` array. To check if the current user has already rated the received book:

```javascript
hasRated(swap) {
  if (!swap.ratings || swap.ratings.length === 0) return false
  const bookId = this.receivedBook(swap)?.id
  return swap.ratings.some(r => r.book_id === bookId)
},
```

The existing rating for display (star count) can be retrieved from the same array:

```javascript
existingRating(swap) {
  const bookId = this.receivedBook(swap)?.id
  return swap.ratings.find(r => r.book_id === bookId) ?? null
},
```

### Pattern 5: Local state update after submit (no page reload)

After a successful `POST /api/ratings`, the response is the created `Rating` object (`{ id, swap_request_id, book_id, rater_id, stars, review, ... }`). Push it into the swap's `ratings` array in place:

```javascript
async submitRating() {
  this.ratingModal.sending = true
  try {
    const { data } = await axios.post('/api/ratings', {
      swap_request_id: this.ratingModal.swapId,
      stars:           this.ratingModal.stars,
      review:          this.ratingModal.review || null,
    })
    // Local update — find the swap and push the new rating
    const swap = this.history.find(s => s.id === this.ratingModal.swapId)
    if (swap) swap.ratings.push(data)
    this.ratingModal.open = false
  } catch (err) {
    this.ratingModal.error = err.response?.data?.message || 'Something went wrong.'
  } finally {
    this.ratingModal.sending = false
  }
},
```

Because Vue reactivity tracks array mutations, the template re-renders immediately and `hasRated(swap)` returns true — the Rate button is replaced by read-only stars without a page reload.

### Pattern 6: Star picker — Unicode, no package

```vue
<template>
  <!-- Star picker (read-write) -->
  <div class="star-picker mb-3">
    <span
      v-for="n in 5"
      :key="n"
      class="star"
      :class="{ active: n <= stars }"
      @click="$emit('update:stars', n)"
      style="cursor:pointer; font-size:1.5rem"
    >★</span>
  </div>
</template>
```

Or built directly into RatingModal with internal `data() { return { stars: 0, review: '' } }` — simpler since there's no need to share star state with a third component.

Read-only stars display (for already-rated rows and book cards):

```vue
<!-- Inline in swap row or book card -->
<span v-for="n in 5" :key="n" style="color: #f5a623">
  {{ n <= rating.stars ? '★' : '☆' }}
</span>
```

### Pattern 7: Book card rating display (Home.vue and UserProfile.vue)

Both browse and user-library API responses already include `ratings_avg_stars` and `ratings_count` per book (confirmed in `BookController::browse` and `UserController::show`).

Laravel's `withAvg` produces a field named `ratings_avg_stars` (convention: `{relation}_avg_{column}`). When no ratings exist the value is `null`, not `0`.

Display snippet (additive — goes inside the existing `.card-body` before the buttons):

```vue
<div class="book-rating mb-2" style="font-size: 0.85rem; color: #888">
  <template v-if="book.ratings_count > 0">
    ★ {{ Number(book.ratings_avg_stars).toFixed(1) }} ({{ book.ratings_count }})
  </template>
  <template v-else>
    <span class="text-muted">{{ t('books.noRatings') }}</span>
  </template>
</div>
```

In UserProfile.vue, `langMixin` is NOT currently mixed in — the component does not use `t()`. Two options:
1. Add `langMixin` mixin (requires import + mixins array entry) for full i18n support.
2. Hard-code the "No ratings" placeholder inline in English only (simpler, but inconsistent with the rest of the app).

Option 1 is consistent with the project's approach. UserProfile.vue uses `import AppNavbar` already, so adding one more import is trivial.

### Anti-Patterns to Avoid

- **Do not use vue-i18n `$t()`** — the project uses a custom `t(key)` from `langMixin`. There is no vue-i18n plugin registered in `app.js`.
- **Do not mutate the full history array to update rated state** — find the specific swap and push to its `.ratings` array. Replacing the whole history array triggers unnecessary re-renders.
- **Do not rely on `book.user_id` to determine receiver** — this was explicitly documented as broken (books.user_id changes on swap accept). Use `swap.requester_id` and `swap.owner_id`.
- **Do not show a "0.0 (0)" placeholder** — `ratings_avg_stars` is `null` when no ratings exist, and `Number(null).toFixed(1)` produces `"0.0"`. Gate on `ratings_count > 0`.
- **Do not use `<style scoped>` unless other components do** — check component conventions. SwapModal and Profile.vue have no `<style>` block at all; styles live in the global CSS. Follow the same pattern unless minimal scoped styles are needed.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| i18n | Custom translation lookup | `t(key)` from langMixin | Already exists; adding to translations.js is all that's needed |
| Modal overlay/backdrop | Custom CSS overlay | `.modal-overlay` + `.modal-card` CSS classes | Already defined globally; SwapModal uses them |
| HTTP client | fetch() | axios (already imported in every component) | CSRF + interceptors already configured |
| Star picker | External package | 5 Unicode ★ chars + v-for | Locked decision; ~10 lines, no new dependency |
| Average calculation | Frontend average | Use `ratings_avg_stars` from API | Already computed server-side via `withAvg` |

---

## API Reference (confirmed from codebase)

### POST /api/ratings
**Auth:** Required (Sanctum)

Request body:
```json
{
  "swap_request_id": 42,
  "stars": 4,
  "review": "Great condition!"  // optional, nullable, max 1000 chars (backend); frontend should limit to 500 per REQ-002
}
```

Response 201:
```json
{
  "id": 7,
  "swap_request_id": 42,
  "book_id": 15,
  "rater_id": 3,
  "stars": 4,
  "review": "Great condition!",
  "created_at": "...",
  "updated_at": "..."
}
```

Error responses:
- 422: swap not accepted, or already rated
- 403: current user is not a participant in the swap

### GET /api/profile/history (paginated, 10 per page)

Each item in `data[]`:
```json
{
  "id": 42,
  "requester_id": 3,
  "owner_id": 5,
  "offered_book_id": 10,
  "wanted_book_id": 15,
  "status": "accepted",
  "updated_at": "...",
  "requester": { "id": 3, "name": "Alice" },
  "offered_book": { "id": 10, "title": "Moby Dick", "author": "Melville", ... },
  "wanted_book":  { "id": 15, "title": "1984", "author": "Orwell", ... },
  "ratings": [
    { "id": 7, "swap_request_id": 42, "book_id": 15, "rater_id": 3, "stars": 4, "review": "..." }
  ]
}
```

Note: `owner_id` is present on the swap record (added in Phase 1, plan 01-02). `ratings` is an array (may be empty). The current user's `id` is available from `authStore.user.id`.

### GET /api/browse (paginated, 12 per page)

Each book in `data[]`:
```json
{
  "id": 15,
  "title": "1984",
  "author": "Orwell",
  "genre": "Klasika",
  "language": "English",
  "status": "Available",
  "ratings_avg_stars": 4.2,    // null if no ratings
  "ratings_count": 5,
  "user": { "id": 5, "name": "Bob" }
}
```

### GET /api/users/{id}

`library` array — each book:
```json
{
  "id": 10,
  "title": "Moby Dick",
  "status": "Available",
  "ratings_avg_stars": null,
  "ratings_count": 0,
  ...
}
```

---

## i18n — New Keys Required

All new strings must be added to **both** `en` and `lv` objects in `resources/js/translations.js`. No plugin call needed — just add the key-value pairs.

### Required new keys

| Key | EN value | LV value |
|-----|---------|---------|
| `profile.rate` | `Rate` | `Novērtēt` |
| `profile.rated` | `Rated` | `Novērtēts` |
| `profile.ratingTitle` | `Rate this book` | `Novērtē šo grāmatu` |
| `profile.ratingStars` | `Your rating` | `Tavs vērtējums` |
| `profile.ratingReview` | `Review (optional)` | `Atsauksme (nav obligāta)` |
| `profile.ratingPlaceholder` | `Write a short review…` | `Uzraksti īsu atsauksmi…` |
| `profile.ratingSubmit` | `Submit` | `Iesniegt` |
| `profile.ratingSubmitting` | `Submitting…` | `Iesniedz…` |
| `books.noRatings` | `No ratings yet` | `Nav vērtējumu` |

The `t()` function falls back to the English value if an LV key is missing, but both must be present per the phase success criteria.

---

## Common Pitfalls

### Pitfall 1: ratings_avg_stars is null, not 0
**What goes wrong:** `{{ book.ratings_avg_stars }}` renders blank; `Number(null).toFixed(1)` renders `"0.0"` — both confusing to users.
**Why it happens:** Laravel's `withAvg` returns `null` when the relation has no records, not `0`.
**How to avoid:** Always gate on `ratings_count > 0` before rendering the average. Show a "No ratings yet" placeholder otherwise.
**Warning signs:** Book cards show "★ 0.0 (0)" in the UI.

### Pitfall 2: Receiver derivation error
**What goes wrong:** Rate button appears for the wrong book (the one the user gave away, not received); user rates their own book.
**Why it happens:** Using `book.user_id` instead of `swap.requester_id` / `swap.owner_id`.
**How to avoid:** Use the confirmed logic: if `swap.requester.id === authStore.user.id`, the received book is `swap.wanted_book`; otherwise it's `swap.offered_book`.
**Warning signs:** After submitting, the API returns 403 ("You are not a participant") or 422 from the wrong book_id.

### Pitfall 3: Reactivity not triggered after local state update
**What goes wrong:** After submit, the Rate button still shows (UI doesn't update).
**Why it happens:** Assigning a new array to `swap.ratings` instead of pushing into it, or mutating a property Vue isn't tracking.
**How to avoid:** Use `swap.ratings.push(data)` where `swap` is a reference to the object inside `this.history`. Vue 3 tracks array mutations. Do not do `swap.ratings = [...swap.ratings, data]` with a found reference — that also works, but only if `swap` is the actual array element reference (which `Array.find()` returns).

### Pitfall 4: UserProfile.vue does not use langMixin
**What goes wrong:** Calling `t('books.noRatings')` in UserProfile.vue throws "t is not a function".
**Why it happens:** UserProfile.vue doesn't currently include `langMixin` — it was written without needing any translated strings.
**How to avoid:** Add `import langMixin from '../langMixin.js'` and `mixins: [langMixin]` to UserProfile.vue when adding the rating display. Alternative: hard-code the placeholder string, but this is inconsistent.

### Pitfall 5: Modal opened before swap.ratings array is defined
**What goes wrong:** `swap.ratings.some(...)` throws on a swap that returned without the ratings relation.
**Why it happens:** If history is loaded from a future paginated page via `loadMoreHistory`, those items come from the same paginated endpoint which includes `ratings` — so this should not be an issue. But defensive coding with `swap.ratings?.some(...)` or `(swap.ratings || []).some(...)` prevents edge cases.
**How to avoid:** Use optional chaining: `swap.ratings?.some(r => r.book_id === bookId) ?? false`.

---

## Code Examples

### Full receiver-derivation computed helper (verified against RatingController logic)

```javascript
// In Profile.vue methods
receivedBook(swap) {
  // Requester sent offered_book, wanted wanted_book → received wanted_book
  // Owner received offered_book from requester
  if (swap.requester.id === this.authStore.user.id) {
    return swap.wanted_book
  }
  return swap.offered_book
},

hasRated(swap) {
  const book = this.receivedBook(swap)
  return swap.ratings?.some(r => r.book_id === book?.id) ?? false
},

existingStars(swap) {
  const book = this.receivedBook(swap)
  const rating = swap.ratings?.find(r => r.book_id === book?.id)
  return rating?.stars ?? null
},
```

### Swap row template fragment (Profile.vue)

```vue
<div v-for="swap in history" :key="swap.id" class="card border">
  <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2 p-3">
    <div>
      <p class="mb-1 small">
        <!-- existing youGave/andReceived text unchanged -->
      </p>
      <p class="text-muted mb-0" style="font-size:12px">{{ formatDate(swap.updated_at) }}</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <!-- Already rated: show read-only stars -->
      <template v-if="hasRated(swap)">
        <span style="color:#f5a623">
          <span v-for="n in 5" :key="n">{{ n <= existingStars(swap) ? '★' : '☆' }}</span>
        </span>
      </template>
      <!-- Not yet rated: show Rate button -->
      <button v-else class="btn btn-sm btn-outline-primary" @click="openRatingModal(swap)">
        {{ t('profile.rate') }}
      </button>
      <span class="tag tag-green">{{ t('profile.completed') }}</span>
    </div>
  </div>
</div>
```

### Book card rating display snippet (Home.vue and UserProfile.vue)

```vue
<!-- Insert inside .card-body, above the mt-auto action buttons -->
<div class="mb-2" style="font-size:0.8rem; color:#888">
  <template v-if="book.ratings_count > 0">
    <span style="color:#f5a623">★</span>
    {{ Number(book.ratings_avg_stars).toFixed(1) }}
    <span class="text-muted">({{ book.ratings_count }})</span>
  </template>
  <span v-else class="text-muted">{{ t('books.noRatings') }}</span>
</div>
```

Note: Home.vue already uses `langMixin` (via `mixins: [homeMethods, langMixin]`). UserProfile.vue does not — add the mixin there.

---

## Environment Availability

Step 2.6: SKIPPED — this phase is purely frontend component authoring. No external services, CLI tools, or runtimes beyond Node.js (already running) and the existing Laravel dev server are required.

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| No ratings on API | `ratings_avg_stars` + `ratings_count` on browse and user library | Phase 1 complete | Ready to consume — no backend changes needed in Phase 2 |
| No ratings on history | `ratings` relation eager-loaded on history items | Phase 1 complete | Ready for rate/rated state detection |
| No RatingController | `POST /api/ratings` live with full auth | Phase 1 complete | Frontend just needs to POST |

---

## Open Questions

1. **Review field max length mismatch**
   - Backend accepts up to 1000 chars (`'review' => ['nullable', 'string', 'max:1000']`)
   - REQ-002 says max 500 chars
   - Resolution: enforce 500 on the frontend textarea (`maxlength="500"`); backend won't reject anything under 1000. No backend change needed.

2. **`owner_id` vs `requester.id` for determining receiver**
   - `swap.owner_id` (integer) is the raw ID in the swap record
   - `swap.requester` is the eager-loaded object `{ id, name }` — available from the history relation
   - `swap.owner` is NOT eager-loaded in `ProfileController::history()` (only `requester:id,name` is loaded)
   - Resolution: the template already uses `swap.requester.id` for the "you gave / received" text. The rating logic must use `swap.requester.id === authStore.user.id` (not `swap.owner_id`) since owner is not eager-loaded. Both refer to the same person — this is consistent.

---

## Sources

### Primary (HIGH confidence)
- `app/Http/Controllers/RatingController.php` — confirmed API contract, receiver derivation logic, response shape
- `app/Http/Controllers/ProfileController.php` — confirmed history query, eager-loads `ratings` relation
- `app/Http/Controllers/BookController.php` — confirmed `withAvg('ratings', 'stars')` and `withCount('ratings')` on browse
- `app/Http/Controllers/UserController.php` — confirmed same withAvg/withCount on user library
- `app/Models/SwapRequest.php` — confirmed `ratings()` HasMany, `requester()`, `owner()`, `offeredBook()`, `wantedBook()` relations
- `resources/js/components/SwapModal.vue` — reference pattern for RatingModal
- `resources/js/components/Profile.vue` — current swap history structure, modal patterns, langMixin usage
- `resources/js/components/Home.vue` — book card structure, langMixin usage
- `resources/js/components/UserProfile.vue` — book card structure, no langMixin currently
- `resources/js/translations.js` — full EN/LV key inventory, confirmed custom i18n (no vue-i18n)
- `resources/js/langMixin.js` — confirmed `t(key)` method, `setLocale(locale)` method
- `.planning/STATE.md` — locked decision: no new packages for star widget

### Secondary (MEDIUM confidence — none required for this phase)

---

## Metadata

**Confidence breakdown:**
- API response shapes: HIGH — read directly from controller source
- Receiver derivation logic: HIGH — read directly from RatingController
- Component patterns: HIGH — read directly from SwapModal.vue, Profile.vue, Home.vue, UserProfile.vue
- i18n system: HIGH — read translations.js, langMixin.js, langStore.js fully
- Required new translation keys: MEDIUM — keys proposed by research; exact LV phrasing should be reviewed by the developer

**Research date:** 2026-03-27
**Valid until:** Stable — all findings from the local codebase, not external APIs. Valid until code changes.
