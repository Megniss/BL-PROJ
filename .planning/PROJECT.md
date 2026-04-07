# BookLoop

## What This Is

A book exchange web app where users build personal libraries and arrange swaps with other users. Built as a school project in Laravel + Vue 3.

The core loop: add books → browse others' libraries → request a swap → exchange ownership → rate the book you received.

## Context

- **Type:** School project (brownfield — core features already built)
- **Stack:** Laravel 11 SPA, Vue 3 (Options API), SQLite, Tailwind CSS v4, Laravel Sanctum
- **Deadline:** 1–2 weeks
- **Evaluator:** Teacher grades everything (features + code quality)

## Core Value

A working end-to-end book exchange with ratings that closes the loop after a swap.

## Users

- **Students/readers** who want to exchange books without spending money
- Single user type — no admin role needed

## Requirements

### Validated

- ✓ User registration and login — existing
- ✓ Token-based auth (Laravel Sanctum) — existing
- ✓ Password reset via email — existing
- ✓ Book library management (add, edit, delete) — existing
- ✓ Public book browse with search, filter, sort — existing
- ✓ Swap request flow (create, accept, decline, cancel) — existing
- ✓ Book ownership transfer on swap accept — existing
- ✓ User-to-user messaging — existing
- ✓ Database notifications (swap accepted/declined, new message) — existing
- ✓ Public user profile pages — existing
- ✓ Dark mode — existing
- ✓ EN/LV language toggle — existing
- ✓ 404 page and navigation guards — existing

### Active

- [ ] REQ-001: Users can rate a book (1–5 stars) after a swap is accepted
- [ ] REQ-002: Users can leave an optional text review alongside their rating
- [ ] REQ-003: Average star rating is visible on book cards in browse and user profiles
- [ ] REQ-004: A user can only rate a book they received via a completed swap (one rating per swap)
- [ ] REQ-005: Swapped books are excluded from /api/browse (currently shows them — bug fix)
- [ ] REQ-006: Messages.vue fetch errors are shown to the user instead of silently swallowed

### Out of Scope

- Admin panel — not needed for school scope
- Real-time WebSockets — polling is sufficient
- OAuth / social login — out of scope
- Book cover image upload — file storage adds complexity not worth the time
- Email notifications — database notifications are sufficient for demo

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Ratings tied to SwapRequest | Prevents rating without exchanging; enforces one rating per swap | — Pending |
| Rating by receiver only | The person who received the book rates it | — Pending |
| Ratings visible on browse page | Most visible showcase of the feature | — Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd:transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd:complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-03-26 after initialization*
