# Deploying to crm.zephryxlabs.in

Upload-only, **no Terminal / SSH, no Node.js on the server.** Two zip files
and a one-time setup page. Foundation spec §10 is the reasoning; this file is
only the steps.

## How it is laid out on the server

```
/home/<cpanel-user>/
├── public_html/        ← the website (only public files + install.php)
└── zephryx-crm/        ← the application, BESIDE public_html, never inside it
    ├── .env            ← written by install.php
    ├── app, vendor, storage, …
```

Nothing in `zephryx-crm/` can be opened from a browser, so `.env`, the code
and uploaded documents stay private. `public_html/index.php` reaches across
to `../zephryx-crm` for everything else.

---

## 1. On the PC — make the zips

Double-click **`MAKE-UPLOAD.bat`** in the project folder.

It builds, from the last commit:

- `upload\zephryx-crm.zip` — the application (about 9 MB)
- `upload\public_html.zip` — the website files (under 1 MB)

Uncommitted changes are left out on purpose; it warns you if there are any.

## 2. cPanel — once

1. **MultiPHP Manager** → set the domain to **PHP 8.3 or newer**.
2. **MySQL Databases** → create a database, create a user, add the user to
   the database with **ALL PRIVILEGES**. Write down the three names exactly as
   cPanel shows them (with the prefix, e.g. `cpuser_zephryx`).
3. **Email Accounts** → create `no-reply@…` and open **Connect Devices** to
   see its SMTP host, port and username.

## 3. File Manager — upload

1. Go to your home folder (`/home/<cpanel-user>`, one level ABOVE
   `public_html`). Upload `zephryx-crm.zip` → right-click → **Extract**. You
   should now have a `zephryx-crm` folder beside `public_html`. Delete the zip.
2. Open `public_html`. **Delete what you uploaded earlier** (the old
   `index.php`, `build`, `assets`, and any `public.rar`).
3. Upload `public_html.zip` into `public_html` → **Extract** → delete the zip.
   Turn on **Settings → Show Hidden Files** and check `.htaccess` is there.

## 4. Browser — run the installer

1. Open `https://<your-domain>/install.php`.
   If it lists problems (PHP version, an extension, a folder), fix them in
   cPanel and reload.
2. It asks for a **setup code**. In File Manager open
   `zephryx-crm/storage/install-token.txt` and copy the code. (This stops a
   stranger who finds the page from installing it first.)
3. Fill in database, email and the owner password → **Install**.

It writes `.env`, generates the encryption key, creates every table, creates
the owner account, then **deletes itself**. The owner password is never saved
in any file.

## 5. Check it works

- [ ] The site opens over HTTPS (cPanel → SSL/TLS Status → run AutoSSL if not)
- [ ] `/login` → admin email (or user ID `OWNER`) + owner password
- [ ] The sign-in code email arrives **in the inbox, not spam**
- [ ] Change the owner password
- [ ] `public_html/install.php` is gone
- [ ] **Download `zephryx-crm/.env` and keep it somewhere safe.** Its
      `APP_KEY` unlocks every encrypted record. If it is lost or changed, ID
      numbers, PANs and bank details become unreadable forever.
- [ ] Admin Panel → Integrations → connect Google → "Test connection"

## 6. DNS (Cloudflare)

- `A` record for the site → the MilesWeb server IP, **grey cloud
  (DNS-only)**. Decided 2026-10-09: an orange cloud would make every visitor
  look like a Cloudflare IP and break the per-IP login lock-out.
- Mail: SPF and DKIM records for the sending (sub)domain from cPanel → Email
  Deliverability, **grey cloud** on every MX/TXT. Never touch the apex SPF that
  Google Workspace uses — a second SPF record breaks both.

## Changing a setting later

Edit `zephryx-crm/.env` in File Manager and save. It takes effect on the next
page load — the installer deliberately does not cache config, because
without Terminal there would be no way to clear that cache.

## Uploading a new version later

1. Commit on the PC, double-click `MAKE-UPLOAD.bat`.
2. Upload and extract over the top (overwrite). **Never delete or replace
   `zephryx-crm/.env` or `zephryx-crm/storage`** — they hold the key and the
   uploaded files.
3. If the new version has new database tables, they need a migration run —
   ask for an update page before uploading (not built yet; the first install
   does not need it). The `install.php` that comes back with
   `public_html.zip` is harmless: the lock file makes it show "Not found"
   and delete itself. Delete it anyway.

## Rules

- Never run the installer twice on a live site, and never delete
  `zephryx-crm/storage/installed.lock`.
- Never put `zephryx-crm` inside `public_html`.
- Never set `APP_DEBUG=true` on the live site.
