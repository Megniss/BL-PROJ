# Roadmap

## Milestone 1: Ratings & Polish

**Goal:** Close the exchange loop — users can rate books they received via a swap, and average ratings are visible everywhere books are shown.
**Requirements:** REQ-001, REQ-002, REQ-003, REQ-004, REQ-005, REQ-006, REQ-007

---

### Phase 1: Fixes + Backend Foundation

**Goal:** The database schema is correct, both bugs are gone, and the ratings API is live and fully tested — no frontend work depends on anything unresolved.

**Requirements:** REQ-005, REQ-006, REQ-007, REQ-001 (backend), REQ-002 (backend), REQ-004

**Success Criteria:**
1. `GET /api/browse` returns only books with `status = 'Available'` — swapped books are gone from search results
2. `Messages.vue` fetch failures show a visible error instead of silently failing
3. `swap_requests.owner_id` column exists, is populated on swap creation, and history queries use it correctly
4. `POST /api/ratings` accepts a valid rating from the receiver, rejects a duplicate with 422, rejects a non-receiver with 403, and rejects a non-accepted swap — confirmed by tests

#### Plans

1. **bug-fixes** — Fix `BookController::browse` status filter (REQ-005) and replace silent catches in `Messages.vue` with visible error state (REQ-006). These are independent of each other and of ratings. (parallel)
2. **owner-id-migration** — Add `owner_id` to `swap_requests`, set it in `SwapRequestController::store`, update `ProfileController::history` and `UserController::show` to use it. This fixes the broken history query that rating eligibility depends on. (parallel with bug-fixes)
3. **ratings-backend** — Create `ratings` migration + `Rating` model, implement `RatingController::store` with full authorization logic, register `POST /api/ratings` route, add `withAvg`/`withCount` to browse and user profile queries, add ratings relationship to history query. Depends on owner-id-migration completing first.

---

### Phase 2: Rating Entry UI + Display

**Goal:** Users can submit a star rating (and optional review) from their swap history, and average ratings are visible on every book card in browse and on user profiles.

**Requirements:** REQ-001 (frontend), REQ-002 (frontend), REQ-003, REQ-004 (frontend gate)

**Plans:** 3 plans

Plans:
- [x] 02-01-PLAN.md — Build RatingModal.vue (star picker + review textarea, matches SwapModal pattern)
- [x] 02-02-PLAN.md — Add star average + count display to book cards in Home.vue and UserProfile.vue
- [x] 02-03-PLAN.md — Wire RatingModal into Profile.vue swap history with rate/rated state and i18n keys

**Success Criteria:**
1. On Profile.vue swap history, an accepted swap shows a "Rate" button for the book the user received — clicking it opens a modal with a 1-5 star picker and optional review textarea
2. After submitting, the swap row shows the submitted stars instead of the "Rate" button (immediate local update, no page reload)
3. A swap that has already been rated shows read-only stars — the "Rate" button does not reappear
4. Book cards on the browse page (`Home.vue`) and user profile pages (`UserProfile.vue`) show average stars and rating count, e.g. "★ 4.2 (5)" — books with no ratings show a neutral placeholder, not a broken or zero display
5. Both EN and LV strings are present for all new UI text

---

## Requirement Traceability

| ID | Feature | Phase |
|----|---------|-------|
| REQ-001 | Submit star rating | Phase 1 (backend), Phase 2 (frontend) |
| REQ-002 | Optional review text | Phase 1 (backend), Phase 2 (frontend) |
| REQ-003 | Average rating display | Phase 2 |
| REQ-004 | One-rating gate | Phase 1 (backend enforcement), Phase 2 (frontend state) |
| REQ-005 | Browse bug fix | Phase 1 |
| REQ-006 | Messages error display | Phase 1 |
| REQ-007 | owner_id migration | Phase 1 |

---

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Fixes + Backend Foundation | 3/3 | Complete | 2026-03-27 |
| 2. Rating Entry UI + Display | 0/3 | Planned | - |

---

*Last updated: 2026-03-27*
