# SchainsERP — How to Deploy It

A step-by-step guide to putting SchainsERP live in production.

Written 2026-09-09. Everything here was checked against the real servers and
the real code — not copied from older notes.

The guide is split by provider, so you can hand one part to one person:

- **[Part 1 — Render](#part-1--render)** — the database and the API
- **[Part 2 — GoDaddy](#part-2--godaddy)** — the web addresses
- **[Part 3 — Vercel](#part-3--vercel)** — the screens the users see
- **[Part 4 — Check it works](#part-4--check-it-works)**
- **[Part 5 — When something breaks](#part-5--when-something-breaks)**
- **[Part 6 — After go-live](#part-6--after-go-live)**

---

## What you are building

Three pieces that talk to each other:

| Piece | Lives on | Web address | What it does |
|---|---|---|---|
| The screens | Vercel | `www.lensnstories.com` | What your staff see and click |
| The API | Render | `api.lensnstories.com` | Does the work, checks the rules |
| The database | Render | (private) | Stores the data |

Your staff open the website. The website asks the API for data. The API reads
and writes the database.

## Do the parts in this order

**Render first, then GoDaddy, then Vercel.**

That order matters. Vercel needs to be told the API's web address, and that
address does not work until Render and GoDaddy are both done. If you set up
Vercel first, you have to redo it.

## Where things stand today

Some of this is already done. Checked on 2026-09-08:

**Working:**

- The API is live and running on Render.
- The database is connected to the API and answering.
- The website loads at `www.lensnstories.com`.
- `lensnstories.com` correctly forwards to `www.lensnstories.com`.

**Not working — these three are why the live site is broken:**

1. **`api.lensnstories.com` does not exist.** The address was never created
   at GoDaddy. → [Part 2](#part-2--godaddy)
2. **Vercel was never told the API address.** So the website asks itself for
   data instead of asking the API, and gets nothing. → [Part 3](#part-3--vercel)
3. **Refreshing a page shows "404".** Only the home page loads. Already fixed
   in the code — it just needs to be committed and deployed.
   → [Step 3.1](#step-31--commit-the-page-refresh-fix)

**One more problem, less urgent:** every time the API restarts, all users get
logged out. → [Step 1.5](#step-15--set-the-login-keys)

---

# Part 1 — Render

Render runs two things for you: the **database** and the **API**.

## Step 1.1 — Check your Render plan first

Do this before anything else, because fixing it later means moving your data.

On Render's **free** plan:

- **Your database is deleted after 30 days.** All of it. Gone.
- **Your API goes to sleep after 15 minutes of no use.** The next person to
  use it waits 30–60 seconds for a screen to load. We measured this on your
  current setup: one request took over 12 seconds, the next took half a
  second. Your staff will think the system is down.

Neither is acceptable for a business that stores gold transactions.

**Upgrade both the database and the API to a paid plan before you put real
data in.**

## Step 1.2 — Get your database connection details

Your database already exists. It is called `schains_erp`.

1. Open the Render dashboard.
2. Click your PostgreSQL database.
3. Click **Connections**.
4. Copy **both** of these and keep them somewhere safe:

| What it's called | What it's for |
|---|---|
| **Internal Database URL** | For the API. Use this one in Step 1.4. |
| **External Database URL** | For connecting from your own laptop. Use this one in Step 1.6. |

They look like this:

```
postgresql://username:password@hostname/schains_erp
```

Copy them exactly. Do not type them out by hand.

> **Keep these private.** Anyone with them can read and change every
> transaction in your system.

## Step 1.3 — Make the login keys

Your API signs people in using two security keys. You make them on your own
computer, then paste them into Render in the next step.

You need PHP working on your machine first. If `composer install` fails,
you need to switch on one PHP setting:

1. Run `php --ini` to find your `php.ini` file.
2. Open it and remove the `;` in front of `extension=sodium`.
3. Check it worked: `php -r "var_dump(extension_loaded('sodium'));"` should
   print `true`.
4. Now run `composer install` inside the `schainbackend` folder.

Then make the keys:

```
cd schainbackend
php artisan key:generate --show
```

Copy what it prints. It starts with `base64:`. This is your **APP_KEY**.

```
php artisan passport:keys --force
```

This creates two files in `schainbackend/storage/`:

- `oauth-private.key`
- `oauth-public.key`

Open each one in a text editor. You will paste their full contents into
Render next.

> **Never put these files into Git.** They are already blocked from Git, so
> just don't force them in.
>
> **Never run `php artisan passport:install`.** It looks harmless but it
> broke the whole deployment once before. The two commands above are the
> correct ones.

## Step 1.4 — Fill in the API settings on Render

1. Open the Render dashboard.
2. Click your web service, **`schainserp`**.
3. Click **Environment**.
4. Add each of these settings.

Copy the database values from the **Internal Database URL** you saved in
Step 1.2. That URL is built like this, so you can pick the pieces out of it:

```
postgresql:// USERNAME : PASSWORD @ HOSTNAME / DATABASE
```

The settings to add:

| Setting | Value |
|---|---|
| `APP_NAME` | `SchainsERP` |
| `APP_ENV` | `production` |
| `APP_KEY` | The `base64:...` line from Step 1.3 |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://api.lensnstories.com` |
| `LOG_LEVEL` | `error` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | `dpg-daf7pklg1s2s73dd1880-a` (the HOSTNAME part only) |
| `DB_PORT` | `5432` |
| `DB_DATABASE` | `schains_erp` |
| `DB_USERNAME` | The USERNAME part |
| `DB_PASSWORD` | The PASSWORD part |
| `SESSION_DRIVER` | `database` |
| `SESSION_LIFETIME` | `120` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |

Three things people get wrong here:

- **`APP_DEBUG` must be `false`.** If it is `true` and anything goes wrong,
  the error page shows your database password to whoever is looking at it.
- **`DB_HOST` is just the hostname.** Not the whole `postgresql://...` line.
  No `https://`, no port number, no username.
- **Do not set `CACHE_STORE` to `redis`.** You do not have Redis. It will
  break.

## Step 1.5 — Set the login keys

**This fixes the "everyone gets logged out" problem.**

Right now, nobody has set these, so the API makes fresh keys every time it
starts. Render wipes the disk on every restart, so the new keys don't match
the old ones, and everyone who was signed in gets thrown out.

In the same **Environment** screen, add two more settings:

| Setting | Value |
|---|---|
| `PASSPORT_PRIVATE_KEY` | The **entire** contents of `storage/oauth-private.key` |
| `PASSPORT_PUBLIC_KEY` | The **entire** contents of `storage/oauth-public.key` |

Paste the whole file. Include the `-----BEGIN...-----` line at the top, the
`-----END...-----` line at the bottom, and every line between them. Render
accepts text spread over several lines.

> **Heads up:** the first time you set these, everyone signed in right now
> gets logged out once. After that it stops happening. Do it in the evening
> or before staff start work.

## Step 1.6 — Put the starting data in the database

The database has empty tables. You need to add the basic rows before anyone
can log in.

**The problem:** Render does not give you a command line on the free plan.
Check for a **Shell** tab on your service — if you have one, use it and skip
straight to the commands below.

**If you have no Shell tab,** run the commands from your own computer,
pointed at the Render database instead of your local one.

Open PowerShell in the `schainbackend` folder and paste this, filling in
the pieces from the **External Database URL** you saved in Step 1.2:

```powershell
$env:DB_HOST     = 'PASTE THE HOSTNAME FROM THE EXTERNAL URL'
$env:DB_PORT     = '5432'
$env:DB_DATABASE = 'schains_erp'
$env:DB_USERNAME = 'PASTE THE USERNAME'
$env:DB_PASSWORD = 'PASTE THE PASSWORD'
$env:DB_SSLMODE  = 'require'
```

Check you are pointed at the right database before changing anything:

```powershell
php artisan db:show
```

It should show the Render hostname, not `127.0.0.1`. If it shows
`127.0.0.1`, the lines above did not take effect — check for typos.

Now add the data:

```powershell
php artisan db:seed --class=RoleSeeder --force
php artisan db:seed --class=CashCategorySeeder --force
php artisan db:seed --class=HeadUserSeeder --force
```

**When you are finished, close that PowerShell window.** Those settings stay
active until you do, and you don't want your next command hitting the live
database by accident.

### What each command does

| Command | What it adds | Safe to run twice? |
|---|---|---|
| `RoleSeeder` | The five user roles. **Required.** | Yes |
| `CashCategorySeeder` | Basic cash categories. **Required.** | Yes |
| `HeadUserSeeder` | A temporary login so you can get in. | **No** — it fails the second time |

**Do not run any other seeder.** The rest add fake test data — test items,
test gold stock, test purchases. You do not want them mixed into your real
records.

**Never type `php artisan db:seed` on its own.** Without the `--class=` part
it runs broken leftover code and fails. Always name the seeder.

## Step 1.7 — Deploy and check the log

Click **Manual Deploy** in Render, then watch the **Logs** tab.

A good start-up looks like this:

```
Starting Laravel application
Clearing Laravel caches...
Checking the Passport migration ledger...
Running database migrations...
Passport keys: using PASSPORT_PRIVATE_KEY / PASSPORT_PUBLIC_KEY from the environment.
Ensuring a Passport personal access client exists...
Caching Laravel configuration...
Starting Apache...
```

Two lines to look for:

- `Passport keys: using PASSPORT_PRIVATE_KEY...` — good, Step 1.5 worked.
- `Passport keys: none found - generating a pair` — **Step 1.5 did not
  work.** Go back and check the two settings.

If the log stops at `Migrations FAILED`, the API did not start. That is on
purpose — it refuses to run on a half-built database. The log prints exactly
which database step failed right underneath.

### Two things about how Render works here

- **The database is set up automatically.** Every time the API starts, it
  updates the database structure by itself. You never do this by hand.
- **A bad code change takes the site down.** If a database update fails, the
  API stops. It does not go back to the old working version. Test changes
  before deploying.

**Render is done.** Move on to GoDaddy.

---

# Part 2 — GoDaddy

GoDaddy is where your web addresses point somewhere. You have three
addresses. Two work. One is missing.

| Address | Points to | Status |
|---|---|---|
| `www.lensnstories.com` | Vercel | Working — leave it alone |
| `lensnstories.com` | Forwards to `www` | Working — leave it alone |
| **`api.lensnstories.com`** | Should point to Render | **Missing** |

We checked `api.lensnstories.com` against Google's and Cloudflare's public
address books. Neither has heard of it. It was never created.

**Until you create it, the website has no way to reach the API.**

## Step 2.1 — Add the address at GoDaddy

1. Sign in to GoDaddy.
2. Go to your domain `lensnstories.com` → **DNS** → **Manage DNS**.
3. Click **Add New Record**.
4. Fill it in exactly like this:

| Field | Value |
|---|---|
| Type | `CNAME` |
| Name | `api` |
| Value | `schainserp.onrender.com` |
| TTL | `600` (or 10 minutes) |

5. Save.

Put **only** `api` in the Name box. Not `api.lensnstories.com` — GoDaddy adds
the rest for you.

## Step 2.2 — Tell Render about the address

**Do not skip this step.** Step 2.1 on its own is not enough.

1. Go back to Render → your `schainserp` service.
2. Click **Settings** → **Custom Domains**.
3. Click **Add Custom Domain**.
4. Type `api.lensnstories.com` and save.

Render now knows to answer for that address, and it creates the security
certificate that makes `https://` work.

If you skip this, your browser shows a security warning instead of loading.
People usually think that's a GoDaddy problem. It isn't — it's this step.

## Step 2.3 — Wait, then check

Render will show the domain as **Verified** once it is ready. Usually a few
minutes. It can take up to an hour.

Then test it:

```
curl https://api.lensnstories.com/up
```

You want a normal response with no error. If you get a security warning,
Step 2.2 is not finished yet. If you get "cannot find the server", the
GoDaddy record has not spread yet — wait longer.

**Do not start Part 3 until this test passes.**

## Do not touch the other two addresses

`www.lensnstories.com` and `lensnstories.com` both serve your staff screens.
If you point either of them at Render, your ERP disappears and gets replaced
by a blank Laravel page.

**GoDaddy is done.** Move on to Vercel.

---

# Part 3 — Vercel

Vercel hosts the screens your staff use. It rebuilds the site automatically
every time you push code to GitHub.

## Step 3.1 — Commit the page-refresh fix

Right now, if someone refreshes any page, or opens a saved link, they see a
plain **404** error. Only the home page loads.

The fix is already written. It is a file called `frontend/vercel.json`. It
just needs to go into Git:

```
git add frontend/vercel.json
git commit -m "fix(deploy): serve the app on every URL, not just the home page"
git push
```

**Why this happens:** the site is one page that swaps its own content as you
click around. When you refresh, the browser asks Vercel for a page that
doesn't exist as a file, and Vercel says 404. This file tells Vercel to hand
back the app instead. Real files like images and scripts still load normally.

## Step 3.2 — Check the project settings

Your code is inside a `frontend` folder, not at the top of the repository.
Vercel needs to know that.

Go to Vercel → your project → **Settings** → **General**:

| Setting | Value |
|---|---|
| Framework Preset | `Vite` |
| Root Directory | **`frontend`** |
| Build Command | `npm run build` |
| Output Directory | `dist` |
| Install Command | `npm install` |
| Node.js Version | `20` or `22` |

If the site is already loading, these are probably right already. Check
anyway.

## Step 3.3 — Tell the website where the API is

**This is the main reason the live site does not work.**

Nobody ever told the website the API's address. So it asks itself for data,
finds nothing, and every screen fails.

1. Go to Vercel → your project → **Settings** → **Environment Variables**.
2. Click **Add New**.
3. Fill it in:

| Field | Value |
|---|---|
| Key | `VITE_API_BASE_URL` |
| Value | `https://api.lensnstories.com/api/v1` |
| Environments | Tick **all** of them |

4. Save.

**Include the `/api/v1` at the end.** Without it, nothing works.

> **Important:** this address gets baked into the files when Vercel builds
> the site. Saving the setting changes nothing on its own. **You must
> redeploy afterwards.** Restarting or clearing the cache will not do it.

### If GoDaddy isn't ready yet

If you want the site working today and Part 2 is still spreading, use the
Render address instead:

```
https://schainserp.onrender.com/api/v1
```

This works straight away. Change it to `api.lensnstories.com` later and
redeploy again.

### Don't set this on your own computer

When developing locally, leave this setting alone. The local setup already
connects to your local API by itself. There is no file to create.

## Step 3.4 — Deploy

Vercel only publishes what is in GitHub. Anything sitting unsaved on your
laptop does not go live.

As of 2026-09-08 there were **13 changed files** in `frontend/src/` that had
never been committed. Those screens are not on the live site.

```
git status
```

Commit and push whatever you want live, then in Vercel click **Deployments**
→ **Redeploy**.

When the build finishes, check it used the commit you expect.

> The build runs a code check first. If there is a mistake in the code, the
> deploy fails instead of publishing a broken site. That is deliberate.
> Don't switch it off to force a deploy through.

**Vercel is done.**

---

# Part 4 — Check it works

Run these in order. Each one tests a different link in the chain, so the
first one that fails tells you exactly which part to go back to.

### 1. Is the API alive?

```
curl https://api.lensnstories.com/up
```

Expect a normal response. If not, go to [Part 2](#part-2--godaddy).

### 2. Is the API answering properly?

```
curl -H "Accept: application/json" https://api.lensnstories.com/api/v1/items
```

Expect: `{"message":"Unauthenticated."}`

**That is the correct answer.** It means the API is working and is correctly
refusing to hand out data to someone who has not signed in.

### 3. Is the database connected?

Send a login with a deliberately wrong password:

```
curl -X POST https://api.lensnstories.com/api/v1/login -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"user_name\":\"wrong\",\"password\":\"wrong\"}"
```

Expect: `{"success":false,"message":"Invalid username or password"}`

That wording proves the API looked in the database and did not find the user.
If you get a **500 error** instead, the database is not connected. Check
Step 1.4.

### 4. Can someone actually log in?

```
curl -X POST https://api.lensnstories.com/api/v1/login -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"user_name\":\"demo_head\",\"password\":\"password\"}"
```

Expect a long token in the response.

**This is the one test that has never been run on your live system.**
Everything before and after it has been checked. This one has not. Run it.

If it fails:

- Message about a *personal access client* — look in the Render log for a
  line starting with `!! Could not verify or create` and read what it says.
- Anything mentioning a key or a file — Step 1.5 did not work.

### 5. Does refreshing a page work?

```
curl https://www.lensnstories.com/login
```

Expect a normal web page. A **404** means Step 3.1 is not deployed yet.

### 6. Does the website know the API address?

Open `https://www.lensnstories.com` in Chrome, press **F12**, click
**Network**, then sign in.

Look at where the requests are going:

- Going to `api.lensnstories.com` — correct.
- Going to `www.lensnstories.com/api/...` — Step 3.3 is not done, or you did
  not redeploy after saving it.

### 7. The real test

Open `https://www.lensnstories.com` in a browser.

1. Sign in.
2. Open a few screens.
3. **Press F5 to refresh in the middle of using it.**

If you stay signed in and nothing shows 404, you are live.

---

# Part 5 — When something breaks

| What you see | What is wrong | Go to |
|---|---|---|
| Site loads but every screen shows an error | Website does not know the API address | [Step 3.3](#step-33--tell-the-website-where-the-api-is) |
| Refreshing shows 404 | Refresh fix not deployed | [Step 3.1](#step-31--commit-the-page-refresh-fix) |
| "Cannot find server" for the API | GoDaddy record missing | [Step 2.1](#step-21--add-the-address-at-godaddy) |
| Security or certificate warning on the API | Render was not told about the domain | [Step 2.2](#step-22--tell-render-about-the-address) |
| Everyone logged out after each deploy | Login keys not set | [Step 1.5](#step-15--set-the-login-keys) |
| Login gives a 500 error | Login keys, or the sign-in setup | [Step 1.5](#step-15--set-the-login-keys), then the Render log |
| Correct password rejected | Wrong database, or the password was changed | [Step 1.6](#step-16--put-the-starting-data-in-the-database) |
| First use each morning takes a minute | Free plan, the server was asleep | [Step 1.1](#step-11--check-your-render-plan-first) |
| Render log stops at "Migrations FAILED" | A database update failed | Read the lines printed just after it |
| Errors show technical details to users | `APP_DEBUG` is on | Set it to `false`, [Step 1.4](#step-14--fill-in-the-api-settings-on-render) |
| Screens you built are missing | Never committed to Git | [Step 3.4](#step-34--deploy) |

**Where to look:** Render, then your service, then **Logs**. That is your
only window into the API. The start-up messages explain each step as it
happens, so read from the top.

---

# Part 6 — After go-live

## Remove the temporary login, first thing

`HeadUserSeeder` created a login called **`demo_head`** with the password
**`password`**. Your site is on the public internet. Anyone who guesses that
pair gets full access to your gold records.

1. Sign in as `demo_head`.
2. Go to the **Users** screen and create real logins for your staff.
3. Sign in as one of the real accounts and check it works.
4. **Delete `demo_head`.**

> **Switching it off is not enough.** There is a fault in the code: the
> sign-in check ignores whether an account is switched off. A disabled
> account can still sign in, and its access still works.
>
> To really close it, **delete the row** from the `user_details` table where
> `user_id` is `999`, or change its password. Connect the same way as in
> Step 1.6.
>
> Until this fault is fixed, treat the "active" switch as a display filter,
> not as security.

## Set up your real data

Through the app, not through commands:

- **Your item list**, on the Items screen.
- **One item named exactly `Metal`.** The metal picker on the Item Change and
  Item Conversion screens looks for that exact name. Without it, that
  dropdown stays empty.
- Your **head and employee mappings**, **cash categories**, and **customer
  touches**.

## Back-ups

Render only backs up databases on paid plans. On the free plan there are no
back-ups at all, and the database is deleted after 30 days.

You are storing gold transactions. Confirm back-ups are switched on, and
**try restoring one** before you trust it.

## Known faults to be aware of

These are real problems in the code as it stands. The full list is in
`frontend/PENDING_WORK.md`. The ones that matter in production:

1. **Disabled accounts can still sign in.** Delete accounts instead of
   disabling them.
2. **The Live Metal Balance screen shows the wrong numbers.** It reads the
   wrong item, showing Gold Chain figures under a "Metal" heading. Filtering
   it by date and time crashes it outright. Do not rely on that screen yet.
3. **Auto Entry ignores the date you type.** Every entry is stamped with the
   current time. You cannot back-date one.
4. **Two cash screens can record the wrong staff member** as the person who
   made an entry.

## Everyday deployments

**Changing the screens:** commit, push, and Vercel rebuilds by itself. If you
change `VITE_API_BASE_URL`, you must redeploy for it to take effect.

**Changing the API:** commit, push, and Render rebuilds and updates the
database by itself. If a database update fails, **the API goes down** — it
does not fall back to the working version. Test first.

**Changing the login keys:** only if one leaks. It signs everybody out. Make
a new pair (Step 1.3), replace both settings in Render, then redeploy.

---

# Quick checklist

Print this and tick as you go.

**Render**

- [ ] Paid plan on both the database and the API
- [ ] Copied the Internal and External database URLs
- [ ] Made `APP_KEY` and the two login key files
- [ ] Added all the settings, with `APP_DEBUG` set to `false`
- [ ] Added `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`
- [ ] Ran the three seeders
- [ ] Deployed, and the log says "using PASSPORT_PRIVATE_KEY"

**GoDaddy**

- [ ] Added the `api` CNAME pointing to `schainserp.onrender.com`
- [ ] Added `api.lensnstories.com` in Render's Custom Domains
- [ ] `https://api.lensnstories.com/up` loads with no warning

**Vercel**

- [ ] Committed and pushed `frontend/vercel.json`
- [ ] Root Directory is set to `frontend`
- [ ] Added `VITE_API_BASE_URL`
- [ ] **Redeployed** after adding it
- [ ] Committed every screen you want live

**Finally**

- [ ] All 7 checks in Part 4 pass
- [ ] Created real staff logins
- [ ] **Deleted `demo_head`**
- [ ] Created an item named `Metal`
- [ ] Back-ups confirmed working
