# ZephryxLabs CRM

Internal software for running ZephryxLabs. Not a SaaS product, not
multi-tenant, no public sign-up — every account is created by an administrator.

**The design authority is
[`docs/superpowers/specs/2026-08-24-zephryx-crm-foundation-design.md`](docs/superpowers/specs/2026-08-24-zephryx-crm-foundation-design.md).**
Read it before changing anything; it records the decisions and the reasons.

## Stack

| | |
|---|---|
| Framework | Laravel 13 (PHP 8.2+) |
| Database | MySQL / MariaDB, co-located |
| Front end | Blade + hand-written CSS on design tokens. No CSS framework. |
| Build | Vite |
| Mail | PHPMailer over MilesWeb SMTP |

## Local setup

```bash
composer install
npm install
cp .env.example .env        # then fill in DB and mail credentials
php artisan key:generate
npm run build               # or: npm run dev
php artisan serve
```

`.env` is never committed (spec §11.1).

## Layout

```
app/
  Http/Controllers/         one controller per surface
  Http/Middleware/          SecurityHeaders — applied to every response
  Support/                  Theme, Realm — small stateless helpers
config/zephryx.php          brand, support mailbox, theme defaults
resources/
  css/tokens.css            the single source of colour truth (spec §7)
  css/base.css              reset + primitives reused by every module
  css/pages/                page-specific styles
  views/layouts/            public.blade.php — shell for unauthenticated pages
  views/partials/           brand lockup, theme toggle
public/assets/brand/        z-black.svg (light) · z-white.svg (dark)
refference/                 the designer's original handover, for reference only
```

## Conventions worth knowing

- **Never hard-code a colour.** Add a token to `resources/css/tokens.css`.
- **No inline `<style>`, `style="…"`, or inline `<script>`.** The CSP is
  `'self'` with no `'unsafe-inline'`, so anything inline is silently dropped by
  the browser. `LandingPageTest` enforces this; keep that guard on new pages.
- **No third-party CDN assets** (spec §6). Fonts and scripts ship from
  `public/build`.
- **`data-theme` is rendered server-side** on `<html>`, so there is no flash of
  the wrong palette and no need for a blocking inline script.
- **The realm a route belongs to is the file it is written in.** `routes/staff.php`,
  `routes/client.php` and `routes/admin.php` are each mounted behind their own
  middleware in `routes/web.php`, so a page inherits the guard rather than
  needing one added. Anything left in `web.php` is public.
- **Sidebar entries live in `config/navigation*.php`** — one file per realm,
  each entry with a permission key. Adding a module means adding a line there
  and replacing its placeholder route, not editing a Blade file.
- **Authorisation goes through `App\Support\Rbac\Rbac`.** One service answers
  `can()` and `outranks()` for the whole application; a guarded action asks both
  plus the self-action check (§2.6), which `mayActOn()` bundles. Never add a
  `can()` to a model — a second implementation of the union rules is the bug.
- **The Employee base is not a role.** It is granted from `staff_kind` so that
  no role edit can revoke it (§5). The client and admin realms have equivalent
  implicit bases, for the same reason.
- **Permissions are seeded from where they are used.** `RbacSeeder` reads the
  navigation files and the dashboard registry, so a key cannot exist in the
  application and be missing from the table that grants it.

## Tests

```bash
php artisan test
```

## Database

```bash
php artisan migrate --seed
```

Seeds roles, permissions, domains and per-domain ranks, plus the owner account.
In local + debug it also seeds staff and client accounts matching the demo
directory. The owner's password comes from `ZEPHRYX_OWNER_PASSWORD`; the seeder
**refuses to run in production without it** rather than leaving a default on the
most powerful account in the application.

## Status

Phase 0. All three realms are built as front ends, on demo data.

**Modules:** Clients, Employees, Teams, Projects, Tasks, Attendance, Leave,
Salary, Tickets, Invoices, Meetings, Announcements, My Profile, Dashboard. Leads,
Calendar and Reports are deferred to v2 and return a "not built yet" 404 (§12).
The client portal (`/client`) and Admin Panel (`/admin`) are built.

**Built and real, not stubbed:**

- **Authentication (§4)** — credential check in constant time, one generic
  refusal, rate limiting per identifier *and* per IP, email OTP (6 digits, 10
  minutes, 5 attempts, single live code), device trust for 7 days, rotating
  remember-me with theft detection, absolute and per-realm idle session limits,
  and password reset that invalidates sessions, tokens and device trust.
- **Realm enforcement (§3.1)** — on the route group, by the file a route is in.
- **The RBAC engine (§5)** — union across stacked roles, implicit Employee base,
  per-domain rank, and the self-action check.
- **The audit log (§6)** for every authentication event.

Sign in with a seeded account: `EMP005` is HR, `EMP004` a manager, `EMP002` a
developer, `CLI001` a client, `OWNER` the Admin Panel. In local + debug the
password is in `AccountSeeder::DEV_PASSWORD` and the OTP is written to the mail
log rather than sent — set `MAIL_MAILER=log` and read `storage/logs`.

**Set `SESSION_DRIVER=database`.** §4.6 requires a password reset to invalidate
every other session for that user, and no other driver can reach one — on `file`
the reset appears to succeed while an attacker stays signed in with the password
it just changed. The application logs an error when it cannot do this.

**Not built: the writes.** Every state-changing route is still `abort(501)` — the
forms carry real CSRF tokens and real validation shapes, and nothing is stored.
The modules read from `App\Support\Demo\*`, which returns nothing outside local
+ debug, so a deployed application shows its empty states.
