---
phase: "01"
plan: "02"
subsystem: backend
tags: [migration, swap-requests, bug-fix, owner-id]
dependency_graph:
  requires: []
  provides: [swap_requests.owner_id, correct-history-queries]
  affects: [ProfileController, UserController, SwapRequestController, SwapRequest]
tech_stack:
  added: []
  patterns: [store-owner-at-creation-time, direct-column-query-over-whereHas]
key_files:
  created:
    - database/migrations/2026_03_26_124001_add_owner_id_to_swap_requests.php
  modified:
    - app/Models/SwapRequest.php
    - app/Http/Controllers/SwapRequestController.php
    - app/Http/Controllers/ProfileController.php
    - app/Http/Controllers/UserController.php
decisions:
  - Store owner_id at swap creation time (wanted_book.user_id) so queries remain correct after ownership transfer on accept
  - Backfill accepted swaps from offered_book.user_id since offered_book was transferred to the original owner on accept
  - Use direct orWhere('owner_id') instead of orWhereHas — simpler and cannot break after book.user_id changes
metrics:
  duration_minutes: 15
  completed_date: "2026-03-26"
  tasks_completed: 5
  files_changed: 5
---

# Phase 1 Plan 02: Owner ID Migration Summary

**One-liner:** Added `owner_id` to `swap_requests` with backfill migration and replaced three broken `orWhereHas(offeredBook)` queries with direct `orWhere('owner_id')` across ProfileController and UserController.

## What Was Done

The `swap_requests` table lacked a stable column to identify the original book owner. Three query sites (ProfileController::show swap count, ProfileController::history, UserController::show swap count) all derived "who was the owner" from `books.user_id` via `orWhereHas('offeredBook', ...)`. After `SwapRequestController::accept()` transfers ownership by changing `books.user_id`, this query returned wrong results — swaps disappeared from history for the original owner.

**Fix:** Store `owner_id` (the current owner of the wanted book) at swap creation time in `store()`, then query against it directly.

## Tasks Completed

| Task | Description | Commit |
|------|-------------|--------|
| 1 | Add owner_id migration with backfill | 245a99a |
| 2 | SwapRequest model: add to fillable, add owner() relation | b9400b5 |
| 3 | SwapRequestController::store(): set owner_id on create | 6fe6723 |
| 4 | ProfileController: fix show() and history() queries | 7c884bf |
| 5 | UserController::show(): fix swap count query | 68a8d74 |

## Migration Backfill Logic

- **Pending/declined swaps:** `wanted_book.user_id` is still the original owner (no transfer happened) → backfill from `books WHERE books.id = wanted_book_id`
- **Accepted swaps:** Ownership already transferred. The offered book now belongs to the original owner (they received it). → backfill from `books WHERE books.id = offered_book_id`
- Column set nullable for backfill, then altered to non-nullable with foreign key constraint.

## Verification

- `php artisan migrate` ran without error (fresh DB: all 10 migrations applied)
- `SwapRequest::whereNull('owner_id')->count()` returns 0
- `composer run test` — 50 tests, 117 assertions, all passed

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None.

## Self-Check: PASSED

- database/migrations/2026_03_26_124001_add_owner_id_to_swap_requests.php — FOUND
- app/Models/SwapRequest.php — FOUND (owner_id in fillable, owner() relation present)
- app/Http/Controllers/SwapRequestController.php — FOUND (owner_id set in store())
- app/Http/Controllers/ProfileController.php — FOUND (both queries use owner_id)
- app/Http/Controllers/UserController.php — FOUND (swap count uses owner_id)
- Commits 245a99a, b9400b5, 6fe6723, 7c884bf, 68a8d74 — all present in git log
