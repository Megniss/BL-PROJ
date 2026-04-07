# Feature Landscape: Book Ratings & Reviews

**Domain:** Book rating/review system for a peer-to-peer book exchange app
**Researched:** 2026-03-26
**Scope:** Milestone addition to an existing Laravel SPA (BookLoop)

---

## How Goodreads, LibraryThing, and StoryGraph Handle Post-Exchange Ratings

These platforms treat ratings as tied to a user's reading history, not an exchange event. Any user can rate any book at any time. BookLoop's constraint — rating only after receiving a book via swap — is more restrictive and more defensible for a school demo. It proves the rating is earned, not arbitrary.

Key patterns observed across these platforms:

- **Goodreads:** 1–5 star scale (half-stars only in mobile app), optional text review, visible average on book pages, one rating per book per user (update allowed), no exchange-gating
- **LibraryThing:** Same 1–5 scale, optional review, "Work" aggregation (one canonical book entry, many editions), community-driven
- **StoryGraph:** More nuanced (pace, mood tags, content warnings) — adds complexity beyond scope
- **OpenLibrary:** Community-contributed reviews, no rating gate, anyone can post

The exchange-gated rating model (rate only what you received) is BookLoop's differentiating constraint. It is not done by any major platform because they are catalogs, not exchange apps. This makes it a genuine design decision worth explaining in a school demo.

---

## Table Stakes

Features that must exist for ratings to feel like a real system. Missing any of these makes the feature feel broken.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| 1–5 star rating input | Universal convention; users immediately understand it | Low | Integer stars only — half-stars add JS complexity for no demo value |
| One rating per swap | Prevents double-counting; enforces integrity | Low | Unique constraint on `swap_request_id` in DB |
| Rating only by the receiver | The recipient judges what they got | Low | Gate on `swap_request.receiver_id === auth user` |
| Rating only after swap is accepted | You can't rate a book you haven't received | Low | Gate on `swap_request.status === 'accepted'` |
| Average star rating on book cards | The aggregated payoff — visible proof ratings matter | Low–Med | `AVG()` query; display on browse cards and book detail |
| Average rating on user profile | Shows a user's book quality history | Low | Reuse same average query scoped to user's books |
| Rating visible immediately after submission | Feedback that submission worked | Low | Refresh or reactive update after POST |
| Prevent rating a book you no longer own | Edge case — book transferred, rating is on the book object | Low | Rating belongs to the swap, not current ownership |

---

## Differentiators

Features that would impress a teacher grader without requiring a rewrite. Each is additive and independent.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| Optional text review alongside rating | Adds qualitative depth; shows the form can be extended | Low | `nullable text` column; conditional display when present |
| Review visible on book detail page | Gives context to star scores; feels like a real app | Low | List reviews below average score; show reviewer name + date |
| "Rated" badge on swap history | User can see at a glance which swaps they've rated | Low | Boolean derived from whether a Rating row exists for the swap |
| Rating prompt in notifications | After a swap is accepted, notify the receiver to rate | Med | Adds a new notification type; piggybacks existing notification system |
| Edit your own rating | Users change their minds; shows you thought about mutability | Low | PUT endpoint; guard that only the original rater can edit |
| Rating count alongside average | "4.2 stars (7 ratings)" is more trustworthy than "4.2 stars" | Low | Return `count` alongside `avg` in the query |

---

## Anti-Features

Features that sound reasonable but are over-engineering traps for a 1–2 week school timeline.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| Half-star (0.5 increment) ratings | Requires custom star slider or fractional logic in Vue; adds JS complexity for no UX payoff at this scale | Integer 1–5 only |
| "Helpful" votes on reviews | Full sub-system (vote model, vote controller, vote UI); completely out of scope | Not needed for demo |
| Review moderation / flagging | Requires admin role, which is explicitly out of scope | Skip |
| Spoiler-tagged reviews | Adds conditional display logic and a toggle; purely cosmetic complexity | Skip |
| Book-level vs edition-level ratings | Goodreads has "Works" to merge editions — irrelevant here since books are individual user items, not a shared catalog | Rate the swap, not the abstract book |
| Pagination of reviews | More than a handful of reviews is impossible in a school demo with a handful of test accounts | Simple list, no pagination |
| Rating expiry or deletion by reviewer | Adds a DELETE endpoint and confirmation UI; not worth it unless requirements demand it | Omit; edit is sufficient |
| Content warnings / mood tags (StoryGraph-style) | Impressive but a separate milestone of its own | Defer entirely |
| Aggregate rating feed / activity stream | "Recent ratings" page; adds a new route, controller, query; low demo value | Not needed |
| Anonymous reviews | Contradicts the trust model (exchange is between known users) | All reviews are attributed |

---

## Feature Dependencies

```
SwapRequest (accepted status) → Rating (creation gate)
  SwapRequest.receiver_id     → Rating.user_id (who may rate)
  SwapRequest.id              → Rating.swap_request_id (unique constraint)

Rating (exists)               → AverageRating (displayed on book card)
Rating.body (nullable)        → ReviewText (only displayed if present)

AverageRating                 → BrowsePage (shows avg on each card)
AverageRating                 → UserProfile (shows avg on user's books)

Rating (exists for swap)      → "Rated" badge on swap history row
```

Key constraint: the rating model links to both a `swap_request` (for integrity) and a `book` (for aggregation). The book reference can be derived from the swap, but storing it explicitly simplifies the `AVG()` query.

---

## MVP Recommendation

For the 1–2 week school timeline, build exactly what PROJECT.md REQ-001 through REQ-004 specify:

**Must ship:**
1. Rating creation (1–5 stars, post-swap gate, one-per-swap, receiver only) — REQ-001, REQ-004
2. Optional text review field — REQ-002
3. Average rating displayed on browse cards and user profile — REQ-003

**Add if time allows (in priority order):**
4. Review text visible on book detail page (low effort, high visual payoff)
5. Rating count alongside average ("4.2 (5 ratings)")
6. "Rated" badge on swap history

**Defer to never (for this milestone):**
- Helpful votes, moderation, half-stars, mood tags, pagination

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Table stakes | HIGH | Derived from validated REQs in PROJECT.md plus universal UX conventions |
| Differentiators | HIGH | Standard patterns well-established across Goodreads/LibraryThing/StoryGraph — no research access needed |
| Anti-features | HIGH | Engineering judgment; complexity estimates are conservative |
| Exchange-gating pattern | MEDIUM | No verified source — this constraint is BookLoop-specific, not industry-standard. PROJECT.md already decided it. |

---

## Sources

- PROJECT.md (c:/Proj/my-laravel-app/.planning/PROJECT.md) — authoritative requirements (HIGH confidence)
- Training knowledge of Goodreads, LibraryThing, StoryGraph, OpenLibrary feature sets (MEDIUM confidence — knowledge cutoff August 2025, platforms stable)
- WebSearch unavailable in this environment — no live source verification performed
