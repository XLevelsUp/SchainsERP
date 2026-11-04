# Backend — Open Items

For the backend team. **Only things that still need doing.** Anything fixed is
deleted from this file — the history lives in
[`frontend/PENDING_WORK.md`](frontend/PENDING_WORK.md).

**Checked against:** `schainbackend/` as it stands on **2026-11-04**, after
**PR #52** (`2502a051`), with `origin` fetched and nothing unpulled. PR #52
changed four backend files: `SystemLogController`, `ReportController`,
`CashTransactionReportResource` and `MetalStockSeeder`. It closed the old
§21 (retailer name suffix) and two of the four points in §22. Nothing else in
the backend has changed since the full re-read on 2026-10-29, so every other
item stands as checked then; line numbers were updated where PR #52 moved
code. §16–§20 and §22 came from checking the backend team's cash-modules
document (`Schain_ERP_Cash_Modules_Documentation`, rev 2.0.0) against the
code on 2026-11-03.

The `#n` tags point at the matching entry in `PENDING_WORK.md` if you want the
full history.

---

## How to read this

| Priority | Meaning |
|---|---|
| 🔴 **Now** | Wrong numbers or exposed data in production today |
| 🟠 **Soon** | Broken feature, or something that will bite when used |
| 🟡 **When you can** | Inconsistency, missing scoping, cleanup |

---

# 🔴 Now

## 1. Wastage is added to the wrong person's balance  `#21`

**Where:** `app/Services/StockInService.php:72` and `:75`,
`app/Services/StockOutService.php:75` and `:78`

**The code:**

```php
if ($givenBy->role_id != 1) { // Assuming role_id 1 is HEAD/Admin
    $givenbygrams = $this->add($grams, $wasteValue);
}
```

**The problem:** that comment is wrong. In `RoleSeeder`, **role 1 is
`CUSTOMER`**. HEAD is **role 3** — and `UserDetailSeeder` creates `head_admin`
with `role_id = 3`.

The rule is meant to be *"a head does not carry wastage on their own balance."*
What actually happens is the reverse:

| User | Intended | Actual |
|---|---|---|
| HEAD (role 3) | no wastage added | **wastage added** |
| CUSTOMER (role 1) | wastage added | **no wastage added** |

**Why it matters:** every Stock In and Stock Out carrying a wastage value
writes the wastage onto the wrong party. These are gold balances, and the error
compounds with every transaction. This is the most serious open item.

**Fix:** compare against the role that actually means head. Either check
`$user->role->role === 'HEAD'` (the approach `ReportController::getLiveMetalBalance`
already uses correctly) or fix the constant to 3. Please don't leave a bare
integer with a comment — that is what caused this.

---

## 2. The metal picker is broken on every database — `system_settings` is never seeded

**Where:** `app/Http/Controllers/Api/StockDetailsController.php:616`,
`app/Services/ReportService.php:937`, and the missing seeder for
`2026_09_18_223934_create_system_settings_table`.

**Symptom:** every call to `GET /stock-details/available-metals` returns

```
400  {"success": false, "message": "Selected item is not valid for metal selection"}
```

Example that fails today: `?item_id=4&user_id=999`, where item 4 **is** named
`Metal` and user 999 **does** exist.

**Cause:** PR #45 changed the gate from a name check to a settings lookup:

```php
$metalItemIds = SystemSetting::get('metal_popup_items', []);
if (!$item || !in_array($item->item_id, $metalItemIds)) { /* 400 */ }
```

The migration creates `system_settings`, but **nothing seeds it** — grepped
all of `database/`, there is no seeder. So `get()` returns its `[]` default and
`in_array($anything, [])` is always false. Verified on the local database
2026-10-08:

```
=== system_settings rows ===
  (EMPTY TABLE)
=== metal_popup_items as the controller reads it ===
  array(0) { }
```

**Why it matters:** the metal picker is not a corner of the app. Stock In,
Stock Out, GMS In, GMS Out and Item Change all open it to draw down a metal
lot. All of them have been dead since PR #45 on any database that was not
hand-configured — which is all of them.

**The same gap takes out a second endpoint.** `ReportService:937` reads
`live_metal_report_items` the same way; with it unset, Live Metal Balance
throws a validation error unless the caller passes `item_id` explicitly. (Our
screen always sends `item_id`, resolved by name, so it works either way — this
bites anyone else calling the endpoint.)

