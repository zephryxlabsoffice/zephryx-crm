# Deploying to crm.zephryxlabs.in

First deployment to MilesWeb (cPanel, shared hosting). Foundation spec §10 is
the reasoning; this file is only the steps. Do them in order.

Checked ready on 2026-10-09: 1367 tests pass, `npm run build` succeeds, every
commit is pushed to `origin/main`. No scheduled tasks or queued jobs exist yet,
so **no cron job is needed** for this first deploy.

---

## A. Before you start — have these ready

- [ ] cPanel login for MilesWeb, with **Terminal** (or SSH) enabled
- [ ] Cloudflare login for `zephryxlabs.in`
- [ ] GitHub access to `zephryxlabsoffice/zephryx-crm` from the server (a
      deploy key, or a personal access token for the clone)
- [ ] MilesWeb SMTP host, username and password for `no-reply@crm.zephryxlabs.in`
- [ ] A strong owner password chosen (`ZEPHRYX_OWNER_PASSWORD`)

## B. cPanel

1. **PHP version** — MultiPHP Manager → set the domain to **PHP 8.3 or newer**
   (`composer.json` requires `^8.3`). Extensions needed: `pdo_mysql`, `openssl`,
   `mbstring`, `fileinfo`, `tokenizer`, `xml`, `ctype`, `curl`.
2. **Database** — MySQL Databases → create `zephryx_crm`, create a user (not
   root), give it ALL PRIVILEGES. Note the full prefixed names cPanel shows
   (e.g. `cpuser_zephryx_crm`).
3. **Mailbox** — Email Accounts → create `no-reply@crm.zephryxlabs.in`.
   Email Deliverability → note the SPF and DKIM records it asks for.

## C. Code onto the server (cPanel Terminal)

```bash
cd ~
git clone https://github.com/zephryxlabsoffice/zephryx-crm.git zephryx-crm
cd zephryx-crm
composer install --no-dev --optimize-autoloader
```

**The server runs PHP only — no Node.js, no `npm`.** The compiled CSS/JS in
`public/build` is committed to git, so it arrives with the clone. Node is used
on the PC only: after any front-end change, run `npm run build` there and
commit `public/build` with the change.

## D. Point the subdomain at `/public`

Domains → create (or edit) `crm.zephryxlabs.in` → **Document Root:
`zephryx-crm/public`**. Never the project folder itself — that would serve
`.env` to the internet.

## E. `.env` on the server

```bash
cp .env.production.example .env
php artisan key:generate
nano .env
```

Fill every `CHANGE THIS`: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`,
`MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `ZEPHRYX_OWNER_PASSWORD`.
Check `APP_DEBUG=false`.

> **About the key.** The server gets its OWN key from `key:generate` — this
> is correct now, while there is no real data. `README-TRANSFER.md` says never
> run it; that warning is about the PC's key and about any time *after* real
> records exist. Once staff data is on the server, **never run `key:generate`
> there again** — it silently makes every encrypted ID, PAN and bank number
> unreadable. Copy the server's `APP_KEY` into a password manager today.

## F. Build the database and cache config

```bash
php artisan migrate --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R 775 storage bootstrap/cache
```

`--force` is required because Laravel refuses to migrate in production
without it. The seed creates roles, permissions, master lists and the owner
account only — no demo people (see `2-CREATE-DATABASE-ON-HOST.md`).

## G. DNS and HTTPS (Cloudflare)

1. `A` record `crm` → the MilesWeb server IP, **grey cloud (DNS-only)**.
   Decided 2026-10-09: proxying it would make every request arrive from a
   Cloudflare IP, breaking per-IP login rate-limiting and the audit log.
   Leave `TRUSTED_PROXIES` empty.
2. Mail records for the **`crm` subdomain only** — SPF naming MilesWeb, and
   MilesWeb's DKIM selector under `_domainkey.crm`. **Grey cloud (DNS-only)**
   on every MX/TXT. Do not touch the apex SPF that Google Workspace uses.
3. cPanel → SSL/TLS Status → run AutoSSL for `crm.zephryxlabs.in`.
   With a grey cloud, the certificate is MilesWeb's — Cloudflare's SSL mode
   does not apply to this record.

## H. Smoke test

- [ ] `https://crm.zephryxlabs.in` loads the landing page over HTTPS
- [ ] Admin sign-in with `admin@zephryxlabs.in` + the owner password
- [ ] The OTP email arrives **in the inbox, not spam** (launch blocker, §10)
- [ ] Change the owner password straight away
- [ ] Remove `ZEPHRYX_OWNER_PASSWORD` from `.env`, then `php artisan config:cache`
- [ ] Admin Panel → Integrations → connect Google, press "Test connection"

## Updating later

```bash
cd ~/zephryx-crm
php artisan down
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Never `migrate:fresh` on the server once real data exists — it drops every
table.
