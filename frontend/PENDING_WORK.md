# Frontend Pending Work & Backend Asks

Living tracker for the frontend team. Updated as items land — tick things off
here rather than opening a new doc.

**Last updated:** 2026-09-08
**Backend baseline:** `bdada98` (PR #35 — live metal balance report)

Every claim below was verified against backend source at that commit, not
against the API doc. Where the two disagree, the source wins and the
discrepancy is listed in [§3](#3-api-doc-corrections).

### Re-verification pass — 2026-09-08

Every item in [§1](#1-frontend-work) and [§2](#2-flagged-to-backend) was
re-checked line by line against the working tree today. **Result: nothing
has been fixed since 2026-09-07, and no item could be marked done.**

That is expected rather than alarming — `schainbackend/` has received no
upstream commits at all in the interval. `git log bdada98..HEAD -- schainbackend/`
returns only our own four deploy commits, and `origin/Backend` is **65 behind
`main` and 0 ahead**, so there is no unmerged backend work in flight either.
Backend asks #2–#20 are therefore all still open, and the four frontend
pending items are still blocked or still deferred for the same reasons.

The pass was not wasted: it turned up **one new deploy blocker** (the API
subdomain does not resolve — see [§0](#0-environments)), **one new backend
ask** ([#21](#21), role-id numbering), and several corrections to line
numbers and claims that had drifted. Items carry a `Re-verified 2026-09-08`
line with the evidence, so the next pass can diff against it rather than
re-deriving everything.

---

## 0. Environments

**Both environments are PostgreSQL. Neither is Supabase.** Earlier revisions
of this doc said the deployed database was Supabase — that was wrong, and any
note anywhere that still says so should be read as meaning Render.

| | Where | Database |
|---|---|---|
| Local dev | `php artisan serve` on `127.0.0.1:8000`, Vite on `5173` proxying `/api` | PostgreSQL 16 in the `schainserp-postgres` Docker container — `127.0.0.1:5432`, db `schains_erp` (see `schainbackend/.env`) |
| Deployed | Render web service, Docker image, Apache on Render's `$PORT` (10000) via `schainbackend/docker/entrypoint.sh` | **Render-managed PostgreSQL** — host `dpg-daf7pklg1s2s73dd1880-a`, port `5432`, db `schains_erp` (confirmed from a deploy log, 2026-09-07) |

### Where the frontend lives

The SPA is deployed on **Vercel** at `https://www.lensnstories.com`
(`www` CNAME → `ee1d8a2940e5c628.vercel-dns-017.com`, apex `216.198.79.1`;
DNS is at GoDaddy, `ns73`/`ns74.domaincontrol.com`). The main domain serves
the frontend — it is **not** and should not be pointed at Render, which would
replace the ERP with Laravel's welcome page.

The API is reached at `https://api.lensnstories.com` (CNAME → the Render
service). `src/lib/api.ts` builds its base from `VITE_API_BASE_URL`, falling
back to the relative `/api/v1` that Vite's dev proxy serves. **That variable
must be set in the Vercel project** —

> #### ⛔ BLOCKER, found 2026-09-08 — `api.lensnstories.com` does not exist in DNS
>
> The subdomain returns **NXDOMAIN**, not a CNAME. Confirmed against two
> independent public resolvers, so it is not our ISP or a local cache:
>
> ```
> $ nslookup api.lensnstories.com 1.1.1.1
> *** one.one.one.one can't find api.lensnstories.com: Non-existent domain
> ```
>
> The other two records in this section are healthy and still match what is
> documented above, which is what rules out a whole-zone problem:
> `www.lensnstories.com` → `ee1d8a2940e5c628.vercel-dns-017.com`, and the
> apex → `216.198.79.1`. **Only the `api` record is missing.**
>
> Consequence: with `VITE_API_BASE_URL` pointing at this host, every API call
> from the deployed SPA fails at DNS resolution — before TLS, before Render,
> before any of the Passport or `APP_KEY` questions below. Those two items are
> still worth fixing, but they cannot be *tested* until this record exists.
> A green Render deploy does not mean the deployed frontend can reach it.
>
> Fix is a GoDaddy DNS change, not a code change: add the `api` CNAME →
> the Render service hostname. Until then the deployed SPA has no working
> backend regardless of what the Vercel env var says.
>
> Not yet established, and worth checking before assuming it regressed:
> whether this record ever existed or was only ever planned. The
> 2026-09-07 note above recorded the *intended* topology, and the deploy-log
> evidence quoted in this section is all Render-side, none of it proves the
> DNS record was ever live.

```
VITE_API_BASE_URL=https://api.lensnstories.com/api/v1
```

Vite inlines `VITE_*` at build time, so changing it needs a redeploy, not a
restart. Without it the built SPA requests `/api/v1/...` from the Vercel
origin and every call 404s.

Practical consequences for us:

- MySQL-only SQL is a hard error in every environment, not just locally —
  see backend ask #18.
- The deployed database already has migration history in it. A fix that
  assumes a clean slate (`migrate:fresh`, renumbering existing migrations)
  will not apply there — see backend ask #1.
- Render's Postgres credentials are not in the repo and not in any `.env`
  we hold; the backend team manages them in the Render dashboard.
- **There is no shell on the Render service.** Anything the deployed app
  needs doing has to be expressible in `schainbackend/docker/entrypoint.sh`
  and run on container start — see [§2b](#2b-what-we-changed-in-schainbackend-2026-09-07).

---

## 1. Frontend work

### Done

| Item | Where |
|---|---|
| Auth layer — bearer token, 401 bounce, logout, session persistence | `lib/authSession.ts`, `lib/api.ts`, `stores/auth.ts`, `main.ts` |
| Item Change unblocked (`stock_in_id` now nullable) + metal lot picker | `components/stock/ItemChangeModal.vue` |
| Customer comments save (`PUT /user-details/{id}/update-cc`) | `components/stock/CustomerContextPanel.vue`, `lib/userDetailsApi.ts` |
| Items OB & CB report | `views/ItemsObcbReportView.vue` |
| Consolidated report | `views/ConsolidatedReportView.vue` |
| Customer Touch Mappings — list, filter, inline active toggle, reassign | `views/CustomerTouchMappingsView.vue`, `lib/customerTouchMappingsApi.ts` |
| Stock Auto Entry — 4 transaction types, NORMAL/GMS/FITEM rows, live previews | `views/StockAutoEntryView.vue`, `types/stockAutoEntry.ts`, `lib/stockApi.ts` |

**Every endpoint shipped in PRs #29–#32 now has a frontend.** What remains
below is cleanup, deferred polish, and work blocked on backend gaps.

### Pending

- [ ] **Re-enable the two Cash Transactions report filters.** The comment in
      `types/cashTransactionReport.ts` says `cash_main_category_id` and
      `bank_entry_from_date`/`bank_entry_to_date` reference un-migrated
      columns and 500 if used. **That is now stale** — migrations
      `2026_08_17_151734` and `2026_08_17_151809` added both columns, and
      `ReportController` reads them (lines 36-42, 87-91). Delete the note and
      wire the filters. `bank_entry_date` in the response should no longer be
      hard `"-"` either.

      *Re-verified 2026-09-08 — still pending, and still unblocked.* The
      stale comment is `types/cashTransactionReport.ts:5-9`; the view has no
      such filters (`grep` for either name in `CashTransactionsReportView.vue`
      returns nothing). Backend readiness re-confirmed at the same line
      numbers: `ReportController:36-42` (`cash_main_category_id`) and
      `:87-91` (`bank_entry_from_date`/`_to_date`), both migrations present.
      Nothing external is blocking this one — it is purely our work.

- [ ] **Cash transaction edit/delete.** The comment in `lib/cashTxnDetailsApi.ts`
      says there is "no show/update/destroy for a single row, so history is
      browsable but not editable". **Also stale** —
      `apiResource('cash-txn-details')` is registered (`routes/api.php:62`) and
      `CashTxnDetailController` has `index` (254), `show` (820), `update` (869)
      and `destroy` (1547). Row-level edit/delete can be built.

      *Re-verified 2026-09-08 — still pending, and still unblocked.* Stale
      comment is `lib/cashTxnDetailsApi.ts:22-23`. All four controller
      methods confirmed at the line numbers above (unchanged). The
      `apiResource` line moved 61 → **62**; corrected inline.

- [ ] **Multi-tab session sync.** Signing out in one tab leaves other tabs
      holding a stale in-memory token until their next request 401s and
      bounces them. A `storage` event listener in `lib/authSession.ts` would
      tighten it. Deliberately deferred — the 401 path already degrades
      correctly.

      *Re-verified 2026-09-08 — still pending, still deliberate.*
      `lib/authSession.ts` has no `addEventListener` and no `storage`
      handler. Unblocked whenever we decide it is worth doing.

- [ ] **Session validation on boot.** Blocked on a backend `/me` endpoint —
      see [§2](#2-flagged-to-backend).

      *Re-verified 2026-09-08 — still pending, still blocked.* Backend ask
      #7 is unchanged: no `/me` route and no `me()`/`user()` method on
      `AuthController`. The standing note at `stores/auth.ts:14` stays
      accurate.

---

## 1b. Local environment — one-time setup after pulling PR #32

Done on this machine 2026-09-03. Every other dev needs the same four steps.

1. **Enable PHP's `sodium` extension.** Passport pulls
   `lcobucci/jwt ^5.6`, which requires `ext-sodium`; without it
   `composer install` refuses to resolve the lock file. Uncomment
   `extension=sodium` in your `php.ini` (`php --ini` shows which one), then
   confirm with
   `php -r "var_dump(extension_loaded('sodium'));"`.
   Do **not** work around it with `--ignore-platform-req` — Passport needs
   sodium at runtime to sign tokens.
2. `composer install` — `laravel/passport` was added in PR #32 and is not
   in anyone's `vendor/` yet.
3. `php artisan migrate` — runs clean as of 2026-09-07, backend ask #1 is
   fixed. If your local database still carries the old workaround (ten
   `migrations` rows for oauth files that no longer exist), clear them once:
   `delete from migrations where migration like '%oauth%' and migration not
   like '2026_09_01_0004%';` — they are orphaned bookkeeping, harmless to
   `migrate` but they would confuse `migrate:rollback`.
4. `php artisan passport:keys` and
   `php artisan passport:client --personal` — no `storage/oauth-*.key`
   exists otherwise and login 500s.

Not needed: Redis. This `.env` uses `CACHE_STORE=database` and the `cache`
table exists.

### Test credentials

`demo_head` / `password` (user_id 999, role_id 3) — verified working.

`head_admin` (user_id 1, role_id 1) and `employee_one` (user_id 2) have
password hashes that match neither `password` nor the other obvious
candidates, so they cannot be logged into. `head_admin` is the only
role_id 1 account, and `AutoEntryService` treats role_id 1 as "head" for
its weight adjustment — worth resetting those two hashes before testing
that path.

> **2026-09-08:** that last sentence turned out to be the visible corner of
> a real backend bug, now written up properly as ask
> [#21](#21) — `demo_head` is role_id **3**, both head/admin checks in the
> code test for **1**, and `RoleSeeder` says 1 is `CUSTOMER`. Resetting the
> hashes is still worth doing, but it is a workaround for the numbering
> mismatch rather than a fix.

### Seed data gaps

- `customer_touch` is empty, so `customer_touch_user_mappings` is too and
  the mappings screen renders an empty table. Create a few touches on the
  Customer Touch screen, then insert mapping rows directly (there is no
  create route — backend ask #11).
- `fitem_boxes` is empty, so the FITEM box picker in Auto Entry has no
  options. `box_id` is optional, so those rows still submit.
- Fine already: items 1–4 including **Metal** (id 4), with usable metal
  lots held by `head_admin` (stock 4) and `employee_one` (stock 13), so the
  Item Change lot picker is testable.

---

## 2. Flagged to backend

> Frontend team does not modify `schainbackend/`. These are asks, in priority
> order.

### Blockers

1. ~~**`php artisan migrate` fails on a fresh database — and this now
   crash-loops the Render deploy.**~~ **FIXED 2026-09-07 by the frontend
   team**, with the backend team's go-ahead, because the deploy was down and
   Render gives this service no shell to repair it from. See
   [§2b](#2b-what-we-changed-in-schainbackend-2026-09-07) for exactly what
   changed. Kept here because the numbering is referenced elsewhere and the
   root cause is worth not repeating.

   The five Passport tables were committed **three times each** —
   `2026_09_01_000437`–`000441`, `_000650`–`000654`, `_000717`–`000721` —
   and the files were byte-identical (verified with `diff` and `md5sum`).
   The second batch threw
   `SQLSTATE[42P07] Duplicate table: relation "oauth_auth_codes" already
   exists`.

   **Root cause:** `passport:install` calls
   `vendor:publish --tag=passport-migrations`, which republishes all five
   migrations with fresh timestamps every time it runs. It was run three
   times (00:04, 00:06, 00:07 on 2026-09-01) and each run's copies were
   committed in `1fae310`. Passport 13.7 does *not* auto-load its own
   package migrations (`PassportServiceProvider` only registers the publish
   tag), so the duplication is entirely from the repeated publish — nothing
   in `vendor/` is contributing a fourth copy.

   **Why it took the deploy down rather than just being noisy:**
   `docker/entrypoint.sh` ran `php artisan migrate --force` under `set -e`,
   so the failure exited the container before `config:cache` and
   `exec apache2-foreground` were ever reached. Render logged
   `==> Exited with status 1`, restarted, and hit the identical failure —
   a crash loop that would never have cleared on its own. It was never an
   env or credentials problem.

   **Which set was kept:** `_000437`–`000441`. Migrations apply in filename
   order, so on the Render database that set had already applied and was
   recorded in the `migrations` table; the other ten had not. Deleting a
   *different* set would have orphaned live ledger rows. No data repair was
   needed on Render — the next `migrate --force` finds nothing new for
   oauth and proceeds to `customer_touch_user_mappings`, which had never
   run there. `migrate:fresh` was never an option: the deployed database
   holds real data ([§0](#0-environments)).

   **Do not re-run `passport:install`** on this repo — it republishes all
   five migrations again and puts us straight back here. The entrypoint now
   handles keys and the personal access client on its own.

2. **Acting-user resolution is inconsistent now that auth is mandatory.**
   `StockDetailsController::getActingUserId` (line 83) and
   `CashTxnDetailController` (2395, 2419) prefer `$request->user()->user_id`
   and fall back to the `X-User-ID` header. But
   `CashCategoryController` (line 44) and `CashAutoEntryService` (line 37)
   still read **only** the header. Since every route now sits behind
   `auth:api`, those two write a different `added_by` than the rest of the
   app for the same signed-in operator.

   *Re-verified 2026-09-08 — still pending, unchanged.* A full sweep for
   `X-User-ID` across `app/` returns exactly six hits, splitting the same
   way as before:

   | Reads the token first (correct) | Header only (the bug) |
   |---|---|
   | `StockDetailsController:83` | `CashCategoryController:44` |
   | `CashTxnDetailController:2395`, `:2419` | `CashAutoEntryService:37` |
   | `ReportController:143` | |

   `ReportController:143` was not in the original list — it is on the
   correct side, so it does not widen the bug, but it does mean the two
   stragglers are now outnumbered five to two.

3. **`AuthController::login` does not check `is_active`.** A deactivated user
   can still authenticate and receive a working token.

   *Re-verified 2026-09-08 — still pending, unchanged.* `is_active` does not
   appear anywhere in `AuthController.php`.

4. **Auto Entry silently discards the transaction date.**
   `AutoEntryRequest` validates `items.*.added_at`
   (`date_format:Y-m-d H:i:s`), but `AutoEntryService::executeAutoTransfer`
   reads a **top-level** `$data['added_at']` — which has no validation rule,
   so `$request->validated()` strips it before the service ever sees it.
   Net effect: the per-row date is validated and then ignored, the top-level
   one can never arrive, and **every auto entry is stamped `now()`**. The
   API doc's §29 claim that `added_at` sets the entry time is wrong in
   practice. Backdating an auto entry is impossible today.
   Either add a top-level `added_at` rule, or read the per-item value inside
   the loop the way every other stock service does.
   `StockAutoEntryView` sends the per-row value regardless, so the screen is
   correct the moment this is fixed.

   *Re-verified 2026-09-08 — still pending, unchanged.* Both halves of the
   mismatch are still exactly as described: `AutoEntryRequest:136` validates
   `'items.*.added_at'`, while `AutoEntryService:23` reads
   `$data['added_at']` (top level) and falls back to `now()`, feeding lines
   80 and 183. Every auto entry is still stamped `now()`.

### Missing routes for code that already exists

5. **`StockDetailsController::postHide`** (line 209) and its
   `HideStockRequest` are implemented, but the route is commented out at
   `routes/api.php:91`. Nothing can reach it.

   *Re-verified 2026-09-08 — still pending.* Method still at line 209; the
   commented route drifted **88 → 91** (corrected inline) and still reads
   `// Route::post('hide', [StockDetailsController::class, 'postHide']);`.

6. **`StockDetailsController::postCash`** (line 231) and its `CashOutRequest`
   are implemented with **no route at all**.

   *Re-verified 2026-09-08 — still pending, unchanged.* Method at line 231;
   `grep postCash routes/api.php` returns nothing.

### Missing endpoints

7. **No `/me` or token-validation endpoint.** A token that expired while the
   tab was closed can only be discovered by firing a real request and
   handling the 401. Frontend handles that gracefully today, but a cheap
   validation endpoint would let the app verify a restored session on boot.

   *Re-verified 2026-09-08 — still pending, unchanged.* No `me` route in
   `routes/api.php`; no `me()` or `user()` method on `AuthController`.
   This is the sole blocker on frontend pending item 4.

8. **No lot-listing endpoint for non-metal items.** `available-metals` covers
   items literally named "Metal" only. Item Change and Item Conversion can
   therefore only attach a `stock_in_id` for metal rows; every other item
   posts `null` and loses the parent-lot draw-down and OB/CB snapshot. A
   generic "list stock lots for a user + item" endpoint would close this.

   *Re-verified 2026-09-08 — still pending, unchanged.*
   `stock-details/available-metals` (`routes/api.php:59`) remains the only
   lot-listing route; no `stock-lots`-style endpoint exists.

9. **`user_details` display flags are readable but not writable.** The
   ~30 `is_*_shown` / `is_*_need_to_shown` columns come back on
   `GET /user-details/{id}` and drive which sections `CustomerContextPanel`
   renders, but `UserDetailController::store()`/`update()` validate a
   different, unrelated subset of booleans. None of the display flags can be
   written through the API, so no settings screen for them can be built.

   *Re-verified 2026-09-08 — still pending, with one correction.* Exact
   counts rather than "~30": `create_user_details_table` defines **31**
   distinct `is_*_shown` / `is_*_need_to_shown` columns. `update()` writes
   an 11-boolean allow-list (`UserDetailController:885-925`), and **exactly
   one** of the 31 is in it — `is_create_order_shown`. So "none can be
   written" was very slightly overstated: it is 1 of 31, which does not
   change the conclusion that a settings screen is not buildable, but the
   backend team should know one flag is already wired if they use that
   allow-list as the template. `store()` (line 109) validates no display
   flags at all. Note there is no `UserDetail*Request` class — validation is
   inline in the controller, so the fix belongs there.

10. **Customer Deliver has no backend.** `app/Models/OrderDetail.php` exists as
    a stub with no migration, controller or route (verified: no references
    outside the model file). `CustomerDeliveryModal` shows an empty state
    flagging the gap.

    *Re-verified 2026-09-08 — still pending, unchanged.* A `grep` for
    `OrderDetail` across `app/`, `routes/` and `database/`, excluding the
    model file itself, returns **0 hits**.

11. **Customer touch mappings cannot be created or deleted.**
    `CustomerTouchUserMappingController` implements `index()` and `update()`
    only, and `routes/api.php` registers only `GET` and `PUT`/`PATCH`. There
    is no `store` and no `destroy`, so new mappings have to be inserted
    straight into `customer_touch_user_mappings` by hand and stale ones can
    only be deactivated, never removed. `CustomerTouchMappingsView` therefore
    offers no "New mapping" or delete action. Adding those two routes would
    make the screen complete.

    *Re-verified 2026-09-08 — still pending, unchanged.*
    `CustomerTouchUserMappingController` still declares exactly two public
    methods, `index()` (17) and `update()` (48). `routes/api.php:40-42`
    still registers only `GET`, `PUT` and `PATCH`.

12. **`update()` on that controller drops the eager-loaded relations.**
    `index()` returns each mapping `with(['user', 'customerTouch'])`, but
    `update()` returns the bare model, so a save comes back without the names
    the table displays. The frontend merges scalars and falls back to a local
    lookup to compensate — returning the same shape from both would remove
    the special case.

    *Re-verified 2026-09-08 — still pending, unchanged.* `update()` still
    ends `'data' => $mapping` with no `load()` between `save()` and the
    response.

13. **No server-side export.** Both report screens do client-side CSV of
    whatever is currently loaded. A real export endpoint would let operators
    pull the full filtered set rather than just the paged-in rows.

    *Re-verified 2026-09-08 — still pending, unchanged.* A case-insensitive
    `grep` for `export|csv|xlsx` in `routes/api.php` returns nothing.

14. **Reports have no `head_id` override.** `getHistoryItemsObcb` and
    `getConsolidatedReport` derive the head from the bearer token only, so an
    admin cannot pull another head's report. (`getHistory` *does* accept
    `head_id` — the inconsistency is worth resolving one way or the other.)

    *Re-verified 2026-09-08 — still pending, and the split is wider than
    recorded.* One endpoint honours the override, **three** do not:

    | Endpoint | Head derivation | Override? |
    |---|---|---|
    | `getHistory` | `StockDetailsController:40` — `$request->query('head_id') ?? getActingUserId()` | ✅ yes |
    | `getHeadStocks` | `:369` — `getActingUserId()` | ❌ no |
    | `getHistoryItemsObcb` | `:405` — `getActingUserId()` | ❌ no |
    | `getConsolidatedReport` | `:426` — `getActingUserId()` | ❌ no |

    `getHeadStocks` was not previously listed. Every `ReportService` method
    already takes `int $headId` as its second parameter, so the fix is
    per-controller and small.

### Cosmetic

15. `AuthController::login`'s docblock says it returns a **Sanctum** token;
    the code issues a **Passport** one (`->accessToken`). Misleading for
    anyone reading the OpenAPI output.

    *Re-verified 2026-09-08 — still pending, unchanged.* `AuthController:16`
    still reads "return a Sanctum Bearer Token", and the inline comment at
    line 80 still says "Generate Sanctum Token".

16. The same docblock's example shows `role_id` as an integer `1`.
    `user_details.role_id` is a `varchar(50)` in the migration.

    *Re-verified 2026-09-08 — still pending, unchanged.* `AuthController:41`
    — `@OA\Property(property="role_id", type="integer", example=1)`.

### Data correctness bugs (PR #35 — `GET /report/live-metal-balance`)

> These block building a frontend screen for this endpoint — it currently
> returns wrong data in the common case and crashes in the other. Not
> flagging as "missing UI," flagging as "don't build UI against this yet."

17. **Wrong item hardcoded as "Metal."** `ReportService::getLiveMetalBalanceReport`
    hardcodes `item_id = 2` / `to_item_id = 2` to mean Metal. In the actual
    `items` table, **item_id 2 is "Gold Chain"; item_id 4 is "Metal."**
    (`LiveMetalSeeder.php`'s own inline comment calls its `item_id => 2` row
    `// Gold`, so the seeder and the query share the same wrong assumption.)
    Right now this endpoint reports Gold Chain balances mislabeled as "Metal
    Live Balance" — not a crash, a wrong-answer bug. Needs `item_id`
    corrected to 4 (or better, looked up by name the way `getAvailableMetals`
    already does, rather than hardcoded).

    *Re-verified 2026-09-08 — still pending, and the "look it up by name"
    half of the fix now looks mandatory rather than merely nicer.* The
    hardcoding is unchanged (`ReportService:796` `item_id` = 2, `:798`
    `to_item_id` = 2). But **no seeder creates an item named "Metal" at
    all** — `grep "'Metal'"` across `database/` and `app/` returns 0 hits.
    `StockTestDataSeeder` creates only items 1-3 (Gold Ring, Gold Chain,
    Gold), and `MetalStockSeeder:20` gives the game away with the comment
    *"Find item ID 2 (which we updated to Metal)"* — i.e. it depends on a
    manual database edit that no migration or seeder reproduces.

    So the item-4 "Metal" row noted in [§1b](#seed-data-gaps) is hand-made
    local data, and **the id differs per environment by construction**.
    Hardcoding *any* integer is therefore wrong, not just `2` — swapping it
    to `4` would fix this developer's machine and break the next one. This
    also means `getAvailableMetals`, which matches on the name, currently
    has nothing to match on a freshly seeded database (already recorded from
    the other direction in [§3](#missing--backend-enforces-doc-is-silent):
    "§22 needs `MetalStockSeeder`"). Worth the backend team seeding a real
    Metal item rather than leaving it to manual setup.

18. **The `date`+`time` branch will throw a SQL error on this database.** It
    builds raw SQL via `selectRaw`/`havingRaw` using `IFNULL(...)` and
    backtick-quoted identifiers (`` `stock_details` ``) — MySQL syntax. Every
    environment we run is **PostgreSQL** — local Docker Postgres and Render's
    managed Postgres in the deploy, *not* Supabase ([§0](#0-environments)) —
    and Postgres has no `IFNULL` (use `COALESCE`) and doesn't use backticks.
    There is no environment where this branch works. Any call with
    `date`+`time` params 500s.

    *Re-verified 2026-09-08 — still pending, unchanged.* Single offending
    statement, `ReportService:817` — one `selectRaw` carrying both
    `IFNULL(...)` and `` `stock_details` ``. It is the only MySQL-ism left
    in the file, so this is a one-line fix.

19. **The `?user_id=` admin override can never activate.** It checks
    `$actingUser->role_id == 1` — `RoleSeeder` defines role_id 1 as
    `CUSTOMER`, not admin/head (same role-numbering trap as backend ask
    [#21](#21)). It falls back to `$actingUser->role->role_name` — but the
    `roles` table's actual column is `role`, not `role_name` (checked the
    migration), so that lookup is always `null`. Both halves of the `OR` are
    unreachable for any real user in this dataset.

    *Re-verified 2026-09-08 — still pending, with a location correction.*
    The code is in **`ReportController::getLiveMetalBalance` (line 152)**,
    not in `ReportService` — worth fixing here because this section is
    otherwise all `ReportService` and the backend team will look in the
    wrong file. Both halves re-confirmed: `RoleSeeder:16` is
    `['id' => 1, 'role' => 'CUSTOMER']`, and the roles migration declares
    `$table->string('role', 50)` with no `role_name` anywhere in `app/`.
    The cross-reference to ask #2 was wrong (that ask is about `X-User-ID`,
    not role numbering) and now points at the new ask #21.

20. Minor: `LiveMetalSeeder.php` has a duplicate `'added_by' => 1,` key in
    its first insert array. Harmless (PHP keeps the last value) but sloppy.

    *Re-verified 2026-09-08 — still pending, unchanged.* `LiveMetalSeeder`
    lines 32 and 33, both `'added_by' => 1,`.

### Role numbering

<a id="21"></a>

21. **`role_id == 1` means two different things, and the two seeders
    disagree.** Found during the 2026-09-08 pass; previously recorded only
    obliquely, as a test-credentials aside in [§1b](#test-credentials).

    `RoleSeeder` is the authority and defines `1 => CUSTOMER`,
    `2 => EMPLOYEE`, `3 => HEAD`, `4 => PURCHASE`, `5 => SALARY`. Two places
    in the code instead treat `role_id == 1` as head/admin:

    - `AutoEntryService:133-134` — `$isSenderHead = ($givenBy->role_id == 1)`
      and the matching `$isReceiverHead`, which gate the auto-entry weight
      adjustment.
    - `ReportController:152` — the admin override in ask #19 above.

    The seeders disagree with each other too: `HeadUserSeeder:26` correctly
    gives `demo_head` `'role_id' => 3, // HEAD role`, while
    `StockTestDataSeeder:71-79` gives `head_admin` `'role_id' => 1`.

    Practical effect, and why this is worth its own item: **`demo_head` is
    the only account anyone can actually log into** ([§1b](#test-credentials)),
    it is role_id 3, and both code paths above test for 1 — so the head
    branch of `AutoEntryService` never fires and the live-metal admin
    override never activates for the one usable account. The account that
    *would* satisfy both, `head_admin`, has an unusable password hash. That
    combination means the head-adjustment path in Auto Entry has most likely
    never been exercised by anyone, and a frontend bug report against it
    would be untestable from our side.

    Fix is the backend team's call on which convention wins, but it needs to
    be one or the other everywhere. If `RoleSeeder` is right, both code sites
    should test for 3 and `StockTestDataSeeder` should be corrected.

---

## 2b. What we changed in `schainbackend` (2026-09-07)

> The frontend team does not normally touch `schainbackend/`. This is the
> documented exception: the Render deploy was in a crash loop, Render gives
> this service **no shell**, so every repair had to ship as repo changes that
> run on container start. Backend team approved.

**1. Deleted the ten duplicate Passport migrations** (backend ask #1) —
`2026_09_01_000650`–`000654` and `_000717`–`000721`. Kept `_000437`–`000441`.
52 migration files → 42.

**2. Rewrote `schainbackend/docker/entrypoint.sh`.** Three additions, all
driven by the no-shell constraint:

| Step | Behaviour | Why |
|---|---|---|
| Migrations | Still **fatal** on failure, but dumps `migrate:status` before exiting | Booting Apache against a half-applied schema turns one clear failure into scattered 500s. The status dump is what replaces having a shell. |
| Passport keys | Uses `PASSPORT_PRIVATE_KEY`/`PASSPORT_PUBLIC_KEY` if set; else keeps existing `storage/*.key`; else generates a pair and warns | `/storage/*.key` is gitignored, so the image ships **no key pair** and nothing generated one. Every login would have 500'd on a missing key path the moment the migration fix landed. |
| Personal access client | Looks it up, creates it only if absent. **Non-fatal** on error | `AuthController:81` uses `$user->createToken(...)->accessToken`, which needs an `oauth_clients` row with the `personal_access` grant. Nothing created it. `passport:client --personal` is not idempotent, hence the guard. Non-fatal so a bug in this block can't brick every deploy. |

A fourth block baselines the Passport migration ledger before migrating: if
an `oauth_*` table exists with no `migrations` row, it is recorded as
applied. That is the one other state the duplicate bug could have left
behind, and it would have failed with the *identical* "Duplicate table"
error — undiagnosable without a shell. Idempotent, no-op when healthy.

Every block was verified against the local database, the repair paths inside
rolled-back transactions.

**3. Added the missing `sessions` table migration**
(`2026_09_07_170000_create_sessions_table.php`, verbatim from Laravel's own
`Illuminate/Session/Console/stubs/database.stub`). `SESSION_DRIVER=database`
but no migration ever created the table — Laravel's skeleton creates it
inside `create_users_table`, which this project replaced with
`create_user_details_table`; `create_cache_table` survived, `sessions` was
lost with it. Any route in the `web` middleware group 500'd with
`SQLSTATE[42P01] relation "sessions" does not exist`. **This reproduced
locally too** — it was never Render-specific. `/api/*` was unaffected
(`auth:api` is stateless), which is why nobody hit it until the deploy
started serving `/`.

### Still needs doing on Render — backend team

- [ ] **Set `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` as environment
      variables.** Without them the entrypoint generates a fresh key pair on
      every container start, and Render's filesystem is ephemeral — so
      **every restart silently logs out every user**. `config/passport.php`
      already reads both (lines 31/33); nothing else is needed once they are
      set. ~~This is the one remaining known defect in the deploy.~~ —
      superseded 2026-09-08, the missing `api` DNS record
      ([§0](#0-environments)) is the more immediate one.
- [ ] **Confirm `APP_KEY` is set** in the Render environment. It cannot be
      generated at boot — `key:generate` writes to a `.env` the container
      doesn't have, and rotating it would break existing encrypted values.

*Both re-checked 2026-09-08 — status still unknown, and unknowable from
here.* These live in the Render dashboard, which the frontend team cannot
read, so neither can be ticked off by inspecting the repo. The usual
proxy — call the deployed API and see whether login works — is unavailable
while `api.lensnstories.com` does not resolve, so these stay open by
default rather than by evidence. The repo-side halves are confirmed present
and correct: `docker/entrypoint.sh:81` reads both key variables, and the
three entrypoint blocks described above are intact.

*Repo-side state re-confirmed 2026-09-08:* 43 migrations (42 after the
dedupe, plus the sessions migration), exactly 5 `oauth_*` migrations
(the kept `_000437`–`000441` set), and
`2026_09_07_170000_create_sessions_table.php` present. No regression.
`render-build.sh` is also still present and still carries
`php artisan passport:keys --force` at line 15 — see the note below; it has
not been touched, and neither has anyone confirmed whether it is live.

### Deliberately not touched

- **`schainbackend/render-build.sh`** appears to be dead — the service
  deploys from the `Dockerfile`/`ENTRYPOINT`, not this script. Flagging
  rather than editing, because it may belong to another Render service:
  it runs `php artisan passport:keys --force` unconditionally, which
  **rotates the signing keys on every build** and invalidates all live
  tokens. If it is dead, delete it. If it is live, that `--force` is a bug.
- **The `migrate`-on-every-boot pattern itself.** Running migrations from
  the entrypoint means any future migration failure takes the service down
  instead of leaving the previous version serving. A Render pre-deploy
  command would be the better home for it. Backend team's call — out of
  scope for an outage fix.

---

## 3. API doc corrections

Against `frontend/Stock Outward Module API Testing & Param.txt`. Section
numbers are the doc's own.

### Wrong — will not work if followed

| § | Doc says | Actual |
|---|---|---|
| 26 | `POST /api/v1/numeric-waste-in` | `/api/v1/stock/numeric-waste-in` — the route is inside the `v1/stock` prefix group (`routes/api.php:85`) |
| 30 | `GET /api/v1/stocks/reports/items-obcb` | `/api/v1/stock/reports/items-obcb` — **singular** `stock` (`routes/api.php:87`) |
| 5, 6 | `given_by`, `given_to`, `souce_type`, `bank_id`, `bank_name` | `sender_id`, `recipient_id`, `payment_method`, `bank_account_id` — see `StoreCashTxnDetailRequest`. Plus undocumented `category_id`, `amnt_transfer_to_head`, `head_id`. The doc describes a pre-PR-#13 schema. |
| 31 | Records contain nested `"item": { "item_name": ... }` | Flat: `stock_id`, `entry_type`, `stock_type`, `grams`, `touch`, `purity`, `waste_value`, `added_at`, `remarks`, `item_id`, `item_name`, `given_by_name`, `given_to_name` |
| 31 | `"added_at": "2025-10-10 10:10:10"` | **Verified against the live API:** `"2026-09-03T13:05:00.000000Z"`. `StockDetails` casts `added_at` to `datetime`, so it serialises as a Carbon instance. (§30's *is* `"Y-m-d H:i:s"`; that one calls `->toDateTimeString()`.) |
| 1 | `]m` after the items array | Stray `m` — invalid JSON if pasted into Postman |
| 29 | "`added_at` (optional): Date-Time of the entry. Defaults to current time if omitted." | Not true — the value never reaches the service, so every auto entry is stamped `now()`. See backend ask #4. |
| all | `X-User-ID: 1`, no `Authorization` header | Contradicts §32/§36. Every route but `/login` needs `Authorization: Bearer <token>`, and `X-User-ID` is now **ignored** wherever `$request->user()` resolves. |

### Missing — backend enforces, doc is silent

- **`different:given_by` applies to IN-direction endpoints only.** New In,
  GMS In and Numeric Wastage In reject a self-transfer; New Out, GMS Out and
  Numeric Waste Out do not.
- **§2 `stock_in_id` is now `nullable`** (was `required`), changed in
  `6747887`.
- **§29 Auto Entry `touch`/`to_touch` validate `min:1|max:999`**, not
  `between:0,100`.
- **§1 accepts a top-level `retailer_id`.**
- **§23 / §25 `given_by` is nullable.**
- **§5 / §6 `amount` is regex-capped at 2 decimal places** —
  `regex:/^\d+(\.\d{1,2})?$/`.
- **§7 `amount_sources` is `required_if:amnt_transfer_to_head,true`**, whereas
  §8's is unconditionally required. The doc calls both plain "required".
- **§13 / §14 page size is `per_page` (default 15)** — the doc only mentions
  `page`.
- **§22 needs `MetalStockSeeder`.** The endpoint 400s unless the item is
  literally named "metal"; `StockTestDataSeeder` only creates Gold Ring /
  Gold Chain / Gold.
- **§30 / §31 `from_time` and `to_time` are silently ignored without their
  matching date** — the service only builds `"$date $time"` inside
  `if ($fromDate)`.
- **Gold endpoints accept undocumented fields**: `added_at`, `is_rate_avg`,
  `retailer_id` on all four; plus `bank_entry_date`, `is_live` and `taken_*`
  on purchase/sale.
- **§29 omits the `to_waste_*` fields** (`to_waste_id`, `to_waste_total`,
  `to_waste_value`) that `AutoEntryRequest` validates.

### Also worth knowing

- **The two report endpoints return weights in different JSON types.**
  Verified live: §30 returns `"grams":10` (number), §31 returns
  `"grams":"5.0000"` (string) — §30 `(float)`-casts every weight while §31
  passes the attributes through uncast and `StockDetails` casts them
  `decimal:4`. Do not assume one shape covers both.
- **§31's `waste_value` can be null** (the only nullable one of the four —
  `grams`/`touch`/`purity` are NOT NULL in the schema).
- **§31's echoed `page_no`/`page_size` are strings** when supplied (raw query
  values) and numbers only when defaulted. Verified live: a request with
  `page_size=1` echoes `"page_no":1,"page_size":"1"` in the same object.
  §30 `(int)`-casts them.
- **§31 returns both sections in one response but pages them separately**
  (`page_no_out` / `page_no_in`), so paging one side re-runs the other side's
  query server-side.
- **The doc's `.env` sample contradicts its own instructions** — it sets
  `CACHE_STORE=redis` twice (plus a stray `CACHE_DRIVER=redis`) while the
  surrounding text tells you to use `CACHE_STORE=file` locally.
- **The setup section omits `ext-sodium`.** It lists `composer install`,
  `migrate` and `passport:install`, but `composer install` fails outright on
  a stock Windows PHP 8.4 because Passport's `lcobucci/jwt ^5.6` requires
  the sodium extension and it ships disabled. That step needs adding.
  It also lists `passport:install`, which should be dropped — running it
  republishes the Passport migrations and recreates backend ask #1.
  `passport:keys` plus `passport:client --personal` is the correct pair.