**Fix:** ship a seeder for `system_settings` covering at least
`metal_popup_items` and `live_metal_report_items`, resolving the items **by
name** rather than hardcoding ids — ids already differ between environments,
which is what caused the original Live Metal bug (`#17`).

A settings-driven design is the right call and we are not asking you to revert
it. It just cannot ship without the row that makes it work, because the
failure mode is a 400 that looks like the caller's fault.

**Workaround meanwhile** (one row):

```sql
insert into system_settings (setting_key, setting_value, created_at, updated_at)
values ('metal_popup_items', '[4]', now(), now());
```

**One more thing this changed, which nobody told us:** the frontend still
decides whether to open the metal picker by matching the item name
`'metal'` — the rule the backend used before PR #45. The backend now decides
by the settings list. Those two will disagree as soon as the list holds
anything other than exactly the items named "Metal". We will move the
frontend onto `GET /settings`; flagging it so the drift is on the record.

---

## 3. Any signed-in user can read any head's books

```php
$headId = $request->query('head_id') ?? $this->getActingUserId($request);
```

**The problem:** the `head_id` override that lets a head pull another user's
report has **no check on who is allowed to use it.** Any authenticated user
can pass another user's id and read that ledger. An employee can read their
head's books.

Every one of these takes the id straight from the query string:

