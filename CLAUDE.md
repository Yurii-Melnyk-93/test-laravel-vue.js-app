# CLAUDE.md — promo codes and bonuses (Laravel + Vue)

## What this project is

A test assignment for an "AI-assisted developer (Laravel/Vue)" position.
The domain is a gambling/betting platform: player balance and promo-code bonuses.

Two tickets in one project:
1. Crediting a bonus for a promo code + claim history with pagination and a status filter.
2. Revoking a bonus that was credited by mistake.

**The client stated explicitly** that correctness and abuse protection (double crediting,
negative amounts) are graded on par with the feature simply working. So any change that
touches the balance must answer the question "what happens under a race / a retry /
a negative amount?".

The full plan and the **matrix of 30 atomic requirements** are in `docs/PLAN.md`. Walk
through it before handing the work in.

## Stack and decisions

| | |
|---|---|
| Backend | Laravel (REST API), PHP 8.2+ via Laravel Herd |
| DB | SQLite (`database/database.sqlite`) — zero setup for the reviewer |
| Auth | Sanctum, token in the header; the player **always** comes from the token, never from the request body |
| Frontend | Vue 3, **Composition API + `<script setup>`**, Vite, axios. No vue-router, no store |
| Tests | Pest, feature level |

## Hard domain rules

**Money is integer cents.** `balance_cents`, `amount_cents`, `bigint`. No floats on money,
anywhere. The API returns both `cents` and `formatted`.

**The balance is never mutated directly.** Every balance change = a row in
`wallet_transactions` + an update of `users.balance_cents` **inside a single
`DB::transaction()`**. The ledger is the source of truth; the sum of its entries always
reconciles with the balance.

**Protection at the DB level, not just in an `if`.** A check in code does not catch a race.
- partial unique `(user_id, promo_code_id) WHERE status <> 'rejected'` — against a double claim
- unique `(promo_claim_id, type)` on the ledger — against a double revoke
- CHECK `bonus_amount_cents > 0`

**Rejected attempts are written to the DB too.** History has a "rejected" filter value — so
every failed claim creates a row with status `rejected` and a reason, and only then is the
error returned.

**A revoke with insufficient balance is forbidden.** 409 `insufficient_balance`, the status
does not change. We neither allow a negative balance nor debit down to zero.

**Error codes:** `422` — format errors only (validation). `409` — a business-rule violation,
with a machine-readable `reason` (`not_found` / `expired` / `already_used` / `already_revoked` /
`not_applied` / `insufficient_balance`). Someone else's claim → `404`, not `403`, so as not to
leak the fact that another player's records exist.

## Structure

Business logic lives in `PromoService`; controllers are thin. Validation goes in a Form
Request. Responses go through API Resources — we never assemble arrays by hand.

## Commands

```bash
php artisan migrate:fresh --seed   # bring up the DB with seeded players and promo codes
php artisan serve --port=8000      # API
npm run dev                        # frontend (hot reload)

php artisan test                   # backend tests (PHPUnit)
npm test                           # frontend tests (Vitest)
php vendor/bin/pint                # code style
```

**Tests are mandatory on both sides.** Every slice is covered by both PHPUnit and Vitest —
plus a live check in the browser. One does not replace the other: tests catch regressions,
the browser catches what tests do not see.

**Keep the dev environment up between steps**: both servers in the background, a Chrome tab
on `http://127.0.0.1:8000/` open. Do not shut them down after a check — Vite gives hot
reload, so the open tab shows changes immediately.

## How to work with me (user's style)

- I write in Ukrainian, with technical terms in English. Answers — short, with `path:line`.
- **Scope above all.** Out of scope means removed completely, with no dead code.
  "Let's leave it like this for now" is a valid answer — respect it. What is out of scope
  goes into `docs/PLAN.md`.
- **Clean data contracts**, not parsing of concatenated strings. If it can be a separate
  field, make it a separate field.
- **Lint-clean on delivery.** Run the linter/type checks before saying "done".
- **Verify live** (Chrome automation), not "the code looks right".
- **Conventions come from the neighbouring code**, not from your own preferences.
- **Don't over-clarify.** Enough information — act. Need a decision from the user — one
  clear question.
- Report honestly: tests failed — show the output; a step was skipped — say so.

## Workflow (agreed)

We cut the work into **vertical slices**: first the backend of a feature with tests,
immediately followed by the frontend for it. Not "the whole API, then the whole UI".

We work one logical step at a time. **Having finished a step, always go through four points:**

1. **Check against the plan** — which items of the `docs/PLAN.md` requirement matrix are
   closed; name discrepancies explicitly.
2. **Check for surplus code** — dead code, scaffolding leftovers, commented-out chunks,
   duplication.
3. **Tests** — run them if they already exist and show the real output. They failed — say so.
4. **Commit proposal** — the title + a short description, then stop.

After approval — **commit and push right away** (no separate question about pushing).
Approval applies to that specific commit, not to all the following ones.

- Commit and push **without approval — never**, but do not wait silently either: the
  initiative is mine, the decision is the user's.
- The commit description is **short**: a conventional-commits-style subject + 2–3 lines
  of substance.
- We do not create branches; history is linear on `main`.
- Commit history is a separate grading criterion for the assignment, so it must be
  step-by-step and meaningful, not one final commit.
- `docs/PROMPT-LOG.md` is maintained **in parallel with the work**, not reconstructed at
  the end.

## Deliverables

| File | What |
|---|---|
| `docs/PLAN.md` | plan + requirement matrix |
| `docs/PROMPT-LOG.md` | prompt log per ticket, iterations and fixes |
| `docs/CODE-REVIEW.md` | Part 2 — a written review of someone else's code fragment |
| `docs/SCREENSHOTS.md` | setup and both features in screenshots |
| `README.md` | how to set up and run |
