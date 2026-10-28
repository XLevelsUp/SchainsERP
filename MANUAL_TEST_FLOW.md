# SchainsERP — Manual Test Flow

A step-by-step script for testing the app by hand, from creating a user
through every transaction type.

Written 2026-10-11 against the local dev stack. Steps are in dependency
order — later sections rely on data created in earlier ones, so run them
top to bottom the first time.

---

## Before you start

| | |
|---|---|
| **Frontend** | **http://localhost:5173** |
| **Backend** | http://127.0.0.1:8000 |
| **Start both** | `start-dev.ps1` at the repo root |
| **Login** | `demo_head` / `password` |

**Use `localhost`, not `127.0.0.1`, for the frontend.** Vite binds to IPv6
only, so `http://127.0.0.1:5173` refuses the connection while
`http://localhost:5173` works.

**`demo_head` is the only account that logs in locally.** `head_admin` and
`employee_one` exist in the database but their password hashes don't match
`password`. You'll still *use* those two as counterparties in transactions —
you just can't sign in as them.

### What's already in the local database

| Items | Users | Metal lots |
|---|---|---|
| 1 Gold Ring | 1 `head_admin` (HEAD) | `head_admin` — stock_id 4, 100.000 g |
| 2 Gold Chain | 2 `employee_one` (EMPLOYEE) | `employee_one` — stock_id 13, 10.000 g |
| 3 Gold | 999 `demo_head` (HEAD) | `demo_head` — **none** |
| 4 **Metal** | | |

That last cell matters: you are signed in as `demo_head`, who holds no metal.
Any metal picker scoped to them opens empty. Use `head_admin` or
`employee_one` as the source user when testing metal lots.

---

## 🚫 Known issues — please don't log these

All of these are already recorded in `BACKEND_TODO.md`. Finding them again
costs you time and tells us nothing new.

| What you'll see | Why |
|---|---|
| **Wastage lands on the wrong party** | Backend gates the wastage adjustment on `role_id != 1`, but role 1 is CUSTOMER, not HEAD. Any transaction with wastage > 0 credits it to the wrong side. **Biggest open bug.** |
| Metal Live "As of date + time" → 500 | That query branch is invalid on PostgreSQL |
| `Reply Out`, `Reply In` greyed out | No backend endpoint exists |
| `Assign to Employee`, `Employee Deliver` greyed out | No backend endpoint exists |
| No INVEST user type anywhere | Role doesn't exist in our system yet |
| Every user sees every reminder / every order | Those endpoints aren't scoped yet |
| Any user can read another head's report | `head_id` override has no permission check |

**Do log** anything not on this list.

---

## How to record results

For each numbered step, note: **Pass**, **Fail**, or **Blocked**. For a
failure, capture:

1. The step number
2. What you expected vs what happened
3. The red error text on screen, if any
4. Browser console (F12 → Console) and the failing request (F12 → Network →
   click it → Response)

The Network tab matters more than the screen text — a 422 with a validation
body tells us far more than "it didn't save".

---

# Section A — Master data

Do this first. Everything else depends on it.

## A1. Sign in

1. Open http://localhost:5173
2. Sign in as `demo_head` / `password`
3. **Expect:** the Dashboard, with the sidebar on the left

**Also check:** sign in with a deliberately wrong password.
**Expect:** a red box, "Invalid username or password".

## A2. Look at the roles

1. **Administration → Roles**
2. **Expect** exactly five: CUSTOMER, EMPLOYEE, HEAD, PURCHASE, SALARY

Note there is no INVEST. That's the known gap above.

## A3. Create a customer

1. **Administration → Users → Add User**
2. Fill in every required field — Name, Username, Password, Phone, Address,
   Role (Mailing name, Signature and the Proof image are optional)
3. Set **Role = CUSTOMER**
4. Save

**Expect:** the modal closes, a success toast, and the new row in the table.

