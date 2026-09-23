# Frontend Pending Work & Backend Asks

Living tracker for the frontend team. Updated as items land — tick things off
here rather than opening a new doc.

**Last updated:** 2026-09-23
**Backend baseline:** `515db79` (PR #46 — history head-scoping, role-numbering
and report fixes)

Every claim below was verified against backend source at that commit, not
against the API doc. Where the two disagree, the source wins and the
discrepancy is listed in [§3](#3-api-doc-corrections).

### PR #46 pass — 2026-09-23

One merge since the last baseline: **#46** (`515db79`, backend commit
`60b3816`). Six files, 93 insertions / 49 deletions — small in size, large in
effect: it closes **seven** previously-open asks in one pass, more than any
prior batch. Verified against backend source; not yet re-checked against the
live Render database (no route or migration changes in this PR, so nothing
here should differ by environment).

**Closed by this PR**, with frontend now updated to match:

| Ask | What changed | Frontend |
|---|---|---|
| [#36](#36) | `cash-txn-details/{in,out}-history`, `/print-report` and `stock-details/cash-transaction-history` now accept `head_id` alone — `cash_user_id` only narrows instead of being silently mandatory. Out keeps `sender_id = head`, In keeps `recipient_id = head`; only the counterparty half became conditional. | `CashManagementView.vue`'s history section now shows on Head alone (`canShowHistory`), matching the legacy screen. `CashTxnHistoryTable.vue` / `StockCashHistoryTable.vue` take `userId: number \| null`. |
| [#18](#18) | Live Metal Balance's "as of" query is Postgres-safe now (`COALESCE`, no backticks, no more MySQL `IFNULL`/backtick syntax error). | `LiveMetalBalanceView.vue`'s warning banner and the special-cased error message for this path are removed — the filter is expected to work now. |
| [#19](#19) / [#35](#35) | The `role_id == 1` arm of the live-metal admin override is deleted outright — it granted CUSTOMER-level access, which is what #35 flagged. Only `role->role == 'HEAD'` remains, and (unlike before) it can actually match now that role checks are name-based. | Same view: the "View as" note about it having no effect is removed. |
| [#22](#22) | `getOneDayActionReport` now ANDs `given_by = headId OR given_to = headId` into both the stock and cash queries — it actually uses the `$headId` parameter for the first time. | **Breaking for us** — see below. |
| [#23](#23) | Pagination now runs over the *combined, globally-sorted* dataset (`records` + `pagination: {current_page, per_page, total_items, total_pages}`) instead of per-source-then-concatenated (`transactions` + flat `page_no`/`page_size`). | Same — response shape changed, not just behavior. |
| [#24](#24) | Cash rows carry `amount` now; `grams` is `null` on them (and `amount` is `null` on STOCK rows). No more dual-purpose column. | Row shape changed. |
| [#25](#25) | `added_at` is `"YYYY-MM-DD HH:mm:ss"` like every other endpoint (or the literal `"-"`), not `"DD-MM-YYYY HH:mm:ss"`. | No more bespoke date parser needed. |

**#22–25 together broke `OneDayActionView.vue` outright**, not just made it
stale: the view read `result.transactions`, which no longer exists on the
response, so `sortByDateDesc(result.transactions)` threw on every search.
Fixed: `types/oneDayAction.ts` (new shape), `lib/reportApi.ts`
(`getOneDayAction` now takes a required `actingUserId` and sends
`X-User-ID` — see the new ask below), and `views/OneDayActionView.vue`
(`records`/`pagination`, `row.amount` for cash rows, `formatDateTime` instead
of the old regex parser, stale banner removed).

**Also closed, no frontend involved:**

- **Ask #21 (role numbering), the `AutoEntryService` half.** `$isSenderHead`
  / `$isReceiverHead` now test `role->role == 'HEAD'` instead of
  `role_id == 1`. `StockTestDataSeeder`'s `head_admin` was also corrected
  from `role_id => 1` to `role_id => 3`, so the one seeded, loginable HEAD
  account now actually satisfies the head check it never used to. The
  `ReportController` half of #21 was already fixed in the PR #43–#45 pass.
- `ReportController::getLiveMetalBalance` now catches
  `ValidationException` and returns **422** instead of falling through to
  the generic 500 handler — relevant if `item_id` is omitted and no
  `live_metal_report_items` System Setting exists (see #17 note below).

**New ask, found while fixing OneDayActionView:**

<a id="37"></a>

37. **`ReportController::getOneDayActionReport` (line 192) resolves the
    acting user from `X-User-ID` only** — `(int) $request->header('X-User-ID', 1)`
    — with no `$request->user()->user_id` preference, unlike the pattern ask
    #2 already tracks elsewhere (`StockDetailsController:83`,
    `CashTxnDetailController:2395/2419`). This was harmless while the
    parameter was ignored (ask #22); now that PR #46 makes the endpoint
    actually head-scope on it, an omitted header silently reports **user
    1's** activity instead of the signed-in head's. `reportApi.ts` now
    always sends `X-User-ID` from the auth store to compensate, but the
    inconsistency itself — this method should prefer the bearer token the
    same way its neighbours do — is backend work. Consider folding into #2's
    fix rather than treating it as separate; it's the same bug, one more
    site.

**Not yet reflected in the testing doc.** The working-tree edits to
`frontend/Stock Outward Module API Testing & Param.txt` (uncommitted as of
this pass) add sections for PR #43–#45's endpoints (System Settings, Orders
delete, user-details store/update, customer-touch-mappings store/destroy,
the two CSV exports) but **nothing about PR #46** — no mention of the
history head-only fix, the live-metal role/validation changes, or the
one-day-action response shape change. Worth folding in before it goes
further out of date.

### PR #43–#45 pass — 2026-09-22

Three merges since the last baseline: **#43** (`067fe04`), **#44** (`cac57a2`),
**#45** (`07b01b5`). Verified against backend source and against the **live
Render database**, which the local `.env` currently points at.

> #### 🔴 The two unauthenticated routes are STILL LIVE
>
> `GET /run-seeders` and `GET /run-migrations` in `routes/web.php` are
> **unchanged** by these three PRs — `git diff 2e11345..HEAD -- routes/web.php`
> is empty. `DatabaseSeeder` still calls `MassTestDataSeeder` (line 40), so
> `/run-seeders` still inserts 40 fabricated gold movements into the live
> ledger per hit. The only change in this batch was making its `item_id`
> dynamic (`Item::first()` instead of a hardcoded `1`) — the risk is
> identical. See the PR #38–#42 section below for the full write-up. Still
> not probed from here, for the same reason.

**Good news first: the route table is clean.** `php artisan route:list`
resolves all **117** `api/v1` routes with no missing controller or method —
the recurring "route references a class that doesn't exist" failure is *not*
present in this batch. Every new route was checked individually too
(`OrderController`, `SystemSettingController`, `CustomerTouchUserMappingController@store/@destroy`,
`StockDetailsController@exportHistoryItemsObcb/@exportConsolidatedReport`),
and all resolve. Both new migrations are `Ran` on the live database.

Six new routes:

| Route | Controller | Frontend today |
|---|---|---|
| `apiResource('orders')` — 5 routes | `OrderController` → `OrderDetail` | **none** |
| `apiResource('settings')`, keyed by `{key}` — 5 routes | `SystemSettingController` | **none** |
| `POST customer-touch-user-mappings` | `@store` | **none** — closes ask #11 |
| `DELETE customer-touch-user-mappings/{id}` | `@destroy` | **none** — closes ask #11 |
| `GET stock/reports/items-obcb/export` | `@exportHistoryItemsObcb` | **none** |
| `GET stock/reports/consolidated/export` | `@exportConsolidatedReport` | **none** |

Also changed, without new routes:

- **`UserDetailController` (+339 lines)** — `update()`/`store()` now validate
  **45 distinct `is_*` per-user feature flags** (`is_create_order_shown`,
  `is_cash_mngmt_need_to_shown`, `is_ob_cb_rpt_need_to_shown`, …). Checked
  each against the live `user_details` table: **44 of 45 have a column**, and
  the 45th (`is_primary`) is not a user flag at all — it is a nested
  `item_mappings.*.is_primary` field, and `users_items_mappings` does have
  that column. **No mismatch.** `index()` now also returns
  `role => $user->role->role`.
- **`ReportController::getLiveMetalBalance`** — ask #19 partially fixed, and
  it introduced a new privilege bug. See [#19](#19).
- **`AuthController`** — docblock only (Sanctum→Passport wording, `role_id`
  example corrected to a string). No behaviour change.
- **`ReportService`** — added an `is_export` branch that skips pagination and
  returns every row. Set by the two export endpoints.

**Net for us:** nothing that shipped in these three PRs has any frontend yet.
Full UI breakdown in [§1 Pending](#pending).

---

### PR #38–#42 pass — 2026-09-16

> #### 🔴 READ FIRST — two unauthenticated routes are live in production
>
> PR #38 added `GET /run-seeders` and `GET /run-migrations` to
> `routes/web.php`, outside every middleware group. PR #41 patched them but
> left them public. They are reachable **right now** on
> `https://api.lensnstories.com`.
>
> `/run-seeders` runs `DatabaseSeeder`, which since PR #40 calls
> `MassTestDataSeeder` — 20 stock-IN plus 20 stock-OUT transactions with
> `rand()` gram amounts, pushed through the real `StockInService` /
> `StockOutService`. So each hit inserts 40 fabricated gold movements into
> the live ledger and shifts `grams_grand_total` / `purity_grand_total` on
> the affected users.
>
> The file's own comment says *"These are safe — seeders use
> firstOrCreate/updateOrCreate so no data is ever deleted or overwritten."*
> Nothing is deleted; that is not the risk. The risk is insertion.
>
> They are **GET** routes, so a crawler, a browser prefetch, or an
> `<img src>` on any page fires them. No CSRF token applies to GET. Both
> handlers also return `$e->getTraceAsString()` on failure.
>
> **Deliberately not probed from here** — a single request would have run
> the seeders against production. Existence is from source, not a live hit.
>
> **Worse than the ledger risk:** `UserDetailSeeder` creates `head_admin`
> (role_id 3, **HEAD**) and three others, all with `Hash::make('password')`.
> Checked against the live Render database on 2026-09-16 — **all four exist
> in production already, along with `demo_head`, and all five accept the
> password `password`.** Two of them are HEAD role. That is an open door
> right now, independently of the route.
>
> **Asks, in order: delete both routes; then rotate or delete those five
> accounts.** Neither is frontend-fixable. Full detail at [#28](#28).

Backend changes across the five PRs, verified against source:

| PR | Change |
|---|---|
| #38 | `is_active` blocked at login (**403**) · `GET /me` added · per-table seeders · the two web routes above |
| #39 | Postgres sequence reset before seeding |
| #40 | `no_of_pcs` on `stock_in_details` · `getAvailableStockLots` · `MassTestDataSeeder` · removed 8 leftover duplicate oauth migrations |
| #41 | Patched the web routes |
| #42 | Registered `/me`, `available-lots`, `hide`, `cash-out` · top-level `added_at` rule on Auto Entry · `item_id` parameterised on Live Metal Balance |

**All four newly-routed methods exist** (`AuthController::me`,
`StockDetailsController::getAvailableStockLots`, `postHide`, `postCash`),
and `HideStockRequest`/`CashOutRequest` both exist too. This is the first
pull in a while with no route pointing at a missing class.

**Six asks closed by these PRs: #3, #4, #5, #6, #7, #8.** #17 is closed for
our screens but not properly fixed — see its entry. Frontends for all of it
shipped in the same pass ([§1](#1-frontend-work)), and five new asks came
out of reading the code — [#28–#32](#28).

### PR #37 pass — 2026-09-11

`a55b7cc` (merge of `XLevelsUp/Backend`) landed three independent changes in
519 added lines, nothing deleted:

| Change | Where |
|---|---|
| `GET /report/one-day-action` — a day's stock movements and cash transactions in one feed | `ReportController:181`, `ReportService:878` |
| `apiResource('phone-book')` — CRUD over `user_details` | `PhoneBookController.php`, `PhoneBookResource.php` |
| Passport token lifetimes: access + personal access **24h**, refresh 30d | `AppServiceProvider:21-23` |

Both routes were probed on Render and answer `401`, not `404`, so they are
deployed. **Unlike the previous few PRs, every class, relation and column
this one references actually exists** — `StockDetails::givenBy/givenTo/
item/toItem`, `CashTxnDetail::givenByUser/givenToUser`, and the
`entry_type`/`stock_type`/`retailer_id`/`added_by` columns all check out.
Neither feature was on our asks list; both arrived unrequested.

Two frontends were built against them in the same pass (see
[§1](#1-frontend-work)), and six new backend asks came out of reading the
code — [#22–#27](#22) — plus the F-Items screen below, which is now the
only documented endpoint in the testing doc with no UI at all.

The token-lifetime change is the one that affects everybody today: personal
access tokens defaulted to a year and now expire in 24h, so all users get
signed out daily. Nothing breaks — `lib/api.ts:86` already turns the 401
into a clean bounce — but see the pending item about it.

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

> #### ✅ RESOLVED 2026-09-09 — the `api` record now exists
>
> `api.lensnstories.com` resolves via CNAME to `schainserp.onrender.com`
> (216.24.57.7 / .15), TLS is valid, and a request pinned to that host
> returns the API's own JSON. The GoDaddy step is done; the blocker below is
> kept for history.
>
> **What actually blocks the deployed SPA now** is `VITE_API_BASE_URL` not
> being set in the Vercel project. The live bundle has the relative
> `/api/v1` compiled in, so the SPA POSTs login to its own Vercel origin and
> gets a **405** with `Content-Disposition: inline; filename="index.html"`.
> Setting the variable and redeploying **without the build cache** fixes it
> — Vite inlines `VITE_*` at build time, so a cached rebuild changes
> nothing.
>
> Behind that sits a second one: the Step 1.6 seeders were never run.
> `POST https://schainserp.onrender.com/api/v1/login` with
> `demo_head`/`password` returns `{"success":false,"message":"Invalid
> username or password"}`, which means Laravel queried the database
> successfully and found no such user. CORS is fine (preflight returns 204
> with `access-control-allow-origin: *`), and the SPA deep-link rewrite is
> fine (`GET /login` → 200).
>
> <details><summary>Original 2026-09-08 finding</summary>
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
>
> </details>

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
| Customer Touch Mappings — create + delete (closes backend ask #11) | `views/CustomerTouchMappingsView.vue`, `lib/customerTouchMappingsApi.ts` |
| Server-side CSV export on Items OB&CB + Consolidated | `lib/download.ts`, `lib/stockReportsApi.ts`, both report views |
| System Settings — CRUD keyed by `setting_key`, JSON value editor | `views/SystemSettingsView.vue`, `lib/systemSettingsApi.ts`, `types/systemSetting.ts` |
| Orders — CRUD with client-side validation the API lacks | `views/OrdersView.vue`, `lib/ordersApi.ts`, `types/order.ts` |
| Per-user feature flags — 40 toggles, grouped, on the user form | `views/UsersView.vue`, `lib/userFeatureFlags.ts`, `types/userDetail.ts` |
| Clearable date filters — inline × on every filter date/time input | `components/ui/BaseInput.vue` (`clearable` prop), 7 filter screens |
| Stock user picker hides the signed-in operator | `components/stock/UserPickerPanel.vue` |
| One Day Action report (PR #37) | `views/OneDayActionView.vue`, `types/oneDayAction.ts`, `lib/reportApi.ts` |
| Phone Book — read-only directory (PR #37) | `views/PhoneBookView.vue`, `types/phoneBook.ts`, `lib/phoneBookApi.ts` |
| Session validation on boot — `GET /me`, refreshes the cached user, signs out a deactivated one (PR #38) | `lib/authApi.ts`, `stores/auth.ts` (`validateSession`), `main.ts` |
| Deactivated-account 403 reads as a notice, not a retryable form error (PR #38) | `views/LoginView.vue` |
| Auto Entry backdating actually applies — top-level `added_at` now sent (PR #42) | `lib/stockApi.ts` (`toAutoEntryPayload`), `views/StockAutoEntryView.vue` |
| Live Metal Balance reports the real Metal item — resolved by name, always sent (PR #42) | `views/LiveMetalBalanceView.vue`, `lib/reportApi.ts`, `types/liveMetalBalance.ts` |
| Lot picking for **any** item in Item Change and Item Conversion (PR #42) | `lib/availableLotsApi.ts`, `types/availableLot.ts`, `components/stock/MetalPickerModal.vue` (`source` prop), `ItemChangeModal.vue`, `ItemConversionModal.vue` |
| Hide transactions — row selection + confirm (PR #42) | `components/stock/TransactionHistoryPanel.vue`, `lib/stockApi.ts` (`postHideStocks`) |
| Cash Out (PR #42) | `components/stock/CashOutModal.vue`, `types/cashOut.ts`, `lib/stockApi.ts` (`postCashOut`) |
| Cash Management history shows on Head alone, matching the legacy screen (PR #46 closed ask #36) | `views/CashManagementView.vue` (`canShowHistory`), `components/cash-txn/CashTxnHistoryTable.vue`, `components/cash-txn/StockCashHistoryTable.vue` (`userId: number \| null`) |
| One Day Action — fixed for the new response shape after PR #46's #22–25 broke it (`records`/`pagination`, `amount` on cash rows, standard date format), and now sends `X-User-ID` so the head-scoping PR #46 added actually scopes to the signed-in head (ask #37) | `views/OneDayActionView.vue`, `types/oneDayAction.ts`, `lib/reportApi.ts` |
| Live Metal Balance — stale warning banners removed for the two bugs PR #46 fixed (#18 Postgres SQL, #19 admin override) | `views/LiveMetalBalanceView.vue`, `types/liveMetalBalance.ts` |
| `api.ts` `get()` accepts a headers argument, matching `post`/`postForm` (needed to send `X-User-ID` on a GET) | `lib/api.ts` |

**Every endpoint shipped in PRs #29–#32 now has a frontend**, PR #37's two
new endpoints have one as of 2026-09-11, and everything PRs #38–#42 exposed
has one as of 2026-09-16. What remains below is cleanup, deferred polish,
and work blocked on backend gaps.

Notes on the PR #38–#42 screens, all of them consequences of the backend
contract rather than choices:

- **Auto Entry takes ONE date for the whole entry.**
  `executeAutoTransfer` reads a single top-level `$data['added_at']` and
  stamps every row with it; `items.*.added_at` is validated and never read.
  The payload builder sends the first row's value, and the view shows an
  amber notice when rows disagree instead of silently dropping the rest.
- **Live Metal Balance never omits `item_id`.** The parameter is optional
  to the backend but its default is `2`, which is "Gold Necklace" in this
  dataset. The view resolves the item named `Metal` by name — the rule
  `getAvailableMetals` already uses — and refuses to query at all if no
  such item exists, rather than reporting the wrong one ([#17](#17)).
- **The lot picker is one component, two endpoints.** `MetalPickerModal`
  gained a `source` prop: `'metal'` (default, unchanged, still what Stock
  In/Out and both GMS screens use) or `'lots'`. `availableLotsApi`
  normalises the new response onto the existing row shape so nothing
  downstream forks. Auto-opening on item select stays Metal-only —
  non-metal rows use the explicit "Pick lots…" button, since the lot is
  optional and a modal that opens itself on every item choice would add a
  step for operators who do not need one.
- **Item Conversion does not copy alloys when a multi-lot pick splits a
  row.** Each alloy line carries its own grams, so duplicating them across
  rows would multiply the alloy quantity silently. Extra rows start with no
  alloys and a toast says so.
- **Hide is presented as one-way.** No endpoint sets `is_hided` back to
  false ([#30](#30)), and `hideStocks` also hides the parent lot of any row
  with a `stock_in_id`. Both facts are in the confirm step, because neither
  is recoverable from this app.
- **Cash Out has no sender picker and no date field.** `createCashOut`
  takes the sender from `$addedBy` and the row is stamped by the database.
  The form states both rather than offering inputs the backend ignores.

Two deliberate limits on the PR #37 screens, both visible on-screen as
amber banners rather than hidden:

- **One Day Action** shows every user's activity, because
  `ReportService::getOneDayActionReport` takes a `$headId` and never uses
  it ([#22](#22)). The view sends no `X-User-ID` for the same reason —
  implying a scoping that does not exist would be worse than not sending
  it. Grams and rupees are totalled separately since cash amounts arrive in
  the `grams` field ([#24](#24)), and `added_at` is parsed locally because
  it is the one endpoint using `DD-MM-YYYY` ([#25](#25)).
- **Phone Book is read-only.** `store`/`update`/`destroy` are registered
  and were deliberately not wrapped in `lib/phoneBookApi.ts` — creating a
  contact mints a real ERP login with a hardcoded shared password, and
  deleting one deactivates the shared `user_details` row ([#27](#27)).
  Contacts are created and edited on the Users screen, which goes through
  `UserDetailController` and sets real credentials.

### Pending

#### New work from PRs #43–#45 (added 2026-09-22)

Ordered by effort-to-value. All four are **unblocked** — the routes resolve
and both migrations are applied on the live database.

- [x] **Customer touch mappings: add create + delete.** ✅ Built 2026-09-22. *Smallest win — it
      finishes a screen we already shipped, and it was our own longest-standing
      backend ask (#11).* `lib/customerTouchMappingsApi.ts` currently exposes
      only `list` and `update`; add `create(payload)` → `POST
      customer-touch-user-mappings` (`user_id`, `customer_touch_id`, optional
      `is_active`, returns 201 with `user`/`customerTouch` already loaded) and
      `remove(id)` → `DELETE customer-touch-user-mappings/{id}`. Then add a
      "New mapping" button + modal and a per-row delete to
      `CustomerTouchMappingsView`. Reuse `BaseSelect` for both pickers.
      Note the asymmetry: `store()` eager-loads the relations but `update()`
      still does not (ask #12), so keep the existing merge/fallback on update
      and skip it on create.

      This also clears the [§1b seed-data gap](#seed-data-gaps) — mappings no
      longer have to be inserted by hand to make the screen testable.

- [x] **CSV export buttons on the two stock reports.** ✅ Built 2026-09-22.
      `GET stock/reports/items-obcb/export` and
      `GET stock/reports/consolidated/export` take the *same* query filters as
      their JSON siblings, set `is_export` internally to skip pagination, and
      `streamDownload()` a CSV (`history_items_obcb.csv`, and out+in rows for
      the consolidated one).

      ⚠️ **These do not fit `lib/api.ts`.** `request()` parses JSON and would
      choke on a CSV body, and the response is a file, not an envelope. They
      also sit behind `auth:api`, so a plain `<a href>` or `window.open` will
      **401** — the bearer token cannot ride along on a naive navigation, and
      per [§2b](#2b-what-we-changed-in-schainbackend-2026-09-07) sandboxed
      contexts block script-driven downloads too. Build one shared helper
      (`lib/download.ts`) that does `fetch` with the `Authorization` header,
      reads `res.blob()`, and triggers a temporary object-URL anchor. Do not
      bolt this onto `request()`.

- [x] **System Settings screen.** ✅ Built 2026-09-22. `apiResource('settings')` is full CRUD,
      **keyed by `setting_key`, not by id** — `show`/`update`/`destroy` all do
      `where('setting_key', $key)->firstOrFail()`, and the route is declared
      `->parameters(['settings' => 'key'])`. The model casts `setting_value`
      to `array` and `store()` validates it `required|array`, so **a bare
      string or number will be rejected** — the editor has to produce
      JSON, and the UI should validate that before submitting rather than
      surfacing a raw 422.

      Table is `id` / `setting_key` (unique) / `setting_value` (json) /
      `description` (nullable) / timestamps. No seeded rows yet, so the screen
      needs a real empty state.

- [x] **Orders screen.** ✅ Built 2026-09-22, with the status vocabulary flagged on-screen as unconfirmed.
      `apiResource('orders')` → `OrderController` → `OrderDetail`
      (`order_details`: `order_id`, `customer_id`, `item_id`, `grams`,
      `status` default `PENDING`, `added_at`).

      Flagging rather than guessing, because the controller is thin in ways
      that matter: every method is `$request->all()` straight into
      `create()`/`update()` on a `$guarded = []` model — **no validation, no
      `FormRequest`, no status enum, no `customer_id`/`item_id` existence
      check**. So the frontend is the only thing standing between a typo and a
      bad row. Decide the allowed `status` values with the backend team before
      building the picker; right now any string is accepted.

      Worth connecting to two existing items: `HeadStockSummaryPanel` already
      renders an **"Active Orders"** row that has always read 0 because no
      orders table existed, and `user_details` carries
      `is_create_order_shown` / `is_need_order_status_shown` flags (below).

- [x] **Per-user feature flags — editing (phase 1).** ✅ Built 2026-09-22. Gating nav on them (phase 2) is still open, see ask #33.
      `UserDetailController` now validates and stores 44 `is_*` booleans per
      user — `is_create_order_shown`, `is_cash_mngmt_need_to_shown`,
      `is_ob_cb_rpt_need_to_shown`, `is_gallery_need_to_shown` and so on —
      each one plainly a nav/feature visibility toggle for the legacy screen.

      Two separate pieces of work, and they should be sequenced:
      1. **Editing them** — a permissions tab on the user form. Mechanical,
         but 44 checkboxes needs grouping to be usable.
      2. **Honouring them** — gating `lib/nav.ts` and route guards on the
         signed-in user's flags. This is the larger and riskier change.

      **Not blocked, but read this before starting (verified 2026-09-22).**
      `UserDetail::toArray()` exposes all 44 `is_*` keys — the model hides
      only `password_hash`, `report_password` and `otp` — so
      `GET user-details/{id}` and `index()` *do* return the flags, and (1) is
      buildable today.

      For (2), the catch is that **`GET /me` does not return them.**
      `AuthController::me` hand-picks exactly five fields — `user_id`, `name`,
      `user_name`, `role_id`, `is_active` — so gating nav on the signed-in
      user's flags means a second call to `user-details/{user_id}` after
      login, then caching the result in the auth store. That works, but it is
      an extra round trip on every cold boot.

      Worth asking the backend team to widen `/me` instead (it is a five-line
      change in one method), rather than us building the workaround and then
      unpicking it later. Ask logged as [#33](#33).

#### Carried over

- [ ] **F-Items IN/OUT screen.** `POST /api/v1/fitems` has existed since
      before PR #37 but was only documented in the 2026-09-11 doc update
      (section 41), and it is now the only documented endpoint with no UI.
      `FitemRequest` gives a clean, fully validated contract: `type`
      (IN|OUT), `given_by`, `given_to` (must differ), optional `added_at`,
      and an `items[]` array of `item_id`, `grams`, `touch`, optional
      `mtouch`, `wastage`, `box_id`, `item_gross_weight`,
      `item_added_gross_grams`, `item_no_of_pcs`, `item_remarks`,
      `remarks`, `added_at`. `FitemBoxesView` already manages the boxes
      that `box_id` refers to, so the dropdown data exists.

      Not built in the PR #37 pass for one reason worth a decision before
      anyone starts: **there is no read endpoint.** `routes/api.php:39`
      registers `POST fitems` only, so the screen would be submit-only.
      Entries do land in `stock_details` with `type = FITEM`, which means
      the existing Transaction History panel and the reports can show them
      — but that is a UX call (a standalone screen vs. a fourth entry mode
      inside Stock Auto Entry, which already has a FITEM row type) rather
      than something to guess at. Unblocked either way.

- [ ] **Token expiry is now 24h — decide whether to detect it on boot.**
      `AppServiceProvider:21-23` (PR #37) sets
      `personalAccessTokensExpireIn(24h)`, down from Passport's one-year
      default, so everyone is signed out daily. Nothing is broken:
      `lib/api.ts:86` clears the session and bounces on the 401. The gap is
      that **the app looks signed in until the first API call**, and the
      dashboard makes none — `DashboardView` is static tiles and
      `RemindersPanel` is local-only — so someone opening the app the next
      morning sees a normal dashboard and only discovers the expiry when
      they open a module.

      *Updated 2026-09-16 — largely closed by the boot validation below.*
      `validateSession()` now fires a real `GET /me` on every boot, so an
      expired token is detected before the operator touches a module rather
      than on their first click. What is left is narrower: a token that
      expires **while** a tab sits open on the static dashboard is still
      only noticed on the next call. Reading the JWT `exp` claim and setting
      a timer would close that remainder; low value, since the 401 path is
      clean. Keep or drop as a judgement call.

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

- [x] ~~**Multi-tab session sync.**~~ **DONE.** `lib/authSession.ts` now
      carries a `storage` listener plus a `sessionChangedHandler`, and
      `main.ts` acts on it — a sign-out in one tab sends the others to
      `/login`, a sign-in is adopted rather than left stale.

- [x] ~~**Session validation on boot.**~~ **DONE 2026-09-16**, unblocked by
      `GET /me` in PR #38 (ask [#7](#7) closed). `stores/auth.ts` gained
      `validateSession()`, fired from `main.ts` after mount.

      Three deliberate properties: it is **not** awaited before mount, so
      the app renders at the same speed it did before; it **never** clears
      a session on its own, because a 500 or an offline laptop must not sign
      an operator out (a genuinely dead token 401s, and `lib/api.ts:86`
      already handles that path); and it **does** sign out when `/me`
      reports `is_active: false`, which is the only way the app learns that
      an account was deactivated mid-session — login blocks those, but a
      token already issued keeps working.

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

**Rewritten 2026-09-16 — PR #38's `UserDetailSeeder` replaced all of this.**
Four accounts are now seeded, every one of them with the password
`password`, and all four verified present on the live Render database:

| Username | user_id | role_id | Role |
|---|---|---|---|
| `head_admin` | 1 | 3 | HEAD |
| `employee_one` | 2 | 2 | EMPLOYEE |
| `customer_one` | 3 | 1 | CUSTOMER |
| `retailer_one` | 4 | 4 | PURCHASE |
| `demo_head` | 999 | 3 | HEAD (from `HeadUserSeeder`) |

Two earlier claims here are now wrong and have been removed: `head_admin`
is role_id **3**, not 1, and its hash *does* match `password` — the old
"cannot be logged into" note no longer applies.

Convenient locally. **Not acceptable in production** — these are live on
the public API right now; see ask [#28](#28).

`AutoEntryService`'s head check still tests `role_id == 1`, which
`RoleSeeder` calls `CUSTOMER`, so `customer_one` is the only seeded account
that satisfies it. That is ask [#21](#21), and PR #38 made it sharper rather
than fixing it: there is now a properly-seeded HEAD account (role_id 3) that
the head check will not recognise.

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
   | `ReportController:143` | `ReportController:192` |

   `ReportController:143` was not in the original list — it is on the
   correct side, so it does not widen the bug, but it does mean the two
   stragglers are now outnumbered five to two.

   **Update 2026-09-23 (PR #46) — a third straggler found, and it just
   went live.** `ReportController:192` (`getOneDayActionReport`) is
   header-only too — was not previously listed because
   `getOneDayActionReport` ignored the resulting `$headId` entirely (ask
   #22). PR #46 fixed #22, so this controller's header-only resolution now
   has a real effect: an omitted `X-User-ID` silently scopes the report to
   user 1 instead of the signed-in head. Full detail at
   [#37](#37); frontend now always sends the header
   (`reportApi.ts::getOneDayAction`) to work around it.

3. ~~**`AuthController::login` does not check `is_active`.**~~
   **✅ FIXED in PR #38.** `AuthController:80-87` now returns **403** with
   *"Your account has been deactivated. Please contact your administrator."*
   before issuing a token.

   Frontend done 2026-09-16: `LoginView` renders a 403 as an amber notice
   rather than the red form error used for bad credentials — the operator's
   password was right and retyping it will not help.

   **Residual, worth knowing:** this only blocks *new* logins. A token
   issued before the account was deactivated keeps working until it expires
   (24h, per PR #37). `validateSession()` closes most of that by checking
   `is_active` from `/me` on boot, but a tab already open is unaffected.
   Revoking the tokens on deactivation is the backend-side fix; until then
   **deleting the `user_details` row is the only immediate revocation.**

4. ~~**Auto Entry silently discards the transaction date.**~~
   **✅ FIXED in PR #42**, via the first of the two options this ask
   offered: `AutoEntryRequest:24` now carries
   `'added_at' => ['nullable', 'date_format:Y-m-d H:i:s']` at the top level,
   so `validated()` stops stripping the key that `AutoEntryService:23`
   always read. Backdating works.

   Frontend done 2026-09-16. Note the earlier claim here — *"`StockAutoEntryView`
   sends the per-row value regardless, so the screen is correct the moment
   this is fixed"* — **was wrong**, and would have left the bug looking
   unfixed. `toAutoEntryPayload` sent `added_at` only inside `items[]`,
   which the service never reads. It now sends a top-level value too.

   **Residual by design:** the service applies ONE timestamp to every row it
   writes (`$addedAt` feeds lines 80 and 183) and never reads
   `items.*.added_at`, which remains validated-but-ignored. Per-row dates
   are therefore not achievable without a backend change. The payload sends
   the first row's value and the view shows a notice when rows disagree.
   Not re-raising it as an ask — one date per entry is defensible — but the
   API doc's §29 should stop implying otherwise.

### Missing routes for code that already exists

5. ~~**`StockDetailsController::postHide`** route commented out.~~
   **✅ FIXED in PR #42.** `routes/api.php:97` is now live:
   `Route::post('hide', [StockDetailsController::class, 'postHide'])`.
   `HideStockRequest` exists (`stock_ids` — required array, each an integer
   that must exist in `stock_details`).

   This also retires a stale warning: `StockManagementView`'s header used to
   say the class was missing and every call 500'd. That was true when
   written; it is not now, and the comment has been corrected.

   Frontend done 2026-09-16 — selection checkboxes in
   `TransactionHistoryPanel` with a confirm step. See [#30](#30) for the
   one-way-ness, which is the reason the confirm exists.

6. ~~**`StockDetailsController::postCash`** had no route.~~
   **✅ FIXED in PR #42.** `routes/api.php:98` registers
   `POST v1/stock/cash-out`. `CashOutRequest`: `given_to` (required, must
   exist), `amount` (required, numeric, `gt:0`), `remarks` (nullable, max
   5000).

   Frontend done 2026-09-16 — `CashOutModal`. Two contract facts shaped it:
   the sender is `$addedBy`, never a field, so this records only "I handed
   cash over"; and `payment_method` is hardcoded `CASH_ON_HAND`, so it is
   not a general cash-transfer screen.

### Missing endpoints

7. ~~**No `/me` or token-validation endpoint.**~~
   **✅ FIXED in PR #38.** `GET /api/v1/me` (`routes/api.php:33` →
   `AuthController::me`, line 145) returns `user_id`, `name`, `user_name`,
   `role_id` and `is_active`.

   Frontend done 2026-09-16 — see the boot-validation item in
   [§1](#1-frontend-work). The extra `is_active` in the response turned out
   to matter more than the token check: it is the only way the app learns an
   account was deactivated after its token was issued.

8. ~~**No lot-listing endpoint for non-metal items.**~~
   **✅ FIXED in PR #42.** `GET /stock-details/available-lots`
   (`routes/api.php:65` → `getAvailableStockLots`, line 613) takes
   `user_id` + `item_id` (both required, 422 otherwise), optional
   `page_size` (default 50), and returns a paginated list of parent IN lots
   with `balance > 0`. No name check on the item.

   Frontend done 2026-09-16 — Item Change and Item Conversion now attach a
   real `stock_in_id` for any item, so the parent-lot draw-down and the
   OB/CB snapshot stop landing as 0 on non-metal rows.

   **Two caveats, both raised as new asks:** its `date`+`time` branch does
   not run on PostgreSQL ([#28](#28)), and unlike every other lot query it
   does not filter `is_hided` ([#29](#29)).

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

11. ~~**Customer touch mappings cannot be created or deleted.**~~
    **✅ FIXED BY BACKEND in PR #43–#45 (confirmed 2026-09-22).**

    `CustomerTouchUserMappingController` now declares `index()`, `store()`,
    `update()` and `destroy()`, and `routes/api.php` registers
    `POST customer-touch-user-mappings` and
    `DELETE customer-touch-user-mappings/{id}` alongside the existing verbs.
    All four resolve in `route:list`.

    `store()` validates `user_id` (required int), `customer_touch_id`
    (required int) and `is_active` (boolean, defaults to `1`), and — unlike
    `update()`, see #12 — it *does* `load(['user','customerTouch'])` before
    returning, so the created row comes back with the display names already
    populated. Returns 201.

    **The work is now ours.** `lib/customerTouchMappingsApi.ts` still exposes
    only `list` and `update`; `CustomerTouchMappingsView` still has no "New
    mapping" or delete action. See [§1 Pending](#pending).

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

    **Update 2026-09-16 — half fixed, and the remaining half is the one
    this entry argued was mandatory.** PR #42 turned the hardcoded value
    into a parameter: `ReportService:791` now reads
    `$itemId = $params['item_id'] ?? 2`, and `ReportController:159` passes
    `$request->all()` straight through, so `?item_id=` works.

    **But the default is still `2`, and the lookup-by-name was not done.**
    Any caller that omits `item_id` still gets the wrong item — silently,
    with a "Metal Live Balance" heading over it. Verified against the live
    Render database on 2026-09-16: **item 2 is "Gold Necklace", item 4 is
    "Metal"** (`ItemSeeder`, added in PR #38, now creates both, so the
    "no seeder creates Metal" note above is itself out of date — ids 1 and 3
    no longer exist at all).

    Closed *for our screens* rather than fixed: `LiveMetalBalanceView`
    resolves the Metal item by name via the existing `isMetalItem` helper
    and always sends `item_id`, and refuses to query when no such item
    exists rather than falling back to the wrong one. The on-screen banner
    about this bug is gone.

    **Still an ask** for anyone else calling this endpoint: make the default
    a name lookup, or make `item_id` required. A silent wrong answer is a
    worse default than a 422.

    **Update 2026-09-23 (PR #46) — the silent-wrong-answer half is gone,
    though not via either suggested fix.** `ReportService:807-816` no
    longer defaults to item `2` at all: an omitted `item_id` now falls back
    to a `live_metal_report_items` System Setting (a new key, seeded
    through the same `apiResource('settings')` PR #43–#45 already exposed
    a screen for), and if that setting is empty too, the service throws
    `ValidationException` — which `ReportController` now catches and turns
    into a real **422** instead of a generic 500. A caller that omits
    `item_id` on a database with nothing configured gets a clear error, not
    a wrong item. Doesn't affect this screen either way, since it always
    sends `item_id` explicitly and never reaches this branch.

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

    *Re-verified 2026-09-16 — STILL OPEN after PRs #38–#42, now at
    `ReportService:820`.* Executed against the live Render database rather
    than read:

    ```
    FAILS : live-metal-balance date+time branch
            SQLSTATE[42601]: Syntax error: 7 ERROR: syntax error at or near "`"
    ```

    Galling detail: `getAvailableStockLots`, written in the very same PR
    window, gets this right — it uses `COALESCE` and no backticks. So the
    correct form of this query now exists in the codebase, twenty lines
    away, and could be copied. (That newer query has its own, different
    Postgres problem — [#28](#28).)

    **✅ FIXED in PR #46 (2026-09-23).** `ReportService:838` now reads
    exactly the fix this entry described: `COALESCE(...)` and no backticks.
    Frontend done same day — `LiveMetalBalanceView.vue` no longer shows the
    "as of" warning banner or the special-cased error message for this
    path.

<a id="19"></a>

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

    **Update 2026-09-22 (PR #43–#45) — half fixed, and a privilege bug
    introduced.** `ReportController::getLiveMetalBalance` now reads:

    ```php
    if ($actingUser && ($actingUser->role_id == 1
        || in_array(strtoupper(optional($actingUser->role)->role), ['ADMIN', 'HEAD']))) {
        if ($request->filled('view_as'))      { $targetUserId = (int) $request->query('view_as'); }
        elseif ($request->filled('user_id'))  { $targetUserId = (int) $request->query('user_id'); }
    }
    ```

    - ✅ **`role_name` → `role` is fixed**, so the override can finally
      activate. Verified against the live `roles` table: `3 => HEAD` exists,
      so a head user now genuinely gets the override.
    - ⚠️ **`'ADMIN'` is dead.** The live `roles` table holds exactly
      `1 CUSTOMER, 2 EMPLOYEE, 3 HEAD, 4 PURCHASE, 5 SALARY`. There is no
      ADMIN row, so only the `HEAD` arm can ever match.
    - 🔴 **`role_id == 1` is still there, and now it is reachable.** While
      both halves were broken this was inert. Now that the second half works,
      the first half grants the override to **role_id 1 — which is
      `CUSTOMER`**. A customer can pass `?view_as=` or `?user_id=` and read
      any other user's live metal balance. That line should simply be
      deleted; the role-name check already covers the intended case.
    - 🆕 **New undocumented parameter `view_as`**, taking precedence over
      `user_id`. Not in the API doc. Frontend should prefer `view_as`.

    **✅ FULLY FIXED in PR #46 (2026-09-23).** The dangling `role_id == 1`
    arm — the CUSTOMER-access hole this entry's previous update flagged —
    is deleted outright. `ReportController:151` now reads only
    `in_array(strtoupper(optional($actingUser->role)->role), ['HEAD'])`.
    Frontend done same day: `LiveMetalBalanceView.vue`'s note that "View
    as" has no effect is removed; the override is expected to work for a
    signed-in HEAD user now. (`view_as` still isn't sent by this app —
    `user_id` alone is enough since the two are mutually exclusive here —
    so that part of the ask stands as a nice-to-have, not a blocker.)

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

    **✅ FIXED in PR #46 (2026-09-23) — `RoleSeeder` won, as this entry
    suggested.** `AutoEntryService:133-134` now tests
    `role->role == 'HEAD'` for both `$isSenderHead`/`$isReceiverHead`
    (the `ReportController` half was already fixed in PR #43–#45).
    `StockTestDataSeeder:79` also corrects `head_admin` from `role_id => 1`
    to `role_id => 3`, so the one seeded, loginable HEAD account now
    satisfies the check it never used to — the head-adjustment path in Auto
    Entry is testable for the first time.

### One Day Action report (PR #37 — `GET /report/one-day-action`)

<a id="22"></a>

22. **The report ignores its own `$headId`, so it is not scoped to anyone.**
    `ReportController:184` reads `X-User-ID` and passes it to
    `ReportService::getOneDayActionReport($filters, $headId)` — and the
    method never references `$headId` in its body. Compare
    `getHeadStocks`, which scopes on it in four places.

    Effect: any signed-in user gets every user's stock movements and cash
    transactions for the day. The frontend cannot filter around this —
    `employee_id` filters `added_by` (who keyed the entry), which is a
    different question, and there is no head parameter at all.

    Note also that the same controller uses the better pattern one method
    up, at line 143: `$request->user()->user_id ?? (int)$request->header('X-User-ID', 1)`.
    This method reads the header only, so it would silently default to
    user 1 even if the service did use it.

    **✅ Query FIXED in PR #46 (2026-09-23)** — `ReportService:966-970`
    (stock) and `:1049-1053` (cash) now AND
    `given_by = $headId OR given_to = $headId` /
    `sender_id = $headId OR recipient_id = $headId` into both queries.
    **The header-only gap predicted above is now live, not hypothetical**
    — see new ask [#37](#37): this controller still reads `X-User-ID` only,
    still defaults to user 1 if it's absent, and now that default actually
    changes what the report scopes to. `reportApi.ts` sends the header from
    the auth store to compensate; the backend inconsistency itself is
    still open.

23. **Pagination is applied per source, then the two lists are
    concatenated.** `page_no`/`page_size` are applied to the stock query
    and the cash query separately, so page 2 means "stock rows 501–1000
    *plus* cash rows 501–1000", and the response carries
    `total_stock_count` and `total_cash_count` rather than one total.

    A conventional paginator over this would lie about what a page
    contains. The frontend loads page 1 at the backend's own default of
    500, appends further pages, re-sorts the merged list client-side, and
    shows the two counts separately — which works, but a single paginated
    query would be a better contract.

    **✅ FIXED in PR #46 (2026-09-23).** `ReportService:983`/`:1057` now
    fetch both sources unpaginated, sort the combined collection by
    `added_at` descending, then slice the merged list (`:1104-1105`) —
    `page_no`/`page_size` apply to one dataset, not two. Response shape
    changed to match: `records` (the sorted-then-sliced page) plus a
    `pagination` object (`current_page`/`per_page`/`total_items`/
    `total_pages`), replacing the old `transactions` + flat `page_no`/
    `page_size`. `total_stock_count`/`total_cash_count` are unchanged.
    Frontend updated same day — see the PR #46 pass note at the top of this
    file for the full list of touched files. Pages now arrive pre-sorted
    across the board, so the client-side re-sort on append was removed
    rather than kept as a no-op.

<a id="24"></a>

24. **Cash amounts are returned in the `grams` field.** Deliberate, per the
    service's own comment ("Using grams column to display amount for
    consolidated views"), and `touch`/`purity`/`waste_*` are null on those
    rows. It means the column cannot be totalled without first splitting on
    `record_type`, and any consumer that sums it naively adds rupees to
    grams. A separate `amount` field would remove the trap.

    **✅ FIXED in PR #46 (2026-09-23) — exactly the suggested fix.**
    `ReportService:1072` now returns `amount` on CASH rows (`grams` is
    `null` there); STOCK rows get `amount: null` for shape symmetry
    (`:996`). Frontend updated same day: `OneDayActionRow.amount`, and the
    view uses it for the cash total and the cash-row cell instead of
    reinterpreting `grams`.

<a id="25"></a>

25. **`added_at` is formatted `DD-MM-YYYY HH:mm:ss`.** Every other endpoint
    in the app returns `YYYY-MM-DD HH:mm:ss`, which `new Date()` parses and
    `lib/date`'s `formatDateTime` handles. This one does not parse, so the
    frontend carries a bespoke parser for a single endpoint. Returning the
    raw timestamp and letting the client format would be more consistent.

    Related, low probability but a hard 500 if it hits: the combined sort
    calls `Carbon::createFromFormat('d-m-Y H:i:s', $item['added_at'])`,
    while the formatter maps a null timestamp to `'-'`, which that call
    throws on. `stock_details.added_at` is `useCurrent()` and non-null so
    stock rows are safe, but `cash_txn_details.created_at` comes from
    `$table->timestamps()` and is nullable — one imported row with a null
    `created_at` takes down the whole endpoint.

    **✅ FIXED in PR #46 (2026-09-23).** Both rows now format
    `Y-m-d H:i:s` (`ReportService:1003`, `:1079`), matching every other
    endpoint. The null-timestamp crash risk is also gone: the new sort
    (`:1120-1123`) checks `if ($item['added_at'] === '-') return 0;`
    before calling `Carbon::createFromFormat`, instead of calling it
    unconditionally. Frontend updated same day — `OneDayActionView.vue`
    now formats `added_at` with the shared `formatDateTime` helper instead
    of a bespoke regex parser, with a small guard for the endpoint's own
    `"-"` sentinel.

### Phone book (PR #37 — `apiResource('phone-book')`)

<a id="26"></a>

26. **`role.role_name` is always null — the same `role_name` mistake as ask
    [#19](#19), now in a second file.** `PhoneBookResource:18` reads
    `$this->role?->role_name`, but the `roles` table column is `role` —
    `Role.php:15` and the `create_roles_table` migration both say so, and
    `RoleSeeder` inserts `'role' => 'CUSTOMER'`. The relation loads fine;
    the field name is simply wrong, so every contact's role reads empty.
    The frontend keeps the column in place so it starts working the moment
    this is corrected.

    Worth fixing as one job with #19: two independent files now reach for a
    column that has never existed, which suggests the name is wrong in
    whatever the backend team is working from rather than being a typo
    twice.

<a id="27"></a>

27. **The three write routes are unsafe against a shared table, so the
    frontend does not call them.** `phone-book` is `user_details` — the
    same rows the Users and Clients screens own and every picker selects
    from — and:

    - `store()` injects `user_name = 'pb_' . time() . rand(10,99)` and
      `password_hash = bcrypt('phonebook123')`. **Every contact created
      this way is a working ERP login with a hardcoded, shared password.**
      `AuthController::login` does not check `is_active`, so switching the
      contact off would not close the account — only deleting the row or
      changing the password would.
    - `destroy()` sets `is_delete = true, is_active = false` on that shared
      row, so "deleting a contact" can deactivate a head, employee or
      customer that has live transactions.
    - `update()` writes name/phone/role/address onto the same shared row
      with no guard for what kind of record it is.

    Two smaller ones in the same controller: `index()` never filters
    `is_delete`, so rows deleted through `destroy()` keep appearing unless
    the caller also filters by `is_active`; and `profile_image` comes back
    as a raw storage path (`profile_images/x.jpg`) rather than the resolved
    `profile_image_url` that `GET /user-details/{id}` returns for the same
    column.

    If the phone book is meant to manage contacts that are *not* ERP users,
    it needs its own table or at minimum a non-loginable marker — not a
    `user_details` row with a constant password.

    Doc mismatch worth noting: testing doc section 48 shows the list
    returning `role_id`, `address` and `remarks` and a populated
    `role.role_name`. `PhoneBookResource` returns none of those four —
    only `user_id`, `name`, `phone_no`, `profile_image`, `is_active` and
    the (null) role object.

### New in PRs #38–#42 — 2026-09-16

<a id="28"></a>

28. 🔴 **`GET /run-seeders` and `GET /run-migrations` are public — and the
    seeders create HEAD accounts with a known password.** The headline item
    — see also the [PR #38–#42 pass](#pr-38-42-pass--2026-09-16) at the top.
    Added in PR #38's `routes/web.php`, patched but still unauthenticated in
    PR #41.

    Three separate problems, in descending order of severity:

    **a. Account creation.** `UserDetailSeeder` (PR #38) creates
    `head_admin` (role_id **3**, HEAD), `employee_one`, `customer_one` and
    `retailer_one`, every one of them `Hash::make('password')`. It uses
    `firstOrCreate` keyed on `user_name`, so it does not overwrite an
    existing account — but on any database lacking those usernames, one
    unauthenticated GET mints a HEAD login with the password `password`.

    **Verified against the live Render database, 2026-09-16 — all four
    already exist in production:**

    ```
      1 head_admin    role_id=3  weak_password=true
      2 employee_one  role_id=2  weak_password=true
      3 customer_one  role_id=1  weak_password=true
      4 retailer_one  role_id=4  weak_password=true
    999 demo_head     role_id=3  weak_password=true
    ```

    Five accounts on a publicly reachable API, all with the password
    `password`, two of them HEAD. **This is an open door today**, quite
    apart from the route that can reopen it. Deleting the accounts is not
    sufficient while the route stands — anyone can recreate them.

    **b. Ledger pollution.** `/run-seeders` runs `DatabaseSeeder`, which
    since PR #40 calls `MassTestDataSeeder`: 20 stock-IN + 20 stock-OUT with
    `rand()` gram amounts, through the real `StockInService`/
    `StockOutService`, moving `grams_grand_total` and `purity_grand_total`.
    Each hit adds another 40. `stock_details` was **0 rows** at time of
    check, so this has not fired in production yet.

    **c. Information disclosure.** Both handlers return
    `$e->getTraceAsString()` on failure.

    They are **GET** routes, so a crawler, a prefetch or an `<img src>`
    triggers them; no CSRF protection applies. Not probed from here — one
    request would have run them.

    **Asks, in order: delete both routes; then rotate or delete the five
    weak-password accounts.** Highest-priority item on this list by a wide
    margin.

<a id="29"></a>

29. **`getAvailableStockLots`' `date`+`time` branch does not run on
    PostgreSQL.** Same class of bug as [#18](#18), different cause. It
    builds `havingRaw('grams - used_grams > 0')` where `used_grams` is a
    SELECT-list alias. Postgres resolves output names in `ORDER BY` and
    `GROUP BY` but **not** in `WHERE` or `HAVING`, and a bare `HAVING` with
    no `GROUP BY` additionally requires aggregate expressions.

    Executed against the live Render database on 2026-09-16:

    ```
    FAILS : available-lots date+time branch
            SQLSTATE[42703]: Undefined column: 7 ERROR: column "used_grams" does not exist
    ```

    Credit where due: the `selectRaw` in this method uses `COALESCE` and no
    backticks, so it is already Postgres-correct — only the `HAVING` is
    wrong. Moving that predicate into a subquery or a CTE would fix it.

    Frontend impact: none today. `availableLotsApi` exposes no date/time
    parameters, and `types/availableLot.ts` records why. The live branch
    works and is all we use.

<a id="30"></a>

30. **There is no way to un-hide stock.** `StockOutService::hideStocks`
    sets `is_hided = true`; nothing anywhere sets it back to false
    (`grep is_hided app/` — the only writes are lines 833 and 840 of that
    method, both `true`). `getHistory` and `getAvailableMetals` filter on
    it, so a mistaken hide removes rows from Transaction History and the
    metal picker with no route back short of direct SQL.

    Compounding it: `hideStocks` also hides the **parent lot** of any row
    carrying a `stock_in_id`, so hiding one child transaction can pull a
    whole lot out of the picker — a larger blast radius than the operator
    selected.

    An `unhide` route taking the same `stock_ids` payload would close this.
    Until then the frontend treats Hide as irreversible and says so in a
    confirm step, which is friction that only exists because of this gap.

<a id="31"></a>

31. **`getAvailableStockLots` does not filter `is_hided`.** Every other lot
    query does — `getAvailableMetals` (`StockDetailsController:551`) and
    `getHistory` (`ReportService:48`) both carry `->where('is_hided', 0)`.
    The new one filters `stock_type = IN`, `stock_in_id IS NULL` and
    `balance > 0`, but not this.

    Net effect: a lot hidden through `POST /stock/hide` disappears from the
    metal picker and from history, yet still appears in the generic lot
    picker. Same lot, two screens, opposite answers. One `where` closes it.

<a id="32"></a>

32. **The testing doc does not cover any of PRs #38–#42.** The
    2026-09-11 update added sections 36–52, but nothing since. Missing
    entirely: `GET /me`, `available-lots`, `/stock/hide`, `/stock/cash-out`,
    and the login 403 for deactivated accounts.

    Worse than absent in one place: **§40 Live Metal Balance lists its query
    parameters and `item_id` is not among them** — the single most important
    new parameter on that endpoint, and the one whose omission silently
    returns the wrong item ([#17](#17)). Anyone integrating from the doc
    will get Gold Necklace figures under a Metal heading.

    Everything the frontend needed for these five endpoints came from the
    request classes and controllers, not the doc.

### New in PRs #43–#45 — 2026-09-22

<a id="33"></a>

33. **`GET /me` does not return the per-user feature flags.**
    `AuthController::me` hand-picks five fields — `user_id`, `name`,
    `user_name`, `role_id`, `is_active`. Meanwhile PR #43–#45 added 44
    `is_*_shown` / `is_*_needed` visibility flags to `user_details`, and
    `UserDetail::toArray()` already exposes all of them (only
    `password_hash`, `report_password`, `otp` are hidden).

    To gate navigation on those flags we need them on the signed-in user.
    Today that means a second request to `user-details/{user_id}` right after
    login on every cold boot. Returning the full model from `/me` — or the
    five fields plus the `is_*` set — removes that round trip entirely.
    Please also include `role` (the name), which `index()` now returns but
    `/me` still does not.

<a id="34"></a>

34. **`OrderController` has no validation whatsoever.** Every method pipes
    `$request->all()` into a `$guarded = []` model:
    `OrderDetail::create($request->all())` in `store()`, `$order->update($request->all())`
    in `update()`. There is no `FormRequest`, no `status` enum, and no
    existence check on `customer_id` or `item_id` — so an order can be
    created against a customer or item that does not exist, with any string
    at all as its status.

    We will validate in the UI, but that only protects our own screen; the
    endpoint stays open to anything else that calls it. Please confirm the
    intended `status` values so our picker matches the backend's expectation
    rather than inventing a vocabulary.

<a id="35"></a>

35. **`role_id == 1` in the live-metal override now grants CUSTOMER access.**
    Full detail in [#19](#19) — flagged separately here because it changed
    from inert to exploitable in this batch, and it is a one-line deletion.

    **✅ FIXED in PR #46 (2026-09-23) — that exact line was deleted.** See
    [#19](#19) for the full re-verification.

### Cash Management history — head-only view is impossible (2026-09-22 finding)

<a id="36"></a>

36. **§13/§14/§15's `cash_user_id` is documented as if it narrows results,
    but it is load-bearing — omit it and all three return zero (or, on one
    of them, unscoped) rows.** Found while diagnosing an empty Cash
    Management screen against the legacy screenshot, which shows Out/In data
    populated from **Head alone** — Roles and Users both left on their
    placeholder.

    `CashTxnDetailController::getOutHistory` (`:53-56`) and `getInHistory`
    (`:120-124`) both do:

    ```php
    if ($headId) {
        $query->where(function($q) use ($headId, $cashUserId) {
            $q->where('sender_id', $headId)->where('recipient_id', $cashUserId);
        });
    }
    ```

    `cash_user_id` is ANDed in unconditionally the moment `head_id` is
    present, even when it is `null` — so `recipient_id = NULL` never matches
    a real row and a head-only query always comes back empty. There is no
    way today to ask "everything for this Head" the way the legacy screen
    did; the two ids must be an exact, pre-known matched pair.

    `StockDetailsController::getCashTransactionHistory` (`:534-543`, backs
    the Stock History panel on the same screen) has the **opposite** bug from
    the same root cause: `if ($cashUserId && $headId)` skips the filter
    entirely when `cash_user_id` is missing, so a head-only call there
    returns *every* user's PURCHASE_GOLD/SALE_GOLD/GOLD_TO_CASH/CASH_TO_GOLD/
    conversion rows, not just the selected head's.

    **Ask:** make `cash_user_id` genuinely optional in all three — filter by
    `sender_id = $headId OR recipient_id = $headId` (matching the legacy
    "head is either party" behaviour) when only `head_id` is given, and keep
    the current exact-pair filter when both are given. That is one `if`
    branch added per method, not a rewrite.

    Frontend today (`views/CashManagementView.vue`) intentionally gates the
    whole history section behind picking **both** Head and User, matching
    this constraint — see the design-rationale comment at the top of that
    file. That gate should be loosened to "Head alone reveals the section"
    once the backend accepts a head-only query; until then, loosening it
    frontend-side would only swap "hidden" for "visibly empty," not fix
    anything.

    **✅ FIXED in PR #46 (2026-09-23, backend commit 60b3816).** All three
    methods now make `cash_user_id` conditional exactly as asked — Out/In
    keep their directional half (`sender_id`/`recipient_id = head`) always
    on, and only the counterparty half is skipped when `cash_user_id` is
    absent; the stock-details endpoint's opposite bug (skipping the head
    filter entirely) is fixed the same way, to `given_by = head OR
    given_to = head`. Frontend done same day: `canShowHistory` in
    `CashManagementView.vue` now needs only a Head, and
    `CashTxnHistoryTable.vue` / `StockCashHistoryTable.vue` accept
    `userId: number | null`.

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
