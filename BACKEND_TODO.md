# Backend — Open Items

For the backend team. **Only things that still need doing.** Anything fixed is
deleted from this file — the history lives in
[`frontend/PENDING_WORK.md`](frontend/PENDING_WORK.md).

**Checked against:** `schainbackend/` as it stands on **2026-10-15**. The
backend code has not changed since **PR #50** (`c4dd0445`) — the two commits
after it (`56a77ccb`, `dcc3b977`) are frontend-only, and `origin` was fetched
with nothing unpulled. Every item below was still re-read from source this
pass rather than carried forward. Anything fixed is deleted.

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

**Where:** `app/Services/StockInService.php:71` and `:74`,
`app/Services/StockOutService.php:74` and `:77`

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
`app/Services/ReportService.php:818`, and the missing seeder for
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

**The same gap takes out a second endpoint.** `ReportService:818` reads
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

**The problem:** we asked for a `head_id` override so an admin could pull
another head's report, and you added it — thank you. But there is **no check on
who is allowed to use it.** Any authenticated user can pass another user's id
and read that ledger. An employee can read their head's books.

Re-checking this pass found the same unchecked pattern on more than the two
reports first listed — every one of these takes the id straight from the
query string:

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

**Fix:** allow the override only for HEAD (or admin) accounts, the way
`getLiveMetalBalance` does. Everyone else gets their own data regardless of
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

**Status:** the `/run-seeders` and `/run-migrations` routes are **gone** —
thank you, that was the urgent half.

**What is left:** the accounts those seeders created. The seeders themselves
still hash the literal `'password'` (re-checked 2026-10-15:
`HeadUserSeeder.php:19`, `StockTestDataSeeder.php:72` and `:99`,
`UserDetailSeeder.php` ×4), so any fresh environment gets them again.
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

# 🟠 Soon

## 6. Live Metal Balance still crashes with a date + time filter  `#18`

**Where:** `app/Services/ReportService.php:855`

Half of this is genuinely fixed — `IFNULL` and the backticks are gone, replaced
with `COALESCE`. But the query still fails, now for a different reason:

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

The comment on line 853 says *"(PostgreSQL safe)"* — it is not, and that
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
| `Services/CashAutoEntryService.php` | 37 | header only ❌ |
| `Controllers/Api/CashCategoryController.php` | 44 | header only ❌ |
| `Controllers/Api/ReportController.php` | 192 | header only ❌ (one-day-action) |
| `Controllers/Api/ReportController.php` | 222 | header only ❌ (**daywise-grand, new**) |

For comparison, these get it right:
`StockDetailsController:151`, `CashTxnDetailController:2405` and `:2429`,
`ReportController:143`, `FitemController:23`.

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

The feature itself is **done** — thank you. The table, model, controller and
`apiResource('reminders')` all landed in PR #50 and the schema matched what we
proposed field for field. The screen is live and persisting.

Four smaller things came out of wiring it up. All verified against the running
API on 2026-10-08, not read off the controller; all four still stand in
`ReminderController` as of 2026-10-15.

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

*(The two open questions we flagged got answered by the implementation:
`index` returns everything, and complete/view are plain `PATCH` fields. The
second matches what the frontend already did.)*

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
  We don't know what goes in the brackets (retailer? user type?), and
  neither report returns it.

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

---

# 🟡 When you can

## 11. The API testing doc is well behind the code  `#32`

`frontend/Stock Outward Module API Testing & Param.txt` was last extended on
2026-09-23 (`e2fe5953`). That update covered `apiResource('settings')`,
`POST /fitems`, `POST /stock/hide`, both CSV exports, and `DELETE /orders/{id}`
— thank you. These still have **no documentation at all**:

- `GET /me`
- `GET /stock-details/available-lots`
- `POST /stock/unhide`
- `POST /stock/cash-out`
- `GET /report/daywise-grand`
- `apiResource('orders')` — everything except delete (list, show, create, update)
- the login **403** for deactivated accounts

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

---

# Recently fixed — thank you

Dropped from this list after re-checking the current code. Recorded here only
so nobody re-reports them; detail is in `PENDING_WORK.md`.

| Was | Now |
|---|---|
| API doc lacked settings, `POST /fitems`, `/stock/hide`, the CSV exports, order delete | Documented in `e2fe5953` (2026-09-23). The rest is still open, see [§11](#11-the-api-testing-doc-is-well-behind-the-code--32) |
| **Reminders / View Notes had no backend** (`#38`) | **PR #50** — table, model, controller, `apiResource('reminders')`. Schema matched the proposal exactly. Four polish items remain, see [§8](#8-reminders-four-follow-ups-from-pr-50) |
| `/run-seeders`, `/run-migrations` public | Routes removed |
| Login ignored `is_active` | Returns 403 |
| No `/me` endpoint | Exists, returns feature flags too |
| Auto Entry discarded the date | Top-level `added_at` validated |
| `postHide` / `postCash` unreachable | Both routed, plus a new `unhide` |
| No lot listing for non-metal items | `available-lots`, and it filters `is_hided` |
| `available-lots` date+time crashed | Fixed with `whereRaw` |
| Live Metal hardcoded to item 2 | Now a parameter with a settings fallback |
| Live Metal admin override unreachable | Checks `role->role === 'HEAD'`, accepts `view_as` |
| Customer touch mappings: no create/delete | `store` and `destroy` added |
| Mapping `update()` dropped relations | Now re-loads them |
| Customer Deliver had no backend | `order_details` migration + `OrderController` |
| No server-side export | Two CSV export routes |
| Reports had no `head_id` override | Added — but see [§3](#3-any-signed-in-user-can-read-any-heads-books) |
| One Day Action ignored its `$headId` | Scoped properly |
| Cash amounts returned in `grams` | Own `amount` field |
| `added_at` in `DD-MM-YYYY` | Now `Y-m-d H:i:s` |
| Paginated per source, then merged | Combined then paginated |
| Phone book writes were unsafe | Restricted to `index` + `show` |
| `role.role_name` always null | Uses the real `role` column |
| Display flags not writable | Now in the validation |
| Cash history needed `cash_user_id` | Optional — head-only view works |

---

*When you fix something here, delete the item and move a one-line note into
`frontend/PENDING_WORK.md`. This file should only ever contain open work.*
