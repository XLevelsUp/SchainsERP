# Frontend Pending Work & Backend Asks

Living tracker for the frontend team, and **the history**. Items stay here once
fixed, struck through, with what changed — this file is the record.

> ### 📋 Backend asks now live in [`BACKEND_TODO.md`](../BACKEND_TODO.md)
>
> As of 2026-09-30 the open backend items have their own file at the repo
> root, written for the backend team in plain language, containing **only what
> is still outstanding**. Items are deleted from it as they are fixed.
>
> **Both files get updated from now on:**
>
> | File | Audience | Contains |
> |---|---|---|
> | `BACKEND_TODO.md` | Backend team | Open items only — deleted once fixed |
> | `PENDING_WORK.md` (this) | Frontend team | Everything, including fixed items, as history |
>
> When a backend item is fixed: strike it through here with the evidence, and
> delete it from `BACKEND_TODO.md`.

**Last updated:** 2026-11-21
**Backend baseline:** `982afd78` (PR #53) plus our own uncommitted backend
fixes below. Since 2026-11-06 we own the backend too.

### HEAD form + the app shows only what's switched on (2026-11-21)

Following the legacy head user form. **Agreed with the user:** existing
users start from what's saved, with no backfill. Most switches are off on
Aiven today, so features stay hidden until an admin switches them on per
user.

**Backend**
- Migration `2026_11_21_000100_add_head_form_fields_to_user_details_table`
  adds:
  - `aadhar_number` (`proff` holds free-text proof notes, not this);
  - `aadhar_back_image`;
  - three permissions, default off: `is_item_change_need_shown`,
    `is_item_conversion_need_shown`, `is_phone_book_need_shown`.
- **HEAD profile** (`UserFormProfiles`):
  - Sections: head users, items, balances, image, profile picture, proof
    (Aadhar number, front and back), salary (per-day salary with Salary
    person), joining date, report password, permissions.
  - 15 flags.
  - Role can change on edit; ADMIN's can't (`lock_role_on_edit`, enforced
    in `ApplyUserFormProfile`).
- **ADMIN's "Image Url"** is now `image` (the `image_url` column), not the
  profile picture. The HEAD form shows the two side by side.
- **Head users:**
  - `head_user_mappings: [{employee_id}]` is the head side of
    `head_employee_mappings`, synced by `UserMappingSyncService::syncHeadUsers`.
  - It's a head assignment, so the special password applies.
  - A head may list themselves, as the legacy form does. The employee-side
    sync leaves that row alone.
- **Stored on save:** `per_day_salary`. `report_password` is **hashed**
  (blank keeps it). `show()` returns only `has_report_password`, plus
  `image_url` / `aadhar_back_image_url`. `destroy()` deletes the new files.
- ~~**Login and `/me` leaked `report_password` and `otp`.**~~ **Fixed.**
  `AuthUser` hid only `password_hash`.
- **`EnsureUserPermission` (`user.permission:<flag>`)** refuses a feature's
  own endpoint when the signed-in user's switch is off:
  - item-change → item change;
  - item-conversion → item conversion;
  - stock cash-out → cash management;
  - phone-book index/show → phone book;
  - `POST orders` → create order.
  Shared endpoints (cash in/out) are left to the screens.
- Tests: `AdminHeadFormTest` (6), plus `ItemChangeTest` / `OrderAccessTest`.
  Their fixtures now carry the permissions, and each has a "refused without
  it" test. Full suite 101/101.

**Frontend**
- HEAD form in `UserFormCard`:
  - Head users (every user, the head included);
  - Image and Profile picture;
  - Aadhar number, front and back;
  - Salary person with per-day salary (editable when Yes);
  - joining date;
  - report password ("Set" with Change, write-only);
  - 14 switches, two columns.
  Role is editable on edit unless locked. The view dialog shows the same
  sections.
- `lib/appPermissions.ts` lists which permissions the app checks.
  `AuthUser.permissions` is filled from login / `/me`, and
  `useAuthStore().can()` reads it.
- Gated by the signed-in user's permissions:
  - Stock → Item Change / Item Conversion buttons;
  - Stock → Cash Out / Cash In;
  - Phone Book (nav link and route);
  - Orders → New order;
  - Stock → customer touch panel (the user's switch **and**, as before, the
    customer's).
- Stored but not wired yet (no such feature in the app): delivery item /
  checks, polish needed / checks, WhatsApp share, thermal cash amount,
  complete history, cash reminder.

**Postgres verification (2026-11-21):**
- Migration applied.
- Signed in as `admin`, created test HEAD `zz_headform_test` (id 13)
  multipart with three images. The special password was required (head
  users); customer comments were dropped; the report password was set but
  never returned.
- Signed in to the app as that head:
  - permissions arrived, with no report password / otp in the response;
  - phone book (on) → 200;
  - item conversion, cash out, new order (off) → 403;
  - item change (on) → past the gate.
- Head-users edit needed the special password, and a self entry was
  allowed. Deleted afterwards: no rows, files or live tokens left.

### Admin console — Users page, role-driven form (2026-11-21)

Modelled on the legacy "User Details" list and the legacy admin user form.
**Each role gets its own form.** The sections and permissions offered
depend on the user's role. ADMIN is defined from the user's screenshots.
HEAD and the others come next; until each is specified, a role gets the full
form, exactly as before.

**Backend**
- `App\Support\UserFormProfiles` is the single source of truth: role name →
  sections + permission flags.
  - Sections: contact, remarks, customer_comments, heads, cash_heads, items,
    balances, customer_touches, profile_image, proof, joining_date, account,
    flags.
  - Always on the form: role, name, user name, phone, password, category.
  - **ADMIN:** heads, customer comments, items, balances, customer touches,
    image, joining date, and 6 flags. The flags are cash reminder, WhatsApp
    share in stock, delivery item (head), delivery checks (employee), cash
    amount on thermal print, and customer touch.
  - Adding HEAD's set is one entry here.
- `GET /api/v1/admin/user-form-profiles` returns the catalog (`by_role_id`,
  `default`).
- `ApplyUserFormProfile` (`user.profile`) runs on admin user create/update. It
  drops input the role doesn't allow (flags outside it, sections it lacks) so
  the backend defaults apply. On create, the three calculation engines default
  on, the same as the column defaults, so the same as the legacy form.
- New user-form behaviour (store / update, `UserMappingSyncService`):
  - joining date: migration `2026_11_21_000000_add_joining_date_to_user_details_table`;
  - customer touches saved with the user, `customer_touch_mappings`,
    replaced as a set on edit;
  - head lists editable on edit: sent only when changed, replace the set
    (`[]` clears it), and need the special password.
    `RequireSpecialPassword` now triggers on "sent", not "filled", so
    clearing counts;
  - admins pick items and the primary flag, but **never totals**:
    - new rows start at 0, also on create;
    - kept rows keep their totals;
    - an item still holding a balance can't be removed (422 naming it).
- Head ids are now validated with `exists` and `distinct` (an unknown id
  used to 500 on the foreign key).
- **Admins see balances, read-only** (`BalanceVisibility::viewerIsAdmin`),
  as the legacy admin form shows them. `viewerIsHead()` stays false for
  admins, so the head-only writes still refuse.
- ~~**`GET /user-details/{id}` leaked any user's grams/purity.**~~
  **Fixed.** `show()` added `gm` / `purity` after the model had hidden the
  balances, so any signed-in user could read anyone's. Now gated by
  `canSeeBalancesOf`.
- The list (`index`) also returns `user_name`, `role_id`,
  `is_salary_person` and `per_day_salary`.
- `PATCH /admin/user-details/{id}/status`: the list's Active toggle,
  audited as `USER_STATUS_CHANGE`. `GET /admin/customer-touch` is read-only,
  for the touch picker.
- Tests: `AdminUserFormTest` (9). Full suite 93/93.

**Frontend**
- `admin/views/UsersView.vue` (rewritten):
  - columns: user name, phone, role, salary person, per-day salary, status,
    plus view / edit / delete;
  - one filter per column (status starts on Active, like the legacy list);
  - 20 a page with "Show all";
  - status toggle behind a confirm.
- `admin/components/users/UserFormCard.vue`: the role-driven form.
  - Role is picked first on create and read-only on edit.
  - Sections render from the profile.
  - Item totals and balances are read-only, and rows holding a balance can't
    change item or be removed.
  - A short flag list shows flat in two columns; the full set stays grouped.
  - The special password is asked for inline when heads go out.
- `admin/components/users/UserViewDialog.vue`: read-only view, same sections.
- `admin/lib/userForm.ts` and `admin/lib/userFormProfiles.ts` hold the
  helpers.
- `userDetailsApi`:
  - `update()` takes `includeHeadMappings`;
  - profile image goes multipart;
  - `joining_date` and `customer_touch_mappings` are in the payload;
  - an empty list goes multipart as `''`;
  - new `setStatus()`.
- Catalog labels for the six legacy-named flags now use the legacy wording.

**Postgres verification (2026-11-21), signed in as `admin`:**
- Created role **ADMIN** (id 7) through the Roles API. It's kept, since the
  ADMIN form needs it.
- An ADMIN-role test user (`zz_userform_test`, id 12) checked:
  - the special password on create and on a heads change (rename needed none);
  - cash heads, remarks and act-as-head dropped by the profile;
  - item total 0 although 77 was sent;
  - balances visible;
  - joining date and touch saved;
  - status toggle;
  - delete with the special password.
- The user was deleted, with no orphan rows. Its audit rows stay.

### Admin console — 2026-11-20

A separate admin console with its own accounts, sign-in, layout and API.
Rules agreed with the user:

- **An admin is not a head.** Admins are their own accounts (new table
  `admins`, `App\Models\Admin`). They don't hold balances and don't appear in
  any picker.
- **Admins create users, assign them to heads, and create roles.** The
  console has Users, Head Employee Mappings (stock and cash), Roles (create
  and view: name + active status, no edit or delete — added on a follow-up
  the same day, modelled on the legacy admin Roles screen), and the admin's
  own passwords. Heads keep everything else in the app, as before: items,
  fitem boxes, customer touch (+ mappings), banks, cash categories,
  settings, System Logs.
- **The special password.** Each admin has a second password, asked for
  again for sensitive changes: assigning or changing heads (stock and cash,
  including when creating a user with heads) and deleting a user.
- **The app keeps Add Retailer only.** The Stock screen's full "Add User"
  is gone.

**Backend**
- Migrations: `2026_11_20_000000_create_admins_table` (name, user_name,
  password_hash, special_password_hash, is_active, last_login_at,
  password/special changed timestamps) and
  `2026_11_20_000100_add_admin_id_to_system_logs_table` (nullable FK,
  `nullOnDelete`). Both additive and reversible. **Already applied to Aiven
  2026-11-20.**
- Auth: `admin` guard (Passport) on its own `admins` provider. Realms are
  kept apart twice:
  - Admin tokens come from a personal access client pinned to `admins`, so
    Passport refuses them on `auth:api`, and app tokens on `auth:admin`. Each
    side gets a 401.
  - Admin tokens also carry the `admin` scope, which `EnsureAdminSession`
    requires.
  `docker/entrypoint.sh` now creates the `admins` personal access client on
  deploy. It was created on Aiven by hand on 2026-11-20.
- `routes/admin.php` (`/api/v1/admin`, `admin.` names):
  - login (throttled 5/min per user+IP), logout, me;
  - `account/password` and `account/special-password` (each needs the
    current value; the two must differ);
  - user-details CRUD;
  - head-employee-mappings index/store/update;
  - cash-head-employee-mappings;
  - read-only roles and items for the Users form's pickers.
- `RequireSpecialPassword` (`special.password`): reads the
  `X-Special-Password` header. Missing or wrong → 422 on `special_password`;
  5 wrong tries → 429 for 5 minutes. It applies:
  - always on delete and on the mapping writes;
  - on create only when `head_mappings` / `cash_head_mappings` are filled.
    `update()` never touches head mappings, so edits don't need it.
- `LogAdminAction` (`admin.audit`) writes one audit row per successful admin
  change. Module `ADMIN`, `admin_id` set, `user_id` null. Actions:
  USER_CREATE / USER_UPDATE / USER_DELETE (keeps the deleted user's name),
  HEAD_ASSIGN, CASH_HEAD_ASSIGN / CASH_HEAD_UNASSIGN, ADMIN_PASSWORD_CHANGE,
  ADMIN_SPECIAL_PASSWORD_CHANGE (no payload). ADMIN_LOGIN / ADMIN_LOGOUT are
  logged by the controller. Metadata records `special_password_verified`.
- `SystemLogService`:
  - takes `adminId`, and no longer reads `user()->id` as a user id (that
    would have credited admin #1's actions to user #1);
  - also redacts the new password fields.
- `routes/api.php`:
  - heads' admin writes are back (`head.only`), and System Logs is back for
    heads;
  - user update/delete and head-mapping writes are gone from the app (405);
  - `POST user-details` gets `EnsureQuickAddCustomer`: CUSTOMER only, mapped
    to the signed-in user only, no cash heads, no act-as-head flag. That is
    Add Retailer's exact payload.
- `UserDetailController::store` lets admins create heads and grant "Can act
  as head".
- Artisan: `admin:create {user_name} --name=` and
  `admin:reset-password {user_name} [--special]`. Both ask for passwords
  hidden, and reset signs the admin out everywhere. There is no screen for
  creating admins.
- ~~**`GET /system-logs/filters` 500'd on PostgreSQL.**~~ **Fixed.**
  `whereHas('role')` compared varchar `role_id` with bigint `roles.id`, so
  the filter dropdowns never loaded. Role ids are now looked up first.
- ~~**`head-employee-mappings` show/destroy routes pointed at missing
  methods.**~~ **Fixed.** Only the methods that exist are registered.
- `LoginRejectedException` renders the envelope and isn't reported, so a
  wrong password no longer writes a stack trace to the log.
- **Roles** (follow-up, same day):
  - BACKEND_TODO §14 fixed: `2026_11_20_000200_add_is_active_to_roles_table`
    (default true), `Role` cast, and `RoleController` validates and returns
    `is_active`.
  - `store()` saves names trimmed and in capitals and refuses duplicates
    ignoring case (422). Several checks match role names exactly (e.g.
    `'CUSTOMER'`), so "Customer" or a second "HEAD" would quietly break them.
  - Admin routes: roles index / store / show. The app keeps reading roles but
    lost role writes (`roles` added to `$adminOwned`). `ROLE_CREATE` is
    audited.
- ~~**Creating a role failed on Aiven.**~~ **Fixed.** The roles had been
  seeded with explicit ids 1–5, but the id sequence was left at 1, so the
  first insert hit `duplicate key … roles_pkey (id)=(1)`.
  `2026_11_20_000300_resync_roles_id_sequence` moves the sequence past the
  highest id (PostgreSQL only, safe to re-run). A scan of every serial
  column on Aiven found no other drifted sequence.
- Tests: `AdminAuthTest` (8, real tokens, admin #1 vs user #1),
  `AdminAccessTest` (14), `AdminCommandsTest` (3). In `RecordAccessTest`,
  the item-totals test was rewritten (see Open below). 84/84 passing.

**Frontend**
- `src/admin/`:
  - client and the special-password client (`adminApi.ts`);
  - auth API and account API;
  - store, shell, nav, routes;
  - Users, Head Employee Mappings, Roles and My account (`AdminAccountView`);
  - `SpecialPasswordDialog`.
- Admin Roles screen (`admin/views/RolesView.vue`): # / Role / Status / view,
  name search, status filter (starts on Active, like the legacy screen),
  "Showing N of M", Refresh, an inline "New role" form (name + Active), and
  a view dialog (role, status, added, id). It replaces the heads' app
  `views/RolesView.vue`, which is deleted along with its route and nav entry.
- The special password is asked for:
  - inline on the Users form, only when a new user has heads or cash heads;
  - inline on every Head Employee Mappings save;
  - in `SpecialPasswordDialog` for Delete user, which replaces the old
    confirm popover.
  It's sent as a header, never in the body or kept.
- Admin Users screen: assigned items are read-only when editing, because
  totals are balances and the backend ignores an admin's.
- `lib/authSession.ts`, `stores/sessionState.ts` and `lib/authApi.ts` are
  generic over the realm's account type (`AuthUser` / `AdminUser`). The admin
  session key is now `schainserp:admin-auth:v2`. `lib/api.ts` clients take
  default headers.
- Restored to the app for heads: Items, Fitem Boxes, System Settings,
  Customer Touch (+ mappings), Bank Details, System Logs. The System Logs
  "Performed by" column shows "Name (admin)" on ADMIN rows.
- Stock screen: "Add User" and `components/user/AddUserModal.vue` removed;
  Add Retailer unchanged.

**Postgres verification (2026-11-20).** Through the running API on Aiven:
- a test admin `zz_admin_console_test_admin` (id 1) signed in;
- the special password was required (missing / wrong → 422) and accepted;
- tokens were refused across realms both ways;
- Add Retailer's restriction held;
- heads saw the ADMIN entries in System Logs.

Test users 10 (employee) and 11 (customer) were created and deleted again
through the endpoints. Left behind:
- admin 1, deactivated;
- head test user 8, deactivated again;
- `system_logs` rows 52–58.
All safe to delete.

Roles, on Aiven: the `is_active` and sequence migrations were applied
(existing roles all active). Signed in as `admin`, a test role `ZZ TEST
ROLE` (id 6, inactive) was created, a different-case duplicate was refused
(422), and the role was viewed. The test role was then deleted. Its
`ROLE_CREATE` log row (65) stays.

**Dev account:** `admin` / `admin` (admin id 2), special password
`special123`, created on request for testing. It's weaker than the
8-character rule. Change it before go-live: My account, or
`php artisan admin:reset-password admin`.

**Open**
- ~~**No screen edits a user's item totals any more.**~~ Settled 2026-11-21
  by the legacy admin user form: item totals are read-only there too
  (transactions keep them). See "Users page" above.
- `user_details.is_admin_login_otp_need_to_shown` belongs to the old idea of
  an "admin login" for users. It does nothing now.
- Create the real admin(s) on the server: `php artisan admin:create <name>`.

### Step 2 (security) — 2026-11-12

Business rules agreed with the user before coding:

- Heads can read anyone's books; everyone else only their own.
- Heads and employees see all orders; customers and retailers only their own.
- For the shared `password` accounts, fix the seeders only for now.
- Only the item named "Metal" opens the metal picker.

- ~~**BACKEND_TODO §3 — any signed-in user could read any head's books.**~~
  **Fixed.** New trait `app/Http/Controllers/Concerns/ScopesRecordAccess.php`,
  used by `StockDetailsController`, `CashTxnDetailController` and
  `ReportController`.
  - A non-head who names another user in `head_id`, `employee_id`,
    `user_id`, `head_user_id`, `customer_id` or `retailer_id` (including
    `user_<id>` / `retailer_<id>`) gets
    `403 "You can only view your own records."`.
  - ID Wise (`stock_id`) and the single receipt (`print-report?id=`) also
    check that a non-head is a party to the row.
  - Cash in/out history, the bulk print report and stock cash history used
    to return **every** user's rows when `head_id` was left out. They now
    default to the signed-in user.
  - `report/cash-transactions-obcb` had no user scope at all. Non-heads now
    get only entries they're a party to; heads still see everything.
- ~~**BACKEND_TODO §7 — `X-User-ID` header trusted.**~~ **Fixed.** Nothing
  in the backend reads the header any more: One Day Action, Daywise Grand,
  Cash Auto Entry's `added_by`, cash categories' `added_by`, Fitem and the
  user list all use the token's user. Before this, One Day Action and
  Daywise Grand fell back to user 1 when the header was missing.
- **Tests:** new `tests/Feature/RecordAccessTest.php` (7 tests). The 3
  `StockInDetailsTest` tests were already failing (they sent only the header
  and got `401`); they now sign in with `Passport::actingAs`. The suite is at
  12/12 passing. They run on SQLite: Aiven's host didn't resolve all of
  2026-11-12 and Docker wasn't running, so nothing was checked against
  Postgres.
- **Frontend follow-up:** the API modules still send `X-User-ID` and their
  comments say the backend reads it. That's harmless now but misleading;
  see Pending.
- **New:** BACKEND_TODO §25 covers the two access gaps left: the user list
  shows everyone's balances, and write endpoints take the head from the body.
- ~~**BACKEND_TODO §4 — every user saw every order.**~~ **Fixed.**
  `OrderController` was rewritten:
  - Heads and employees see and manage every order; customers and retailers
    only orders where they're the customer (`403` otherwise, on list, show,
    create, edit and delete).
  - `GET /orders` is paginated (`per_page` 1–200, default 50) with
    `status`, `customer_id`, `from_date` and `to_date` filters, newest first.
    The response is `{ data: Order[], pagination }`, the same shape as System
    Logs.
  - The `remarks` / `due_date` rules were dropped (no such columns, so they
    caused 500s).
  - `create` returns the row with its database-set `added_at`.
  - Frontend: `OrdersView` uses server-side filters, adds a date range and a
    pager, and loses its stale warning banner. `ordersApi.list(query)` returns
    `{ rows, pagination }`. New shared `types/pagination.ts` (`ApiPagination`);
    `SystemLogPagination` is now an alias of it.
  - 6 tests in `tests/Feature/OrderAccessTest.php`.
- **BACKEND_TODO §5 — seeders fixed, accounts kept (by decision).** All
  seeded accounts now get `SEED_USER_PASSWORD` (new `config('app.seed_user_password')`,
  added to `.env.example`), or a random password printed during seeding
  (`database/seeders/Support/SeedPassword.php`). The five existing
  `password` accounts on Aiven stay until go-live (§5 keeps that part).
  2 tests in `SeedPasswordTest`.
- **BACKEND_TODO §2 — metal picker settings.** Fixed in code: migration
  `2026_11_12_000000_seed_metal_item_settings` and `SystemSettingSeeder` set
  `metal_popup_items` and `live_metal_report_items` to the item named
  "Metal" (found by name, existing keys kept). Frontend `lib/metalItem.ts`
  now reads `metal_popup_items` (cached and reactive, with the name match as
  fallback), and the System Settings screen clears that cache on save or
  delete. 3 tests in `MetalSettingsTest`. **Not yet run on Aiven** (it was
  down); §2 keeps that step.
- **BACKEND_TODO §17 — cash edit/delete made safe, rewrite still open.**
  `apiResource('cash-txn-details')` is now `->only(['index', 'show'])`, so
  the broken `store`/`update`/`destroy` can't corrupt balances. `index`,
  `show` and the image add/delete routes follow the §3 rule. The frontend
  only used `show`. The rewrite needs decisions, listed in §17.
- **Backend suite: 25/25 passing; frontend build passes.**

**Later the same day — access model refined (user decisions):** anyone given
access can act as a head and enter values; access is a permission on the
user; such users can open the head's books but never see balances;
non-heads never see balances.

- **New permission `is_act_as_head_enabled`** ("Can act as head"):
  - Migration `2026_11_12_000100_add_is_act_as_head_enabled_to_user_details`.
  - Validated and saved by user create/update.
  - A new "Access" group on the Users screen holds the toggle.
- **`ScopesRecordAccess`:** heads and permitted users may open anyone's
  history, lots, stock summary, entries and receipts. The balance reports
  (Items OB/CB, Consolidated, and their exports) stay HEAD-only for other
  users. Everyone else gets only their own records.
- **Entering values** (all stock POSTs, the four gold endpoints, cash
  in/out/auto entry, cash images, fitems) now needs HEAD or the permission:
  new `EnsureCanActAsHead` middleware (`act.as.head`), otherwise `403`.
- **Balances hidden from non-heads** (new `App\Support\BalanceVisibility`):
  - `UserDetail`, `UsersItemsMapping` and `CashTxnDetail` leave out other
    users' balance fields and opening/closing snapshots when serialised.
  - The user list leaves out `hand_cash`/`rtgs_cash`/`gm`/`purity` for
    other users.
  - The Cash Transactions report returns `null` OB/CB for balances that
    aren't the viewer's.
  - Your own balance is always visible.
- **User edit:** only a head's `item_mappings` are applied. A non-head
  can't see the item totals, so their form would have zeroed them.
- **Frontend:**
  - The auth store keeps `role_name` and `is_act_as_head_enabled` (from
    login and `/me`) and exposes `isHead` / `canActAsHead`.
  - Withheld balances show "—": PartyBalanceCards (including computed
    closing balances that came out NaN), the Cash Management balance strip
    (which would have crashed on `undefined.toLocaleString()`), the cash
    report's OB/CB and CSV, the user picker labels (name and role only, no
    fake "0.000"), and the post-save balance line on the four gold screens.
  - On the Users edit form the assigned-items section is read-only for
    non-heads, and their saves leave `item_mappings` out.
- **New:** BACKEND_TODO §26: the admin endpoints (users, roles, items,
  settings, banks, categories, mappings) still have no role checks.
- **Backend suite: 30/30 passing; frontend build passes.** Still SQLite
  only; Aiven was down all day.

**Later still — Aiven back, §26 and §17 phase 1:**

- **Migrations run on Aiven:** `2026_11_12_000000_seed_metal_item_settings`
  (both settings set to item 1, "Metal") and
  `2026_11_12_000100_add_is_act_as_head_enabled_to_user_details`. §2 is
  closed.
- **Step 2 verified on Postgres:** 13 API checks passed (metal picker,
  books access, balance hiding, write gate, orders, disabled cash delete).
- ~~**BACKEND_TODO §26 — admin endpoints had no role checks.**~~ **Fixed.**
  - Users, items, fitem boxes, roles, customer touch (and its user
    mappings), users-items-mappings, head-employee and cash-head-employee
    mappings, cash categories, banks and settings: reading stays open, but
    changes are HEAD-only (new `EnsureHead` middleware, `head.only`).
  - Creating a user is also open to "Can act as head" users (Add Retailer
    on the Stock screen), but they can't create a head or grant the
    permission (`403`).
  - Saving customer comments (`update-cc`) needs HEAD or the permission.
  - 3 tests in `AdminAccessTest`.
- ~~**BACKEND_TODO §17 phase 1 — cash entry editing.**~~ **Done.**
  - New `PUT/PATCH /cash-txn-details/{id}` (heads only) →
    `CashTxnEditService`. It edits the amount, payment method and bank,
    category, remarks and date of plain Cash In / Cash Out, and corrects
    balances, the bank(s), this entry's closings and every later entry's
    opening/closing snapshots for both parties. It logs `CASH_EDIT`.
  - `show()` returns `edit_blocked_reason`.
  - 5 tests in `CashEditTest`, including a full ledger-chain consistency
    check.
  - Verified on Aiven with test entries #85 (in, 7→1, ₹100) and #86 (out,
    1→7, ₹40): edited to 130, to bank 1, then back. Every balance, the bank
    and the later entry's snapshots matched and returned exactly; 17 checks
    passed.
  - Frontend: `CashTxnDetailModal` has an **Edit** form for heads (amount,
    payment method, bank, category, date & time, remarks) and explains why
    other entries can't be edited yet. The history table reloads after a
    save. The modal's bank label now uses `account_name` (it read a
    `bank_name` column that doesn't exist).
- **Backend suite: 38/38 passing; frontend build passes.**
- ~~**BACKEND_TODO §17 phase 2 — Cash Auto Entry editing.**~~ **Done.**
  - `CashTxnEditService::edit()` (renamed from `editCashInOut`) now also
    takes `AUTO_ENTRY` rows: only the sender's cash moves, matching
    `CashAutoEntryService`. The payment method can't change (`422`).
  - 2 more tests in `CashEditTest`; the suite is at 40/40.
  - Verified on Aiven with test rows #87/#88 (head 1 → user 7, ₹60 + ₹25):
    edited to 80 and back, all 10 checks passed.
  - Frontend: the edit form hides payment method and bank for auto entries
    and explains that only the sender's cash moves.
- **§17 phase 3 decided: gold entries get correcting entries** (reverse the
  original, post the corrected one, link them), because editing weights in
  place would mean rewriting frozen stock snapshots in two tables whose
  insertion order isn't reliable.
- ~~**§17 phase 3 backend — gold corrections.**~~ **Done.**
  - `GoldCorrectionService` re-runs the original's own service with
    negated money/weight (the reversal), then with the corrected input.
  - New endpoints `GET /gold-entries/{id}` (prefill) and
    `POST /gold-entries/{id}/correct`, both heads only.
  - Migration `2026_11_12_000200_add_correction_links_to_cash_to_gold`
    (run on Aiven).
  - `GoldToCashService` / `PurchaseGoldService` take a `reversal` option
    that skips only their "insufficient balance" checks.
  - `BaseStockService` collapses doubled minus signs (`'-' . -1` used to
    crash bcmath).
  - 5 tests in `GoldCorrectionTest`, one per kind plus rules: corrected
    balances equal entering the right values directly. The suite is at
    45/45.
  - Verified on Aiven (test entry #1 → #5 → #7, reversals #4/#6, balances
    restored exactly).
- ~~**Gold correction screens.**~~ **Done.**
  - New `types/goldEntry.ts`, `lib/goldEntriesApi.ts` and
    `lib/goldCorrection.ts` (prefill helpers and the shared note).
  - The four gold API modules gained `correct(goldEntryId, form)`, which
    reuses their own field builder and posts JSON.
  - `CashToGoldModal`, `GoldToCashModal`, `PurchaseGoldModal` and
    `SaleGoldModal` take an optional `correction` prop. They open prefilled,
    titled "Correct … #N", with item and transfer toggle locked, no receipt
    upload, and no balance preview (it would leave the original in). Saving
    calls `correct()`. Purchase keeps a deliberately lower "taken" figure
    instead of letting it follow the totals.
  - New `GoldCorrectionHost` loads items and banks and opens the right
    modal.
  - `CashTxnDetailModal` shows **Correct gold entry #N** to heads on
    gold-linked rows, explains when an entry can't be corrected (a reversal,
    or already corrected), and emits `correct-gold`.
    `CashTxnHistoryTable` closes the detail modal, opens the host and reloads
    after saving.
  - The Sale window's exact payload was verified on Aiven: test entry #2 →
    #9 → #11 → #13, reversals #8, #10 and #12. #2 had been created by
    script with ₹6,000 where the window's formula gives ₹5,496, so a final
    correction restored ₹6,000; the test customer's balances are back.
- ~~**BACKEND_TODO §17 — numeric wastage corrections.**~~ **Done. That
  finishes entry editing.** BACKEND_TODO §17 now only lists the loose ends.
  - `NumericWastageCorrectionService` corrects one Numeric Wastage In or Out
    line the same way as gold. It re-runs `createNumericWasteIn` /
    `createNumericWaste` with grams, pieces, wastage weight, purity and
    amount negated (the reversal), then with the corrected line, validated
    by the same rules as a new line. The parties and item can't change.
  - A line is identified by its stock row id, the "ID : N" shown in its cash
    entry's remarks. New migration
    `2026_11_12_000300_add_correction_links_to_stock_rows` (run on Aiven)
    adds `reversal_of_id` / `corrects_id` / `corrected_by_id` to
    `stock_details` and `stock_in_details`.
  - The reversal's and the corrected line's cash entries get
    "- reversal of #N" / "- corrects #N" after their usual remarks.
  - New endpoints, heads only: `GET /numeric-wastage-entries/{in|out}/{id}`
    (prefill) and `POST …/correct`. `GET /cash-txn-details/{id}` returns
    `numeric_wastage: {direction, id}` for these cash entries, and their edit
    message now points at the correction.
  - Fixed along the way: the stock services' cash entries (both numeric
    wastage directions and the Stock screen's cash out) now fill all eight
    opening/closing cash and RTGS snapshots (new
    `BaseStockService::cashOnHandSnapshots`). Before, only the opening cash
    was filled, so the Cash Transactions report had no OB/CB for them.
    Numeric Wastage Out and the stock cash out now also lock both users'
    rows.
  - 4 tests in `NumericWastageCorrectionTest`: in and out with cash,
    dropping the cash, and rules. Each checks the balances equal entering the
    right line directly, and that the cash snapshots chain from entry to
    entry. The suite is at 49/49.
  - Frontend: new `types/numericWastageEntry.ts`,
    `lib/numericWastageEntriesApi.ts`, and `lib/numericWastage.ts` (the WValue
    / Total formulas, now shared by Numeric Wastage In, Out and the
    correction). New `NumericWastageCorrectionModal`: one line, same fields,
    item locked, no date, and a cash figure showing the old value when it
    changes. `CashTxnDetailModal` shows **Correct numeric wastage in/out #N**
    to heads and emits `correct-numeric-wastage`; `CashTxnHistoryTable` opens
    the modal and reloads after saving.
  - Verified on Aiven between head_admin (1) and test user 7. IN #26 → #28
    (reversal #27) → #30 (reversal #29); OUT #45 → #47 (reversal #46), which
    cancels the IN. Then the window's exact payload: #30 → #32 (reversal #31),
    remarks only. Cash entries #99–#108. Users 1 and 7 ended with exactly
    their starting cash, RTGS, grams, purity and item totals (user 7 now has
    a zero item-1 row, which the first IN created). The cash snapshots chain
    across all the new entries.
- ~~**BACKEND_TODO §27 — raw exception text in `500` responses.**~~
  **Fixed.**
  - 71 failure responses in 13 controllers stopped sending
    `'error' => $e->getMessage()`. That included the `config('app.debug')` /
    `env('APP_DEBUG')`-gated ones, since APP_DEBUG may be on in a deployment.
    Every one still has its `message`, which is what the screens show.
  - 37 of those catch blocks didn't log at all; they now call `report($e)`,
    so the detail goes to `storage/logs/laravel.log`.
  - The five `InvalidArgumentException` handlers keep returning their
    message: it's our own text ("insufficient balance", "already
    corrected"), not a database error.
  - Verified on Aiven: a Numeric Wastage In with 2.5 pieces (the integer
    column bug, BACKEND_TODO §17) now answers `500` with only "Failed to
    process Numeric Wastage In transaction."; the SQL error is in the log
    and nothing was written. Suite 49/49.
  - Still worth checking: Render's `APP_DEBUG` must be `false`. An exception
    no controller catches is rendered by Laravel itself, which includes the
    message and stack trace when debug is on.
- ~~**BACKEND_TODO §24 — Sale Gold "In Cash Converter" couldn't be
  saved.**~~ **Fixed.**
  - Migration `2026_11_13_000000_allow_in_cash_converter_entry_type` (run on
    Aiven) adds `IN_CASH_CONVERTER` to the `entry_type` CHECK on
    `stock_details` and `stock_in_details`. Postgres gets the constraint
    rebuilt; SQLite (tests) gets the enum changed. The down migration
    refuses if such rows exist by then.
  - `SaleGoldController` and `PurchaseGoldController` had no error handling,
    so the services' own rules (sources must add up, insufficient cash or
    bank) came back as a bare `500`. They now return `422` with the reason,
    like Cash to Gold and Gold to Cash; anything else is logged and returns
    a generic `500`.
  - All four gold controllers took `auth()->id() ?? 1`; the fallback to user
    1 is gone (the routes require a token anyway).
  - 2 tests in `GoldCorrectionTest`: an In Cash Converter sale saves and
    corrects (confirmed to fail without the migration), and both rule
    failures are `422` with their message. Suite 52/52.
  - Verified on Aiven: test In Cash Converter sale, gold entry #14 (head 1 ←
    user 7, ₹10, 0.001 g, remarks "TEST §24"), saved with stock row #48
    (`IN_CASH_CONVERTER`, In). A Purchase beyond head 1's cash returned
    `422` "Insufficient cash balance. Head has -20015, needs 5000." and
    wrote nothing.