**Also check:** submit with Name blank. **Expect** a red "Name is required."
under the field, and nothing saved.

## A4. Create an employee

Same as A3 but **Role = EMPLOYEE**. Give them a name you'll recognise.

## A5. Create a second head

Same again with **Role = HEAD**. You'll need this to test head-to-head
transfers later.

## A6. Check the items list

1. **Settings → Items**
2. **Expect** Gold Ring, Gold Chain, Gold, Metal

**Important:** an item named exactly `Metal` must exist, or the metal picker
has nothing to match. If it's missing, create it.

## A7. Map the employee to a head

1. **Administration → Head Employee Mappings**
2. Create a mapping: your new employee → one or more heads
3. Save, then reopen it and confirm the heads stuck

**Worth knowing:** this is master data only. The Stock screen's user picker
does **not** filter by these mappings — it lists every user except the one
you're signed in as. So mapping someone doesn't restrict who you can transact
with. Tell us if you expected otherwise; that's a design question, not a bug
we've logged.

## A8. Add a bank account

1. **Administration → Bank Details** → add one
2. Needed later for Cash In with a non-cash payment method

---

# Section B — Stock Out and Stock In

## B1. Stock Out

1. **Stock** in the sidebar
2. **Don't pick a user yet.** Look at the two button rows.
   **Expect:** Out shows 5 buttons, In shows 3. No Cash Out, no Reply.
3. Click **Stock Out** — a row appears in the grid below
4. Fill in Item = Gold Ring, Grams, Touch
5. Click **Submit** at the bottom

**Expect:** a red error on the user dropdown — "select a user" — and nothing
saved. You can fill the form before choosing a person; the check happens at
submit.

6. Now pick a user in the **Users** dropdown on the left
7. Submit again

**Expect:** success toast, the row clears, and the transaction appears in
**Transaction History** on the right.

## B2. The button rows change when a user is picked

With a user selected, look at the rows again.

**Expect — Out row:** Stock Out · Item Change · Item Conversion · GMS Out ·
Numeric Wastage Out · **Reply Out** (greyed) · **Cash Out**

**Expect — In row:** Stock In · GMS In · Numeric Wastage In ·
**Reply In** (greyed) · **Cash In**

Hover the greyed ones — a tooltip should explain the backend is missing.

## B3. Stock In

Same as B1 but with the **Stock In** button. The direction reverses: this
records stock coming *from* the selected user.

## B4. Check the maths

After a few transactions, open **Head Stocks** (left panel) and
**Transaction History** (right).

- Head Stocks totals should move by what you entered
- Transaction History should list each row with the right IN/OUT tag
- The **Total** row above the table covers the whole filtered set, not just
  the visible page

⚠️ **If you entered a wastage value, the totals will be wrong** — that's the
known `role_id` bug, not a new one. Test with wastage = 0 first so you're
checking the rest of the maths against clean numbers.

## B5. Transaction History scrolling

**Expect:** the table scrolls after about four rows, with the Total row and
column headers staying pinned at the top while rows move under them. The
panel should stay roughly level with Head Stocks on the left.

---

# Section C — Item Change and Item Conversion

These convert stock from one item into another. Both are modals.

## C1. Item Change — non-metal

1. Pick a user, click **Item Change**
2. From item = Gold Ring, To item = Gold Chain
3. Enter Grams and the touches
4. **Expect:** a "Pick lots…" link under the From item — but no modal opens
   by itself
5. Submit

**Expect:** success, and a matching pair of rows in Transaction History
(`Gold Ring => Gold Chain`).

## C2. Item Change — metal auto-opens the picker

1. Click **Item Change** again
2. Set **From item = Metal**

**Expect:** the metal lot picker opens **automatically**, without clicking
anything else.

3. The picker is scoped to the user picked on the page. Since `demo_head`
   holds no metal, select `head_admin` or `employee_one` on the page first,
   or the list will be empty.
