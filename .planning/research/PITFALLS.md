# Domain Pitfalls

**Domain:** Rating/review system on a Laravel + Vue 3 SPA book exchange app
**Project:** BookLoop (brownfield — milestone addition)
**Researched:** 2026-03-26
**Scope:** Specific to adding ratings to the existing BookLoop codebase

---

## Critical Pitfalls

Mistakes that cause rewrites, broken business rules, or grade-losing bugs.

---

### Pitfall 1: Rating Authorization Anchored to the Wrong Identity

**What goes wrong:** The most natural mistake is writing the rating ownership check as "does this user own this book?" — but after a swap, book ownership has already changed. The receiver now owns the book. Checking `book->user_id === auth()->id()` will pass for the *new owner* for future ratings but also for anyone who happens to own the book by other means. The correct check is: "did this user receive this specific book via this specific swap?"

**Why it happens:** Book ownership is the intuitive proxy for "I have this book," but `swap_requests` is the actual record of the transaction. The receiver of a swap is not stored directly — you have to derive it:
- The `requester` gets `wanted_book`
- The original book owner (wantedBook's previous `user_id` at accept time) gets `offered_book`

After accept(), `book->user_id` has already been rewritten (SwapRequestController line 131–132). The pre-swap identity is gone from the books table.

**Consequences:** Either anyone who owns a book can rate it (too permissive), or legitimate receivers get blocked (too restrictive). The "one rating per swap" constraint becomes impossible to enforce correctly without anchoring to the swap record.

**Prevention:** Anchor authorization to `swap_requests`, not `books`. The ratings table must store `swap_request_id`. The controller check must verify: swap exists, swap status = 'accepted', and the authenticated user is either `requester_id` (they received `wanted_book`) or was the original owner of `wanted_book` (they received `offered_book`). The second identity is the hard one — derive it from the swap at rating time, not from current book ownership.

**Detection:** Write a test where User A and User B swap books, then User C (who later acquires the same book via another swap) tries to rate — if they can, the check is wrong.

**Phase:** Must be solved in the ratings backend phase, before any frontend work.

**Severity:** CRITICAL for a school project. Broken authorization is the first thing an examiner will probe.

---

### Pitfall 2: Allowing Ratings on Both Books in a Swap from Both Parties

**What goes wrong:** A swap involves two books changing hands. If the schema allows each user to rate any book involved in the swap, you end up with up to 4 ratings per swap (user A rates book A, user A rates book B, user B rates book A, user B rates book B). The business rule is: each party rates the book *they received*, once.

**Why it happens:** A ratings table with just `(user_id, book_id)` unique constraint seems correct but doesn't prevent a user from rating a book they sent rather than one they received.

**Consequences:** Inflated rating counts, users gaming their own books' scores, rating data that doesn't mean "I read/received this book."

**Prevention:** The unique constraint must be `(user_id, swap_request_id)` — one rating per user per swap. The controller must additionally verify the user is the receiver of the specific book they are rating in that swap, not just a participant in the swap.

**Detection:** Warning sign: if your migration has `unique(['user_id', 'book_id'])` without `swap_request_id`, this pitfall is open.

**Phase:** Schema design phase. Cannot be retrofitted cleanly.

**Severity:** HIGH — broken uniqueness constraint produces meaningless average scores.

---

### Pitfall 3: Average Rating Computed in PHP Instead of SQL

**What goes wrong:** Fetching all ratings for every book in a browse response and computing `avg()` in PHP/Vue is the beginner move. With 12 books per browse page, each book needing its ratings loaded, this is 13 queries per page load minimum (N+1).

**Why it happens:** `$book->ratings->avg('stars')` looks clean in Blade/Eloquent but loads the full ratings collection into memory.

**Consequences:** Browse page becomes the slowest endpoint. SQLite handles `AVG()` fine in a single query; PHP loop does not.

**Prevention:** Use `withAvg('ratings', 'stars')` (Laravel 8+, HIGH confidence) on the browse query. This generates a single subquery and adds `ratings_avg_stars` to each book model. Alternatively, store a denormalized `avg_rating` column on the books table and update it via an observer — but that adds complexity not worth it for a school project. `withAvg` is the right choice here.

**Detection:** Warning sign: any `->with('ratings')` on the browse query. That eager-loads all rating rows instead of just the average.

**Phase:** Backend browse endpoint, alongside ratings schema.

**Severity:** MEDIUM for school demo scale (SQLite with tens of rows hides the problem). HIGH for code quality evaluation — an examiner who reads the query will notice.

---

### Pitfall 4: SQLite Does Not Enforce Foreign Keys by Default

**What goes wrong:** On SQLite, `PRAGMA foreign_keys` is OFF unless explicitly enabled. This means a `ratings` row with a nonexistent `swap_request_id` or `book_id` can be inserted without error. The cascade delete defined in the migration also silently does nothing.

**Why it happens:** Laravel migrations define foreign key constraints correctly, but SQLite simply ignores them unless the pragma is set per-connection. MySQL enforces them without any extra step, so developers coming from MySQL expect the same behavior.

**Consequences:** Orphaned rating rows after swap deletion. Ratings referencing deleted books still appear in averages. Data inconsistency that only shows up in edge cases during the demo.

**Prevention:** Add `DB::statement('PRAGMA foreign_keys = ON;')` in `AppServiceProvider::boot()`. This is a one-line fix and should be added regardless of ratings. Also add a `ratings` index on `book_id` and `swap_request_id` (SQLite does not automatically index foreign keys — confirmed by CONCERNS.md section 6.1, same issue exists for `books.user_id`).

**Detection:** Try inserting a rating with a fake `swap_request_id = 9999` in tinker. If it succeeds without error, the pragma is not set.

**Phase:** Should be addressed in the first migration for ratings, and also backfilled to AppServiceProvider immediately.

**Severity:** MEDIUM. Won't crash the demo, but produces silent data corruption.

---

### Pitfall 5: The Browse Bug (Swapped Books Visible) Interacts With Average Ratings

**What goes wrong:** REQ-005 fixes `/api/browse` to exclude Swapped books. If this fix is deferred until after ratings are built, the ratings feature ships on a broken browse endpoint. Swapped books with ratings will be visible in browse results, confusing users who try to swap a book that has already been exchanged.

**Why it happens:** The browse bug (BookController line 13: `where('status', '!=', 'Pending')`) is a pre-existing issue (CONCERNS.md 5.1) and easy to overlook as "separate work." But ratings are displayed on book cards in browse — the two features are coupled at the UI level.

**Consequences:** Demo shows a book card with a star rating and a disabled "Request Swap" button (because the book is Swapped), which looks like a broken feature even if the rating itself is correct.

**Prevention:** Fix REQ-005 first, or in the same phase as the ratings browse display. The fix is a one-line change: `->where('status', 'Available')`.

**Detection:** Warning sign: any test of the browse endpoint that does not assert Swapped books are excluded (CONCERNS.md 8.4 confirms this test gap exists).

**Phase:** Phase 1 / bug fix phase, before or alongside ratings display.

**Severity:** HIGH for demo presentation. Low fix complexity — do it first.

---

### Pitfall 6: History Page Uses Broken Swap Count Query — Ratings Will Inherit the Bug

**What goes wrong:** ProfileController::history() (line 65–68) and ProfileController::show() (line 16–19) both use a post-ownership-transfer broken query (CONCERNS.md 2.4, 5.3). The same query pattern will need to be used when showing "which swaps can I rate?" If copied naively, the rating eligibility list will be wrong — showing swaps the user is not party to.

**Why it happens:** The query uses `orWhereHas('offeredBook', fn => where('user_id', $user->id))` — but after a swap, offeredBook now belongs to the other user. The original owner is no longer findable via current `user_id` on the book.

**Consequences:** Users see rating prompts for swaps they weren't part of. Or they don't see prompts for swaps they were part of.

**Prevention:** Fix the history query at the same time as adding ratings. The correct approach is to query by `requester_id = user OR (wanted_book.original_owner = user)` — but since the original owner is not stored, the correct fix is to query by explicit participant columns that don't change after the swap: `requester_id = user OR` (a stored `owner_id` column on swap_requests, added in the ratings migration). Alternatively, accept that history is shown from the requester perspective only, which is simpler and correct for 95% of cases.

**Detection:** Warning sign: any rating eligibility query that uses `whereHas('offeredBook', ...)` on current `user_id`.

**Phase:** Must be addressed when building the "which swaps can I rate?" query. Cannot be ignored.

**Severity:** HIGH. Broken eligibility directly breaks the ratings feature.

---

## Moderate Pitfalls

---

### Pitfall 7: Rating a Swap That Was Later "Dismissed" or Has No Book

**What goes wrong:** SwapRequests have `requester_dismissed` and `owner_dismissed` flags. After dismissal, the swap is hidden from the dashboard. A rating submitted against a dismissed swap is still valid by the data model, but there is no UI path to get back to it if the rating wasn't submitted before dismissal.

**Why it happens:** Dismissal hides the swap but does not delete it. The rating window (time between accept and dismissal) is not enforced anywhere.

**Prevention:** The rating controller should not check dismissed flags — the swap record still exists and is valid. But the frontend must surface the rating prompt before the user dismisses the swap. Best approach: show the rating prompt on the Dashboard swap card immediately after accept, before the user can dismiss it.

**Phase:** Frontend rating UI phase.

**Severity:** MEDIUM. Easy to handle with correct UI flow.

---

### Pitfall 8: Star Rating Input Built With Radio Buttons + CSS Hack

**What goes wrong:** The standard "CSS-only star rating" trick (reverse-order radio buttons styled with `::before`) requires specific HTML structure. It breaks in dark mode if the `:checked` sibling selector relies on default browser colors, and it fights Bootstrap's form-control resets.

**Why it happens:** Tutorials for "pure CSS star rating" all show this pattern, but they assume vanilla CSS. Bootstrap v5 and Tailwind v4 both reset `appearance` on inputs in ways that interfere.

**Prevention:** Use a simple Vue `v-for` loop rendering 5 clickable span/button elements that apply an active class. Store the hovered/selected value in `data`. This is 15 lines of Vue and is fully controllable in dark mode. Avoid the pure-CSS radio hack entirely.

**Detection:** Warning sign: any `<input type="radio">` elements in the star rating component.

**Phase:** Frontend rating component phase.

**Severity:** MEDIUM. A broken star UI on a demo is immediately visible to an examiner.

---

### Pitfall 9: fetchAll() Cascade on Rating Submit

**What goes wrong:** Dashboard.vue currently calls `this.fetchAll()` after acceptSwap() and declineSwap() (CONCERNS.md 3.3), firing 5 parallel API requests. If a rating is submitted from the Dashboard and triggers the same `fetchAll()` pattern, it will re-fetch everything including the now-stale swap list, but the average rating on book cards won't update because book data comes from a separate browse endpoint.

**Why it happens:** `fetchAll()` is the existing pattern for "refresh state after an action." It's easy to reach for, but it doesn't include the browse book list.

**Prevention:** After a rating submit, do a targeted update: update the local `rating` field on the relevant swap card (disable the rate button, show submitted stars), and separately invalidate the browse cache if the user navigates back to browse. Do not trigger `fetchAll()` for a rating action.

**Phase:** Frontend rating UI phase.

**Severity:** LOW. Doesn't break anything, but causes unnecessary network traffic.

---

### Pitfall 10: Duplicate handleLogout Will Need to Be Copied Again for Any New Component

**What goes wrong:** CONCERNS.md 7.1 documents that `handleLogout` is copy-pasted across Dashboard.vue, Messages.vue, and Profile.vue. If a new RatingModal.vue or similar component is added that needs logout behavior (e.g., handles a 401 from the ratings endpoint), the pattern will be copied a fourth time.

**Why it happens:** No shared composable exists for this logic.

**Prevention:** Extract `handleLogout` to `authStore.js` or a `useAuth` composable as part of this milestone's cleanup work. REQ-006 (Messages.vue error handling fix) provides a natural opportunity to touch these files — do the extraction at the same time.

**Phase:** Code cleanup phase, alongside REQ-006.

**Severity:** LOW for functionality. MEDIUM for code quality grade.

---

## Minor Pitfalls

---

### Pitfall 11: Translation Keys Missing for Rating UI

**What goes wrong:** The app has an EN/LV language toggle. New rating UI strings added in English only will break the LV locale. CONCERNS.md 3.4 already documents a "Message owner" button that was never translated.

**Prevention:** Add both `en` and `lv` keys for every new string when writing the rating component. Do it in the same commit as the component — don't defer translations.

**Phase:** Frontend rating component phase.

**Severity:** LOW individually, but the teacher will notice if the LV toggle breaks on the new feature.

---

### Pitfall 12: Average Rating Display on Browse Breaks When No Ratings Exist

**What goes wrong:** `ratings_avg_stars` returns `null` (not 0) when a book has no ratings. Displaying `null` as a star rating renders as empty/broken in Vue without a null guard.

**Prevention:** Use `book.ratings_avg_stars ?? null` to explicitly render "no ratings yet" state (e.g., grey stars or "No ratings") rather than 0 filled stars. Never display 0 stars — users interpret 0 stars as "rated zero" not "unrated."

**Phase:** Frontend browse card update.

**Severity:** LOW but visually obvious during demo.

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|----------------|------------|
| Ratings migration | SQLite foreign keys not enforced | Add PRAGMA in AppServiceProvider, explicit indexes |
| Rating controller (authorization) | Wrong identity check (book owner vs swap receiver) | Anchor to swap_request_id, derive receiver from swap record |
| Rating uniqueness | Multiple ratings per user per swap | Unique constraint on (user_id, swap_request_id) in migration |
| Browse endpoint update | Swapped books still visible, avg computed in PHP | Fix status filter first; use withAvg() not ->with('ratings') |
| History/eligibility query | Broken post-transfer ownership query | Fix the underlying ProfileController query; don't copy-paste it |
| Rating UI component | CSS star hack breaks dark mode + Bootstrap | Vue v-for + active class pattern instead |
| Bug fixes (REQ-005, REQ-006) | Shipped alongside ratings as afterthought | Fix these in Phase 1 before ratings frontend, not last |
| Translations | LV strings missing for new UI | Add both locales in same commit as component |

---

## Existing Codebase Concerns to Fix Alongside Ratings

These are from CONCERNS.md and must not be ignored during this milestone — they interact directly with ratings or are flagged as active requirements (REQ-005, REQ-006).

| Concern | File | Required Action | Why Now |
|---------|------|----------------|---------|
| REQ-005: Swapped books in browse | BookController line 13 | Change `!= 'Pending'` to `= 'Available'` | Ratings display on browse is broken without this fix |
| REQ-006: Silent errors in Messages.vue | Messages.vue lines 155–158, 187–190 | Show error state in UI | Active requirement; also catch for ratings API errors |
| Duplicate handleLogout | Dashboard, Messages, Profile | Extract to authStore or composable | Code quality grade; natural cleanup during REQ-006 |
| Broken history/swap count query | ProfileController lines 16–19, 65–68 | Fix ownership identity post-transfer | Directly blocks correct rating eligibility query |
| UserProfile shows Swapped books | UserController line 14 | Filter to Available only, or hide Request Swap button | Compounds the browse bug; visible during demo |
| BookTest missing Swapped assertion | BookTest.php | Add Swapped book to browse test | The browse bug has no test coverage — fix will regress silently without this |

---

## Sources

- Codebase analysis: direct read of SwapRequestController.php, BookController.php, ProfileController.php, migrations, CONCERNS.md
- Laravel `withAvg()`: documented in Laravel 8+ Eloquent relationships, HIGH confidence (core framework feature)
- SQLite foreign key pragma: SQLite documentation (PRAGMA foreign_keys), HIGH confidence
- Vue star rating patterns: standard Options API pattern, HIGH confidence
- Bootstrap + CSS sibling selector interference: MEDIUM confidence (observed pattern, not officially documented)
