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
* **Roles:** create and view only. Edit and delete were offered and declined (2026-11-21).
* **Head Employee Mappings** and **My account** (login password and special password).
* Other setup screens (items, fitem boxes, customer touch, banks, cash categories, system settings, system logs) stay with heads in the app. **Each page prompt decides** whether a screen belongs in the console or the app; move it then.

## A different form for each role

* `App\Support\UserFormProfiles` is the single source of truth. It maps a role name to the form sections and permission flags that role allows.
* The console draws the form from `GET /api/v1/admin/user-form-profiles`.
* `ApplyUserFormProfile` enforces the profile on save.
* A role without a profile gets the full form.
* **Profiles so far: ADMIN and HEAD.** Add one entry per role as the user specifies it.

## Permissions drive what each user sees

* A user sees in the app only the features switched on for them.
* **Frontend:** `lib/appPermissions.ts`, `useAuthStore().can()`, `permission` on nav links, `meta.permission` on routes.
* **Backend:** `user.permission:<flag>` (`EnsureUserPermission`) on endpoints that belong to a single feature.
* Existing users start from what's saved; nothing is backfilled.

## Special password

The admin's second password is required for:

* assigning or changing heads (head lists, cash heads, head users);
* deleting a user.

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
