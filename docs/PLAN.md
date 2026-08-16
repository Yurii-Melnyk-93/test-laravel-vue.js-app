# Test assignment: Laravel + Vue, promo codes and bonuses

## Context

A test assignment for an "AI-assisted developer (Laravel/Vue)" position. The domain is
gambling/betting, so the brief explicitly states that correctness and abuse protection (double
crediting, negative amounts) are graded on par with the feature working.

Goal: a working project with a real commit history + the accompanying documents (prompt log,
the Part 2 code review).

### Decisions agreed with the user
| Question | Decision |
|---|---|
| PHP | Laravel Herd for Windows |
| Revoke with insufficient balance | Forbid it, return 409 `insufficient_balance`, do not change the status |
| Vue | Composition API + `<script setup>`, no store |
| Auth | Sanctum + a minimal login form, 2 seeded players |
| Tests | PHPUnit (backend) + Vitest (frontend), both mandatory |
| Code style | Pint for PHP |

### Environment state

PHP 8.4.24 + Composer 2.10.1 via Herd, Node 24 / npm 11, Laravel **13.25**, SQLite.
Avast intercepts TLS — its root was appended to `~/.config/herd/config/php/cacert.pem`, and git
was switched to `http.sslBackend=schannel`. After a Herd update the fix will have to be redone.

---

## Requirement matrix

Every requirement from the assignment text → where it is covered. This is the checklist for the
final review.

### Conditions of completion
| # | Requirement | Where | |
|---|---|---|---|
| U1 | Stack: Laravel (REST API) + Vue (axios) | the whole project | ✅ |
| U2 | A personal Git repository | GitHub, `origin` | ✅ |
| U3 | **A real commit history**, not a single final commit | commit plan below | ✅ ongoing |

### Ticket 1 — Backend, claim
| # | Requirement | Where | |
|---|---|---|---|
| T1.1 | `POST /api/promo/claim` | `routes/api.php` | ✅ |
| T1.2 | The code is required | `ClaimPromoRequest` → `required` | ✅ |
| T1.3 | Format: 6–12 characters, Latin letters + digits | `regex:/^[A-Za-z0-9]{6,12}$/` | ✅ |
| T1.4 | The player comes from the **token**, not from the body | `$request->user()`, `auth:sanctum` | ✅ |
| T1.5 | Check: the code exists | `PromoService::claim()` | ✅ |
| T1.6 | Check: not expired | `PromoCode::hasExpired()` | ✅ |
| T1.7 | Check: not already used by this player | `alreadyConsumed()` + partial unique index | ✅ |
| T1.8 | Success → bonus onto the balance | `WalletService::credit()`, ledger + balance in one transaction | ✅ |
| T1.9 | Response: the updated balance | `PromoController::claim()` → `balance` | ✅ |
| T1.10 | Response: the bonus amount | same → `bonus_amount` | ✅ |
| T1.11 | Invalid format → **422** with a description | Laravel validation | ✅ |
| T1.12 | Missing/expired/used → a **separate** error with a clear reason | **409** + `reason` | ✅ |

### Ticket 1 — Backend, history
| # | Requirement | Where | |
|---|---|---|---|
| T1.13 | `GET /api/promo/history` | `routes/api.php` | ✅ |
| T1.14 | This player's records only | `$request->user()->promoClaims()` | ✅ |
| T1.15 | Pagination | `paginate()` + `per_page` (≤ 50) | ✅ |
| T1.16 | Status filter (applied / **rejected**) | `PromoHistoryRequest` → `?status=` | ✅ |

> **The trap.** The existence of a "rejected" filter means **failed attempts are written to the
> DB too**. Otherwise there is nothing to filter. Implemented: every rejected claim creates a row
> with status `rejected` and a reason — and only then is the error returned.
>
> **A decision on top of the brief:** the brief names two filter values, but after Ticket 2 a
> third state appears — `revoked`. We support `applied` / `rejected` / `revoked` and "all",
> otherwise revoked claims would vanish from the list and that would look like a bug.

