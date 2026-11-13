# Backend — Open Items

Our backend backlog. Since 2026-11-06 there's no separate backend team, so
we fix these ourselves, following `.claude/rules/jewelry-erp-backend.md`.
**Only things that still need doing.** Anything fixed is deleted from this
file — the history lives in
[`frontend/PENDING_WORK.md`](frontend/PENDING_WORK.md).

**Checked against:** `schainbackend/` as it stands on **2026-11-06**, after
**PR #53** (`982afd78`, merging `43e620eb`). PR #53 changed three backend
files: `UserDetailController` (user edits now save item mappings),
`ReportService` (stock history's In/Out filter) and `UserDetail` (an unused
import). It closed part of §10 and added the old §23, a regression in the
stock history filter (fixed 2026-11-12, along with §24). PR #52 (`2502a051`, 2026-11-04) closed the old §21 and two
points of §22. Nothing else in the backend has changed since the full re-read
on 2026-10-29, so every other item stands as checked then. §16–§20 and §22
came from checking the backend team's cash-modules document
(`Schain_ERP_Cash_Modules_Documentation`, rev 2.0.0) against the code on
2026-11-03.

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

## 5. Five existing accounts still use the password `password`  `#28`

*Seeders fixed on 2026-11-12:* they now take `SEED_USER_PASSWORD` from
`.env`, or generate a random password and print it. So no new environment
gets `password`.

**Deferred by decision until go-live:** `head_admin`, `employee_one`,
`customer_one`, `retailer_one` and `demo_head` on Aiven still have the
password `password`, and the deployed API is public. Before going live,
change or delete them. Changing a password does not end sessions already
signed in; revoke their tokens too.

---

## 16. Entries saved between 2026-10-22 and 2026-11-04 may be lost

*(Numbered out of order so the existing §-links keep working.)*

**The code fix is done (2026-11-12):** a failed log write can no longer
take the business write down with it. `SystemLogService::log()` now writes
in its own nested transaction, a savepoint. History is in `PENDING_WORK.md`.

**What happened:** the `system_logs` migration was never run on Aiven, so
every log write failed from 2026-10-22, when logging landed. On PostgreSQL
that silently rolled back the entry being logged, while the API still
answered `201`. No row was saved in `cash_txn_details`, `stock_details`
or `cash_to_gold` in that window; the newest before it are 2026-10-13 (cash)
and 2026-10-20 (stock). The migration was run on Aiven on 2026-11-04, and
saves have worked since.

**Still to do (not code):**

1. **Check the other databases.** `docker/entrypoint.sh` runs
   `migrate --force` on deploy, so deployed environments should have the
   table. Please confirm, along with anyone's local database.
2. **Ask operators whether they entered anything against Aiven between
   2026-10-22 and 2026-11-04.** If they did, those entries are gone (the
   screens showed them as saved) and need entering again.

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

## 10. Editing a user: heads and cash heads still aren't saved, and the new item sync has problems

*PR #53 (`43e620eb`) made `update()` save item mappings. Everything below is
still open.*

**Where:** `app/Http/Controllers/Api/UserDetailController.php:1482–1532`
(`update()`, `PUT /user-details/{id}`)

**Still not saved:** `head_mappings` and `cash_head_mappings`. Only
`store()` reads them. The frontend sends them on `PUT /user-details/{id}` in
the shape `store()` accepts (`[{ head_id }]`), and they are ignored with a
`200`. Please **replace** the set when the key is present, the way
`PUT /head-employee-mappings/{employee_id}` already does for heads. Don't
re-insert the way `store()` does, which would duplicate every mapping on
each save.

**Problems in the new item sync:**

1. **Updating an existing row fails the whole save.** For rows that carry an
   `id`, the code sets `'updated_at' => now()`, but `users_items_mappings`
   has no `updated_at` column (see
   `2026_07_25_160555_create_users_items_mappings_table.php`; the model also
   has `$timestamps = false`). Postgres rejects the statement, the
   transaction rolls back, and the user save returns `500`. Please drop
   `updated_at` from that update. The frontend won't send row ids until
   this is fixed.
2. **Without ids, every save deletes and re-creates all of the user's
   rows.** That resets `added_at` and wipes `stone_cost_grand_total`,
   `beads_cost_grand_total`, `beads_stone_cost_grand_total` and
   `last_txn_date`, which the request doesn't carry. Once point 1 is fixed,
   the frontend can send ids so rows are updated in place.
3. **Saving can put back stale balances.** `item_grams_total` and
   `item_purity_total` are live running balances, and the sync overwrites
   them with whatever the form sent. If stock moves for that user while the
   edit form is open, saving restores the old figure. Suggestion: on update,
   ignore the two totals for existing rows (only add or remove rows and
   toggle `is_primary`), and use the sent totals only for new rows. Until
   then the edit form warns operators to reopen the user if stock has moved.
4. **No validation.** `store()` validates `item_mappings.*` (around line
   400); `update()` doesn't, so a missing `is_primary` or a bad `item_id`
   gives a `500` instead of a `422`. Please copy the `store()` rules.
5. **An empty list can't be sent with a file upload.** When a proof image is
   attached the frontend has to send multipart, and multipart can't carry an
   empty array. The empty-string fallback arrives as `null`, which
   `array_column()` throws on. If `update()` treated a `null` or empty
   `item_mappings` as `[]`, a user's last item could be removed in the same
   save as an image upload. Today the frontend sends `[]` on JSON saves
   only.

