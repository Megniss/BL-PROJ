# Architecture: Rating/Review Integration

**Project:** BookLoop
**Dimension:** Rating model — data relationships, API design, UI integration
**Researched:** 2026-03-26
**Confidence:** HIGH — based on direct codebase inspection

---

## The Core Constraint

A swap involves two books changing hands simultaneously. When a swap is accepted:

- The requester loses `offered_book` and gains `wanted_book`
- The book owner loses `wanted_book` and gains `offered_book`

Both parties received a book, so either could theoretically rate. The product requirement narrows this to **the receiver of a specific book** rates **that specific book**, tied to **that specific swap**. This means one `SwapRequest` can produce up to two ratings — one per book exchanged — but each rating is locked to a single (swap, book, rater) triple.

---

## Rating Model

### Fields

```
ratings
  id               — primary key
  swap_request_id  — FK → swap_requests.id
  book_id          — FK → books.id  (the book that was received)
  rater_id         — FK → users.id  (the user who received and is rating it)
  stars            — tinyint, 1–5, not null
  review           — text, nullable
  created_at
  updated_at
```

### Unique constraint

```sql
UNIQUE (swap_request_id, book_id)
```

This enforces one rating per book per swap. Adding `rater_id` to the unique constraint would also work but is redundant — the same swap+book combination can only ever have one legitimate rater (the receiver), and that is enforced at the controller level anyway.

### Why `book_id` rather than just `swap_request_id`

A single swap produces two book transfers. Tying only to `swap_request_id` would be ambiguous about which book was rated. Storing `book_id` makes each rating unambiguous and allows querying "all ratings for book X" directly without joining through swap_requests.

### Why `rater_id` is stored explicitly

Book ownership transfers on swap acceptance — `books.user_id` changes. Querying "who received this book via this swap" after the fact would require reconstructing historical state from the swap record. Storing `rater_id` at creation time is simpler and safer.

---

## Relationships

### Who rates which book via which swap

At the time a swap is accepted (inside `SwapRequestController::accept`):

```
requester  received  swap.wanted_book   (wanted_book_id → now owned by requester)
owner      received  swap.offered_book  (offered_book_id → now owned by owner)
```

So the valid ratings that can be created from a swap are:

| rater_id          | book_id               |
|-------------------|-----------------------|
| swap.requester_id | swap.wanted_book_id   |
| swap.wantedBook.user_id (original owner) | swap.offered_book_id  |

The controller must verify that the authenticated user is one of these two before allowing a rating POST.

### Model relationships to add

**Rating model:**
```php
Rating
  belongsTo SwapRequest
  belongsTo Book (the rated book)
  belongsTo User (rater, via rater_id)
```

**Book model (add):**
```php
Book
  hasMany Ratings
```

**User model (add):**
```php
User
  hasMany Ratings (as rater, via rater_id)
```

**SwapRequest model (add):**
```php
SwapRequest
  hasMany Ratings  (up to 2 per swap)
```

---

## API Endpoints

### Minimal set — 3 endpoints

#### POST /api/ratings
Auth required. Submit a new rating.

**Request body:**
```json
{
  "swap_request_id": 42,
  "book_id": 17,
  "stars": 4,
  "review": "Great condition, exactly as described."
}
```

**Authorization logic (in controller):**
1. Load the swap. Verify `status === 'accepted'`.
2. Verify the authenticated user is either the requester (if `book_id === swap.wanted_book_id`) or the original owner (if `book_id === swap.offered_book_id`).
3. Check no rating exists yet for `(swap_request_id, book_id)`.

**Responses:**
- `201` with the new rating object
- `403` if user is not the receiver of that book in that swap
- `422` if swap is not accepted, book_id does not match the swap, or duplicate rating

#### GET /api/books/{book}/ratings
Public (no auth required). Returns paginated ratings for a book, newest first.

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "stars": 4,
      "review": "Good read.",
      "created_at": "...",
      "rater": { "id": 3, "name": "Anna" }
    }
  ],
  "average": 3.8,
  "total": 12,
  "next_page_url": "..."
}
```

Including `average` and `total` in this response avoids a second request when loading a book's rating detail view.

#### GET /api/books/{book}/rating-summary
Public. Returns just the average and count — used to populate book cards without loading full review text.

**Response:**
```json
{ "average": 3.8, "count": 12 }
```

This endpoint is called in bulk contexts (browse page loads 12 books at once). A lightweight summary endpoint avoids the overhead of paginated review data on each card.

**Alternative approach — embed in browse response:** The `BookController::browse` response could include `avg_rating` and `rating_count` via a `withAvg`/`withCount` eager load. This is actually preferable for the browse page since it avoids N+1 HTTP calls. See UI integration notes below.

---

## Data Flow for Rating Submission

```
User (requester) navigates to Profile.vue → Swap History
  └── Sees accepted swap entry
        └── Book they received has no rating yet → "Rate this book" button shown
              └── Clicks → RatingModal opens
                    └── POST /api/ratings
                          └── RatingController::store
                                ├── Validate swap is accepted
                                ├── Validate caller is the receiver of that book
                                ├── Validate no duplicate
                                └── Create Rating → 201 response
                                      └── Profile.vue updates swap entry to show submitted stars
