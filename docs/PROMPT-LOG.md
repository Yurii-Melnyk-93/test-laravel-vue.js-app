# Prompt log

A chronology of the work with the AI tool (**Claude Code**, the Opus 5 model) on this assignment.
It is kept **in parallel with the work**, not reconstructed at the end — which is why it contains
both wrong turns and fixes, rather than a tidied-up result.

Format: the request → what the tool did → what had to be fixed.

---

## Stage 0. Preparing the repository

**Prompt:** "connect this folder to https://github.com/Yurii-Melnyk-93/test-laravel-vue.js-app.git"

The folder turned out to be empty, and so was the remote repo. Did `git init -b main` +
`remote add origin`.

**Problem:** `git ls-remote` failed with `unable to get local issuer certificate (20)`.
Diagnosed as HTTPS interception by the antivirus. The fix — `http.sslBackend=schannel` (the
Windows certificate store). First set locally in the repo, then moved to the global config, and
the local duplicate was removed.

**Prompt:** "where is this email from? yurii@projectsimple.ai" → the origin was shown
(`C:\Users\Admin\.gitconfig`).

**Prompt:** "let's do it everywhere" + a screenshot of the GitHub settings.

**A fix initiated by the tool:** the screenshot showed *Keep my email addresses private* enabled.
Writing the gmail address in directly would have leaked the real mail into the commit history and
could have blocked the push. Instead of silently complying, a question was asked and the noreply
address `92314891+Yurii-Melnyk-93@users.noreply.github.com` was chosen.

**One more fix:** the tool also changed `user.name`, which nobody asked for — reverted.

---

## Stage 1. Planning

**Prompt:** the full text of the test assignment + "let's plan it out right away and keep adding
all the necessary descriptions to the code and entries to the files. the main thing is not to
miss a single requirement from the assignment".

The assignment text was broken down into **30 atomic requirements** with identifiers (U1–U3,
T1.1–T1.24, T2.1–T2.9, X1–X3, D1–D4), each mapped to a place in the code. The matrix lives in
`docs/PLAN.md` and doubles as the pre-submission checklist.

**A trap found in the brief.** Ticket 1 requires a history filter by status "successfully applied /
**rejected**". Therefore **failed attempts must be stored in the DB too** — otherwise the filter
filters emptiness. This is not visible from a cursory reading of the ticket.

Before planning, the tool asked 4 questions where the decision belongs to the developer:

| Question | Chosen |
|---|---|
| How to install PHP (the machine had neither PHP nor Composer) | Laravel Herd for Windows |
| A revoke when the player has already spent the bonus | Forbid it with `409 insufficient_balance` |
| Vue style | Composition API + `<script setup>` |
| Scope of authentication | Sanctum + a minimal login form, 2 seeded players |

**Iteration:** the first version of the plan assumed autonomous execution with commits.
Rejected: "let's do it manually, I want to see what you wrote… don't commit yourself until I ask".
A "Workflow" section was added to the plan: step → show the code → pause, commits only on an
explicit request.

**Prompt:** "write down and remember this plan in case of a sudden interruption."
The plan was duplicated into `docs/PLAN.md` + written into the tool's file memory.

---

## Stage 2. Environment and scaffolding

**Prompt:** "installed" → a check showed that Herd had only installed the GUI application, while
the `~/.config/herd/bin` directory was empty: PHP is downloaded on first launch.

**Prompt:** "launched" → PHP 8.4.24 + Composer 2.10.1, all 12 required extensions in place.

**Problem:** `composer create-project` failed with the same certificate error as git in stage 0.
Composer named the cause outright — **Avast Firewall**. The root `CN=Avast Web/Mail Shield Root`
was found in the Windows store.

**A wrong turn:** first a bundle was assembled from the Windows roots and `SSL_CERT_FILE` was set —
it did not work. The reason: Herd **explicitly** writes `curl.cainfo` and `openssl.cafile` into its
own `php.ini`, and an explicit directive overrides the environment variable.

**The fix:** the Avast root was appended to the very bundle Herd points at
(`~/.config/herd/config/php/cacert.pem`, with a backup next to it). Certificate verification was
**not** disabled — we trust the same root Windows already trusts. `npm ping` worked without any
changes.

**Scaffolding:** `composer create-project laravel/laravel` into a temporary directory and then
moved into the project (the folder already contained `.git`, `CLAUDE.md` and `docs/`, while
`create-project` requires an empty one). The result: **Laravel 13.25.0**, SQLite by default,
migrations passed.