4. Enter a **Taken** amount against a lot, Save

**Expect:** the row is replaced by one row per lot taken, each showing a
`Lot #` chip and the lot's own touch.

## C3. Item Conversion — metal auto-opens too

Repeat C2 using **Item Conversion** and **Source item = Metal**.

**Expect:** identical behaviour — the picker opens on its own. This was
inconsistent until recently, so it's worth confirming.

## C4. Item Conversion — alloys

1. Open Item Conversion, pick a non-metal source item
2. Add one or more **alloy** rows
3. Check the "Converted grams" preview updates as you type
4. Submit

**Expect:** success. The server recalculates the final values — the preview
is only a guide.

## C5. Multi-lot split

In Item Conversion, take from **two different lots** in the picker.

**Expect:** the row splits into one row per lot, a toast says extra rows were
added, and **the extra rows have no alloys** — you have to add them. That's
deliberate: copying alloys would silently multiply the quantity.

---

# Section D — GMS and Numeric Wastage

## D1. GMS Out / GMS In

1. Pick a user → **GMS Out**
2. Fill in grams, touch, stone, thread, and the mtouch fields
3. Submit, then repeat with **GMS In**

**Expect:** success, and rows in Transaction History. Selecting Metal as the
item should open the lot picker here too.

## D2. Numeric Wastage Out / In

1. **Numeric Wastage Out** adds a row to an inline grid (not a modal)
2. **Numeric Wastage In** opens a modal
3. Both need **number of pieces** greater than 0

**Expect:** a field error if pieces is 0 or blank. The row total should be
pieces × waste total.

---

# Section E — Cash

## E1. Cash Out

1. Pick a user → **Cash Out**
2. **Expect:** a note saying the sender is always you (`demo_head`), and no
   date field — both are set server-side
3. Enter an amount and optional remarks, save

**Expect:** success. This writes a cash transaction, not stock, so the stock
panels won't change — only the Cash figure in Head Stocks.

**Also check:** amount of 0 or blank → "Amount must be greater than 0."

## E2. Cash In

1. Pick a user → **Cash In**
2. **Expect** the full cash entry modal, with payment methods and the bank
   account you created in A8
3. Save

**Expect:** success, and the transaction visible under **Cash Management**.

---

# Section F — Role-driven panel (new, test carefully)

This is the newest work and the most likely to be wrong. The right-hand
panel changes based on the **role of the selected user**.

## F1. Select a CUSTOMER

**Expect:**
- Customer photo box (or "No photo")
- **Customer Comments** box
- A green **Customer Deliver** button
- **Customer Touch** dropdown — *only if that user has the touch flag set*.
  Two different customers can legitimately differ here.

## F2. Select an EMPLOYEE

**Expect:**
- Customer Comments box
- **Assign to Employee** and **Employee Deliver**, both greyed out
- **No** Customer Deliver
- **No** Customer Touch dropdown

## F3. Select a HEAD (or PURCHASE / SALARY)

**Expect:**
- Customer Comments box only
- **No** deliver button of any kind

## F4. Comments save

With a customer selected, type into **Customer Comments** and save.
Reload the page, reselect them.

**Expect:** the comment persisted.

---

# Section G — Hide

## G1. Hide transactions

1. In **Transaction History**, tick two rows
2. **Expect:** a "Hide N selected" button appears in the panel header
3. Click it

**Expect:** an amber confirmation explaining that rows stay in the ledger,
that a row drawn from a parent lot hides the lot too, and that it cannot be
undone from this app.

4. Confirm

**Expect:** the rows disappear from the history and the list reloads.

## G2. Hidden rows stay hidden

Reload the page.
**Expect:** the hidden rows are still gone, and they no longer appear in the
metal picker either.

---

# Section H — View Notes (reminders)

Newly connected to a real backend — worth a careful pass.

## H1. Create a note

1. On the **Stock** screen, find the **View Notes** panel (collapsed by
   default — click the header to expand)
