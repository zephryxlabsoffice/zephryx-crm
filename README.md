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
- **New route groups inherit their guards.** Realm enforcement (spec §3.1)
  belongs on the group, never on individual routes.

## Tests

```bash
php artisan test
```

## Status

Phase 0 in progress.

**Built (front end):** landing page, login page, OTP verify step, forgot
password, reset password, theme system, notice component, security headers.

**Built (real, not a stub):** the password policy — `App\Rules\NotACommonPassword`
plus a 12-character minimum — and the no-enumeration guarantee on
`POST /forgot-password`.

**Not built:** authentication itself. `Auth\LoginController::attempt()`,
`verify()` and `resend()` are stubs — field validation is real, everything past
it is not. The credential check, rate limiting, OTP issue/verify, trusted
devices and sessions described in spec §4 land in the backend phase, and
**this page must not be deployed anywhere reachable before then.**
