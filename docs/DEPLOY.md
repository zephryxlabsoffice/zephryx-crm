# Deploying to crm.zephryxlabs.in

Upload-only, **no Terminal / SSH, no Node.js on the server.** Two zip files
and a one-time setup page. Foundation spec §10 is the reasoning; this file is
only the steps.

Live since **2026-10-14** on MilesWeb cPanel. Part A was done once and is kept
for a rebuild; **Part B is what you follow for every update.**

## How it is laid out on the server

```
<site folder>/              ← File Manager shows the real path; on MilesWeb it is
│                              /var/www/<long-id>/, not /home/<user>/
├── public_html/            ← the website (only public files)
└── zephryx-crm/            ← the application, BESIDE public_html, never inside it
    ├── .env                ← written by install.php — the key to everything
    ├── storage/            ← uploaded files, backups, installed.lock
    └── app, vendor, …
```

Nothing in `zephryx-crm/` can be opened from a browser, so `.env`, the code
and uploaded documents stay private. `public_html/index.php` reaches across
to `../zephryx-crm` for everything else.

---

# Part A — first install (done 2026-10-14)

## A1. On the PC — make the zips

For a first install run `php deploy/build.php --with-installer` (it adds
`install.php`); `MAKE-UPLOAD.bat` builds update zips without it. It builds, from the
last commit:

- `upload\zephryx-crm.zip` — the application (about 9 MB)
- `upload\public_html.zip` — the website files (under 1 MB)

Uncommitted changes are left out on purpose; it warns you if there are any.

## A2. cPanel — once

1. **Select PHP Version** → **8.4**. (MilesWeb has no MultiPHP Manager; this
   setting is account-wide, so check the other sites still open afterwards.)
   Tick the extensions the installer asks for.
2. **MySQL Databases** → create a database and a user, add the user with
   **ALL PRIVILEGES**. Note the full names with the prefix
   (live: `crm_zeph1_crm`, user `crm_zeph1_crmuser`).
3. **Mail** is Google Workspace SMTP, not cPanel mail: `smtp.gmail.com`, port
   `587`, TLS, the Workspace user that owns the `no-reply@zephryxlabs.in`
   alias, and a Google **App Password** for that user. If that user's password
   is ever changed, the App Password stops working and the CRM stops sending
   email — make a new App Password and put it in `.env` (`MAIL_PASSWORD`).

## A3. File Manager — upload

1. In the site folder (one level ABOVE `public_html`), **create a folder
   `zephryx-crm`** and open it. The zip has no folder of its own inside it,
   so it must be extracted *inside* this folder.
2. Upload `zephryx-crm.zip` there → right-click → **Extract** → delete the zip.
3. Open `public_html`, upload `public_html.zip` → **Extract** → delete the zip.
   Turn on **Settings → Show Hidden Files** and check `.htaccess` is there.

Zips built after 2026-10-27 carry Unix permissions, so extracted files come out
644 and folders 755. (The first deploy's files came out 666 and had to be fixed
by hand; if a page ever shows a 500 error straight after an extract, check
permissions first.)

## A4. Browser — run the installer

1. Open `https://crm.zephryxlabs.in/install.php`. Fix anything it lists.
2. It asks for a **setup code**: open `zephryx-crm/storage/install-token.txt`
   in File Manager and copy it.
3. Fill in database, email and the owner password → **Install**.

It writes `.env`, generates the encryption key, creates every table, creates
the owner account, then **deletes itself**.

## A5. Check it works

- [ ] HTTPS works (SSL is Cloudflare **Full (strict)**)
- [ ] `/login` → owner email (or user ID `OWNER`) + password; the sign-in code
      email arrives in the inbox, not spam
- [ ] `public_html/install.php` is gone
- [ ] **Download `zephryx-crm/.env` and keep it somewhere safe**, apart from
      the backups. Its `APP_KEY` unlocks every encrypted record and the stored
      Google key. If it is lost or changed, ID numbers, PANs and bank details
      become unreadable forever.
- [ ] Admin → Integrations → connect Google → **Test connection**

## A6. Cloudflare

- `crm` record → the MilesWeb server IP, **orange cloud (proxied)**. Decided
  2026-10-14 (reverses the 2026-10-09 grey-cloud plan): Cloudflare's IP ranges
  are listed in `.env` as `TRUSTED_PROXIES`, so the app still sees each
  visitor's real IP — the audit log and the per-IP login lock-out depend on
  it. If Cloudflare ever publishes new ranges, update that line.
- Mail DNS (SPF/DKIM for Workspace) is Google's; never add a second SPF record.

## A7. Google (done 2026-10-14)

