# Promo Codes and Bonuses — Laravel + Vue

Test assignment: a Laravel REST API + a Vue 3 frontend for a gambling/betting platform.
A player enters a promo code and receives a bonus on their balance; a bonus credited by
mistake can be revoked.

| | |
|---|---|
| Backend | Laravel 13, PHP 8.4 |
| DB | SQLite — no external services |
| Auth | Laravel Sanctum, token in the header |
| Frontend | Vue 3 (Composition API, `<script setup>`), Vite, axios |
| Tests | PHPUnit (feature level) + Vitest |

## Getting started

Requires PHP ≥ 8.2, Composer and Node ≥ 20.

```bash
composer install
npm install

cp .env.example .env          # on Windows: copy .env.example .env
php artisan key:generate
php artisan migrate --seed    # creates the SQLite file, players and promo codes
```

Two processes in separate terminals:

```bash
php artisan serve             # API — http://127.0.0.1:8000
npm run dev                   # frontend
```

Tests:

```bash
php artisan test    # API — PHPUnit
npm test            # Vue components — Vitest
```

## Test data

Two players with different balances — so it is visible that balance and history are isolated:

| Email | Password | Balance |
|---|---|---|
| `olena@example.com` | `password` | 50.00 |
| `ihor@example.com` | `password` | 125.50 |

Promo codes — covering every behaviour branch:

| Code | Bonus | State |
|---|---|---|
| `WELCOME100` | 100.00 | never expires |
| `BONUS50` | 50.00 | never expires |
| `SUMMER25` | 25.00 | valid for another month |
| `OLDCODE99` | 99.00 | **expired** |

## API

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/api/login` | obtain a token |
| `GET` | `/api/me` | current player and their balance |
| `POST` | `/api/logout` | revoke the current token |
| `POST` | `/api/promo/claim` | apply a promo code, credit the bonus |
| `GET` | `/api/promo/history` | claim history: pagination + status filter |
| `PATCH` | `/api/promo/{claimId}/revoke` | revoke a credited bonus |

`GET /api/promo/history` accepts `?status=applied\|rejected\|revoked` (no parameter — all),
`?per_page=` (1–50, default 10) and `?page=`. An unknown status returns `422` instead of
being silently ignored. The filter is preserved in the pagination links.

All `/api/promo/*` endpoints require the `Authorization: Bearer <token>` header.
**The player is determined exclusively from the token** — no endpoint accepts a player
identifier from the request body or the URL.

### Error codes

| Code | When | Example |
|---|---|---|
| `422` | input **format** error | the code does not match `^[A-Za-z0-9]{6,12}$` |
| `409` | format is valid, but a **business rule** is violated | the code is expired, already used, the claim is already revoked |
| `404` | the resource does not exist **or does not belong to this player** | someone else's `claimId` |

The body of a `409` always carries a machine-readable `reason` (`not_found`, `expired`,
`already_used`, `already_revoked`, `not_applied`, `insufficient_balance`) — the frontend
renders the reason text from it instead of parsing the message.

Someone else's claim returns `404` rather than `403`, so as not to confirm that another
player's records exist.

## Key decisions

**Money is integer cents.** No floats on the balance anywhere. The API returns both `cents`
and `formatted`.

**The balance is never mutated directly.** Every change = a row in `wallet_transactions` +
an update of `users.balance_cents` inside a single DB transaction. The ledger is the source
of truth; the sum of its entries always reconciles with the balance.

**Abuse protection lives in the DB schema, not only in code checks.**
An `if` does not save you from concurrent requests, hence:

- a **partial** unique index `(user_id, promo_code_id) WHERE status <> 'rejected'` —
  the second insert fails on the index, so double crediting is impossible. Rejected attempts
  are excluded (the code was not consumed, it can be retried), while `revoked` is **not**
  excluded — otherwise the chain claim → revoke → claim would print money;
- a unique `(promo_claim_id, type)` on the ledger — at most one credit and one debit per
  claim, so a repeated revoke is impossible even under a race;
- `promo_codes.bonus_amount_cents` is `unsigned`;
- a revoke with insufficient balance is rejected with `insufficient_balance`; the balance
  never goes negative.

Each of these rules has a test in `tests/Feature/SchemaGuardsTest.php` that writes **directly
to the DB, bypassing application code** — the whole point is that the guarantee rests on the
schema and survives a bug in the service or a careless future change.

> **Limitations worth knowing.** Partial indexes exist in SQLite and PostgreSQL but **not in
> MySQL** — there the same invariant would have to be expressed differently (for example, via
> a nullable column populated only for completed claims). Under SQLite row-level locking is
> degenerate, so `lockForUpdate()` here is decorative and the real guarantee comes from the
> unique indexes. SQLite only accepts `CHECK` constraints at table creation time, so the
> positivity of the amount rests on `unsigned` and on validation rather than on a `CHECK`.

**Rejected attempts are persisted.** History can be filtered by the "rejected" status, so every
failed claim creates a record with its reason — and only then is the error returned.

## Documents

| File | What's inside |
|---|---|
| [`docs/PLAN.md`](docs/PLAN.md) | work plan + a matrix of all the assignment's requirements |
| [`docs/PROMPT-LOG.md`](docs/PROMPT-LOG.md) | log of prompts to the AI tool: requests, iterations, fixes |
| [`docs/CODE-REVIEW.md`](docs/CODE-REVIEW.md) | Part 2 of the assignment — a written review of the provided code fragment |
| [`docs/SCREENSHOTS.md`](docs/SCREENSHOTS.md) | **setup and both features in screenshots** — an annotated walkthrough |