```

---

## UI Integration Points

### 1. `Home.vue` — Browse page book cards

**What changes:** Each book card (lines 109–131 in `Home.vue`) currently shows title, author, owner, language, and status tags. Add a star display line between the owner line and the tag row.

**How to supply the data:** Modify `BookController::browse` to eager-load rating stats:

```php
$query->withAvg('ratings', 'stars')->withCount('ratings');
```

This adds `ratings_avg_stars` and `ratings_count` to each book object in the paginated response. No extra HTTP request needed — the data rides along with the existing browse response.

**Display:** Show filled/empty star characters (or a simple "★ 3.8" string) and the count in parentheses. If `ratings_count === 0`, show "No ratings yet" or nothing.

**Affected file:** `resources/js/components/Home.vue` — template only, no new data fetching logic needed.

### 2. `UserProfile.vue` — Public user library page

**What changes:** `UserProfile.vue` shows another user's book library (same book card structure as Home.vue). Apply the same star display as above.

**How to supply the data:** `UserController::show` returns the user's books. Add the same `withAvg`/`withCount` eager load there.

**Affected file:** `resources/js/components/UserProfile.vue` — template change only.

### 3. `Profile.vue` — Swap history (rating entry point)

**What changes:** The swap history list (lines 48–71 in `Profile.vue`) currently shows each swap with book titles and a "Completed" badge. This is where the user submits ratings.

For each accepted swap, determine which book the current user received:
- If `swap.requester.id === currentUser.id` → received `swap.wanted_book`
- Otherwise → received `swap.offered_book`

Then check if a rating exists for `(swap.id, that_book.id)`. The `history` API response needs to include ratings — add a `ratings` relationship to the paginated history query in `ProfileController::history`.

Show:
- If rated: display submitted stars (read-only)
- If not rated: show "Rate this book" button → opens `RatingModal`

**Affected files:**
- `resources/js/components/Profile.vue` — template + new `ratingModal` data state
- `app/Http/Controllers/ProfileController.php` — add `.with('ratings')` to the history query

### 4. New component: `RatingModal.vue`

A simple modal (same pattern as `SwapModal.vue`) with:
- Star picker (1–5, implemented as 5 clickable star buttons)
- Optional textarea for review text
- Submit/Cancel buttons
- Error display on failure

Props: `open`, `swapId`, `bookId`, `bookTitle`
Emits: `close`, `submitted` (with the new rating object)

---

## Build Order

Dependencies flow strictly in this order. Each step is a prerequisite for the next.

### Step 1 — Migration + Model (backend foundation)
Create the `ratings` table migration. Create `Rating` model with fillable fields and relationships. Add `hasMany Ratings` to `Book` and `User` models. No other code can proceed without this.

### Step 2 — RatingController + routes (backend API)
Implement `RatingController` with `store` (POST) and `indexForBook` (GET with summary). Register routes in `api.php`. At this point the API is testable independently of the frontend.

### Step 3 — Embed rating stats in browse + user profile responses (backend)
Add `withAvg`/`withCount` to `BookController::browse` and `UserController::show`. Add `.with('ratings')` to `ProfileController::history`. These are additive changes — existing responses gain new fields, nothing breaks.

### Step 4 — RatingModal.vue (frontend component)
Build the modal component in isolation. Can be verified standalone before wiring into Profile.

### Step 5 — Profile.vue rating entry (frontend)
Wire `RatingModal` into the swap history list. Show rate/rated state based on whether a rating exists for the received book in each swap.

### Step 6 — Star display on book cards (frontend)
Add the star/average display to book cards in `Home.vue` and `UserProfile.vue`. These are purely additive template changes — they just render data that is now available in the API response.

---

## What Must NOT Change

- `SwapRequestController::accept` — ownership transfer logic is correct and complete. Ratings are created independently after the fact; there is no reason to touch the swap acceptance transaction.
- `BookController::browse` filter for `status !== 'Pending'` — the bug fix (excluding `Swapped` books) is a separate concern from ratings, addressed in REQ-005.
- The auth pattern — `RatingController` follows the existing `auth:sanctum` middleware pattern, no changes to auth infrastructure.

---

## Unresolved Questions

- **Star display rendering:** Whether to use Unicode star characters, an SVG sprite, or a small Bootstrap Icons dependency. This is a UI decision with no architectural impact; the data model is agnostic.
- **Rating edit/delete:** Not in requirements. The unique constraint and controller logic treat ratings as immutable. If edit support is added later, it requires a `PUT /api/ratings/{rating}` endpoint and additional ownership checks.
- **Average precision:** Whether to round to 1 decimal place or display as integer. Cosmetic — no schema impact.