**Fix:** the tool added rules for `*.sqlite` to the root `.gitignore`, then discovered that Laravel
ships its own `database/.gitignore` with `*.sqlite*` — it removed its own lines so as not to leave
a duplicate.

**A fix from the user:** "where is step 2?" — the tool had done `CLAUDE.md` out of order (before
the scaffolding) and skipped the README, so the project still had Laravel's stock README. A project
README was written.

---

## Stage 2.5. Cleaning up the scaffolding

**Prompt:** "if the first 2 steps are closed, let's check for surplus code and run the existing
tests."

The scaffolding tests passed (2 passed), but both were stubs: `assertTrue(true)` and a check of the
stock welcome page. Real coverage: zero.

Found and **removed**: the `inspire` demo command from `routes/console.php`, both `ExampleTest`s.
The `routes/console.php` file was not deleted — `bootstrap/app.php:11` references it, so only its
contents were cleared.

**Decisions on discrepancies with the plan:**

| What | Decision |
|---|---|
| The scaffolding brought Tailwind CSS 4, which was not in the plan | keep it — it saves time on the forms, the table, the states and the modal |
| `vite.config.js` pulled a font from bunny.net during the build | remove it — a network request at build time and extra surface for that same Avast |
| The plan said Pest, the scaffolding gave PHPUnit 12.5 | keep PHPUnit — zero extra dependencies |
| The `laravel/laravel` metadata in `composer.json` | leave as is (the user's decision) |

**Found in passing:** `app.css` was missing `@source '../js/**/*.vue'`. Without it Tailwind does not
scan the Vue components and strips every class used in them from the production build — the layout
would have fallen apart precisely on `npm run build` while looking fine in dev mode. Added
immediately.

**Fix:** the first comment in `routes/console.php` was written in Ukrainian — cSpell immediately
raised 5 warnings. The neighbouring code (all of Laravel) is in English, so it was agreed:
**comments in code — English**.

After the cleanup: `php artisan test` → `No tests found` (an honest state — real tests will appear
together with the API), `route:list` shows 4 routes, the application boots.

---

## Stage 2.7. Vue + axios

**Prompt:** "let's do step 3."

Vue 3 + axios into the existing Vite, `App.vue` as the shell, `app.blade.php` instead of the stock
welcome page. `api.js` attaches `Bearer` to every request and clears the token on a 401.

**Verified on the build, not only in dev mode:** the classes used **only** in `.vue` files
(`max-w-3xl`, `tracking-tight`, `text-slate-500`) are present in the compiled CSS. This confirmed
that the `@source` added earlier really is needed.

**Nearly threw away something needed:** the `concurrently` package looked dead — no npm script
invokes it. Before deleting it we checked: `DevCommand.php:67` shows that on **Windows**
`php artisan dev` runs precisely through it. Kept.

---

## Stage 2.8. Authentication (Sanctum)

**Prompt:** "let's do step 4."

`php artisan install:api` → `routes/api.php` + Sanctum. Then `HasApiTokens`, the `balance_cents`
column (bigint, cents), `POST /api/login`, `GET /api/me`, `POST /api/logout`, and a seeder with two
players.

**Security decisions taken right away rather than "later":**

- `balance_cents` is **deliberately not in `#[Fillable]`** — the balance cannot change through mass
  assignment, only through the service. The factory had to gain a `withBalance()` state that sets
  the value via `forceFill`.
- Login returns **an identical response** for "no such email" and "wrong password" — otherwise the
  endpoint becomes a tool for enumerating registered addresses. There is a dedicated test that
  compares the response bodies byte for byte.
- `logout` revokes **only the current token**, not all of the player's sessions.
- `throttle:6,1` on login.

**Found and fixed during verification:** `POST /api/login` returned the player unwrapped, while
`GET /api/me` returned it wrapped in `data` (the standard `JsonResource` behaviour). The frontend
would have had to parse two different formats. Added `JsonResource::withoutWrapping()` and explicit
envelope keys; pagination keeps its own `data/meta/links` structure.

**A test that genuinely failed.** `test_logout_revokes_only_the_current_token` expected a 401 after
logging out and got a 200. Against a live server logout worked correctly — so the issue was in the
test environment: the guard caches the resolved user for the lifetime of the test application.
Added `forgetGuards()` between requests plus a check of the DB state. Had we "fixed" it by bending
the expectation to 200, the test would have silently covered up working code.

**Pint** found mixed line endings in `bootstrap/app.php` — a consequence of `install:api`. Fixed;
`pint --test` is clean.

Result: 7 tests, 27 assertions, all green.

---

## Stage 2.9. The DB schema

**Prompt:** "next."

Three tables (`promo_codes`, `promo_claims`, `wallet_transactions`), three backed enums, models,
factories, a promo code seeder and `SchemaGuardsTest`.

**The key decision — the invariants live in the schema, not in the code.** The tests in
`SchemaGuardsTest` deliberately write **directly to the DB, bypassing the services**: an `if` check
does not save you from two simultaneous requests that both passed it before either one wrote
anything.

**Fixed an inaccuracy of my own in the README.** It previously said that the unique indexes "work
the same on SQLite and MySQL". For a **partial** index that is untrue — MySQL does not support
them. The README was rewritten with an honest list of limitations: partial indexes exist in SQLite
and PostgreSQL, `lockForUpdate()` is decorative under SQLite, and SQLite only accepts `CHECK` at
table creation time, so the positivity of the amount rests on `unsigned` rather than on a `CHECK`.

**Tests that almost passed for nothing.** At first the checks were written as
`expectException(QueryException::class)`. Such a test goes green on **any** query error — including
a typo in the factory. An `assertViolates()` helper was added that requires the message to contain
the specific pair of columns. It turned out that SQLite reports the columns rather than the index
name — the first attempt, which keyed on the name, failed, and that was useful.

**Broke a file with my own command.** Replacing lines via `Set-Content -Encoding utf8` in
PowerShell 5.1 added a BOM, and PHP failed with `Namespace declaration statement has to be the very
first statement`. The BOM was removed and the whole repository was checked for BOMs — there are
none anywhere else.

Result: 14 tests, 35 assertions.

---

## Stage 3. Ticket 1 — crediting a bonus

**Prompt:** "let's do step 7."

`POST /api/promo/claim`: `ClaimPromoRequest` with a regex, `PromoService`, `WalletService`,
`PromoException`, `PromoClaimResource`, `PromoController`, 17 tests.

**The subtlest spot — a rejected attempt must survive the error.** The naive implementation wraps
all the logic in one transaction, but then the `throw` rolls back the refusal record along with
everything else and nothing is left in the history. That is why `reject()` is called **outside**
the crediting transaction. Verified live: after three failed attempts there really are three
`rejected` rows in the DB.

**Two lines of defence against double crediting, not one:**
1. an explicit `alreadyConsumed()` check — the fast, readable path for the ordinary case;
2. `catch (UniqueConstraintViolationException)` — insurance for a race where two requests passed
   the check before anyone managed to write.

The check's predicate deliberately mirrors the index predicate (`status <> 'rejected'`) so that the
rule does not scatter across two places with different wordings.

**A branch the tests did not cover.** All 17 tests passed on the first run — and that was
suspicious: the race `catch` is unreachable, because the preceding check intercepts the duplicate
earlier. A test was written that reproduces the race for real: a `PromoClaim::creating` listener
inserts a competing row exactly between the check and the insert.

**Verified by mutation.** To make sure the new test does not go green by accident, the exception
handling was temporarily replaced with a rethrow — the test failed with a **500** and
`UNIQUE constraint failed` at `PromoService.php:47`. So it really does reach the branch in
question. The code was restored.

**Validation does not write to the history.** A conscious decision: a `422` is not an attempt to
apply a promo code but an input error. Otherwise the history would fill up with typos. There is a
dedicated test.

---

## Stage 3.5. The promo code form

**Prompt:** "let's continue" → `PromoClaimForm.vue` with the `idle / loading / success / error`
states.

**Decision:** the new balance is taken **from the claim response**, not from a second request to
`/me` — that is exactly why requirement T1.9 asks for the balance to be returned. The error text is
displayed as the server phrased it, while `reason` remains for machine logic: keeping a second copy
of the wordings on the client guarantees they will drift apart.

**Prompt:** "you also need to block the button while the request is running, to avoid a double
click."

The button was already disabled via `canSubmit`, but the remark is right in substance: that is not
enough. The form is also submitted by the **Enter** key in the input — a path that bypasses the
button. A guard was added inside the handler (`if (status === 'loading') return`) plus disabling of
the input itself.

**Verified by measurement, not by inspection:** three quick Enters in a row produced exactly **one**
`POST /api/promo/claim`, and the balance changed from 150.00 to 200.00. Without the guard the second
and third requests would have gone to the server and returned 409 — the money would not have been
doubled thanks to the index, but the user would have seen an error instead of a success.

---

## Stage 3.6. Frontend tests

**Prompt:** "let's not forget to write tests for the frontend too, and to check directly in the
browser."

A fair remark: the backend had 31 tests, and the three Vue components had none. The frontend was
only being checked by eye in the browser, so a regression in it would have gone unnoticed.

Installed **Vitest + @vue/test-utils + jsdom**, a separate `vitest.config.js` (so as not to touch
`vite.config.js`, which the Laravel plugin owns), and the `npm test` and `npm run test:watch`
scripts. 15 tests over three components.

**Two failures, both errors in the tests rather than in the code:**

1. "called 5 times instead of 1" — the mock's counter accumulated between the tests in the file.
   That is, the "called once" assertion was actually measuring the whole suite. Fixed with
   `clearMocks: true`. Worth remembering: without it such a test **can never fail honestly**.
2. The button stayed `disabled` after a success — and that is correct behaviour: the input is
   cleared, and an empty code cannot be submitted. The expectation in the test was wrong, not the
   code.

**Verified by mutation.** To make sure the double-submit test really catches something, the guard
was temporarily removed — the test failed with "3 calls instead of 1". The code was restored.

---

## Stage 3.7. Reconciling the plan

**Prompt:** "check whether our plan needs adjusting."

Eight discrepancies were found. The most unpleasant one was **the same inaccuracy for the second
time**: the plan still claimed that the unique indexes "work the same on SQLite and MySQL". That was
fixed in the README during the schema stage, but it never carried over into the plan, so the
document remained untrue for a *partial* index.

The others: `CHECK > 0` instead of the actual `unsigned`; "Pest" instead of PHPUnit; no mention of
Vitest or Tailwind at all; `npm run lint` in the verification list even though no such script exists
(ESLint is not configured — moved into "out of scope" explicitly, so that it is a decision rather
than a forgotten item); a stale Context about an empty folder; file names that had diverged from the
actual ones.

The requirement matrix gained a status column — it is now a living checklist rather than a snapshot
of the intent.

---

## Stage 3.8. Claim history

**Prompt:** "let's do the next step from the plan."

`GET /api/promo/history`: `PromoHistoryRequest`, pagination, the status filter, 13 tests.

**A decision on top of the brief:** the brief names two filter values, but after Ticket 2 a third
state appears — `revoked`. All three plus "all" were supported, otherwise revoked claims would
disappear from the list and that would look like a bug.

**Small things that are easy to miss:**
- an unknown status returns **422** rather than being silently ignored — otherwise a "found nothing"
  filter is indistinguishable from an "I did not understand you" filter;
- `per_page` is capped at 50, so the whole table cannot be pulled in one request;
- `withQueryString()` — without it the second page quietly loses the filter. There is a dedicated
  test for that.

**Verified by mutation:** the query was switched from `$request->user()->promoClaims()` to
`PromoClaim::query()` — the isolation test immediately saw 5 foreign records instead of 2. So this
endpoint's most important guarantee really is being verified.

---

## Stage 3.9. The history list — Ticket 1 closed

**Prompt:** "go."

`PromoHistory.vue`: a list with date, amount and status, a filter over four values, pagination by 5,
an empty state, handling of a load error. 9 Vitest tests.

**Found by exactly the thing a browser check exists for.** The tests passed and the component looked
right — but on entering a non-existent code an error appeared while the attempt was missing from the
history until you reloaded the page. A rejected attempt **is recorded** on the server, so the list
went stale immediately.

Fixed with a separate `recorded` event: the form tells the parent that the server wrote something,
and that happens on a success **and on a 409**. A `422` validation error emits no event — it never
reached the domain, so there was nothing to write. Both branches have tests.

**A small thing that is easy to miss:** changing the filter returns you to the first page.
Otherwise, standing on page 3 and switching the filter, the player would see an empty list — page 3
may simply not exist in the filtered set.

The link to the parent goes through `defineExpose({ reload })` and a template ref, without a fake
prop like `refreshKey`.

---

## Stage 4. Ticket 2 — revoking a claim

### Stage 4.1. `PATCH /api/promo/{claimId}/revoke`

**Prompt:** "let's continue from where we stopped."

The revoke refusal reasons were moved into a separate `RevokeRefusal` enum (`not_applied`,
`already_revoked`, `insufficient_balance`) rather than appended to `RejectionReason`. The difference
is fundamental: the reason for a rejected claim **is stored in the history row**, while the reason
for a refused revoke is stored nowhere — it only travels to the client in the 409, because a refused
revoke changes no rows.

**The balance check lives in `WalletService`, under the same lock as the write.** The temptation was
to check it in `PromoService` before the call — but then the decision would be made from one read of
the balance while the write went off another. Under the lock, the read and the write look at the
same value. The guard is phrased generally — "no movement drives the balance negative" — rather than
"a revoke checks its own amount".

A repeated revoke is cut off twice: by the status check and by the **unique
`(promo_claim_id, type)` on the ledger**. The second line is needed for the race — two requests can
both see `applied`. The race test inserts a competing row in the `creating` hook, as in claim.

**Honestly about this test's limits:** the competing row is inserted inside the same transaction, so
the rollback takes it away too — on a real connection it would survive. That is why the test asserts
only what it genuinely proves: **this** request committed neither a ledger entry nor a balance
change. The assertion was rewritten after a failure instead of being bent towards green.

Mutation check of the guard: `if ($balanceAfter < 0)` → `if (false)`, and the insufficient-balance
test went from 409 to 200. The guard really is held by the test.

Someone else's claim is looked up via `$player->promoClaims()->findOrFail()` — a 404, not a 403, so
that the response does not confirm the existence of a foreign id.

**A trap in the tooling, not in the code.** A live check of the "the bonus has already been spent"
scenario twice showed a successful revoke instead of a 409. The cause turned out to be PowerShell:
it parsed the `>` in `->save()` as a redirect, created a file named `save()` in the root and **did
not perform the write** — the balance stayed as it was. The application was right; the test script
was wrong. The file was removed.

Backend: 52 tests (was 44), `pint --test` clean.

### Stage 4.2. The "Revoke" button and our own modal — Ticket 2 closed

**Prompt:** "commit" (after approving step 14) → then step 15.

`ConfirmDialog.vue` — a generic modal (`open`, `title`, `message`, `confirmLabel`, `cancelLabel`,
`busy`) rather than `window.confirm`: the native dialog blocks browser automation and provides
neither an "in progress" state nor focus handling. It closes via the button, a backdrop click and
Escape; while the request is running closing is **forbidden**, so as not to hide an operation whose
outcome is not visible.

**A revoke error does not displace the list.** It gets its own `revokeError` alongside
`errorMessage`: a load error legitimately hides the list (there is no data), a revoke error does not
— the rows are there and they are what you need to look at.

After a **failed** revoke the history is refetched as well: a 409 usually means our copy of the row
is stale (it was revoked in another tab), and a button that cannot work should disappear on its own.

**A test that was initially worth nothing.** "One request for two clicks" passed even with the guard
removed — because the `await` between the clicks let Vue repaint the button as `disabled`, and the
test was measuring the attribute rather than the handler. It was rewritten so that both clicks fly
in the same tick: after that, without the guard the test fails (2 calls instead of 1). Mutation
confirmed it.

**Extending the layout** was not needed: `can_revoke` had been coming from the API since Ticket 1,
and space for the button in the row was already provided for.

Frontend: 36 tests (was 24), `npm run build` passes.

**Browser check.** Claim → balance 50.00 → 150.00, a modal with the amount and the code,
confirmation → balance 50.00, status "Revoked", the button gone. The error branch: the balance was
lowered bypassing the application (`tinker`), revoke → a red banner "Insufficient funds…", the list
in place, the status unchanged.

**Honestly about two things.** The browser automation's own clicks stopped reaching the page in this
session (focus stayed on `body`), so the scenario was run with real DOM events through
`javascript_tool` — the application does work for real that way, but the layout had to be checked
against a screenshot. And: after an `insufficient_balance` refusal the balance card shows the old
value — the balance was changed bypassing the application, and the server does not return a balance
in a 409. We are not introducing polling for that.

---

## Stage 5. Deliverables

### Stage 5.1. Demonstration: a screenshot sequence

**Prompt:** "or a sequence of screenshots showing the project's setup and both features working" —
a clarification of a point in the brief that changed the plan: instead of leaving the demo "up to
the user", the shots were taken right away.

`docs/SCREENSHOTS.md` — 10 shots of **a single pass** over a clean DB, each with an explanation of
what exactly it proves.

The frames were chosen not as "let's show that it works" but by the points being graded: a repeated
claim with an unchanged balance, the difference between 422 and 409 (and the fact that a 422 does
**not** write a history row), rejected attempts in the history with their reasons, a revoke with a
separate negative ledger entry, a revoked code that stays used, and the isolation of the two
players.

Along the way two inaccuracies in the README were fixed: the `PATCH /revoke` row was **after** the
paragraph about the history parameters and fell out of the API table, and the tests were called Pest
although they are PHPUnit.

### Stage 5.2. The code review (Part 2)

**Prompt:** "added the code review, check it" — the review was written by the user; the AI was
checking, not writing.

The check confirmed the 13 original findings and added four more plus one clarification:

- **CSRF in the point about GET** — the wording could be read as "the vulnerability is guaranteed".
  In reality the route lives in `routes/api.php`, where sessions and `VerifyCsrfToken` are not wired
  in at all: the `<img>` vector only fires with session authentication. Clarified, because a
  reviewer will latch onto such an inaccuracy.
- **Money as floats** — an end-to-end principle of this very project, and the point was missing from
  the review.
- **`success: true` as a constant** — not "an incomplete response" but the absence of an error
  branch altogether: an invalid `amount` returns a 500 from PHP rather than a domain error.
- **No tests** on a method that moves money.
- **A contradiction of the HTTP contract**: GET is required to be safe and idempotent, and this one
  is neither.

At the end a "how these same problems are solved in Part 1" table was added — it stitches both parts
of the assignment together: every finding has a counterpart in the repository's code.

**A side effect worth mentioning.** Vite crashed with `EBUSY: resource busy or locked, watch
'docs/CODE-REVIEW.md'` — the watcher could not attach to a file open in the editor. Diagnosing it
took a minute, but the fact itself is useful: the dev server crashes over a file that has nothing to
do with the build.

### Stage 5.3. The final audit

**Prompt:** "let's do the final check that all the conditions are met, there is no surplus code, no
code that would be logical to optimise, comments in the code are in English, all the tests pass, and
a final check in the browser."

**One genuine duplication was found:** extracting the error text from an axios response was written
**four times** across three components. Moved into `resources/js/errors.js` →
`messageFrom(error, fallback)` with four dedicated tests.

The side effect turned out to be an improvement: previously a network failure in the history showed
"Failed to load the history" even though the request never reached the server. Now the text in that
case is precise — "Failed to reach the server". The test that pinned the old phrase was rewritten
**deliberately**: the behaviour changed, rather than the test being bent towards the code.

Two minor inconsistencies: `__()` around a Ukrainian string in `AuthController` with no `lang/`
catalogue present (removed), and an ignored return value from `PromoService::revoke()` (now used).

**Comments:** Cyrillic in PHP remained only where it belongs — user-facing texts
(`RejectionReason`, `RevokeRefusal`, validation messages) and the seeded player names. Not a single
non-English comment in `app/`, `tests/` or `resources/js`.

**The invariants were verified on data generated by the browser**, not only in tests: the balance
`20000` = the initial `5000` + the ledger sum `15000`; zero negative balances; zero duplicate
`(promo_claim_id, type)` pairs; no applied claim without a credit and no revoked one without a debit.

**Verified live in the browser:** a wrong password, a claim, a repeated claim, a 422, an expired
code, a non-existent code, pagination across 2 pages, three filters, Escape and "No, keep it"
without a request, a confirmed revoke, and separately — **a stale button**: the claim was revoked
bypassing the UI, a click on the button that remained returned a 409 "already revoked", the list did
not disappear, and after the refetch the button vanished on its own.

---

## Observations about working with the AI tool

- The most time was eaten **not by the business tasks but by the environment**: TLS interception by
  the antivirus produced three different failures (git, Composer, and the failed workaround via
  `SSL_CERT_FILE`) from a single cause.
- Breaking the brief down into numbered requirements **before** writing code immediately surfaced
  the non-obvious requirement to store rejected attempts.
- A screenshot of the GitHub settings changed a decision that would otherwise have been carried out
  literally and wrongly — context in the form of a picture worked better than a description in words.
