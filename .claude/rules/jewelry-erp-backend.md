---
paths:
  - "schainbackend/**/*"
---

# Jewelry ERP Backend Rules

## Role

When working under `schainbackend/`, act as a senior Laravel + PostgreSQL engineer on a production gold/jewelry-business ERP.

The backend stack is:

* PHP 8.3
* Laravel 13
* Laravel Passport (`auth:api`)
* PostgreSQL (Aiven)
* Routes under `/api/v1`

Since 2026-11-06 the same team owns the frontend (`frontend/`) and the backend. The frontend rules are in `jewelry-erp-frontend.md`.

---

# CRITICAL: Preserve Business Behavior

Fixing a verified bug is expected. Silently changing how the business works is not.

Do not change these unless explicitly requested:

* calculations (grams, touch, purity, wastage, rates, amounts)
* which balances a transaction moves, and in which direction
* validations and required fields
* response shapes and field names
* permissions and role behavior

When a fix would change a money or gold figure (for example the wastage rule or the rate per gram), confirm the business rule with the user first.

---

# API Contract

* **One change, both sides.** If a route, HTTP method, payload field, response shape or status code changes, update `frontend/src/types` and `frontend/src/lib` in the same piece of work.
* Keep the `{ success, message, data }` response envelope.
* Validate input with a FormRequest (`app/Http/Requests`) or `Validator`. Bad input must return `422` with field errors and never reach a `500`.
* Never return raw exception messages to the client, because they leak SQL and the database host. Log the exception and return a generic message.
* Take the acting user from the token (`auth()->user()`), not from an `X-User-ID` header or a query-string id.
* Check roles by **name** (`HEAD`, `EMPLOYEE`, `CUSTOMER`, `PURCHASE`), never by `role_id`. Ids differ between databases.
* After route changes, run `php artisan route:list`, and confirm every referenced controller, method and class exists.

---

# Money and Gold Writes

* Every write that moves balances runs in one `DB::transaction`. Lock the user and bank rows with `lockForUpdate()` first, and read the opening balances after locking.
* Use bcmath (`bcadd` / `bcsub`, scale 4) for money and weights, not float arithmetic.
* Fill the opening/closing snapshot columns from the locked values.
* `entry_type` and `type` values must be allowed by the database CHECK constraints. Adding a value needs a migration that alters the constraint, on both `stock_details` and `stock_in_details`.
* Reversing a transaction (delete or edit) must undo exactly the balances the original service moved: sender and recipient, cash or RTGS, and the bank.
* `SystemLogService::log` must never be able to roll back the business write (savepoint or after-commit). Pass the real table name and primary key.

---

# PostgreSQL Specifics

* A failed statement aborts the whole transaction, and catching the exception in PHP does not recover it. Wrap anything that's allowed to fail in a nested `DB::transaction()`, which creates a savepoint.
* String comparison is case-sensitive: `'CashToGold'` is not `'CASHTOGOLD'`. Match `entry_type` values exactly as the services write them.
* Laravel `enum` columns are CHECK constraints in PostgreSQL.
* Don't write MySQL-only SQL (backtick quoting, `IF()`, `IFNULL` habits, and so on).

---

# Migrations and Data

* Change the schema only through new migrations. Never edit a migration that has already run on a shared database.
* The deployed Render service shares the Aiven database and runs `migrate --force` on every deploy, so a pushed migration reaches the shared data on the next deploy. Make migrations reversible and safe for existing rows.
* Never run plain `php artisan db:seed` on an empty database. `DatabaseSeeder`'s `setval` preamble shifts ids, and the role ids end up wrong. Run individual seeder classes instead.
* Check `DB_HOST` in `.env` before any artisan write. Test writes on Aiven are allowed while the system isn't live: tag test rows clearly and report them. Ask before anything destructive (drop, truncate, mass delete).
* Never print or commit credentials from `.env`.

---

# Testing

* `php artisan test` runs on in-memory SQLite (`phpunit.xml`), so it can't touch Aiven. It also can't catch PostgreSQL-only behavior: CHECK constraints, aborted transactions, case-sensitive matching.
* Verify every fix against PostgreSQL through the running API (or tinker) before calling it done.
* Add or extend a feature test for each fixed bug where SQLite can express it.

---

# Tracking

* `BACKEND_TODO.md` is our backlog. When an item is fixed, delete it there and add a struck-through history note in `frontend/PENDING_WORK.md`.
* When a fix removes the reason for a frontend warning banner, remove the banner in the same piece of work.

---

# Backend Review Checklist

Before completing any backend task, verify:

* The business rule is preserved, or the change was confirmed
* The frontend is updated for any contract change, and its build passes
* Bad input returns `422`, and no raw errors leak
* Balance writes use a transaction, row locks and bcmath
* Log entries reference the real table and key
* Migrations are reversible and constraint values are updated
* The fix was verified against PostgreSQL through the API, and `php artisan test` passes
* The git diff was reviewed for unintended behavior changes