- ~~**BACKEND_TODO §23 — stock history's In/Out filter hid gold trading
  rows.**~~ **Fixed.**
  - `ReportService::getStockHistory` now special-cases only the four
    transfer types (Out for the sender, In for the receiver). Every other
    row matches on its own `stock_type`, so `CashToGold`,
    `GOLDCASHCONVERSION`, `SALES` and both converters show again, and a new
    entry type can't drop out.
  - `StockHistoryFilterTest`: one row per entry type, and In + Out must
    equal All (confirmed to fail on the old code).
  - Verified on Aiven for head 1: All 47 rows / 496.001 g = In 3 / 110.001 g
    \+ Out 44 / 386 g, every row under exactly one filter (on 2026-11-06, 2
    rows were under neither).
  - (A transfer between two of the head's employees used to show under All
    but under neither In nor Out. The 2026-11-13 direction fix below closed
    that.)
- ~~**BACKEND_TODO §16 (code part) — a failed log write rolled back the
  entry it was logging.**~~ **Fixed.**
  - `SystemLogService::log()` writes the log row in its own nested
    `DB::transaction()`. Inside the caller's business transaction that's a
    `SAVEPOINT`, so a failed insert rolls back only the log row.
  - Checked on Aiven, inside a transaction that was then rolled back (nothing
    kept): a log write with a 60-character action (the column takes 50).
    Before the fix, the next statement failed with `SQLSTATE[25P02] current
    transaction is aborted`. After it, the transaction carried on normally.
  - `SystemLogServiceTest`: with the log table gone, the writes around a
    failed log still commit, and the log ran in a nested transaction. SQLite
    can't show the abort itself. Suite 53/53.
  - §16 stays open for the two non-code steps: check the other databases,
    and ask operators about entries made between 2026-10-22 and 2026-11-04.