### Ticket 1 — Frontend
| # | Requirement | Where | |
|---|---|---|---|
| T1.17 | A promo code input form | `PromoClaimForm.vue` | ✅ |
| T1.18 | "Loading" state | same | ✅ |
| T1.19 | "Success" state | same | ✅ |
| T1.20 | "Error" state **with the reason text** | the text is shown as it came from the server; `reason` stays for the logic | ✅ |
| T1.21 | The history list below the form | `PromoHistory.vue` | ✅ |
| T1.22 | In the history: date | same, `Intl.DateTimeFormat('uk-UA')` | ✅ |
| T1.23 | In the history: amount | same, `—` for rejected ones | ✅ |
| T1.24 | In the history: status | same, a coloured badge | ✅ |

> The history is refetched not only after a success but also after a **business refusal (409)** —
> the server recorded it, so the list is stale. A `422` validation error writes nothing, so we
> ignore it.

### Ticket 2
| # | Requirement | Where | |
|---|---|---|---|
| T2.1 | `PATCH /api/promo/{claimId}/revoke` | `routes/api.php` | ✅ |
| T2.2 | Revokes the claim | `PromoService::revoke()` | ✅ |
| T2.3 | Removes the amount from the balance | `WalletService::debit()`, a negative ledger entry | ✅ |
| T2.4 | A repeated call → **an error**, not a silent double debit | status + the unique index on the ledger | ✅ |
| T2.5 | A "Revoke" button next to each **applied** code | `PromoHistory.vue`, the `can_revoke` field | ✅ |
| T2.6 | Confirmation of the action | `ConfirmDialog.vue` (our own modal) | ✅ |
| T2.7 | The status updates after the revoke | history refetch | ✅ |
| T2.8 | The balance updates after the revoke | balance from the response | ✅ |
| T2.9 | Done **after** Ticket 1, in the same project | commit order | ✅ |

### Abuse protection (the brief's preamble)
| # | Requirement | Where | |
|---|---|---|---|
| X1 | Correctness | 52 backend tests + 36 frontend ones | ✅ ongoing |
| X2 | No double crediting | partial unique `(user_id, promo_code_id)` + a unique index on the ledger, `SchemaGuardsTest` | ✅ |
| X3 | No negative amounts | `unsigned` on the bonus amount; a `balanceAfter < 0` guard in `WalletService` under a lock | ✅ |

### What to submit
| # | Requirement | Where | |
|---|---|---|---|
| D1 | A link to the repo | GitHub | ✅ |
| D2 | A prompt log (per ticket, iterations, fixes) | `docs/PROMPT-LOG.md` | ✅ maintained |
| D3 | A 2–5 min video **or** screenshots of the setup and both features | `docs/SCREENSHOTS.md` — 10 shots from a single pass | ✅ |
| D4 | A written code review for Part 2 | `docs/CODE-REVIEW.md` — 16 findings + a "how it is solved in Part 1" table | ✅ |

---

## Architecture

**Money is integer cents** (`bigint`), no floats. Formatting lives only in `App\Support\Money`;
the API returns both `cents` and `formatted`.

**The balance is never mutated directly.** The only place that moves the balance is
`WalletService`: a row in `wallet_transactions` + an update of `users.balance_cents` inside a
single `DB::transaction()` under `lockForUpdate()`. The sum of a player's ledger entries always
equals their balance.

**Business logic lives in `PromoService`**; controllers are thin. Validation goes in a Form
Request. Responses go through API Resources (`JsonResource::withoutWrapping()`, envelopes named
explicitly). Statuses and reasons are backed enums in `App\Enums`. A business-rule refusal is a
`PromoException` with its own `render()` producing a 409.

### Tables

`users` (on top of the standard columns): `balance_cents` bigint, **not in `#[Fillable]`**

