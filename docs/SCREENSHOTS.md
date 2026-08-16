# Setup and both features in screenshots

The assignment allows a video **or a sequence of screenshots**. This is the sequence: a single
pass over a clean DB (`php artisan migrate:fresh --seed`), from setup to both features.
The shots were taken in Chrome at `http://127.0.0.1:8000/`, with no editing and no retouching.

## 0. Setup

```
$ php artisan migrate:fresh --seed
  INFO  Seeding database.
  Database\Seeders\PromoCodeSeeder .. 20 ms DONE

$ php artisan serve --port=8000
  INFO  Server running on [http://127.0.0.1:8000].

$ npm run dev
  VITE v8.2.1  ready in 763 ms
  ➜  Local:   http://localhost:5173/
  LARAVEL v13.25.0  plugin v3.2.0
  ➜  APP_URL: http://localhost:8000
```

Tests on both sides:

```
$ php artisan test
  Tests: 52 passed (205 assertions)

$ npm test
  Test Files  5 passed (5)
       Tests  36 passed (36)

$ php vendor/bin/pint --test
  PASS
```

## 1. Login form

![Login form](screenshots/01-login.jpg)

The project is up and the frontend is served through Vite. There is no registration — it is out
of scope, the players are seeded (`olena@example.com` / `ihor@example.com`, password `password`).

## 2. Logged in: balance and empty history

![State after login](screenshots/02-logged-in.jpg)

Balance **50.00**, history empty. The player is determined from the token — no endpoint accepts
their identifier from the request body or the URL.

## 3. Ticket 1: bonus credited

![Successful promo code claim](screenshots/03-claim-success.jpg)

`WELCOME100` → balance **50.00 → 150.00**, a message with the amount, and a row with the
"Applied" status appeared in the list. The balance and the amount came back in the claim
response — we do not make a separate request for the balance.

## 4. Applying the same code again

![Repeated claim rejected](screenshots/04-already-used.jpg)

**The key frame.** The balance stayed at 150.00 — there is no double crediting. The protection
rests not on an `if` but on a **partial unique index**
`(user_id, promo_code_id) WHERE status <> 'rejected'`, so two simultaneous attempts will not get
through either: the second one fails on the index.

The second requirement of the assignment is visible right here as well: **the rejected attempt is
recorded in the history** with its reason. Otherwise the "rejected" filter would have nothing to
filter.

## 5. A format error is 422, and it writes nothing to the history

![Validation error](screenshots/05-validation-422.jpg)

`ABC-1` does not match the format `^[A-Za-z0-9]{6,12}$` → **422**. The history still has the same
two rows: the request never reached the domain, so there was nothing to record.

These are different codes for different things: **422 — format**, **409 — business rule** (with a
machine-readable `reason` in the body).

## 6. Expired code and the status filter

![Expired code and the filter](screenshots/06-expired-and-filter.jpg)

`OLDCODE99` → "The promo code has expired" (409, `reason: expired`). The "Rejected" filter shows
both failed attempts with their reasons. Pagination is 5 rows per page, and the filter is
preserved in the page links.

## 7. Ticket 2: revoke confirmation

![Confirmation modal](screenshots/07-confirm-dialog.jpg)

The "Revoke" button appears only next to applied claims — driven by the `can_revoke` field from
the API, not by a rule duplicated on the frontend.

The modal is **our own, not `window.confirm`**: it shows the amount and the code, it closes on
Escape and on a backdrop click, and closing is blocked while the request is in flight.

## 8. Revoked

![After the revoke](screenshots/08-revoked.jpg)

Balance **150.00 → 50.00**, status "Revoked", the button is gone. The debit went as a separate
**negative entry** in `wallet_transactions` — the sum of a player's entries always reconciles
with their balance.

A repeated revoke is impossible: the unique `(promo_claim_id, type)` on the ledger will not let a
second debit be inserted even under a race, and the client gets a 409 `already_revoked`.

## 9. A revoke does not free up the promo code

![Repeated claim after the revoke](screenshots/09-revoked-code-stays-used.jpg)

`WELCOME100` after the revoke — "already used" again, balance 50.00. This is a deliberate
decision: if `revoked` freed the code up, the claim → revoke → claim loop would print money.

## 10. Players are isolated

![The second player](screenshots/10-other-player.jpg)

Signed in as `ihor@example.com`: their own balance **125.50**, empty history, and the same
`WELCOME100` is available to them. History and balance never overlap.

---

**Not shown in this sequence** (covered by tests, `PromoRevokeTest`): a revoke with insufficient
balance → 409 `insufficient_balance`, the balance does not go negative and the status does not
change; someone else's `claimId` → **404** rather than 403, so as not to confirm that another
player's records exist.
