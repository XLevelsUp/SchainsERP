# Backend — Open Items

For the backend team. **Only things that still need doing.** Anything fixed is
deleted from this file — the history lives in
[`frontend/PENDING_WORK.md`](frontend/PENDING_WORK.md).

**Checked against:** `schainbackend/` as it stands on **2026-10-08**, up to and
including **PR #50** (`c4dd0445`). Read from source, and for PR #50 confirmed
against the running API. Nothing here is copied forward untested — items get
re-verified each pass, and anything fixed is deleted.

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

## 2. Any signed-in user can read any head's reports  *(new)*

**Where:** `app/Http/Controllers/Api/StockDetailsController.php:495` and `:516`

```php
$headId = $request->query('head_id') ?? $this->getActingUserId($request);
```

**The problem:** we asked for a `head_id` override so an admin could pull
another head's report, and you added it — thank you. But there is **no check on
who is allowed to use it.** Any authenticated user can pass
`?head_id=<anyone>` and read that head's full Items OB/CB and Consolidated
ledger. An employee can read their head's books.

The same applies to the two export routes (`:36`, `:63`), which hand the whole
filtered set back as a CSV.

**Fix:** allow the override only for HEAD (or admin) accounts, the way
`getLiveMetalBalance` does. Everyone else gets their own data regardless of
what they pass.

---

## 3. Every user sees every order  *(new)*

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

## 4. Five accounts share the password `password`  `#28`

**Status:** the `/run-seeders` and `/run-migrations` routes are **gone** —
thank you, that was the urgent half.

**What is left:** the accounts those seeders created. Verified against the live
Render database on **2026-09-16**:

```
  1 head_admin    role_id=3  password = "password"
  2 employee_one  role_id=2  password = "password"
  3 customer_one  role_id=1  password = "password"
  4 retailer_one  role_id=4  password = "password"
999 demo_head     role_id=3  password = "password"
```

Two of them are HEAD. The API is public at `api.lensnstories.com`.

**Not re-checked today** — no database access from this session, so please
confirm before acting. If they are still there: delete them, or set real
passwords.

Also worth knowing: `AuthController` blocks deactivated users at login (good),
but a token **already issued** keeps working until it expires. So switching an
account off does not lock anyone out — deleting it or changing the password
does.

---

# 🟠 Soon

## 5. Live Metal Balance still crashes with a date + time filter  `#18`

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

## 6. Four endpoints still read `X-User-ID` and ignore the token  `#2` `#37`

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

## 7. Reminders: four follow-ups from PR #50  *(new)*

The feature itself is **done** — thank you. The table, model, controller and
`apiResource('reminders')` all landed in PR #50 and the schema matched what we
proposed field for field. The screen is live and persisting.

Four smaller things came out of wiring it up. All verified against the running
API on 2026-10-08, not read off the controller.

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

## 8. `live_metal_report_items` has no seeder  *(new)*

**Where:** `app/Services/ReportService.php:816-825`

Live Metal Balance now resolves its item like this: use `item_id` if given,
otherwise read the `live_metal_report_items` system setting, otherwise throw a
validation error.

That is a good design — much better than the hardcoded `2` it replaced. But
`system_settings` has a migration and **no seeder**, so on a fresh database the
setting is empty and the endpoint fails unless the caller passes `item_id`.

**Fix:** seed `live_metal_report_items` with the Metal item, ideally looked up
by name so it survives differing ids between environments.

(The frontend always sends `item_id` — resolved by name — so our screen works
either way. This is for anyone else calling the endpoint.)

---

# 🟡 When you can

## 9. The API testing doc is well behind the code  `#32`

`frontend/Stock Outward Module API Testing & Param.txt` was last extended on
2026-09-11 (sections 36–52). Since then these have shipped with **no
documentation at all**:

- `GET /me`
- `GET /stock-details/available-lots`
- `POST /stock/hide`, `POST /stock/unhide`
- `POST /stock/cash-out`
- `GET /report/daywise-grand`
- `GET /stock/reports/items-obcb/export`, `/consolidated/export`
- `apiResource('settings')`
- `apiResource('orders')`
- `POST /fitems`
- the login **403** for deactivated accounts

And one active trap: **section 40 (Live Metal Balance) lists the query
parameters and `item_id` is not among them** — the one parameter that decides
which item you get back. Anyone following the doc gets a different item's
balances under a "Metal" heading.

Everything the frontend needed for these came from reading the request classes
and controllers.

## 10. One stale `sanctum` reference  `#15`

`app/Http/Controllers/Api/AuthController.php:112` — the OpenAPI annotation
still says `security={{"sanctum":{}}}`. The app uses Passport. Cosmetic, but it
goes into the generated API docs.

---

# Recently fixed — thank you

Dropped from this list after re-checking the current code. Recorded here only
so nobody re-reports them; detail is in `PENDING_WORK.md`.

| Was | Now |
|---|---|
| **Reminders / View Notes had no backend** (`#38`) | **PR #50** — table, model, controller, `apiResource('reminders')`. Schema matched the proposal exactly. Four polish items remain, see [§7](#7-reminders-four-follow-ups-from-pr-50-new) |
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
| Reports had no `head_id` override | Added — but see [#2](#2-any-signed-in-user-can-read-any-heads-reports-new) |
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