`promo_codes`: `code` (unique, stored uppercased), `bonus_amount_cents` **unsigned** bigint,
`expires_at` nullable

`promo_claims`:
- `user_id`, `promo_code_id` **nullable** (for attempts on a non-existent code)
- `code_attempted`, `status`, `rejection_reason` nullable
- `amount_cents` nullable (null for `rejected`), `revoked_at` nullable
- **partial unique** `(user_id, promo_code_id) WHERE status <> 'rejected'` — the main protection
  against double crediting; `revoked` also blocks a repeated claim, otherwise a
  revoke → claim → revoke loop would appear

`wallet_transactions`: `user_id`, `promo_claim_id`, `type`, `amount_cents` (signed),
`balance_after_cents`, unique `(promo_claim_id, type)` — **a double revoke is impossible even
under a race**, because the second insert fails on the index.

### API

| Method | Path | Success | Errors |
|---|---|---|---|
| POST | `/api/login` | 200 `{token, player}` | 422 |
| GET | `/api/me` | 200 `{player}` | 401 |
| POST | `/api/logout` | 200 | 401 |
| POST | `/api/promo/claim` | 200 `{claim, bonus_amount, balance}` | **422** format · **409** `{reason}` |
| GET | `/api/promo/history?status=&page=&per_page=` | 200 paginator | 422 |
| PATCH | `/api/promo/{claimId}/revoke` | 200 `{claim, balance}` | **409** `already_revoked`/`not_applied`/`insufficient_balance` · **404** someone else's claim |

The split **422 = format** vs **409 = business rule** directly covers T1.11 and T1.12.
Someone else's claim → **404, not 403** — so as not to leak the fact that another player's
records exist.

> **Limitations to know and not to overrate.** Partial unique indexes exist in SQLite and
> PostgreSQL but **not in MySQL** — there this invariant would have to be expressed differently.
> Under SQLite row-level locking is degenerate, so `lockForUpdate()` here is decorative and the
> real guarantee comes from the unique indexes. SQLite only accepts `CHECK` at table creation
> time, so the positivity of the amount rests on `unsigned` and on validation.

### Frontend

Vue 3 + Composition API + `<script setup>`, Vite, **Tailwind CSS 4**, axios. Inside the Laravel
repo (`resources/js`), with no vue-router and no store — for two screens they are unnecessary.

`api.js` (axios instance, Bearer from localStorage, a 401 interceptor → the `auth:expired` event) ·
`LoginForm.vue` · `BalanceCard.vue` · `PromoClaimForm.vue` (idle/loading/success/error) ·
`PromoHistory.vue` (filter, pagination, the revoke button only when `can_revoke`) · `ConfirmDialog.vue`

Confirmation is **our own modal, not `window.confirm`**: the native dialog blocks browser
automation during verification and looks alien.

Money operations are protected from a double submit **in the handler**, not just via `disabled`:
Enter in the input submits the form bypassing the button.

### Tests

**Backend — PHPUnit** (`php artisan test`): a successful claim · 422 on format · 409
`not_found`/`expired`/`already_used` · a rejected attempt landed in the history · another
player's claim is untouched · a race on the unique index → 409 · pagination · the status filter ·
a revoke changes the balance and the status · **a repeated revoke → 409** · a revoke with
insufficient funds → 409 · someone else's claim → 404 · the balance and the ledger sum always
reconcile.

`SchemaGuardsTest` writes **directly to the DB, bypassing the services** — proving that the
invariants rest on the schema and will survive a bug in the code.

**Frontend — Vitest + @vue/test-utils** (`npm test`): the form states, the double-submit guard,
token handling on login, balance rendering.

Critical tests are verified **by mutation**: we temporarily break the code and make sure the test
fails. A test that does not fail on broken code is worth nothing.

---

## Workflow (agreed)

- **We go step by step.** One step = one logical chunk from the list below.
- **We cut vertical slices**: first the backend of a feature with tests, immediately followed by
  its frontend. Not "the whole API, then the whole UI".
