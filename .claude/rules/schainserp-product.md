# SchainsERP — Product and Working Agreement

Agreed with the user on 2026-11-21. Applies to all work in this repository, frontend and backend. The stack rules are in `jewelry-erp-backend.md` and `jewelry-erp-frontend.md`.

---

# What We're Building

* A rebuild of a legacy gold-factory ERP (an old PHP app the user shows as screenshots) on Laravel 13 + Vue 3.
* It manages how gold and cash move inside the factory and to customers.
* We own both the UI and the backend.

---

# Architecture Already in Place

## Two realms, two sign-ins

* **App:** `/login`, for `user_details` accounts (heads, employees, customers, ...). API in `routes/api.php`.
* **Admin console:** `/admin/login`, for accounts in the `admins` table, which are not heads. API in `routes/admin.php`; frontend in `frontend/src/admin/`. Admins are created with `php artisan admin:create`.
* Tokens never cross: Passport providers are pinned (`users` / `admins`) and admin tokens carry the `admin` scope (`App\Support\AdminAccess`).

## What the admin console holds

* **Users:** create users, assign a role, heads, and the role's fields and permissions.
* **Roles:** create, view, and switch the status (2026-11-23). Name edits and delete were offered and declined (2026-11-21).
  * The status is switched from the Status column and needs the special password.
  * Switching a role off locks its users out of the app: sign-in refuses them (403), and an open session ends on its next request (401, `EnsureAppSession` on every `auth:api` route). Switching it back on lets them in again; their own account status is untouched.
* **Phone Book** (2026-11-24): its own `phone_book` table (name, phone, role, active), not the users table.
  * Every user was copied in once (the migration), and each user the console creates is added (`PhoneBookSync`; a number already there is linked, not duplicated).
  * Editing or deleting a contact never changes a user. `user_id` only links a contact to its user, for the picture.
  * Phones: exactly 10 digits, unique in the phone book.
  * The app's Phone Book page reads the same list, read-only (`is_phone_book_need_shown`).
* **Head Employee Mappings** and **My account** (login password and special password).
* **Stock reports for every head** (2026-11-24): ID Wise, One Day Action, Consolidated, Items OB CB, reusing the heads' screens with a console data source (`/admin/reports/*`, `AdminReportController`).
  * Items OB CB and Consolidated are grouped by head: one section per head, each with its own paging and export, plus a Head filter. The figures are exactly as that head sees them.
  * One Day Action covers every head, with a Head filter (empty = all heads). ID Wise opens any stock ID; the per-row receipt print is hidden in the console.
* **One Day Action** (2026-11-25), in both logins: two sections like the legacy screen, **Stock Report** and **Cash Management Report**, each with its Total and its own Load More (`section=stock|cash`). Both sections start closed (the header shows the Total). Today by default; empty To Date = one day.
  * Filters as on the legacy screen: User (entered by, `added_by`), Items (stock only), Stock Type IN/OUT (on cash: the Receive / Pay groups), Given By, Given To, Role (either party has it, `role_id`). Rules: `App\Support\OneDayActionFilters`.
  * Export: one CSV of both sections with a Section column; pages not loaded yet are fetched first.
