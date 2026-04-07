# Requirements

**Project:** BookLoop — Ratings & Polish Milestone
**Created:** 2026-03-26
**Status:** Active

---

## Scope

Add book ratings/reviews as the primary new feature, alongside two bug fixes and one data model improvement that are required for ratings to work correctly.

---

## Feature: Book Ratings & Reviews

### REQ-001 — Submit a star rating
After a swap is accepted, the user who received a book can submit a 1–5 star rating for it.

**Acceptance criteria:**
- Star input (1–5) is displayed in the swap history entry on Profile.vue for received books
- Submitting saves the rating to the database
- A swap can only be rated once; submitting again returns a 422 error
- Only the receiver of the book can rate it (requester received `wanted_book`, owner received `offered_book`)

---

### REQ-002 — Optional review text
A text comment can be submitted alongside the star rating.

**Acceptance criteria:**
- Text field (optional, max 500 chars) appears with the star input
- Stored alongside the rating in the database
- If left blank, rating submits successfully without review text

---

### REQ-003 — Average rating visible on browse and profiles
The average star rating for a book is displayed wherever the book is shown.

**Acceptance criteria:**
- Browse page (Home.vue book cards): shows average stars and rating count, e.g. "★ 4.2 (5)"
- UserProfile.vue book cards: same average/count display
- If no ratings yet: show "No ratings" or neutral placeholder, not a broken/null display
- Average is calculated server-side (not frontend) via `withAvg`

---

### REQ-004 — One rating per swap, receiver-only gate
The system enforces rating rules at the database and API level.

**Acceptance criteria:**
- Database unique constraint on `(swap_request_id, book_id)` prevents duplicate ratings
- `POST /api/ratings` returns 403 if the authenticated user did not receive the book in the specified swap
- `POST /api/ratings` returns 422 if a rating for this swap+book already exists
- Receiver identity is derived from the `swap_requests` record (NOT from `books.user_id` which changes on swap accept)

---

## Bug Fixes

### REQ-005 — Exclude Swapped books from browse [COMPLETE - 01-01]
The public browse endpoint must only return Available books.

**Acceptance criteria:**
- [x] `GET /api/browse` returns only books with `status = 'Available'`
- [x] Swapped books no longer appear in Home.vue search results
- [x] Test: `BookTest::test_browse_returns_available_books` also covers Swapped exclusion

---

### REQ-006 — Show fetch errors in Messages.vue [COMPLETE - 01-01]
Silent catch blocks in Messages.vue must be replaced with visible error state.

**Acceptance criteria:**
- [x] If `fetchConversations()` fails, an error message is shown in the conversation list panel
- [x] If `fetchThread()` fails, an error message is shown in the message thread panel
- [x] Error state is cleared on the next successful fetch

---

## Data Model Improvement

### REQ-007 — Add owner_id to swap_requests
Store the original book owner's user ID on the swap record at creation time, before ownership transfers.

**Acceptance criteria:**
- `swap_requests` table has an `owner_id` column (non-nullable foreign key to `users`)
- `owner_id` is set in `SwapRequestController::store()` from `wantedBook->user_id` before the swap is created
- `ProfileController::history()` and `UserController::show()` swap count queries are updated to use `owner_id` instead of the broken `orWhereHas('offeredBook', ...)` clause
- Migration does not break existing rows (nullable or backfill strategy applied)

---

## Out of Scope

- Half-star ratings — adds JS complexity with no demo payoff
- Rating edit/delete — immutable ratings are simpler and sufficient
- Rating moderation or reporting — not needed for a school project
- Email notifications for ratings — database notifications are sufficient
- Book cover image upload — file storage adds complexity not worth the time
- Admin panel — out of scope for this milestone
- Real-time WebSockets — polling is sufficient

---

## Requirement Traceability

| ID | Feature | Phase |
|----|---------|-------|
| REQ-001 | Submit star rating | Phase 1 (backend), Phase 2 (frontend) |
| REQ-002 | Optional review text | Phase 1 (backend), Phase 2 (frontend) |
| REQ-003 | Average rating display | Phase 2 |
| REQ-004 | One-rating gate | Phase 1 (backend), Phase 2 (frontend state) |
| REQ-005 | Browse bug fix | Phase 1 |
| REQ-006 | Messages error display | Phase 1 |
| REQ-007 | owner_id migration | Phase 1 |

---

*Last updated: 2026-03-26*