- **Having finished a step, I go through four points** and only then stop:
  1. a check against the requirement matrix above — what is covered, where the discrepancies are;
  2. a check for surplus code — dead code, scaffolding leftovers, commented-out chunks, duplication;
  3. tests — **both** commands (`php artisan test` and `npm test`) + `pint --test`, showing the
     real output; plus a live check in the browser;
  4. a commit proposal with a title and a short description.
- **After approval — commit and push right away.** I do not ask separately about pushing.
  Approval applies to that specific commit, not to all the following ones.
- **I never commit or push without approval**, but I do not wait silently either: the initiative
  is mine, the decision is the user's.
- The commit description is short: a conventional-commits-style subject + 2–3 lines of substance.
  We do not create branches; history is linear on `main`.
- Commit messages are passed via `git commit -F <file>`: PowerShell mangles quotes inside `-m`,
  this has already happened.
- Questions about the code and fixes at any step are normal — we go back and rework.

## Commit plan (U3)

Done:

1. ✅ `chore: init Laravel 13 + SQLite`
2. ✅ `docs: project instructions, README and work log`
3. ✅ `docs: define per-step completion protocol`
4. ✅ `chore: wire Vue 3 and axios into Vite`
5. ✅ `feat(auth): sanctum tokens, login endpoint and seeded players`
6. ✅ `feat(ui): login form and balance card`
7. ✅ `feat(db): promo codes, claims and wallet ledger`
8. ✅ `refactor(ui): drop demo credentials from the login form`
9. ✅ `feat(api): claim a promo code`
10. ✅ `feat(ui): promo code form with loading, success and error states`
11. ✅ `test(ui): cover Vue components with Vitest`

Remaining:

12. ✅ `feat(api): promo claim history`
13. ✅ `feat(ui): promo history list` ← **Ticket 1 closed**
14. ✅ `feat(api): revoke a promo claim` + tests (repeated revoke, insufficient balance)
15. ✅ `feat(ui): revoke button + confirm dialog` + tests ← **Ticket 2 closed**
16. `docs: code review`

`docs/PROMPT-LOG.md` is maintained **in parallel with the work**, not reconstructed at the end.

---

## Part 2 — the code review (`docs/CODE-REVIEW.md`)

Around 14 findings are already visible; I will group them by severity:

**Blockers:** a GET for a state-changing operation (caching, prefetch, CSRF via `<img>`, retries) ·
no authentication or authorisation at all — `{player}` in the URL lets anyone be credited ·
`$request->amount` without validation: a negative value **debits** funds, a string gives type
juggling · a race condition on `+=` without a transaction and a lock — the classic lost update ·
no protection against repeated crediting, the endpoint is not idempotent.

**Significant:** no ledger/audit trail for a financial operation · floats on money · no rate
limiting · `success: true` always, errors are not handled · no tests.

**Frontend:** `axios.get` for a mutation · no try/catch — an unhandled rejection · no loading
state and no error display · mutating `this.player.balance` directly (probably a prop) instead of
emitting.

---

## Verification before submission

1. `php artisan test` — all green
2. `npm test` — all green
3. `php vendor/bin/pint --test` — clean
4. `npm run build` — the build passes
5. `php artisan migrate:fresh --seed && php artisan serve` + `npm run dev`
6. A pass in the browser (Chrome automation, clicks **by `ref`**, not by coordinates): login →
   claim a valid code → the balance grew → a repeated claim → "already used" → an invalid format
   → 422 → the history with the filter and pagination → a revoke with confirmation → the balance
   and status updated → a repeated revoke → an error
7. A final pass over the **requirement matrix** above — every row ticked
8. A check that the commit history really is step by step

---

## Out of scope

Registration, roles/admin panel, promo code usage limits, multi-currency, wagering, deployment,
Docker, ESLint. If any of this becomes necessary — we discuss it separately.