2. Click **+**
3. Enter a description, pick a **Remainder Date**, pick **Assign To**
4. Save

**Expect:** success, and the note in the table.

## H2. Check the date

**Look hard at the date in the table.** It should be exactly the date you
picked — not the day before.

This is the single most likely thing to be wrong in this feature. If it's a
day early, log it immediately.

## H3. View marks it seen

1. Click the **eye** icon on a note
2. **Expect:** a read-only detail view, and the row's "Is Viewed" flips to
   viewed
3. Close and reload — the viewed state should have persisted

## H4. Edit and complete

1. Click the **pencil** on a note, change the description, save
2. **Expect:** the change sticks, and the Added By / Assign To names still
   show (they're filled in locally, so check they didn't become `#2`)
3. Tick one or more notes, click **Complete**
4. **Expect:** they flip to completed

---

# Section I — Stock Auto Entry

**Others → Stock Auto Entry.** Four transfer types between two parties.

## I1. Each type

Run one of each: `EMPTOEMP`, `EMPTOHEAD`, `ANOTHERHEADTOEMP`, `HEADTOHEAD`.
The party and item fields change per type.

**Expect:** success, and rows in the history.

## I2. Backdating

1. Set a row's **Date-Time** to a few days ago
2. Submit
3. Find the transaction in a report

**Expect:** it's stamped with **your chosen date**, not today. This only
started working recently, so confirm it.

## I3. Mixed dates warning

Add two rows with **different** dates.

**Expect:** an amber notice saying the whole entry takes the first row's
date. That's a backend limitation, not a bug.

---

# Section J — Reports

Run each with a date range that covers your test data.

| # | Report | Check |
|---|---|---|
| J1 | **Items OB & CB** | Opening and closing balances per item reconcile with your transactions |
| J2 | **Consolidated Report** | IN and OUT sections both populate |
| J3 | **ID Wise** | Enter a `stock_id` from Transaction History — shows that lot's full lineage |
| J4 | **One Day Action** | One day's stock *and* cash in one list. Cash rows should show an **amount**, with grams blank |
| J5 | **Metal Live** | Live balances for the Metal item |
| J6 | **Cash Transactions Report** | Cash in/out over the range |

**For J5 specifically:** leave the "As of date/time" filters **empty**.
Filling them returns a 500 — known, listed above. The live view works.

**For J1 and J2:** try the **Export** button if present. It downloads a CSV
of the *whole filtered set*, not just the page you're looking at.

---

# Section K — Everything else

Quick smoke test — open each and confirm it loads, lists data, and its
create/edit form saves.

- **Clients**
- **Phone Book** (read-only by design — no add/edit/delete, that's correct)
- **Settings → Items**, **Fitem Boxes**, **System Settings**
- **Administration → Customer Touch**, **Customer Touch Mappings**
- **Others → Sale Gold**, **Purchase Gold**, **Cash To Gold**, **Gold To
  Cash**, **Orders**
- **Cash Management** — the whole module, including auto entry

---

# Section L — Cross-cutting

## L1. Page refresh keeps you signed in

Mid-session, press **F5** on any screen.
**Expect:** you stay signed in and land on the same screen. No 404.

## L2. Deep links work

Paste `http://localhost:5173/stock` into a fresh tab.
**Expect:** it loads. A 404 here is a real bug.

## L3. Sign out in two tabs

Open the app in two tabs, sign out in one.
**Expect:** the other tab notices and returns to the login screen.

## L4. Empty states

Filter a report to a date range with no data.
**Expect:** a clear "no data" message, not a blank panel or a spinner that
never stops.

## L5. Numbers are readable

Across every table: amounts right-aligned, consistent decimal places, grams
distinguishable from purity and from rupees.

---

## When you're done

Send back the step numbers that failed with the detail listed under
**How to record results**. Partial results are fine and useful — don't wait
until you've run everything.