Google Cloud project "Zephryx CRM" in the zephryxlabs.in organisation; service
account `zephryx-crm-drive`; Shared Drive **"Zephryx CRM Files"** with the
service account as Content manager; domain-wide delegation with the Calendar
scope only, impersonating `contact@`.

Still to do: switch the org policy `iam.managed.disableServiceAccountKeyCreation`
back **on** for the project, and delete the downloaded JSON key from the PC
(the key is stored encrypted in the CRM).

---

# Part B — uploading an update

Do these in order, every time.

## B1. On the PC

1. Everything committed. Double-click **`MAKE-UPLOAD.bat`**.
2. If the update adds database tables or columns, that is fine: after
   uploading (B5) open **Admin → Updates** and press **Back up and apply**.
   The Admin Overview also warns while a change is waiting.

## B1b. Close the site (optional, recommended)

Admin → **Maintenance** → **Start maintenance now** (everybody is emailed),
or **Schedule** it ahead of time. Turn it off after B5; everybody is emailed
that the site is back.

## B2. Back up first

- After the Backups feature is live: Admin → Backups → **Back up now**, then
  **Download** it.
- Before that (the first update only): cPanel → **phpMyAdmin** → the CRM
  database → **Export** → Quick → SQL → **Go**. Keep the file.

## B3. Upload the application

1. File Manager → open `zephryx-crm`.
2. Upload `zephryx-crm.zip` → **Extract** → when it asks, **overwrite**.
   Delete the zip.

**Never delete or replace `zephryx-crm/.env` or `zephryx-crm/storage`.** The
zip contains neither `.env` nor any uploaded file, so extracting over the top
is safe; deleting the folder first is not.

## B4. Upload the website files

1. Open `public_html`. Delete the old **`build`** folder (old CSS/JS files
   pile up otherwise; the new ones have new names).
2. Upload `public_html.zip` → **Extract** (overwrite) → delete the zip.
3. Zips made after 2026-10-27 have no `install.php`. If one is ever there,
   delete it.

## B5. Check

- [ ] Admin → **Updates**: if it lists changes, press **Back up and apply**
      first (it says "Up to date" when there is nothing to do)
- [ ] Open the site in a private window: the home page and `/login` look
      right (a hard refresh, Ctrl+F5, if the old style shows)
- [ ] Sign in; open a couple of pages
- [ ] Anything in the update's own notes (settings to set, etc.)

If something is broken: re-extract the previous zips (keep the last good pair
in `upload\` on the PC) and, only if data went wrong, restore the backup.

---

## Backups — set up once

The app backs itself up: a copy of the whole database goes to
`zephryx-crm/storage/app/backups` (last 14) and to the Google Shared Drive,
folder `backups` (last 60). Admin → Backups shows them, has **Back up now**
and a Download button, and warns if the nightly run stops.

The nightly run is a cPanel Cron Job (cPanel → **Cron Jobs**; no Terminal needed):

1. **Cron Email** (if the page has one): put the owner's address. cPanel
   mails the output of every run, so a failure arrives in the inbox.
2. Common Settings: **Once Per Day**, then set Hour to `2` (2 AM):
   `0 2 * * *`.
3. Command — replace `<site folder>` with the real path File Manager shows:

   ```
   /usr/bin/php <site folder>/zephryx-crm/artisan backup:database >> <site folder>/zephryx-crm/storage/logs/cron.log 2>&1
   ```

   On MilesWeb PHP 8.4 is `/usr/bin/php` (`/usr/local/bin/php` does not
   exist there). The panel has no Cron Email box, so the output goes to
   `storage/logs/cron.log` — open it in File Manager to see each run.
   Live (set up 2026-10-27): site folder
   `/var/www/411f7520-188d-4c09-8be1-84cf71b3b71b`, daily at `0 2 * * *`.
4. Next day, open Admin → Backups and check last night's file is listed and
   the page shows no warning.

**Restoring:** phpMyAdmin → the CRM database → Import → the `.sql.gz` file.
It only works with the same `APP_KEY`, so keep that copy of `.env` safe.

MilesWeb also keeps its own account backups; this is the copy you control.

## Changing a setting later

Company settings (working day, leave, attendance start date…) are in
**Admin → Settings**. Server settings (mail password, trusted proxies) are in
`zephryx-crm/.env` — edit in File Manager and save; it takes effect on the
next page load (config is deliberately not cached).

## Rules

- Never run the installer twice, and never delete
  `zephryx-crm/storage/installed.lock`.
- Never put `zephryx-crm` inside `public_html`.
- Never set `APP_DEBUG=true` on the live site.
- Never change `APP_KEY`.