* **Item Conversion Group History** and **Item Change History** (2026-11-25), in both logins: what the Stocks page's Item Conversion (`gold_conversions` + alloys) and Item Change (`item_change_history`) recorded. `ItemHistoryReportService`; app `GET /stock/reports/item-conversion-groups|item-changes`, console `/admin/reports/…`.
  * Neither table stores who did it: the user is `given_by` on the OUT stock row (both features only work on the user's own stock), which also gives the remarks. The automatic "Item … from: <id>" remark is shown as none.
  * App: the signed-in user's own records plus a head's mapped employees' (`scopeFor`). Each page needs its feature's permission (`is_item_conversion_need_shown` / `is_item_change_need_shown`), on the route and the menu link. Console: every user, or a head (with its employees) via the Head filter.
  * Conversions are **grouped by item pair** (source → target), pairs in Orders No order, each group with its count and totals, foldable, its conversions newest first (alloys, bill no). Changes: one row each, newest first, totals over the whole search.
  * Filters: Item (either side), From / To Date (the pages open on the current month, 1st to today, agreed 2026-11-25; the endpoints alone default to today; empty To Date = one day). Stock IDs link to ID Wise, which now opens `?stock_id=`.
* **Customer Touch** is also managed from the console (heads keep theirs in the app).

## Side menus (agreed 2026-11-24)

* **App:** Stocks, Cash Management, Consolidated Report, Items OB CB, One Day Action Report, Item Conversion Group History, Item Change History (2026-11-25, each with its permission), *company group*, ID Wise, Phone Book. Every other screen is in **More** at the bottom.
* **Console:** Roles, Phone Book, Users, ID Wise, One Day Action Report, Item Conversion Group History, Item Change History, Consolidated Report, Items OB CB, *company group*, Customer Touch, Items, Salary, then Head Employee Mappings and My account.
* **Company group** (both logins): headed with the `company_name` system setting (NIHAA JEWELS; heads can change it in System Settings). Children: Roles History, Customers, Employees, Retailers, Customer FITEM.
* **Not built yet:** the company group's pages and the console's Salary open `ComingSoonView`; build each when its screens come.
* The menus live in `lib/nav.ts` (`buildNavItems`, `companyGroup`, `COMPANY_PAGES`) and `admin/lib/nav.ts` (`buildAdminNavItems`).
* **Items** (2026-11-24): in the console only. Heads read items (`GET /items`, every dropdown) but can't change them.
  * Columns follow the legacy list: Item Name, Item Touch, Is Need Fitem Shown, Is Active, Orders No.
  * **Orders No** (`items.order_no`) is unique, 1 or more; a new item gets the next free number. `GET /items` sorts by it (then name), so **every item dropdown and list follows it**. Don't re-sort items in the browser.
  * An item still used anywhere (users' items, stock, history) can't be deleted, because deleting would cascade into balances and history. Switch it off instead.
* Other setup screens (fitem boxes, customer touch, banks, cash categories, system settings, system logs) stay with heads in the app. **Each page prompt decides** whether a screen belongs in the console or the app; move it then.

## A different form for each role

* `App\Support\UserFormProfiles` is the single source of truth. It maps a role name to the form sections and permission flags that role allows.
* The console draws the form from `GET /api/v1/admin/user-form-profiles`.
* `ApplyUserFormProfile` enforces the profile on save.
* A role without a profile gets the full form: every section except the role-only ones (`ROLE_ONLY_SECTIONS`: HEAD's Head users, SALARY's Additional phone, EMPLOYEE's Alloy value / Is outside vendor). Their input is dropped on save too (fixed 2026-11-25).
* **Profiles so far: ADMIN, HEAD, CUSTOMER, SALARY and EMPLOYEE.** Add one entry per role as the user specifies it. PURCHASE and the other roles still get the full form.
* A profile can also set `signs_in: false` (no Name or Password on the form; the name follows the user name and a random password is set), `default_category` and `default_primary_item` (what a new user starts with), `default_flags` (permissions a new user starts with on) and `joining_date_defaults_to_today`. On create, the defaults follow a role change (`applyRoleDefaults` in `admin/lib/userForm.ts`).
  * CUSTOMER: no sign-in, PURITY, GOLD. Customers are records for tracking, not accounts.
  * SALARY: no sign-in, GRAMS, GOLD, salary person YES, joining date today, plus an Additional phone no.
  * HEAD: category BOTH and GOLD as the primary item (2026-11-24).
  * EMPLOYEE (2026-11-25): the legacy employee form. No sign-in, BOTH, GOLD, joining date today; Head lists, items, balances, image, joining date, plus **Alloy value** (`alloy_value`, 0 or more, 3 decimals, stored only) and **Is outside vendor** (`is_outside_vendor`, separate from the "Show outside" permission). No permission switches; existing employees keep their saved switches.
  * The GOLD item exists on Aiven since 2026-11-24 (item #9, created with the table defaults: default touch 92, item touch 0). Item rows on the form: the item and Primary can be changed; grams and purity stay read-only, because transactions keep them.
* **Opening balance updates** (2026-11-24): the only manual way to change a user's balances.
  * The buttons: Grams / Purity on each item row, and GRAMS / PURITY / CASH / RTGS on the balances. The modal has Remarks, Previous, Value and Closing, where closing = previous + value (negative reduces).
  * An item's grams or purity also moves the grams or purity grand total by the same value. The grand-total buttons change only the grand total.
  * Existing user: `POST /admin/user-details/{id}/opening-balance`, saved at once.
  * New user: the entries wait on the form and go with the create request as `opening_balances`, in the same transaction.
  * Always the special password. Every change is recorded in `opening_balance_updates` (`OpeningBalanceService`, bcmath, row locks) and the admin audit log.
* Phones: `phone_no` is required and `additional_phone_no` optional (SALARY only so far). Both are exactly 10 digits, and no number may belong to two users in either column.
* Only HEAD users can be chosen as heads (Head lists, Cash heads) or listed as a head's Head users. A head's Head users are its links to other heads only.

## Permissions drive what each user sees

* A user sees in the app only the features switched on for them.
* **Frontend:** `lib/appPermissions.ts`, `useAuthStore().can()`, `permission` on nav links, `meta.permission` on routes.
* **Backend:** `user.permission:<flag>` (`EnsureUserPermission`) on endpoints that belong to a single feature.
* Existing users start from what's saved; nothing is backfilled.

## Special password

The admin's second password is required for:

* assigning or changing heads (head lists, cash heads, head users);
* opening balance updates (below);
* deleting a user;
* switching a role on or off.

---

# How Each Page Gets Built

The user sends one page at a time, with screenshots of the legacy software.

1. **Read the screenshots.**
   * List every field, button, column, filter and action.
   * Map each one to a database column or endpoint.
   * Note what's missing in the backend.
2. **Clarify before implementing.** Post the plan:
   * fields → columns;
   * new columns or endpoints;
   * where the page lives (console or app);
   * which role profile or permission governs it;
   * any change to existing behaviour.

   Ask about anything ambiguous with AskUserQuestion, giving a recommendation. **Do not start until the user confirms.**
3. **Implement the backend and frontend together.** A contract change updates both sides in the same piece of work.
4. **Test every implementation.** Everything must pass before reporting (see Testing).
5. **Report** what was done, the test results, any test data left on Aiven, and open points. **Never commit.** The user commits.

---

# Testing

Every implementation runs all of these:

* **Backend:**
  * `php artisan test`. Add or extend a feature test for each behaviour.
  * After route changes, `php artisan route:list` plus a check that every referenced controller method exists.
* **Frontend logic:** `npm test` (Vitest, `frontend/tests/unit/`).
  * It goes through `scripts/vitest.mjs`. On Windows, a lowercase `c:\` working directory otherwise makes Vitest load twice.
* **Types and build:** `npx vue-tsc --build --force` (covers the tests too) and `npm run build`.
* **Browser:** `npm run test:e2e` (Playwright, `frontend/e2e/`).
  * Add a test for each new page: sign in, open the page, check its fields for each role.
  * Look at the screenshots in `frontend/test-results/screens/` before reporting.
  * These tests only read: the dev database is the shared Aiven one.
  * Credentials live in `frontend/.env.e2e`, which is git-ignored (template: `.env.e2e.example`).
* **PostgreSQL:**
  * Exercise the change through the running API: a temporary `php artisan serve --port=8010`, because port 8000 is the user's `start-dev`.
  * Use clearly tagged test rows (`zz_...`).
  * Clean up and report what's left.
