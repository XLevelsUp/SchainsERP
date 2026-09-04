# Frontend Pending Work & Backend Asks

Living tracker for the frontend team. Updated as items land — tick things off
here rather than opening a new doc.

**Last updated:** 2026-09-03
**Backend baseline:** `1a55e25` (PR #32 — Passport auth, customer touch mappings)

Every claim below was verified against backend source at that commit, not
against the API doc. Where the two disagree, the source wins and the
discrepancy is listed in [§3](#3-api-doc-corrections).

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

- [ ] **Cash transaction edit/delete.** The comment in `lib/cashTxnDetailsApi.ts`
      says there is "no show/update/destroy for a single row, so history is
      browsable but not editable". **Also stale** —
      `apiResource('cash-txn-details')` is registered (`routes/api.php:61`) and
      `CashTxnDetailController` has `index` (254), `show` (820), `update` (869)
      and `destroy` (1547). Row-level edit/delete can be built.

- [ ] **Multi-tab session sync.** Signing out in one tab leaves other tabs
      holding a stale in-memory token until their next request 401s and
      bounces them. A `storage` event listener in `lib/authSession.ts` would
      tighten it. Deliberately deferred — the 401 path already degrades
      correctly.

- [ ] **Session validation on boot.** Blocked on a backend `/me` endpoint —
      see [§2](#2-flagged-to-backend).

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
3. `php artisan migrate` — **will fail partway**, see backend ask #1. The
   first five oauth migrations apply, then the duplicate set aborts the run
   and `customer_touch_user_mappings` never gets created. Workaround that
   touches no backend files: after the failure, insert the ten duplicate
   filenames into the `migrations` table with the same batch number, then
   run `migrate` again. Delete those rows once the backend removes the
   duplicate files.
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

1. **`php artisan migrate` fails on a fresh database.** The five Passport
   tables were committed **three times each** — `2026_09_01_000437`–`000441`,
   `_000650`–`000654`, `_000717`–`000721` — and the files are byte-identical
   (verified with `diff`). The second batch throws *"table already exists"*.
   Two of the three sets need deleting. This blocks anyone following the
   doc's own setup steps.

   **Root cause:** `passport:install` calls
   `vendor:publish --tag=passport-migrations`, which republishes all five
   migrations with fresh timestamps every time it runs. It was run three
   times (00:04, 00:06, 00:07 on 2026-09-01) and each run's copies were
   committed. Fix is to delete two of the three sets and not re-run
   `passport:install` on a repo that already has them — `passport:keys`
   plus `passport:client --personal` is enough once the migrations exist.

2. **Acting-user resolution is inconsistent now that auth is mandatory.**
   `StockDetailsController::getActingUserId` (line 83) and
   `CashTxnDetailController` (2395, 2419) prefer `$request->user()->user_id`
   and fall back to the `X-User-ID` header. But
   `CashCategoryController` (line 44) and `CashAutoEntryService` (line 37)
   still read **only** the header. Since every route now sits behind
   `auth:api`, those two write a different `added_by` than the rest of the
   app for the same signed-in operator.

3. **`AuthController::login` does not check `is_active`.** A deactivated user
   can still authenticate and receive a working token.

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

### Missing routes for code that already exists

5. **`StockDetailsController::postHide`** (line 209) and its
   `HideStockRequest` are implemented, but the route is commented out at
   `routes/api.php:88`. Nothing can reach it.

6. **`StockDetailsController::postCash`** (line 231) and its `CashOutRequest`
   are implemented with **no route at all**.

### Missing endpoints

7. **No `/me` or token-validation endpoint.** A token that expired while the
   tab was closed can only be discovered by firing a real request and
   handling the 401. Frontend handles that gracefully today, but a cheap
   validation endpoint would let the app verify a restored session on boot.

8. **No lot-listing endpoint for non-metal items.** `available-metals` covers
   items literally named "Metal" only. Item Change and Item Conversion can
   therefore only attach a `stock_in_id` for metal rows; every other item
   posts `null` and loses the parent-lot draw-down and OB/CB snapshot. A
   generic "list stock lots for a user + item" endpoint would close this.

9. **`user_details` display flags are readable but not writable.** The
   ~30 `is_*_shown` / `is_*_need_to_shown` columns come back on
   `GET /user-details/{id}` and drive which sections `CustomerContextPanel`
   renders, but `UserDetailController::store()`/`update()` validate a
   different, unrelated subset of booleans. None of the display flags can be
   written through the API, so no settings screen for them can be built.

10. **Customer Deliver has no backend.** `app/Models/OrderDetail.php` exists as
    a stub with no migration, controller or route (verified: no references
    outside the model file). `CustomerDeliveryModal` shows an empty state
    flagging the gap.

11. **Customer touch mappings cannot be created or deleted.**
    `CustomerTouchUserMappingController` implements `index()` and `update()`
    only, and `routes/api.php` registers only `GET` and `PUT`/`PATCH`. There
    is no `store` and no `destroy`, so new mappings have to be inserted
    straight into `customer_touch_user_mappings` by hand and stale ones can
    only be deactivated, never removed. `CustomerTouchMappingsView` therefore
    offers no "New mapping" or delete action. Adding those two routes would
    make the screen complete.

12. **`update()` on that controller drops the eager-loaded relations.**
    `index()` returns each mapping `with(['user', 'customerTouch'])`, but
    `update()` returns the bare model, so a save comes back without the names
    the table displays. The frontend merges scalars and falls back to a local
    lookup to compensate — returning the same shape from both would remove
    the special case.

13. **No server-side export.** Both report screens do client-side CSV of
    whatever is currently loaded. A real export endpoint would let operators
    pull the full filtered set rather than just the paged-in rows.

14. **Reports have no `head_id` override.** `getHistoryItemsObcb` and
    `getConsolidatedReport` derive the head from the bearer token only, so an
    admin cannot pull another head's report. (`getHistory` *does* accept
    `head_id` — the inconsistency is worth resolving one way or the other.)

### Cosmetic

15. `AuthController::login`'s docblock says it returns a **Sanctum** token;
    the code issues a **Passport** one (`->accessToken`). Misleading for
    anyone reading the OpenAPI output.

16. The same docblock's example shows `role_id` as an integer `1`.
    `user_details.role_id` is a `varchar(50)` in the migration.

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
  the sodium extension and it ships disabled. That step needs adding — and
  `migrate` needs the caveat that it currently fails partway (see §1b).