| Endpoint | Where (`app/Http/Controllers/Api/…`) | Takes |
|---|---|---|
| `GET /stock/reports/items-obcb` | `StockDetailsController.php:495` | `head_id` |
| `GET /stock/reports/consolidated` | `StockDetailsController.php:516` | `head_id` |
| `GET /stock/reports/items-obcb/export` (CSV) | `StockDetailsController.php:36` | `head_id` |
| `GET /stock/reports/consolidated/export` (CSV) | `StockDetailsController.php:63` | `head_id` |
| `GET /stock-details/history` | `StockDetailsController.php:108` | `head_id` |
| `GET /stock/reports/id-wise` | `StockDetailsController.php:459` | `head_id` |
| `GET /stock-details/cash-transaction-history` | `StockDetailsController.php:537` | `head_id` |
| `GET /stock-details/head-stocks` | `StockDetailsController.php:672` | `user_id` |
| `GET /cash-txn-details/in-history`, `out-history` | `CashTxnDetailController.php:33`, `:103` | `head_id` |
| `GET /cash-txn-details/print-report` | `CashTxnDetailController.php:173` | `head_id` |
| `GET /report/daywise-grand` | `ReportController.php:224` → `ReportService::getDaywiseGrandReport` | `head_user_id`, `employee_id` / `user_id`, `customer_id` / `retailer_id` — and the head itself comes from the `X-User-ID` header ([§7](#7-four-endpoints-still-read-x-user-id-and-ignore-the-token--2-37)) |

**Fix:** allow the override only for HEAD (or admin) accounts, the way
`getLiveMetalBalance` does. PR #52 added exactly this kind of check to the
System Logs endpoints (`optional($authUser->role)->role !== 'HEAD'` → `403`),
so the pattern is already in the codebase. Everyone else gets their own data regardless of
what they pass. Please keep it working *for heads* — the Stock and Cash
Management screens deliberately pass another user's id to show that user's
ledger to the head operating the screen.

---

## 4. Every user sees every order

**Where:** `app/Http/Controllers/Api/OrderController.php:11`

```php
public function index()
{
    return response()->json([
        'success' => true,
        'data' => OrderDetail::all()
    ]);
}
```

**The problem:** no scoping, no pagination, no filters. Every customer order in
the system goes to anyone who calls `GET /api/v1/orders`. It also loads the
whole table into memory, so it gets slower every day.

**Fix:** scope to the signed-in user (as head, or as the order's customer),
paginate it, and add the filters the list screen will need — at minimum
`status` and a date range.

---

## 5. Five accounts share the password `password`  `#28`

The seeders still hash the literal `'password'` (re-checked 2026-10-29:
`HeadUserSeeder.php:19`, `StockTestDataSeeder.php:72` and `:99`,
`UserDetailSeeder.php` ×4), so any fresh environment gets these accounts.
Verified against the live Render database on **2026-09-16**:

```
  1 head_admin    role_id=3  password = "password"
  2 employee_one  role_id=2  password = "password"
  3 customer_one  role_id=1  password = "password"
  4 retailer_one  role_id=4  password = "password"
999 demo_head     role_id=3  password = "password"
```

Two of them are HEAD. The API is public at `api.lensnstories.com`.

**The live database has not been re-checked since** — please confirm before
acting. If they are still there: delete them, or set real passwords. And
change the seeders so new environments don't recreate them.

Also worth knowing: `AuthController` blocks deactivated users at login (good),
but a token **already issued** keeps working until it expires. So switching an
account off does not lock anyone out — deleting it or changing the password
does.

---

## 16. Entries may be silently thrown away until the `system_logs` migration runs

*(Numbered out of order so the existing §-links keep working.)*

**Where:** `app/Services/SystemLogService.php` (`log()`), called inside the
open `DB::transaction` of every write service: `CashTxnDetailService`,
`CashAutoEntryService`, `CashToGoldService`, `GoldToCashService`,
`PurchaseGoldService`, `SaleGoldService`, `StockInService`, `StockOutService`,
`AutoEntryService`, `FitemService`.

**The problem:** `log()` catches any failure and returns `null`, so the
business write carries on. On PostgreSQL that doesn't work. A failed statement
aborts the **whole** transaction, and catching the exception in PHP doesn't
undo that. Every later statement fails, and the final `COMMIT` quietly becomes
a `ROLLBACK`. PDO doesn't raise on that, so the API still answers `201` with
the new row's data, and nothing is saved.

**Why it matters today:** on 2026-11-03,
`2026_10_22_000000_create_system_logs_table` was **Pending** on the Aiven
database (`migrate:status`), so every log write failed there. No row had been
saved in `cash_txn_details`, `stock_details` or `cash_to_gold` since
2026-10-22, when the logging landed; the newest were 2026-10-13 (cash) and
2026-10-20 (stock). That fits writes being rolled back, but quiet days would
look the same.

On 2026-11-04 the Aiven host (`golderp-goldserp.f.aivencloud.com`) no longer
exists in DNS. The service looks powered off or deleted, so this couldn't be
re-checked. Whichever database replaces it needs the migration, and the fix
below applies regardless. Please confirm by saving one entry and checking
it's there.

**Fix:**

1. Run the migration on every database. `docker/entrypoint.sh` runs
   `migrate --force` on deploy, so deployed environments may already have it;
   local and Aiven do not.
2. Stop a failed log from taking the business write down with it. Either
   wrap the insert in its own transaction — inside an open one, Laravel's
   `DB::transaction()` issues a `SAVEPOINT`, so a failure rolls back only the
   log row — or write the log with `DB::afterCommit()`. Without this, any
   future log failure (a payload that won't encode, a column too short)
   silently drops the entry again.

Until the migration runs, the System Logs screen can only show a load error.

---

# 🟠 Soon

## 6. Live Metal Balance still crashes with a date + time filter  `#18`

**Where:** `app/Services/ReportService.php:974`

The query fails on PostgreSQL:

```php
->selectRaw("... COALESCE(...) as used_grams", [$dateTime])
->havingRaw('grams - used_grams > 0')     // <-- this line
```

**The problem:** `used_grams` is an alias from the SELECT list. PostgreSQL lets
you use an output alias in `ORDER BY` and `GROUP BY`, but **not in `WHERE` or
`HAVING`**. A bare `HAVING` with no `GROUP BY` also expects aggregates. The
error is:

```
SQLSTATE[42703]: Undefined column: column "used_grams" does not exist
```

The comment on line 972 says *"(PostgreSQL safe)"* — it is not, and that
comment will mislead the next person.

**The fix already exists in your own codebase.** `getAvailableStockLots`
(`StockDetailsController.php:738`) had the identical bug and was fixed
correctly — it repeats the subquery inside `whereRaw` instead of referencing
the alias. Copy that.

Until then, any call to `report/live-metal-balance` with `date` **and** `time`
returns a 500. The frontend does not expose those filters for this reason.

---

## 7. Four endpoints still read `X-User-ID` and ignore the token  `#2` `#37`

Every route sits behind `auth:api`, so `$request->user()` is always available.
Most of the code reads it. Four places do not:

| File | Line | Reads |
|---|---|---|
| `Services/CashAutoEntryService.php` | 38 | header only ❌ |
| `Controllers/Api/CashCategoryController.php` | 44 | header only ❌ |
| `Controllers/Api/ReportController.php` | 194 | header only ❌ (one-day-action) |
| `Controllers/Api/ReportController.php` | 224 | header only ❌ (daywise-grand) |

For comparison, these get it right:
`StockDetailsController:151`, `CashTxnDetailController:2405` and `:2429`,
`ReportController:145`, `FitemController:23`.

**Why it matters:** all four default to **user 1** when the header is absent.
For the two report endpoints that now means the report is silently scoped to
the wrong person — `getOneDayActionReport` gained real head scoping in PR #46,
so a missing header no longer just looks odd, it returns someone else's data.
For the two write paths it means `added_by` records the wrong operator.

**Fix:** use the same line everywhere:

```php
$userId = $request->user()->user_id ?? (int) $request->header('X-User-ID', 1);
```

The frontend sends the header everywhere as a workaround, so nothing breaks
when you change this.

---

## 8. Reminders: four follow-ups from PR #50

Four smaller things came out of wiring up the Reminders screen. All verified
against the running API on 2026-10-08, not read off the controller; all four
still stand in `ReminderController` as of 2026-10-29.

**a. It is the only endpoint with no response envelope.** Everything else in
this API returns `{success, message, data}`. `ReminderController` returns the
bare model or array. Not wrong, just inconsistent — every client needs a
special case for this one resource. Worth aligning while only one screen uses
it.

**b. `added_by` / `assign_to` change type depending on the endpoint.** The
relation methods `addedBy()` / `assignTo()` snake-case to `added_by` /
`assign_to`, which are also the foreign-key column names. Eloquent merges
relations *after* attributes, so the relation silently overwrites the scalar:

```
GET  /reminders       ->  "added_by": { user_id: 2, name: "...", ...60 fields }
POST /reminders       ->  "added_by": 2
```

Same field, object or integer depending on how you fetched the row. **Fix:**
rename the relations (`addedByUser` / `assignToUser`) or add an API Resource
that returns `added_by` as the id plus a separate `added_by_name`. The
frontend normalises both shapes today, so there is no rush, but this will
confuse the next person.

Related: the nested user is the **entire** `user_details` row, including
`rak_cash_balance` and the running gram totals. `password_hash` is correctly
in `$hidden`, so nothing secret leaks — but a reminders list should probably
not carry everyone's cash balances.

**c. `store()` and `update()` return no relations.** `index`/`show` eager-load
them, the write methods do not, so a create or edit response has ids but no
names to display. Same issue as the old mapping-controller ask (#12), which
was fixed by calling `->load([...])` before returning. Same fix here.

**d. `index()` is unscoped and unpaginated.** `Reminder::with([...])->get()`
returns every reminder in the system to every user. Reminders are assigned to
named people, so this one matters more than it does for orders. Scope it to
`added_by` **or** `assign_to` = the signed-in user, and paginate.

---

## 9. Missing pieces for the legacy Stock Details screen

We are rebuilding that screen against five screenshots of the old software
(2026-10-11). Most of it maps onto endpoints you already have. Three things
do not.

**a. There is no `INVEST` role.** The old screens show four user types —
CUSTOMER, EMPLOYEE, INVEST and head — and the screen behaves differently for
each. `RoleSeeder` defines only `1 CUSTOMER · 2 EMPLOYEE · 3 HEAD ·
4 PURCHASE · 5 SALARY`, so INVEST users cannot be represented at all.

Please add it as a new role rather than reusing PURCHASE — they are
different things in the old system, and overloading one would make the
numbering confusion in [§1](#1-wastage-is-added-to-the-wrong-persons-balance--21)
worse. The frontend matches on the role **name**, so it will pick INVEST up
automatically the moment the seeder runs; nothing needs coordinating.

**b. `REPLY OUT` / `REPLY IN` have no endpoint.** Two buttons on the old
screen's action rows, present for every user type. Nothing in the API
resembles them. We do not know what they are meant to do — if they are
dead in the old software too, say so and we will drop them.

**c. `Assign to Employee` and `Employee Deliver` have no endpoint.** Both
appear on the old screen whenever an EMPLOYEE is selected, next to where
CUSTOMER gets Customer Deliver. No route, controller or table for either.

All four buttons ship **disabled with a tooltip** in the meantime, so the
screen matches the old layout and the gaps are visible rather than silently
absent.

---

## 10. Editing a user silently drops its item / head / cash-head mappings

`UserDetailController::show()` eager-loads `itemMappings`,
`headEmployeeMappings` and `cashHeadEmployeeMappings`, and the Users screen
now loads all three into the edit form. But `update()` never reads
`item_mappings`, `head_mappings` or `cash_head_mappings` — only `store()`
does. The frontend sends them on `PUT /user-details/{id}` in exactly the
shape `store()` accepts (`head_mappings: [{ head_id }]`, etc.), and they are
ignored, so any change to a user's assigned items, heads or cash heads made
from the edit form is lost on save with a `200`.

Please have `update()` apply them when the key is present — **replace** the
set, the way `PUT /head-employee-mappings/{employee_id}` already does for
heads, rather than re-inserting the way `store()` does (re-inserting would
duplicate every mapping on each save). For item mappings, note that
`item_grams_total` / `item_purity_total` are live running balances: decide
whether an edit should be allowed to overwrite them or only add/remove rows
and toggle `is_primary`.

Until then the edit form shows an **amber banner** saying mapping changes
are not saved, and points to the Head Employee Mappings screen for
reports-to changes.

---

## 15. Printed receipts are missing data — stock has no print endpoint  `#40`

*(Numbered out of order so the links to §11–14 keep working.)*

Re-checked against the code on 2026-10-29, on every remote branch:
`GET /cash-txn-details/print-report` is still the **only** print endpoint.

**a. Cash receipt: Bal OB and Ex. CB always print blank.**
`CashTxnDetailController.php:201` and `:205` (`getPrintReport`, the `?id=`
branch) read `$transaction->sender_ob` and `$transaction->sender_cb`. Those
are not columns. The model has `sender_opening_cash` / `sender_opening_rtgs`
/ `sender_closing_cash` / `sender_closing_rtgs`, so both fields come back
`null` on every receipt. `CashTransactionReportResource` already adds
cash + RTGS for its opening and closing balance. Please do the same here, or
tell us if the receipt should show cash only.

**b. Stock receipts are pieced together from two reports.** The stock row
print (Transaction History and the ID Wise screen) now reproduces the
legacy slip: `"<from> TO <to>"`, date, `<name> Bal OB`,
Time / Items / Pcs / Gms / % / Purity, Total, `<name> Bal CB`. With no stock
print endpoint, each print makes two calls:

1. `GET /stock/reports/id-wise?stock_id=` for the date and time, both
   party names, and the item plus its `item_remarks`.
2. `GET /stock/reports/items-obcb`, narrowed to that exact second
   (`from_date`/`from_time` = `to_date`/`to_time` = `added_at`) and to
   `employee_id=user_<viewed-as user>`, for OB/CB. It's then matched on
   `stock_id`.

What that still can't give:

- **OB/CB when the row doesn't involve the viewed-as user.** For example, a
  head printing a mapped employee's entry: items-obcb doesn't return that
  row, so OB/CB print `—`.
- **`no_of_pcs`.** Neither report returns it. The Transaction History print
  takes it from the history row, so the ID Wise print leaves Pcs blank.
- **The suffix in the heading.** The old slip reads `NIHAA(RAMESH) TO …`.
  PR #52 settled the rule for the cash report: a user with the PURCHASE role
  is a retailer, and gets `(user_name)` after their name. Is the stock slip's
  bracket the same rule? If so, please return the suffixed name from the
  print endpoint below. Neither stock report gives the frontend the parties'
  roles, so we can't apply it reliably ourselves.

A `GET /stock-details/print-report?id=` that mirrors the cash one would
replace both calls. It should return:

- the heading names, with suffix
- date and time
- item with remarks
- pcs, grams, touch, purity
- OB/CB purity for the party the legacy slip shows

Please also confirm whose balance the legacy slip shows on the
`<name> Bal` line: the giver's, or the head's.

**c. Question: what is the bulk branch of `print-report` for?** Without
`?id=`, it returns raw `CashTxnDetail` models filtered by `head_id` /
`cash_user_id` / `from_date` / `to_date`. No screen has a print button for
it. If it matches a legacy print (for example, a head/user cash statement),
tell us which one and we will add the button.

**d. The cash receipt has no category or payment method.** The cash-modules
doc's receipt layout (§3.17) prints Category and Payment (cash or bank), but
the `?id=` payload carries neither. Please add the category name and the
payment method, plus the bank name for bank entries. The amount in words
("Rupees One Lakh Fifty Thousand Only") is now built by the frontend, so
nothing is needed for that.

---

## 17. Deleting a cash entry leaves both balances as if it happened

**Where:** `app/Http/Controllers/Api/CashTxnDetailController.php`: the
`apiResource('cash-txn-details')` write methods `store()` (line 328),
`update()` (line 879) and `destroy()` (line 1557).

**The problem:** all three still use the cash table's old columns:
`given_by`, `given_to`, `souce_type`, `bank_id`, `balance`,
`opening_account_balance`, and so on. `cash_txn_details` now has
`sender_id` / `recipient_id`, `payment_method`, `bank_account_id` and the
eight opening/closing snapshots, and none of the old names are in
`CashTxnDetail::$fillable`. What that does:

- **`DELETE /cash-txn-details/{id}`** looks the user up by
  `$transaction->given_by`, which is always null. So it skips the balance
  restore, then deletes the row and its images and answers `200`. The entry
  disappears from history, but both parties' `rak_cash_balance` (and the
  bank's `ledger_balance`) stay as if it had happened.
- **`PUT /cash-txn-details/{id}`**: the validator only knows the old names, so
  its balance recalculation runs on nulls.
- **`POST /cash-txn-details`**: the model drops the old names and the
  required `sender_id` / `recipient_id` are never set, so the insert should
  fail with a `500` (read from the code, not run). The cash-modules doc names
  this endpoint for Internal Transfer and Cash Loan.

The frontend calls none of the three, but any signed-in user can.

**Fix:** rewrite them against the current schema, reversing balances the
same way the service that wrote the entry moved them (sender and recipient,
cash or RTGS, and the bank). Until then, register only the read routes:
`apiResource(...)->only(['index', 'show'])`.

---

## 18. Cash features the cash-modules doc describes that don't exist

`Schain_ERP_Cash_Modules_Documentation` (rev 2.0.0) presents these as built.
None of them exist in `schainbackend/` as of 2026-11-03, and the frontend has
no screen for any of them:

| Doc § | Feature | What's missing |
|---|---|---|
| 3.3, 3.4 | Bill Cash / Payment Cash (only the customer's, or only the head's, balance moves) | `StoreCashTxnDetailRequest` accepts `amnt_transfer_to_head`, but `CashTxnDetailService` never reads it, so both parties always move. No `is_bill_cash` / `is_payment_cash` / `is_customer_affect` columns. |
| 3.6 | Internal Transfer (cash ↔ bank, bank ↔ bank) | No `amount_transfers` table, and nothing writes `INTERNAL_TRANSFER`. The out-history filter no longer includes the type either. |
| 3.7 | Cash Loan (16 combinations) | No columns for `cash_loan_type`, `rate_of_interest` or the arithmetic operators. Only the broken `store()` mentions them ([§17](#17-deleting-a-cash-entry-leaves-both-balances-as-if-it-happened)). |
| 3.12 | Machine Vendor Expense | No `machine_vendors` table, model or endpoint. |
| 3.13 | Salary advance link | `is_salary_person` exists on users, but there's no `salary_given_details` table and nothing links a payment to it. |
| 3.14 | Category 33 / 34 (3% GST, 5% margin) | No calculation anywhere. If built, find the two categories through a setting, not by id: ids differ between environments, which is what caused the Live Metal bug (`#17`). |
| 3.16 | Hide / Unhide cash entries | `cash_txn_details.is_hide` exists, but no endpoint sets it (`PUT` is broken, §17), and no history or report filters on it. |
| 7 | "Mathematical validation" of the gold figures | The four gold endpoints save `total_cash`, `per_gram_cash`, `total_grams`, `touch` and `purity` exactly as sent. Nothing checks purity = grams × touch ÷ 100. A cash-against-rate check would depend on **b** below. The one check that does exist is that the payment sources add up to `total_cash`. |

**Two places where the doc and the code disagree.** Please say which is right
before either team builds on them:

**a. Cash Auto Entry.** The doc says both parties' balances move and two rows
are written. `CashAutoEntryService` takes cash only from the sender and writes
one row per line. Its own comment says that's deliberate, copied from the
legacy code.

**b. What the rate is per gram of, for Cash To Gold and Purchase Gold.** The
doc's worked example (₹7,20,000 at ₹7,200 a gram, 99.5% touch) gives 100 g
gross and 99.5 g pure, so the rate is per gross gram. The screens divide cash
by the rate to get the **pure** weight: 100 g pure, 100.503 g gross. That
matches Gold To Cash, where the doc and the app agree the rate is per pure
gram. The backend saves whatever figures it's sent, so the screens decide
today. We won't change the calculation until this is confirmed.

---

# 🟡 When you can

## 11. The API testing doc is well behind the code  `#32`

`frontend/Stock Outward Module API Testing & Param.txt` was last extended on
2026-09-23 (`e2fe5953`). These still have **no documentation at all**:

- `GET /me`
- `GET /stock-details/available-lots`
- `POST /stock/unhide`
- `POST /stock/cash-out`
- `GET /report/daywise-grand`
- `apiResource('orders')` — everything except delete (list, show, create, update)
- the login **403** for deactivated accounts
- `GET /system-logs`, `/system-logs/{id}` and `/system-logs/filters` (PR #51)

And one active trap: **section 40 (Live Metal Balance) lists the query
parameters and `item_id` is not among them** — the one parameter that decides
which item you get back. Anyone following the doc gets a different item's
balances under a "Metal" heading.

Everything the frontend needed for these came from reading the request classes
and controllers.

## 12. One stale `sanctum` reference  `#15`

`app/Http/Controllers/Api/AuthController.php:112` — the OpenAPI annotation
still says `security={{"sanctum":{}}}`. The app uses Passport. Cosmetic, but it
goes into the generated API docs.

---

## 13. User fields the screens no longer collect are still required

The Users create/edit forms (and the Add User modal) no longer show **Code**
or **System ID**, make **Mailing name** and **Signature** optional, and
collect **Proof** as an image upload sent as `aadhar_image`, not as text.
But `UserDetailController::store()` still validates `code`, `system_id`
(`unique`), `signature`, `mailing_name` and `proff` as `required|string`,
and all five columns are `NOT NULL` in `create_user_details_table`.

Nothing is broken — the frontend fills blanks with the same placeholders
`PhoneBookController::store()` already uses (`'-'` for code / signature /
proff, the user's name for mailing name, a stamped `USER-<timestamp>` for
system_id so `unique` passes). But those placeholders are now real rows in
`user_details`. If you want the columns kept, please make them `nullable`
(validation **and** migration) so the frontend can send nothing instead.

Also worth confirming: is `proff` (varchar 155) the legacy slot for the
proof document's path? If proof now lives in `aadhar_image`, `proff` could
be dropped from `store()`'s required list entirely.

## 14. Roles cannot be activated or deactivated  `#39`

**Where:** `database/migrations/2026_07_23_125520_create_roles_table.php`,
`app/Models/Role.php`, `app/Http/Controllers/Api/RoleController.php`

The Roles screen now has an Active / Inactive toggle, but the backend has
nowhere to keep it. The `roles` table has only `id`, `role`, `added_at` and
`touch`; `Role::$fillable` has no status; and `store()` / `update()` validate
only `role`, `added_at` and `touch`. So `PUT /roles/{id}` with
`{ "is_active": false }` answers **200** and changes nothing — `validate()`
silently drops the field.

**What the frontend sends and expects** — the same shape items, cash
categories and bank details already use:

- a new migration adding `$table->boolean('is_active')->default(true);` to `roles`
- `Role`: `is_active` in `$fillable`, cast to `boolean`
- `store()` and `update()`: `'is_active' => 'sometimes|boolean'`
- `index()`, `show()` and both write responses return `is_active`
- `index()` keeps returning **all** roles, inactive included — the Users,
  Phone Book and Cash Management filters still need them

Nothing else is needed from the frontend once that lands: the on-screen
warning on Roles disappears by itself, the toggle starts saving, and inactive
roles drop out of the Role picker when creating or editing a user or client
(someone already on an inactive role keeps it).

Open question: should deactivating a role do anything to the users already
on it (block login, hide them)? The frontend currently only stops *new*
assignments.

## 19. Daywise Grand: "Customer" matches retailer tags, not the customer

**Where:** `app/Services/ReportService.php`, `getDaywiseGrandReport()`.

`type4=CUSTOMER` and `type4=RETAILER` run the identical query: they match
`customer_id` / `retailer_id` against the stock row's `retailer_id` /
`to_retailer_id` tags. A customer's own gold movements are recorded in
`given_by` / `given_to`, so picking a customer returns only the rows where
they were tagged as a retailer, usually none. `type4=EMPLOYEE` is the branch
that reads `given_by` / `given_to`, and it works for any user.

The new Daywise Grand screen offers *Me*, *A user* (`EMPLOYEE`) and
*A retailer* (`RETAILER`), and leaves Customer out. Is CUSTOMER meant to
behave like EMPLOYEE? If so, we'll add it back once it does.

Two smaller things on the same endpoint:

- **A missing id quietly becomes the head.** `type4=EMPLOYEE` without
  `employee_id` reports the acting head's figures. The screen requires a pick
  for that reason; a `422` would be safer for other callers.
- **It isn't documented** ([§11](#11-the-api-testing-doc-is-well-behind-the-code--32)).
  Everything the screen relies on was read off the service, including the
  fact that it sums every item together and skips hidden and frozen rows.

## 20. The cash-modules doc doesn't match the database

`Schain_ERP_Cash_Modules_Documentation` §4 (schema) and a few other passages
describe a different database from the one in `database/migrations`. Please
correct the doc, since both teams build from it:

- **One table, not three.** `cash_to_gold` (singular, key `cash_to_gold_id`,
  party column `customer_id`) holds Cash To Gold, Gold To Cash, Purchase Gold
  and Sale Gold, told apart by `type`. There are no `cash_to_golds`,
  `purchase_golds` or `sale_golds` tables.
- **`bank_details`** has only `account_name`, `ledger_balance` and
  `is_active`. There is no `bank_name`, `account_number`, `ifsc_code` or
  `current_balance`, so the doc's "updates `current_balance`" never happens.
  If account number and IFSC are wanted, that's new work. Tell us and we'll
  add them to the Bank Details screen.
- **`system_logs`** columns are `reference_id`, `reference_table`,
  `payload`, `metadata` and `description`, not `record_id` and
  `request_payload`.
- **Values:** Auto Entry rows are typed `AUTO_ENTRY`, not `CASH_AUTO_ENTRY`,
  and `payment_method` is `CASH_ON_HAND` / `BANK`, not `CASH` / `BANK`.
- **Roles:** §1 says role 4 is RETAILER, but `RoleSeeder` calls it
  PURCHASE. PR #52's comments confirm PURCHASE is the retailer role, so the
  doc should use that name, and refer to roles by name rather than number.
  Bare numbers are the trap behind [§1](#1-wastage-is-added-to-the-wrong-persons-balance--21)
  of this file.
- **Daywise Grand** is described as a daily cash overview. It's a stock
  grams/purity report ([§19](#19-daywise-grand-customer-matches-retailer-tags-not-the-customer)).

## 22. System logs: only new entries are logged, by user id

*(§21 was fixed in PR #52 and removed; the numbers after it are kept so links
keep working.)*

**Where:** `app/Services/SystemLogService.php` and the services that call it.

PR #52 made the logs HEAD-only and moved `filters()` onto role names. Two
points are still open:

- **Coverage.** Only the write services log: stock and cash creates, plus
  stock hide, unhide and cash-out. Nothing logs edits or deletes (cash
  `PUT` / `DELETE`, users, roles, items, banks, categories, settings,
  mappings, orders, reminders) or sign-in and sign-out. The legacy system's
  missing audit trail for edits and deletes is the gap the doc says this
  fixes.
- **Names.** Descriptions say "User #12". The screen looks names up from the
  current user list, which breaks for deleted users. Storing the name in
  `metadata` when the log is written would fix that.

The System Logs screen has a banner telling users about both gaps.

---

*When you fix something here, delete the item and move a one-line note into
`frontend/PENDING_WORK.md`. This file should only ever contain open work.*