**Checked with real saves on 2026-11-06** (test user 7, "Claude Test",
through `PUT /user-details/7`):

| Request | Result |
|---|---|
| Rows without ids (what the frontend sends) | `200`. Rows 19–20 deleted and re-created as 21–22, `stone_cost_grand_total` (123.45) and `last_txn_date` wiped, `added_at` reset. `head_mappings: []` ignored, head 1 still mapped |
| A row with its id | `500`: `column "updated_at" of relation "users_items_mappings" does not exist`. Nothing changed |
| Multipart with `item_mappings=''` | `500`: `array_column(): Argument #1 ($array) must be of type array, null given` |
| `item_mappings: []` | `200`, all rows removed |
| A row without `is_primary` | `500`: `Undefined array key "is_primary"` |

The `500` responses also return the raw exception text in `error`, which
for SQL errors includes the database host name. That's
`UserDetailController::update()`'s catch block, and it doesn't depend on
`APP_DEBUG`.

The edit form's **amber banner** says items save but heads and cash heads
don't, and points to the Head Employee Mappings screen for reports-to
changes.

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

## 17. Loose ends found while building entry corrections

Entry editing and corrections are done (2026-11-12; history in
`PENDING_WORK.md`). These came up along the way and are still open:

- **The gold screens don't write `obcb_details` or the `*_op` columns** on
  their own stock rows, so the Items OB/CB report shows no OB/CB for those
  rows.
- **Gold to Cash always books item 3**, whatever item applies (legacy
  hard-coding).
- **Sale Gold without "transfer to head" doesn't move the head's gold or
  cash**, only the customer's (legacy behaviour, unchanged).
- **Numeric Wastage In stores the piece count in an integer column**
  (`stock_in_details.no_of_pcs`), but `NumericWastageInRequest` accepts any
  number above 0. A fractional count (2.5) passes validation and then fails
  on PostgreSQL with a `500`. The screen's step is 1, so it only bites
  through the API. Either validate `integer` or widen the column (Out uses
  `decimal(12,4)`); which one depends on whether half pieces exist.
- **A numeric wastage correction recomputes purity with the screens'
  formula.** The API accepts a custom `purity` on a new line (no screen
  sends one). The reversal cancels the saved purity exactly, but the corrected
  line uses (grams + wastage) × touch ÷ 100.

---

## 18. Cash features the cash-modules doc describes that don't exist

`Schain_ERP_Cash_Modules_Documentation` (rev 2.0.0) presents these as built.
None of them exist in `schainbackend/` as of 2026-11-03, and the frontend has
no screen for any of them:

| Doc § | Feature | What's missing |
|---|---|---|
| 3.3, 3.4 | Bill Cash / Payment Cash (only the customer's, or only the head's, balance moves) | `StoreCashTxnDetailRequest` accepts `amnt_transfer_to_head`, but `CashTxnDetailService` never reads it, so both parties always move. No `is_bill_cash` / `is_payment_cash` / `is_customer_affect` columns. |
| 3.6 | Internal Transfer (cash ↔ bank, bank ↔ bank) | No `amount_transfers` table, and nothing writes `INTERNAL_TRANSFER`. The out-history filter no longer includes the type either. |
| 3.7 | Cash Loan (16 combinations) | No columns for `cash_loan_type`, `rate_of_interest` or the arithmetic operators. Only the old, now-disabled `store()` mentioned them. |
| 3.12 | Machine Vendor Expense | No `machine_vendors` table, model or endpoint. |
| 3.13 | Salary advance link | `is_salary_person` exists on users, but there's no `salary_given_details` table and nothing links a payment to it. |
| 3.14 | Category 33 / 34 (3% GST, 5% margin) | No calculation anywhere. If built, find the two categories through a setting, not by id: ids differ between environments, which is what caused the Live Metal bug (`#17`). |
| 3.16 | Hide / Unhide cash entries | `cash_txn_details.is_hide` exists, but no endpoint sets it (the cash edit, `PUT /cash-txn-details/{id}`, doesn't take it), and no history or report filters on it. |
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

PR #52 made the logs HEAD-only and moved `filters()` onto role names. Three
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
- **Several logs point at tables that don't exist** *(found 2026-11-06)*.
  The `reference_table` / `reference_id` passed to `log()` don't match the
  models:

  | Service | Logs as | Real table, key | Effect |
  |---|---|---|---|
  | `SaleGoldService.php:349` | `sale_golds`, `sale_gold_id ?? id` | `cash_to_gold`, `cash_to_gold_id` | id is always `null` (seen on log rows 1–2) |
  | `PurchaseGoldService.php:330` | `purchase_golds`, `purchase_gold_id ?? id` | `cash_to_gold`, `cash_to_gold_id` | id is always `null` |
  | `CashToGoldService.php:256`, `GoldToCashService.php:274` | `cash_to_golds` | `cash_to_gold` | wrong table name; id is right |
  | `StockOutService.php:345` (item change) | `item_change_histories`, `history_id ?? id` | `item_change_history`, `change_id` | id is always `null` |
  | `StockOutService.php:704` (GMS out) | `gms_histories`, `gms_history_id ?? id` | `gms_history`, `gms_id` | id is always `null` |

  The System Logs screen shows these as e.g. "sale_golds #—", so a log
  entry can't be traced back to its transaction.

The System Logs screen has a banner telling users about the first two gaps.

---

*When you fix something here, delete the item and move a one-line note into
`frontend/PENDING_WORK.md`. This file should only ever contain open work.*