- ~~**Stock history showed a Stock Out as "In" (reported 2026-11-13).**~~
  **Fixed.**
  - **Cause:** the Transaction History panel always sends the picked user
    (`head_id = employee_id = picked user`), and `getStockHistory` then
    labelled every row from *that user's* side. A head's Stock Out to
    Employee One is Employee One receiving gold, so it showed as In. Since
    §23 the In/Out filter went by the saved `stock_type`, so with a user
    picked, the Out filter even listed rows tagged In.
  - **Fix:** rows are labelled from the logged-in user's side: Out if they
    gave the gold, In if they received it. Rows they aren't a party to (the
    picked employee's transfer to another employee) fall back to the picked
    user's side, and rows neither gave nor received keep their saved
    `stock_type`. Item changes and conversions keep `stock_type` as before.
    The label and the filter share one rule
    (`stockHistoryDirection()` / `stockHistoryDirectionSql()`), so a row
    always sits under the filter matching its label. The counterparty column
    follows: an Out row names who received it, so the head's view of
    Employee One shows "Employee One" rather than the head's own name.
  - The controller passes the token user as the viewer; the request and
    response shapes are unchanged. Only a comment changed in
    `TransactionHistoryPanel.vue`.
  - `StockHistoryFilterTest` now covers both views (nobody picked, and the
    screen's picked-user query), including a transfer between two employees.
    It fails on the old code at "labels, picked view". Suite 53/53.
  - Verified on Aiven: your Stock Outs #49 (2 g) and #50 (345 g), head 1 →
    Employee One, now show Out with Employee One picked. With user 7 picked:
    All 16 = In 1 + Out 15; with nobody picked: All 49 = In 3 + Out 46. Zero
    rows whose label differs from their filter.
- ~~**Item Change: always the logged-in user's own stock, and their totals
  updated (requested 2026-11-13).**~~ **Done.**
  - **Before:** the Stock screen sent the user picked on the left, refused to
    save without one, and the backend changed *that* user's stock. It also
    moved only the two item totals. Overall `purity_grand_total` stayed put,
    so with different touches the overall total stopped matching the sum of
    the item totals (0.99 of user 5's drift on Aiven came from their
    2026-10-20 item change).
  - **Backend:** `createItemChange` works on the acting user (token),
    locked. `user_id` is now optional in `ItemChangeRequest`, and anyone
    else's id gets `422` "Item Change is for your own stock only.". A lot
    must be the user's own and of the From item, else `422`. The overall
    purity total now moves by (To purity − From purity); grams don't
    change.
  - **Head stock summary as of a date/time:** for an Item Change or
    Conversion's IN row it read the From item's opening balance, so the To
    item showed as From balance − grams. It now reads the To item's opening
    balance (`ReportService::getHeadStocks`).
  - **Frontend:** `ItemChangeModal` uses the logged-in user (also for the
    lot picker), no longer needs a picked user, and says "Changes your own
    stock (name). The user picked on the left isn't used." `user_id` is gone
    from `ItemChangeFormValues` and the payload. The page already refreshed
    the head stock summary after saving.
  - `ItemChangeTest` (3 tests): own items and totals change, the head stock
    summary is right live and as of a time, a lot is drawn down, and another
    user's id, another user's lot or a different item's lot are refused with
    nothing changed. Suite 56/56.
  - Verified on Aiven as head_admin: another user's id → `422`. 1 g Metal
    (92) → Earrings (99.9): Metal −1 g / −0.92, Earrings +1 g / +0.999,
    overall grams unchanged and purity +0.079. The head stock panel showed
    it live and as of 23:59:59. Then changed back; head_admin is exactly as
    before. Test rows: stock #51–#54.
- ~~**Item Conversion: the same, and the converted items' totals reflected
  (confirmed 2026-11-13).**~~ **Done.**
  - **Before:** it also used the picked user, and moved only the two item
    totals. Its grams genuinely change (converted grams = source grams ×
    source touch ÷ target touch), so the overall grams total drifted too.
    The target item's closing balance on both rows read a snapshot key that
    doesn't exist, so the Items OB/CB report always got 0 for it.
  - **Backend:** `createItemConversion` works on the acting user (token),
    locked. `user_id` is optional and anyone else's gets `422` "Item
    Conversion is for your own stock only.". A lot must be yours and of the
    source item. Overall grams move by (converted − source) and purity by
    (in − out), which is nil with the formula. Both rows now save the target
    item's closing balance. `target_touch` must be above 0: with 0 the
    division helper returned 0, turning the gold into 0 g.
  - **Head stock summary as of a date/time:** covered by the Item Change
    fix above (the IN row now reads the target item's opening balance).
  - **Frontend:** `ItemConversionModal` uses the logged-in user, no longer
    needs a picked user, says "Converts your own stock (name)", and its
    target touch check is now "above 0". `user_id` is gone from
    `ItemConversionFormValues` and the payload.
  - `ItemChangeTest::test_conversion_…`: items and overall totals, both
    rows' target-item closing balance, the head stock summary live and as
    of a time, the three refusals (another user's id or lot, touch 0), and
    a lot drawn down. Suite 57/57.
  - Verified on Aiven as head_admin. Another user's id and touch 0 →
    `422`. 1 g Metal (92) → Earrings (80) = 1.15 g: Metal −1 g / −0.92,
    Earrings +1.15 g / +0.92, overall grams +0.15 and purity unchanged.
    The head stock panel agreed live and as of 23:59:59, and both items'
    closing balances were saved on rows #55/#56. Converted back (#57/#58);
    head_admin is exactly as before.

### PR #53 pass — 2026-11-06

PR #53 (`982afd78`, merging `43e620eb`) is labelled as documentation, but it
changed three backend files. All three were read in full:

- **`UserDetailController::update()` now syncs `item_mappings`** when the
  key is present. It deletes rows whose ids weren't sent, updates rows that
  carry an id, and creates the rest. This closes the item part of
  BACKEND_TODO §10. Heads and cash heads are still ignored on update. The
  new code has its own problems, now listed in §10: the update-in-place
  branch writes an `updated_at` column the table doesn't have, so it would
  fail every save. Because we send no ids, every save deletes and re-creates
  the rows, which wipes the stone/beads cost totals and `last_txn_date`.
  The grams/purity running totals are overwritten from the form. There's no
  validation, and an empty list can't go in a multipart request.
- **`ReportService::getStockHistory`'s In/Out filter** now places the four
  transfer types (EMPTOHEAD, EMPTOEMP, HEADTOHEAD, ANOTHERHEADTOEMP) by who
  sent and who received, which fixes the old Out filter. But its allowed
  list of entry types uses names the services don't save. Filtering In or
  Out now hides Cash to Gold, Gold to Cash, Purchase Gold, Sale Gold and
  cash-converter entries (BACKEND_TODO §23). No frontend change is needed.
- **`UserDetail`** gained an unused `HasMany` import. No effect.

**Also found (older, not from PR #53):** `stock_details.entry_type` is an
enum without `IN_CASH_CONVERTER`, so the database rejects every Sale Gold
"In Cash Converter" save with a `500`. Confirmed read-only on Aiven: the
CHECK constraint lists nine values, and there are no such rows
(BACKEND_TODO §24).

**Frontend done the same day:** see [§1 Done](#done). The Users edit-form
banner was reworded, and removing a user's last item now saves.

**Verified with real saves the same day.** The user okayed test writes on
Aiven, since the system isn't live yet. All of it went through the local
backend as `head_admin`:

- **User edits (BACKEND_TODO §10):** all five cases behaved as predicted,
  with the exact errors recorded in §10. The frontend's two behaviours both
  work: no-id saves return `200`, and `item_mappings: []` removes every
  row.
- **Saves persist with logs (§16):** two Sale Gold entries kept their cash,
  stock and log rows. That closes §16's "confirm saves" step.
- **Stock history filter (§23):** the two test sales show under All and
  under neither In nor Out.
- **In Cash Converter (§24):** `500` check-constraint violation, nothing
  kept.
- **New (§22):** Sale Gold, Purchase Gold, item change and GMS out log a
  table name that doesn't exist and a `null` record id.

**Test data left on Aiven** (all tagged "Claude Test" / "CLAUDE TEST"):

- `user_details` 7 (`Claude Test …`, CUSTOMER), with 1 item mapping
  (item 3) and a head mapping to user 1.
- `cash_to_gold` 1–2 and stock rows 31–32 (1 g each, from head 1 to
  user 7).
- `cash_txn_details` 82–83 (₹6,000 each).
- `system_logs` 1–2.
- Head 1's and user 7's gold and cash balances moved by those two sales.

### PR #52 pass — 2026-11-04

PR #52 (`2502a051`, merging `4202b0cd`) is labelled as docs, but it changed
four backend files. All four were read in full:

- **System Logs is HEAD-only.** `index`, `show` and `filters` now answer
  `403` ("Forbidden. Only HEAD administrators can access system logs.")
  unless `auth()->user()->role->role === 'HEAD'`. `AuthUser` has the
  `role()` relation, so the check works. This closes that point of
  BACKEND_TODO §22.
- **`filters()` finds roles by name** (`CUSTOMER`, `PURCHASE`) instead of
  ids 1 and 4. This closes that point of §22.
- **The cash report's retailer suffix works.** `CashTransactionReportResource`
  now adds `(user_name)` for a party whose role is `PURCHASE`, instead of
  checking `category_name === 'RETAILERS'`, which could never be true.
  `getCashTransactionsObcb` eager-loads `givenByUser.role` /
  `givenToUser.role` for it. This closes BACKEND_TODO §21. The backend's
  comments name PURCHASE as the retailer role.
- **`MetalStockSeeder`** finds its head and customer by role name instead of
  `category_name` values that don't exist. It still hardcodes item id 2.

Nothing else in the backend differs from PR #51, so every other open item
is unchanged. `ReportController` line numbers moved by 2.

**Environment:** the Aiven database in the local `.env` was down for part of
2026-11-04 (its host name didn't resolve), then came back. Once it was up,
the `system_logs` migration was still Pending and no cash, stock or gold row
was dated after 2026-10-22. **The migration was run on Aiven the same day**
(only that one was pending); the table and its four indexes are in place.
Entries made against Aiven between 2026-10-22 and 2026-11-04 were probably
lost and may need re-entering (BACKEND_TODO §16).

**Gap in this file:** it wasn't updated for PR #51 (2026-10-27) or for the
frontend work since 2026-10-15. The backend side of that is in
BACKEND_TODO §16–§20 and §22. The frontend work is now listed in
[§1 Done](#done), and the two follow-ups from this pass are in
[§1 Pending](#pending).

### Re-verification — 2026-10-15

No backend code has changed since PR #50: `56a77ccb` and `dcc3b977` are
frontend-only, and a fetch of `origin` showed nothing unpulled on any branch.
Every open item in [`BACKEND_TODO.md`](../BACKEND_TODO.md) was still re-read
from source rather than carried forward.

- **Everything is still open** except part of [#32](#32): the testing doc's
  2026-09-23 update (`e2fe5953`) added settings, `POST /fitems`,
  `POST /stock/hide`, both CSV exports and `DELETE /orders/{id}`. The previous
  pass missed this and listed them as undocumented. Still missing: `/me`,
  `available-lots`, `unhide`, `cash-out`, `daywise-grand`, the rest of
  orders, the login 403, and `item_id` in §40.
- **The `head_id` scoping item is wider than recorded.** Beyond the two
  reports and their exports, the same unchecked query-string id is on
  `stock-details/history`, `reports/id-wise`, `cash-transaction-history`,
  `head-stocks` (`user_id`), and cash `in-history` / `out-history` /
  `print-report`. The to-do now lists all of them.
- **Tidied, nothing deleted:** two items were both numbered §3, and the
  `live_metal_report_items` seeder item duplicated §2's own "second endpoint"
  paragraph, so it was folded in there. Internal links now point at the right
  sections.
- **Not re-checked:** whether the five `password` accounts still exist in the
  live database. That needs DB access; the seeders still create them.

### PR #50 pass — 2026-10-08

**Ask [#38](#38) is closed.** PR #50 (`f2161934`, "added remainder modules")
shipped the reminders backend in 132 lines: `2026_09_30_124500_create_reminders_table.php`,
`App\Models\Reminder`, `ReminderController`, and `apiResource('reminders')`
inside the `auth:api` group.

**The schema matched the shape this document proposed field for field** —
`description`, `is_completed`, `added_by`, `assign_to`, `remainder_at`,
`is_viewed`, timestamps, FKs to `user_details` with `set null` / `cascade`.
Shipping `ViewNotesPanel` ahead of the API was the right call: only the API
adapter needed rewriting, not the screen.

Both questions the ask deliberately left open were answered by the
implementation: `index()` returns everything unscoped, and complete/view are
plain `PATCH` fields on `is_completed` / `is_viewed` — which is exactly what
the frontend had assumed.

**Four contract deviations**, all found by probing the running API inside a
rolled-back transaction rather than reading the controller. The speculative
`remindersApi` would have broken on the first three:

1. **No `{success, message, data}` envelope.** Bare model/array. The only
   endpoint in the API that does this.
2. **`added_by` / `assign_to` change type by endpoint.** The relation methods
   `addedBy()`/`assignTo()` snake-case onto the FK column names, and
   `toArray()` merges relations after attributes, so an eager-loaded relation
   overwrites the scalar. `index`/`show` give a full ~60-field user object;
   `store`/`update` give an integer.
3. **`created_at`, not `added_at`** — this backend's own convention elsewhere.
4. **`remainder_at` reads a day early if sliced.** It is a `date` column cast
   to `date`, serialised as a UTC instant of midnight in `Asia/Kolkata`
   (`config/app.php:68`). Storing the 15th returns
   `"2026-10-14T18:30:00.000000Z"`.

`password_hash` is in `UserDetail::$hidden`, so the fat nested user leaks no
credentials — but it does carry `rak_cash_balance` and the gram totals.

**Frontend done the same day** — see [§1 Done](#done). Two new backend
follow-ups raised (unscoped `index`, no relations on write responses), both in
[`BACKEND_TODO.md`](../BACKEND_TODO.md) §7 along with the envelope and
relation-naming items.

Every claim below was verified against backend source at that commit, not
against the API doc. Where the two disagree, the source wins and the
discrepancy is listed in [§3](#3-api-doc-corrections).

### Full re-derivation — 2026-09-30

Every backend ask in [§2](#2-flagged-to-backend) re-checked against the current
`schainbackend/` source from scratch rather than diffed against the last pass,
per the standing rule. Result: **most of the list is fixed.** Of the items that
were open going in, only six survive, and two findings are new.

**Verified fixed and struck through this pass** — #9 (display flags now in the
validation, `UserDetailController:203-285`), #10 (`order_details` migration
exists), #11 and #12 (`store`/`destroy` added, `update()` re-loads relations),
#13 (two CSV export routes), #14 (`head_id` accepted on both reports), #17
(item is now a parameter with a `system_settings` fallback, hardcoded `2`
gone), #19 and #35 (override now checks `role->role === 'HEAD'`, the right
column and the right role, and accepts `view_as`), #22 (head scoping applied),
#23/#24/#25 (combined pagination, own `amount` field, `Y-m-d H:i:s`), #29 and
#31 (`whereRaw` instead of the alias, plus `is_hided` filtering), #36
(`cash_user_id` is optional, so the head-only view works).

**Still open** — #2/#37, #18, #21, #32, #38, and the cosmetic #15.

**Two new findings**, both authorization rather than correctness:

- **The `head_id` override we asked for in #14 has no permission check.**
  `StockDetailsController:495` and `:516` (and the two export routes at `:36`
  and `:63`) take `head_id` from any authenticated caller. An employee can read
  their head's full ledger, or export it. We asked for the override and got it;
  we did not ask for it to be unrestricted.
- **`OrderController::index()` returns `OrderDetail::all()`** — unscoped,
  unpaginated, unfiltered. Every order to every user.

**Two corrections to earlier entries in this file:**

- #18 was recorded as a "one-line fix" for the MySQL syntax. The `IFNULL` and
  backticks are indeed gone, but the branch still fails — `havingRaw` at
  `ReportService:855` references a SELECT alias, which PostgreSQL rejects in
  `HAVING`. Same bug class as #29, which *was* fixed correctly twenty lines
  away in `StockDetailsController:738`. The new code comment claiming
  "(PostgreSQL safe)" is wrong.
- #21 was filed as a seeder/numbering mismatch. Re-reading the code, its real
  cost is larger than recorded: `StockInService:71,74` and
  `StockOutService:74,77` gate the *wastage balance adjustment* on
  `role_id != 1`. With role 1 = CUSTOMER and HEAD = 3, wastage is credited to
  the wrong party on every stock transaction that carries one. Promoted to the
  top of `BACKEND_TODO.md`.

**Frontend consequences of this pass** (not yet built, added to
[§1 Pending](#pending)):

- The Live Metal Balance banner about #19 is now stale — the "View as" override
  genuinely works for HEAD users, and `view_as` is accepted alongside
  `user_id`.
- Server-side export exists, so the client-side CSV on both report screens can
  be replaced. Note these routes stream a file rather than returning JSON, so
  they need a blob fetch with the bearer header, not `lib/api.ts`.
- One Day Action's response shape changed: `records` replaces the separate
  stock and cash lists, and cash rows now carry `amount` with `grams: null`.

### PR #48 pass — 2026-09-30

Two merges since the last full audit: **#47** (`5cde92b9`) and **#48**
(`353cef11`, backend commits `9f64dc94`/`cf1eea98`†). This pass covers **#48
only**, at the user's specific request — **#47 is still unaudited**, flagged
here so it isn't silently skipped.

† `cf1eea98` ("ui updated bug fix") is our own earlier frontend work
(ViewNotesPanel/RetailerPickerPanel/StockOutPanel etc.), not backend content
— it landed in this commit range because the Backend branch merged `main`
into itself before this PR closed. The real backend diff is 8 files, verified
with `git diff 5cde92b9..353cef11 -- schainbackend/`, not by reading the
merge commit's full file list.

> #### ✅ The biggest item on this list, closed — see [§2 Blockers](#blockers)
>
> The unauthenticated `/run-seeders`/`/run-migrations` routes are deleted.
> **But the five weak-password accounts they could recreate are still live
> right now** — checked directly against the database during this pass, not
> assumed. Two are HEAD-role. This is still the highest-priority item here;
> see ask [#28](#28)'s update.

Verified against backend source **and** against the live Render database
(migrations, route table, and two findings below confirmed with a real
tinker session — one of them a rolled-back transaction so nothing was
written). `php artisan route:list` resolves all routes with no missing
classes; `php artisan migrate:status` shows nothing pending (no schema
changes in this PR).

**Closed by this PR:**

| Ask | What changed | Frontend |
|---|---|---|
| [§2 Blockers](#blockers) (routes) | `GET /run-seeders`/`/run-migrations` deleted from `routes/web.php` outright — not patched, gone. | None needed. **Account half of this ask is still open — see below.** |
| [#33](#33) | `login()`/`me()` return the **full** `UserDetail` model (`role` eager-loaded) instead of 5 hand-picked fields. All 44 feature flags and the role name now ride on both responses. | Unblocks phase 2 of the feature-flags work (nav-gating) without the `user-details/{id}` round trip that ask used to require. Not yet built — see [§1 Pending](#pending). |
| [#30](#30) | `POST /stock/unhide` exists now, symmetric with hide (unhides the parent lot too). | Not yet built — `TransactionHistoryPanel`'s "no way to un-hide" copy is now stale. See [§1 Pending](#pending). |
| [#31](#31) | `getAvailableStockLots` filters `is_hided = 0` now. | None needed — the lot picker just stops showing hidden lots. |
| [#26](#26) / [#27](#27) | `PhoneBookResource` reads the correct `role` column; `phone-book` is `->only(['index','show'])`, so the unsafe hardcoded-password write routes are gone rather than patched; `index()` filters `is_delete`; the resource gained `address`/`remarks`/`role_id` and a resolved `profile_image` URL. | None needed for the fix itself — `phoneBookApi.ts` never called the removed routes. Two stale "always null"/"reads —" comments corrected in `types/phoneBook.ts` and `PhoneBookView.vue`. |
| [#34](#34) | `OrderController` validates `customer_id`/`item_id` (existence) and `status` (real enum: `PENDING,PROCESSING,COMPLETED,CANCELLED`) — **but see the new bug below.** | `ORDER_STATUSES` had guessed `IN_PROGRESS`; corrected to the real `PROCESSING` in `types/order.ts` and `OrdersView.vue`. This was a live regression risk: after this PR, submitting the old guessed value would 422. |

**New bug this PR introduced** (not a pre-existing issue — confirmed by
reading the migration and testing live): `OrderController`'s new validation
also accepts `remarks`/`due_date`, but `order_details` has neither column.
Sending either now crashes with an uncaught `QueryException` — verified in a
rolled-back transaction against the live database, not just read from code.
Full write-up under ask [#34](#34) and in `types/order.ts`. Our Orders screen
never sends these fields, so it hasn't hit this, but don't add them until the
backend fixes it.

**Also fixed, worth knowing:** `getAvailableStockLots`'s date+time branch
changed `havingRaw('grams - used_grams > 0')` to a `whereRaw` repeating the
full subquery — the old form referenced a `selectRaw` alias inside `HAVING`
with no `GROUP BY`, which strict Postgres doesn't reliably support the way
MySQL does. Same bug family as ask #18 (a different endpoint entirely), not
something we had flagged for this one specifically.

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

> #### ✅ RESOLVED in PR #48 (2026-09-30) — see below for what this used to say
>
> Both routes are gone from `routes/web.php` — confirmed in the diff, not
> assumed: the entire "Dev Utility Routes" block (`GET /run-seeders`,
> `GET /run-migrations`, both outside every middleware group) was deleted,
> leaving only the `/` welcome route. `https://api.lensnstories.com` no
> longer has an unauthenticated way to seed or migrate the live database.
> Kept below for the record of how long this was live and why it mattered.

> #### 🔴 (Historical) two unauthenticated routes were live in production
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
| Transaction History print — isolated to a real slip (row receipt / table report) instead of printing the whole page | `components/stock/TransactionHistoryPanel.vue` |
| Stock row print (Transaction History, ID Wise) prints the legacy slip directly: heading, date, OB, Time/Items/Pcs/Gms/%/Purity, Total, CB, all boxed (#40). It replaces a same-day preview-modal pass that was reverted | `lib/stockReceiptPrint.ts`, `components/stock/StockReceiptSlip.vue`, `components/stock/TransactionHistoryPanel.vue`, `views/IdWiseReportView.vue` |
| ~~View Notes — built against no backend at all (ask #38), banner explains why every action 404s~~ **Now live against the real API (PR #50)** — banner removed, adapter rewritten for the real contract | `components/stock/ViewNotesPanel.vue`, `lib/remindersApi.ts`, `types/reminder.ts`, `lib/date.ts` (`toDateInputValue`) |
| Order status vocabulary corrected (`PROCESSING`, not the guessed `IN_PROGRESS`) to match PR #48's real validation | `types/order.ts`, `views/OrdersView.vue` |
| Phone Book "always null" role/comments corrected now that PR #48 fixed the backend field | `types/phoneBook.ts`, `views/PhoneBookView.vue` |
| System Logs — filtered, paged list and detail view for PR #51's audit log, with a banner for the backend gaps | `views/SystemLogsView.vue`, `components/system-log/SystemLogDetailModal.vue`, `lib/systemLogsApi.ts`, `lib/systemLogLabels.ts`, `types/systemLog.ts` |
| Per-user category (Grams / Purity / Both): the measure a party isn't kept in shows as 0 in the stock pickers and in the gold modals' balance cards (display only) | `lib/userCategory.ts`, `lib/userLabel.ts`, `components/stock/UserPickerPanel.vue`, `components/cash-txn/PartyBalanceCards.vue`, the four gold modals, `views/GoldToCashView.vue` |
| No default category on any create form; the head must choose one (Add Retailer gained the field) | `views/UsersView.vue`, `components/user/AddUserModal.vue`, `views/ClientsView.vue`, `components/stock/AddRetailerModal.vue` |
| Stale "Cash Converter entries don't appear in history" warning removed — backend `0a94a6cc` added both types to the history filters | `views/CashManagementView.vue`, `types/cashTxnHistory.ts` |
| Amount in words on the cash thermal receipt ("Rupees One Lakh Fifty Thousand Only"), Indian numbering, display only | `lib/amountInWords.ts`, `components/cash-txn/ThermalPrintModal.vue` |
| Daywise Grand report — per-day stock grams/purity in and out for me, a user, or a retailer | `views/DaywiseGrandView.vue`, `types/daywiseGrand.ts`, `lib/reportApi.ts` |
| System Logs — a `403` shows "Only heads can view system logs" instead of the page; stale permission sentence removed | `views/SystemLogsView.vue` |
| Users edit form (PR #53): the banner now says items save and heads/cash heads don't, and warns that grams/purity totals are written back as shown. Removing the last item now saves, because an empty `item_mappings` is sent on JSON saves. Row ids are still left out on purpose (BACKEND_TODO §10) | `views/UsersView.vue`, `lib/userDetailsApi.ts` |

**Every endpoint shipped in PRs #29–#32 now has a frontend**, PR #37's two
new endpoints have one as of 2026-09-11, everything PRs #38–#42 exposed has
one as of 2026-09-16, and PR #50's reminders module as of 2026-10-08. What
remains below is cleanup, deferred polish, and work blocked on backend gaps.

How the View Notes adapter handles PR #50's contract, since it is the one
place in the app that departs from the house pattern:

- **`remindersApi` does not unwrap `.data`.** That endpoint sends bare JSON.
  Making it match the other api modules would break it.
- **`normaliseReminder()` accepts `added_by` as either an integer or a user
  object** and flattens both to `added_by` + `added_by_name`, so the panel
  never sees the difference between a list response and a save response.
- **`toDateInputValue()` (new, in `lib/date.ts`) undoes the UTC shift** on
  `remainder_at`. The obvious `value.slice(0, 10)` returns the previous day.
  It assumes the viewer's clock matches the server's `Asia/Kolkata`, which
  holds for these operators; the real fix is the backend sending a bare date.
- **Display uses `formatDateOnly`, not `formatDateTime`** — `remainder_at` is
  a DATE column, so showing a time would show a `00:00` nobody typed.

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
- **Phone Book is read-only.** `store`/`update`/`destroy` were registered
  and deliberately not wrapped in `lib/phoneBookApi.ts` — creating a
  contact minted a real ERP login with a hardcoded shared password, and
  deleting one deactivated the shared `user_details` row ([#27](#27)). As
  of PR #48 those three routes are gone server-side too (`->only(['index',
  'show'])`), so this is no longer just our own caution — the backend
  agrees. Contacts are created and edited on the Users screen, which goes
  through `UserDetailController` and sets real credentials.

### Pending

#### New work from step 2 (2026-11-12)

- [ ] **Hide the buttons users can't use.** The backend answers `403` to:
      stock and cash entries from anyone who isn't a head and doesn't have
      "Can act as head"; and to changes on the admin screens (Users, Items,
      Roles, Banks, Cash categories, Settings, mappings) from anyone but a
      head. The screens still show those buttons. `useAuthStore().isHead`
      and `.canActAsHead` are ready for it.

- [ ] **Stop sending `X-User-ID`.** The backend ignores it since 2026-11-12.
      `lib/stockApi.ts`, `lib/reportApi.ts`, `lib/cashTxnDetailsApi.ts` and
      `lib/cashAutoEntryApi.ts` still add it, and their comments say the
      backend reads it. Drop the header, the `actingUserId` parameters that
      only feed it, and the comments.

#### New work from the PR #53 pass (2026-11-06)

- [x] ~~**Users edit form: reword the mappings banner, and let the last item
      be removed.**~~ **Done 2026-11-06.** (`views/UsersView.vue`,
      `lib/userDetailsApi.ts`)

- [ ] **Send item-mapping row ids once BACKEND_TODO §10 point 1 is fixed.**
      Keep each row's `id` when the edit form loads (`openEdit`), and send it
      in `toPayload()`. Rows will then update in place instead of being
      deleted and re-created. Don't do it before the `updated_at` fix, or
      every user save will fail.

- [ ] **Decide whether grams/purity totals should be read-only on the edit
      form.** They're live running balances, and since PR #53 saving writes
      them back. Making them read-only on edit would stop accidental edits,
      but not the stale-value problem, which needs the backend change
      suggested in §10 point 3. For now the banner warns operators.

#### New work from the PR #52 pass (2026-11-04)

- [x] ~~**System Logs: drop the stale permission sentence, and give non-heads
      a clear message.**~~ **Done 2026-11-04.** The banner no longer claims
      the API is open to everyone. A `403` from either logs call now replaces
      the banner, filters, table and pager with a plain "Only heads can view
      system logs" notice, and hides Refresh. (`views/SystemLogsView.vue`)

- [ ] **Decide whether to hide the System Logs nav link from non-heads.**
      Not done, on purpose: it would be the first role-based navigation in the
      app. Nothing gates by role today, and `AuthUser` carries only `role_id`,
      so the role name would have to be looked up. A non-head who opens the
      page sees the notice above.

- [ ] **Decide what a retailer is on the frontend.** The backend now treats
      PURCHASE-role users as retailers (the cash report suffix). The frontend
      doesn't: `AddRetailerModal` creates its users with the CUSTOMER role,
      and `RetailerPickerPanel` lists every user except the operator. So a
      retailer added from the Stock page never gets the suffix. The options
      are for Add Retailer to create PURCHASE-role users, and/or for the
      retailer picker to list only PURCHASE-role users. Both change existing
      behaviour, so they need a yes from the business side first.

#### New work from the 2026-09-30 full re-derivation

- [ ] **Drop the stale "View as does nothing" warning on Live Metal Balance.**
      Ask #19/#35 is closed: `ReportController:152` now checks
      `strtoupper(optional($actingUser->role)->role) === 'HEAD'` — the real
      column and the real role — so the override works for HEAD accounts.
      `LiveMetalBalanceView`'s amber banner still tells operators it has no
      effect. It also accepts **`view_as`** now, checked before `user_id`;
      `reportApi` sends `user_id`, which still works, but `view_as` is the
      clearer name to move to.

      The same banner's other half (#18, the as-of date/time filter) is still
      accurate — keep that sentence, drop the override one.

- [ ] **Use the real export endpoints.** Ask #13 is closed:
      `GET /stock/reports/items-obcb/export` and
      `/stock/reports/consolidated/export` both exist and stream a CSV of the
      full filtered set, not just the loaded page. `ItemsObcbReportView` and
      `ConsolidatedReportView` still build CSV client-side from whatever is
      paged in.

      One wrinkle worth planning for: these return a **file download**, not the
      `{success, message, data}` envelope, so they cannot go through
      `lib/api.ts` as-is. Fetch as a blob with the `Authorization` header and
      trigger the save from an object URL — a plain `<a href>` cannot carry the
      bearer token. Probably worth a small `downloadApi` helper rather than
      bending `api.ts`.

- [ ] **Re-check One Day Action against its new response shape.** PR #46
      restructured it while closing asks #22–#25: `records` is now a single
      combined, correctly-paginated list rather than separate stock and cash
      arrays; cash rows carry `amount` with `grams: null` instead of cash
      amounts in the `grams` field; and `added_at` is `Y-m-d H:i:s` rather than
      `DD-MM-YYYY`. `types/oneDayAction.ts` and `OneDayActionView`'s local
      date parsing and separate grams/rupees totalling were all written against
      the old shape and need revisiting.

- [ ] **A settings screen for the display flags is now buildable.** Ask #9 is
      closed — `UserDetailController` validates the `is_*_shown` /
      `is_*_need_to_shown` columns in both `store()` and `update()`, so they
      can finally be written. See the two-part note further down about editing
      them versus honouring them in `lib/nav.ts`.

#### New work from PR #48 (added 2026-09-30)

- [ ] **Add an Unhide action.** Ask #30 is closed server-side —
      `POST /stock/unhide` exists, same `stock_ids` payload shape as hide,
      symmetric parent-lot handling. `TransactionHistoryPanel`'s hide
      confirmation still says *"There is no way to un-hide from this app"*
      — that's now false and should either be removed or repointed at a
      real Unhide action. Smallest version: a filter/toggle to show hidden
      rows plus a per-row (or bulk, matching the existing hide checkbox
      selection) Unhide button.

- [ ] **Feature-flag nav-gating is now buildable.** Ask #33 is closed —
      `/me` returns the full user including all 44 flags, no second
      request needed. This was the blocker recorded against the "Decide
      what to do with the 44 per-user feature flags" item below; phase 1
      (editing them on the Users form) already shipped, phase 2 (gating
      `lib/nav.ts`/route guards on them) can now be scoped for real.

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

> ### ⚠️ Status as of the 2026-09-30 re-derivation — read before any entry below
>
> Entries in this section are **historical records**, written on the day the
> ask was raised. Several describe code that has since changed. This table is
> the current truth; the open ones also live in
> [`BACKEND_TODO.md`](../BACKEND_TODO.md).
>
> | Ask | Status | Evidence in current source |
> |---|---|---|
> | 1, 3–8, 11, 26, 27, 30, 31, 33, 34 | ✅ Fixed | Struck through in place |
> | **9** | ✅ Fixed | Display flags validated, `UserDetailController:203-285` |
> | **10** | ✅ Fixed | `2026_09_17_120000_create_order_details_table.php` |
> | **12** | ✅ Fixed | `CustomerTouchUserMappingController:64` re-loads relations |
> | **13** | ✅ Fixed | `exportHistoryItemsObcb:33`, `exportConsolidatedReport:60` |
> | **14** | ✅ Fixed | `head_id` honoured at `StockDetailsController:495`, `:516` — **but unrestricted, see new finding** |
> | **17** | ✅ Fixed | `ReportService:816` — parameter + `system_settings` fallback, hardcoded `2` gone |
> | **19, 35** | ✅ Fixed | `ReportController:152` checks `role->role === 'HEAD'`, accepts `view_as` |
> | **22** | ✅ Fixed | Head scoping, `ReportService:981-985` |
> | **23, 24, 25** | ✅ Fixed | `ReportService:1086` (`amount`), `:1094` (ISO date), `:1107-1109` (combined paging) |
> | **29, 31** | ✅ Fixed | `StockDetailsController:738` (`whereRaw`), `:729` (`is_hided`) |
> | **36** | ✅ Fixed | `cash_user_id` optional, `CashTxnDetailController:53-60` |
> | **2, 37** | ❌ **Open** | Four header-only reads — now includes `ReportController:222` |
> | **18** | ❌ **Open** | MySQL syntax fixed; `havingRaw` alias at `ReportService:855` still fails |
> | **21** | ❌ **Open** | `StockInService:71,74`, `StockOutService:74,77` — worse than filed |
> | **32** | ❌ **Open** | Doc covers none of PRs #38–#48 |
> | **38** | ❌ **Open** | No reminders table, model, controller or route |
> | **15** | 🟡 Cosmetic | One stale `sanctum` at `AuthController:112` |
> | **28** | 🟠 Partly | Routes removed; the five weak-password accounts may remain |
>
> **New this pass, not yet numbered:** the `head_id` override (#14) has no
> permission check, and `OrderController::index()` returns `OrderDetail::all()`
> unscoped. Both written up in `BACKEND_TODO.md` §2 and §3.

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

26. ~~**`role.role_name` is always null**~~ **✅ FIXED in PR #48
    (2026-09-30)** — `PhoneBookResource` now reads `$this->role?->role`
    (confirmed in the diff). Real role names now; no frontend change
    needed, the JSON key `role_name` was never renamed.

    Original write-up — the same `role_name` mistake as ask
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

27. ~~**The three write routes are unsafe against a shared table, so the
    frontend does not call them.**~~ **✅ FIXED in PR #48 (2026-09-30) —
    the unsafe routes are gone, not patched.** `apiResource('phone-book')`
    is now `->only(['index', 'show'])`, confirmed in the diff — `store()`,
    `update()` and `destroy()` (the hardcoded-password/shared-row-mutation
    methods below) are no longer routable at all. Matches exactly what
    this ask was already doing: `lib/phoneBookApi.ts` never called those
    three, so nothing here changes behaviourally — the backend simply
    closed the door this frontend had already declined to walk through.

    The two smaller issues in the same write-up are **also fixed**:
    `index()` now filters `is_delete = false` (confirmed in the diff), and
    `PhoneBookResource` now includes `role_id`/`address`/`remarks` and
    resolves `profile_image` to a full URL via `url()` — closing the doc
    mismatch noted below too. `role.role_name` populating for real is
    ask [#26](#26), fixed in the same PR.

    Original write-up, kept for context — `phone-book` is `user_details` — the
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

    **Update 2026-09-30 — routes closed, accounts NOT rotated.** PR #48
    deleted both routes (confirmed above). But the door they could reopen
    was never actually closed: checked all five accounts directly against
    the live database just now — **every one still authenticates with
    `password`**, including both HEAD-role accounts (`head_admin`,
    `demo_head`). The route that could *recreate* them is gone; the
    accounts it created are still sitting there right now on
    `https://api.lensnstories.com`, reachable through completely normal
    login. This half of the ask is still fully open and still the
    highest-priority item here.

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

30. ~~**There is no way to un-hide stock.**~~ **✅ FIXED in PR #48
    (2026-09-30).** `POST /stock/unhide` now exists
    (`StockDetailsController::postUnhide`, `HideStockRequest`, same
    `stock_ids` payload shape as hide) and
    `StockOutService::unhideStocks` unhides the parent lot too — the same
    symmetry hideStocks itself has, verified in the diff, not assumed.
    **Frontend work still open:** `TransactionHistoryPanel`'s hide
    confirmation still says "There is no way to un-hide from this app" —
    that's stale now and the row-selection/confirm UI has no Unhide action
    yet. See [§1 Pending](#pending).

    Original write-up, kept for context: `StockOutService::hideStocks`
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

31. ~~**`getAvailableStockLots` does not filter `is_hided`.**~~ **✅ FIXED
    in PR #48 (2026-09-30)** — `getAvailableStockLots` now carries
    `->where('is_hided', 0)` alongside its existing `stock_type = IN`/
    `stock_in_id IS NULL` filters, confirmed in the diff. No frontend
    change needed; the lot picker just stops showing hidden lots.

    Original write-up: every other lot query filtered `is_hided` —
    `getAvailableMetals` (`StockDetailsController:551`) and `getHistory`
    (`ReportService:48`) both carried `->where('is_hided', 0)`, but this
    one didn't, so a hidden lot disappeared from the metal picker and
    history yet still appeared in the generic lot picker.

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

    **Partly fixed — 2026-10-15 check.** `e2fe5953` (2026-09-23) documented
    settings, `POST /fitems`, `POST /stock/hide`, both CSV exports and
    `DELETE /orders/{id}`. `/me`, `available-lots`, `cash-out`, the login 403
    and §40's missing `item_id` are still undocumented, as are `unhide`,
    `daywise-grand` and the rest of orders.

### New in PRs #43–#45 — 2026-09-22

<a id="33"></a>

33. ~~**`GET /me` does not return the per-user feature flags.**~~ **✅ FIXED
    in PR #48 (2026-09-30).** Both `login()` and `me()` now return the
    **full** `UserDetail` model with `role` eager-loaded
    (`$user->load('role'); ... 'data' => $user`), replacing the old
    hand-picked 5-field payload. Confirmed safe: `UserDetail::$hidden` still
    protects `password_hash`/`report_password`/`otp`, and the `role()`
    relation exists, so this closes the gap completely — all 44 feature
    flags AND the role name are now on both responses, no second request
    needed. **Frontend work now unblocked:** phase 2 of the per-user
    feature-flag work (gating `lib/nav.ts`/route guards on them) can be
    built without the `user-details/{id}` workaround this ask used to
    require. See [§1 Pending](#pending).

    Original write-up, kept for context: `AuthController::me` used to
    hand-pick five fields — `user_id`, `name`, `user_name`, `role_id`,
    `is_active` — while `UserDetail::toArray()` already exposed all 44
    `is_*_shown`/`is_*_needed` flags PR #43–#45 added.

<a id="34"></a>

34. ~~**`OrderController` has no validation whatsoever.**~~ **Mostly ✅
    FIXED in PR #48 (2026-09-30) — with one new bug it introduced.**
    `store()`/`update()` now validate `customer_id`/`item_id` against
    `exists:user_details,user_id` / `exists:items,item_id` and `status`
    against a real enum: `required|in:PENDING,PROCESSING,COMPLETED,CANCELLED`.
    That answers our own question below — frontend updated to match
    (`ORDER_STATUSES` had guessed `IN_PROGRESS`; corrected to `PROCESSING`,
    the real value, in `types/order.ts`).

    **New bug this introduced, confirmed live in a rolled-back
    transaction:** the same validation also accepts `remarks`
    (`nullable|string`) and `due_date` (`nullable|date`) — but
    `order_details` has no such columns, only
    `order_id/customer_id/item_id/grams/status/added_at`. Sending either
    crashes with an uncaught `QueryException`
    (`SQLSTATE[42703]: column "remarks" of relation "order_details" does
    not exist`) — no try/catch in the controller, so it's a raw 500, and
    since `APP_DEBUG=true` in production the SQL error text would reach
    the client. **Ask: either add both columns via migration, or drop
    those two validation rules until they exist.** Our frontend never
    sends either field today (by design, not luck), so nothing has hit
    this in production — but the vulnerability is live the moment anyone
    (or a future frontend change) does.

    `grams` also went from unvalidated to `nullable|numeric` — looser than
    our own client-side `required`, so no action needed there.

    Original write-up, kept for context — every method used to pipe
    `$request->all()` into a `$guarded = []` model with no FormRequest, no
    status enum, and no existence check on `customer_id` or `item_id`.

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

### View Notes / reminders — feature does not exist on the backend (2026-09-25 finding)

<a id="38"></a>

38. ✅ **FIXED in PR #50 (2026-10-08)** — `reminders` table, `Reminder` model,
    `ReminderController` and `apiResource('reminders')` all landed, and the
    schema matches the proposal below almost exactly. Frontend rewired the
    same day. Four contract deviations and two new backend follow-ups came out
    of it; see the [PR #50 pass](#pr-50-pass--2026-10-08) at the top and
    `BACKEND_TODO.md` §7. Original ask kept below for the record.

    ~~**The "View Notes" panel (Stock Details screen's per-head task list) has
    no backend at all — not a bug, a total absence.** Asked to implement it
    from two legacy screenshots (a notes table: Sno/Description/IsCompleted/
    Added By/Remainder At/Assign To/Is Viewed/Actions, and a "Create new
    Remainder" dialog: Description/Remainder Date/Assign_to). Searched
    exhaustively before writing any frontend code: no migration, no model, no
    controller, no route anywhere in `schainbackend`, and no mention in the
    API testing doc. The only "remainder" hits in the entire backend are
    unrelated — `CashTxnDetail`'s own `remainder`/`remainder_at` columns (a
    single date on one cash transaction, added by
    `2026_08_12_233141_add_remainder_and_hide_to_cash_txn_details_table`) and
    the `is_remainder_shown` per-user UI-visibility flag. Neither is a task
    list.~~

    **Built anyway**, per the standing instruction to ship UI ahead of
    backend gaps rather than hold it back: `ViewNotesPanel.vue`, wired into
    `StockManagementView.vue`'s right column above `TransactionHistoryPanel`
    (matching the legacy screen's placement opposite Head Stocks). It carries
    a permanent on-screen banner saying the backend does not exist yet, and
    every list/create/edit/complete/view action goes through
    `lib/remindersApi.ts` against a speculative `apiResource('reminders')` —
    so today it 404s cleanly with that banner for context, and the day the
    route exists this screen needs no rework, just verification.

    **Ask:** a `reminders` (or similar) table and matching
    `apiResource('reminders')`. Proposed shape, reverse-engineered from the
    two screenshots — every name here is a proposal, not a contract:

    ```
    id              bigint, PK
    description     text, required
    is_completed    boolean, default false
    added_by        FK -> user_details, set server-side from the acting user
    assign_to       FK -> user_details, required
    remainder_at    date (the create dialog only collects a date — but the
                    legacy table's Remainder At column displays a
                    time too, "31-Jul-2026 10:52:36 AM"; where that time
                    comes from is open, most likely the row's created-at
                    timestamp rather than a separate input)
    is_viewed       boolean, default false — set true the first time
                    assign_to (or anyone?) opens the note; scope
                    unconfirmed
    added_at / updated_at
    ```

    Two open questions for whoever builds this, both left unresolved
    on purpose rather than guessed: whether `index()` should scope to the
    signed-in head (as `added_by` or `assign_to`) or return everything the
    way `OrderController::index()` currently does; and whether "complete" and
    "view" are just `PATCH` with `is_completed`/`is_viewed` (what the
    frontend assumes today) or deserve their own endpoints.

### Roles — no active/inactive status (2026-10-29 finding)

<a id="39"></a>

39. ~~**Roles have no status at all, so activate/deactivate cannot be saved.**~~
    **Fixed 2026-11-20** (BACKEND_TODO §14, removed). Migration
    `2026_11_20_000200_add_is_active_to_roles_table` (default true);
    `Role` casts it; `RoleController` validates `is_active` and returns it.
    Roles moved to the admin console at the same time: create and view only,
    names stored in capitals and unique ignoring case. The heads' app
    `RolesView.vue` (toggle, edit, delete, banner) is gone. See "Admin
    console — 2026-11-20". Original finding below.

    Asked for an activate/deactivate option on the Roles screen. Checked the
    backend first: the `roles` migration has only `id`/`role`/`added_at`/
    `touch`, `Role::$fillable` matches, and `RoleController::store()`/
    `update()` validate only those three writable fields — so `is_active` is
    dropped by `validate()` and `PUT /roles/{id}` still returns 200. No later
    migration touches `roles`. Written up for the backend team as
    `BACKEND_TODO.md` §14.

    **Built anyway**, per the standing instruction: `RolesView.vue` has a
    Status column with an inline toggle (optimistic, settles on whatever the
    server echoes, so today it springs back with an error toast instead of
    pretending it saved) and an Active checkbox in the create/edit form. An
    amber banner shows while no role in the list carries `is_active`, so it
    clears itself once the column ships. `isRoleActive()` in `rolesApi.ts`
    treats a missing value as active; the Role pickers in `UsersView`,
    `ClientsView` and `AddUserModal` use it to hide inactive roles (keeping a
    user's current role on edit). Filters in Phone Book and Cash Management
    and the CUSTOMER lookup in `AddRetailerModal` deliberately still see every
    role.

### Print buttons — legacy stock receipt, direct print (2026-10-29 finding)

<a id="40"></a>

40. **Stock has no print endpoint, so the legacy receipt is assembled from two reports.**
    The request was for every print button to use its relevant backend
    print API, and for the stock receipt to look like the old software's
    slip. The routes were re-read on every remote branch. The only print
    endpoint is `GET /cash-txn-details/print-report`, and both cash print
    buttons already use it (the cash report's `id` is the `txn_id`).

    A first pass wrapped every print in an in-app preview modal. **That was
    reverted the same day:** previews were not wanted. Head Stocks, the
    Transaction History page report, the ID Wise header print and
    `ThermalPrintModal` are back to exactly how they were before that pass.

    What changed and stayed:
    - **The stock row print** (Transaction History, and the ID Wise row button,
      which used to print the whole page) prints the legacy slip straight to
      the browser's print dialog:
      - the `"<from> TO <to>"` heading and the date
      - `<name> Bal OB`
      - Time / Items / Pcs / Gms / % / Purity, then a Total row
      - `<name> Bal CB`

      Every cell is boxed so the division lines stay.
    - **Where the slip's data comes from.** `lib/stockReceiptPrint.ts` fetches:
      - `id-wise` for the date, time, names and item remarks;
      - `items-obcb` narrowed to that exact second and to the viewed-as user,
        for OB/CB (`added_at` is `timestamp(0)`, so the second identifies the
        row, and the match is confirmed by `stock_id`);
      - pcs from the history row, because neither report returns it.

    Gaps, logged in `BACKEND_TODO.md` §15:
    - OB/CB is `—` when the row doesn't involve the viewed-as user.
    - There's no pcs on the ID Wise print.
    - The heading's bracketed suffix is unknown.
    - The cash receipt's OB/CB is always null (`sender_ob`/`sender_cb`).
    - Nothing uses the bulk branch of `print-report`.

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
