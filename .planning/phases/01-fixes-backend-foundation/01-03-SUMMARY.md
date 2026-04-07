---
phase: 01
plan: 03
type: summary
completed_at: "2026-03-27"
---

# Plan 01-03 Summary: Ratings Backend

## What was built

- `app/Models/Rating.php` — fillable: swap_request_id, book_id, rater_id, stars, review; BelongsTo: swapRequest, book, rater
- `app/Models/Book.php` — added `ratings(): HasMany` relationship
- `database/migrations/…_create_ratings_table.php` — fields: id, swap_request_id (FK), book_id (FK), rater_id (FK), stars (tinyint 1-5), review (nullable text), timestamps; unique on (swap_request_id, book_id)
- `app/Http/Controllers/RatingController.php` — `store()`: validates, loads swap, checks accepted, derives receiver from swap record, checks duplicate, creates rating; returns 403/422/201
- `routes/api.php` — `POST /api/ratings` inside auth:sanctum group
- `app/Providers/AppServiceProvider.php` — SQLite FK pragma enabled
- `app/Http/Controllers/BookController.php` — browse query extended with withAvg/withCount ratings
- `app/Http/Controllers/UserController.php` — public profile query extended with withAvg/withCount ratings
- `tests/Feature/RatingTest.php` — 8 tests, all passing

## Tests

58 tests, 139 assertions — all green.

## Key decisions

- Receiver derived from swap record (requester_id got wanted_book, owner_id got offered_book) — not from books.user_id which changes on accept
- Unique constraint on (swap_request_id, book_id), not (rater_id, book_id) — allows both parties to rate one swap
- Ratings are immutable (no update/delete endpoints)
