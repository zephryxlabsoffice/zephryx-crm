# ZephryxLabs CRM — Foundation Design

**Date:** 2026-08-24
**Status:** Awaiting review
**Scope:** Phase 0 (Foundation) + Landing page + Login page

---

## 1. Context

ZephryxLabs is a software and media consultancy with 6 employees. This CRM is
**internal software for running that company** — it is not a SaaS product and
will never be sold or resold. There is no multi-tenancy, no billing, no tenant
onboarding.

It replaces scattered tooling with one portal covering projects, teams, tasks,
attendance, leave, payroll, clients, tickets and invoices. Staff and clients
both sign in to it.

### Non-goals

- Multi-tenancy or per-tenant isolation
- Public sign-up — every account is created by an administrator
- Marketing/lead-gen surfaces
- Mobile applications (the web UI is responsive; there is no native app)

---

## 2. Actor model

### 2.1 The two exceptions

Two things in this system do **not** sit on the Employee base layer:

**Admin Panel** — not a role and not assignable. It is the company
configuration surface, reached through a single account operated by the
company owner/founder. It has no "my attendance", no "my payslip", no
personal records of any kind, and no operational authority: it cannot approve
leave, run payroll or mark attendance. It sets the rules; it does not
participate in them.

**Mentor** — read-only visibility across the system, with no Employee base.
A Mentor may be an outside investor or advisor. Mentors have no personal
records and take no actions.

### 2.2 The Employee base layer

Every other human account sits on an Employee base: *my attendance, my leave,
my payslip, my tasks, my profile*. This includes CEO, System Administrator and
HR — each of whom has their own leave and attendance records, which is
precisely why they cannot approve their own.

### 2.3 Roles

| Role | Notes |
|---|---|
| **CEO** | Top of the human hierarchy |
| **System Administrator** | Engineer/moderator maintaining the system. Add/edit/delete rights for system upkeep. Not a business administrator. |
| **HR** | People operations. Outranks System Administrator in the People and Finance domains. |
| **Support Associate** | Sub-roles: Support Manager, Support L3, Support L2, Support L1 |
| **Manager** | Authority over projects and teams |
| **Team Lead** | Authority over teams |
| **Employee** | Sub-roles by position (Content Researcher, Video Editor, …). Sub-roles carry **no rank** — all employees are peers. |
| **Intern** | Employee base with reduced scope |
| **Mentor** | Read-only, no Employee base (see 2.1) |

**Board Member is not modelled.** A board member holds an ordinary account —
Employee (possibly senior) or Mentor. If it needs to appear on a profile card
it is a display label, never a permission.

**Client** is an account type outside the hierarchy entirely (see 3).

### 2.4 Role stacking — additive, not overriding

A person may hold several roles. Roles are **capability packs layered on top of
the Employee base**, and stacking is a **union**:

- Employee + HR → full Employee self-service **plus** HR powers
- Manager + HR → projects and teams **plus** payroll

Nothing is ever lost by gaining a role. Where two roles grant overlapping
access, the **more permissive outcome wins** — this is the only sense in which
anything "overrides".

Permissions are modelled as **discrete keys** (`clients.view`, `clients.edit`,
…) rather than levels, so union resolution is inherent and needs no
precedence logic.

### 2.5 Rank is per-domain, not a single ladder

HR outranks System Administrator in payroll, attendance and leave. System
Administrator outranks HR in system matters. A single `hierarchy_level`
integer cannot express this, so rank is stored **per domain**.

Domains: `people` · `finance` · `work` · `support` · `system`

Rank is used **only** for (a) approval routing and (b) answering "may I act on
this person". It never grants permissions — that is entirely `role_permissions`.

### 2.6 Universal invariants

These hold everywhere and are enforced centrally, not per-module:

1. **No self-action.** Nobody approves, edits or deletes their own records —
   leave, attendance, payroll, profile fields under HR control.
2. **No upward action.** Nobody acts on a person who outranks them in the
   relevant domain.
3. **Admin Panel has no operational authority** and no personal records.
4. **Mentor is read-only, always.**

---

## 3. Realms and routing

Three isolated realms:

| Realm | Route prefix | Who | Session cookie |
|---|---|---|---|
| Staff | `/` — `/dashboard`, `/projects`, … | All internal staff, incl. Mentor | `zx_staff` |
| Client | `/client/…` | Client accounts | `zx_client` |
| Admin | `/admin/…` | The single owner account | `zx_admin` |

**One shared login form** at `/login` serves all three. On successful
authentication the account type determines the destination: `/dashboard`,
`/client/dashboard`, or `/admin/dashboard`. The form never asks which kind of
user you are, and never reveals it before authentication.

### 3.1 Realm enforcement

**Redirects are convenience, not security.** Every request re-checks the
session's realm server-side, in middleware, before any data is read. A client
session hitting `/employees` is refused. A staff session hitting `/admin` is
refused. This is enforced at the route-group level so that new pages inherit
the guard automatically rather than needing one added by hand.

The `/admin` prefix is retained specifically so that a single middleware rule
covers the entire configuration surface, present and future.

### 3.2 Route map (Phase 0)

```
GET   /                        landing (public)
GET   /login                   login form (public)
POST  /login                   credential check → OTP step or session
GET   /login/verify            OTP entry
POST  /login/verify            OTP check → session issued
POST  /login/resend            resend OTP (rate limited)
POST  /logout                  destroys session, clears device trust
GET   /forgot-password         request form
POST  /forgot-password         issues reset token, sends mail
GET   /reset-password/{token}  reset form
POST  /reset-password          applies new password
GET   /support                 contact support (public)
POST  /theme                   persists light/dark preference
```

---

## 4. Authentication

### 4.1 Identifier

A **single login field** accepting either an email address or a user ID.
If the value contains `@` it is treated as an email, otherwise as a user ID.
Both `users.email` and `users.user_id` are unique.

### 4.2 Login flow

1. Rate-limit check (per identifier and per IP). Locked → generic lockout
   message with retry countdown.
2. Look up account. Verify password against the hash.
3. **Failure** → generic message: *"Invalid credentials."* Never distinguishes
   unknown identifier from wrong password — that difference is how an attacker
   enumerates staff. Attempt logged.
4. Account inactive/suspended → generic message; no detail leaked.
5. **Success**, device trusted and within window → session issued, redirect by
   realm.
6. **Success**, device untrusted → OTP issued and emailed; user sent to
   `/login/verify`. No session exists until the OTP is verified.

### 4.3 Two-factor (email OTP)

- Applies to **all account types**, including the owner's admin account.
- 6-digit numeric code, single use, **10-minute expiry**, delivered by
  PHPMailer over the MilesWeb SMTP account.
- Codes stored **hashed**, never in plaintext.
- Maximum 5 verification attempts per code, then invalidated.
- Resend is rate-limited and invalidates the previous code.
- On success the device is trusted for **7 days**.

**Trusted devices.** A random token stored in a cookie, hashed in the database
alongside user agent, IP and expiry. Within the 7-day window, sign-in requires
password only. Trust is cleared by explicit logout, by password change, and by
manual revocation.

**Accepted risk — no backup codes.** Per decision, recovery codes are not
issued. If mail delivery fails, sign-in is blocked. Ordinary staff are
recoverable by HR or System Administrator. **The owner's admin account has no
one above it**, so a manual database/CLI recovery procedure is documented in
§11.3 as the sole safety net.

### 4.4 Session model

| Property | Staff / Client | Admin |
|---|---|---|
| Absolute lifetime | 7 days, then forced sign-out | 7 days |
| Idle timeout | 12 hours | 30 minutes, then re-auth |
| Cookie flags | `HttpOnly`, `Secure`, `SameSite=Lax` | same |
| Regenerated | on login and on privilege change | same |

Explicit logout destroys the session, the remember-me token and the device
trust record.

### 4.5 Remember me

Available to **staff and clients only** — never the admin account. A rotating
token, 30-day expiry, stored hashed. Each use issues a fresh token and
invalidates the previous one; presentation of an already-used token revokes
the whole chain as a theft signal. Remember-me restores a session but **never
bypasses OTP** on an untrusted device.

### 4.6 Password reset

Self-service, database-backed. `POST /forgot-password` always returns the same
confirmation regardless of whether the account exists — no enumeration. A
single-use token is stored hashed with a **60-minute expiry** and emailed as a
link. Applying a reset invalidates the token, all sessions, all remember-me
tokens and all device trust for that user.

### 4.7 Password policy

Minimum 12 characters, checked against a common-password blocklist, hashed
with bcrypt (or Argon2id where available). No composition rules, no forced
rotation — both are known to produce weaker passwords in practice.

---

## 5. Authorisation (RBAC engine)

A single central service answers two questions:

- **`can(user, permission_key)`** → union of permissions across all the user's
  roles, plus the Employee base if the account is staff of kind `employee`.
- **`outranks(actor, target, domain)`** → compares the actor's highest rank in
  that domain against the target's highest rank in the same domain.

Every guarded action calls both, plus the self-action check from §2.6.

**Permission keys** follow `module.action` — `clients.view`, `clients.create`,
`leave.approve`, `salary.run`. They are seeded per module as each module is
built, and the Admin Panel maps them to roles.

**The Employee base is not a role.** It is granted implicitly to any staff
account with `staff_kind = 'employee'`, so it can never be accidentally
revoked by role edits.

---

## 6. Security model

Beyond §3.1 and §4:

- **CSRF tokens** on every state-changing form and request.
- **Ownership checks on every record fetch.** A client requesting invoice `47`
  must be verified as the owner of invoice `47`. This is the single most common
  real-world leak in applications of this shape and is enforced at the query
  layer, not in the view.
- **Parameterised queries throughout** — no string-built SQL.
- **Output escaping by default** in templates.
- **Audit log** for authentication events, permission changes, and all
  Admin Panel actions: actor, action, entity, before/after, IP, user agent,
  timestamp.
- **Security headers**: HSTS, `X-Content-Type-Options`, `X-Frame-Options`,
  Referrer-Policy, and a Content-Security-Policy.
- **No third-party CDN scripts in production.** All assets are served
  locally — see §9.2.
- Uploads validated by type and size, stored outside the web root, served
  through an authorising controller.

---

## 7. Theming

**Dark is the default.** A light toggle sits in the topbar and persists to
`users.theme_preference` (falling back to a cookie pre-login). The designer's
handover already defines complete token sets for both themes; those tokens are
the single source of colour truth and are extracted to one stylesheet consumed
by every module.

Deciding this in Phase 0 is deliberate: retrofitting dark mode across 18
modules later would be substantially more expensive than honouring the tokens
from day one.

---

## 8. Foundation data model

Column lists below are the essential fields; timestamps (`created_at`,
`updated_at`) are present on all tables and omitted for brevity.

**`users`** — `id`, `user_id` (unique), `email` (unique), `password_hash`,
`account_type` ENUM(`staff`,`client`,`admin`), `staff_kind`
ENUM(`employee`,`mentor`) nullable, `status`
ENUM(`active`,`inactive`,`suspended`), `theme_preference`, `last_login_at`,
`password_changed_at`

**`roles`** — `id`, `role_key` (unique), `role_name`, `description`,
`parent_role_id` nullable (for Support sub-roles), `is_active`

**`permissions`** — `id`, `permission_key` (unique), `permission_name`,
`module`, `description`

**`role_permissions`** — `role_id`, `permission_id`

**`user_roles`** — `user_id`, `role_id`, `assigned_by`, `assigned_at`

**`domains`** — `id`, `domain_key`, `domain_name`

**`role_domain_rank`** — `role_id`, `domain_id`, `rank` INT
(higher = more authority)

**`trusted_devices`** — `id`, `user_id`, `token_hash`, `user_agent`,
`ip_address`, `trusted_until`, `revoked_at`

**`remember_tokens`** — `id`, `user_id`, `token_hash`, `expires_at`,
`previous_token_hash`, `used_at`

**`two_factor_codes`** — `id`, `user_id`, `code_hash`, `expires_at`,
`consumed_at`, `attempts`, `ip_address`

**`password_resets`** — `id`, `user_id`, `token_hash`, `expires_at`,
`consumed_at`, `ip_address`

**`login_attempts`** — `id`, `identifier`, `user_id` nullable, `ip_address`,
`succeeded`, `failure_reason`

**`audit_log`** — `id`, `actor_user_id`, `actor_type`, `action`,
`entity_type`, `entity_id`, `before_json`, `after_json`, `ip_address`,
`user_agent`

**`notifications`** — `id`, `user_id`, `type`, `title`, `body`, `link`,
`read_at`

**`company_settings`** — `id`, `setting_key` (unique), `setting_value`,
`updated_by`

**Master data** (`departments`, `designations`, `positions`, `leave_types`,
`holidays`, `document_types`) — each `id`, `name`, `code`, `is_active`.
Detailed in the Master Data module spec.

---

## 9. Page specs

### 9.1 Landing — `GET /` (public)

Layout, visual treatment and responsive behaviour are taken **unchanged** from
`refference/Landing Page.html`. Only copy and CTA destinations change.

| Element | Content |
|---|---|
| Nav | Brand only — canonical Z SVG + "ZephryxLabs CRM" |
| Headline | **Build Stronger Relationships.** (accent word retained) |
| Lede | *The ZephryxLabs workspace — projects, teams, invoices and support in one place, for our people and the clients we build with.* |
| CTA primary | **Login** → `/login` |
| CTA secondary | **Contact Support** → `/support` |
| Info strip | `statMode = features`: *Real-time · Sync across teams* / *Role-based · Access & permissions* / *Secure · Encrypted & audited* |
| Hero | `heroStyle = abstract` |
| Theme | dark default, light toggle |

**Removed:** the `stats` variant (`50+ Happy Clients`, `30% More
Productivity`, `100% Data Secure`) — invented figures on a page real clients
read. **"GDPR Compliant by design"** is removed as an unaudited compliance
claim made in the company's name.

An authenticated visitor is redirected to their realm dashboard.

### 9.2 Login — `GET /login` (public)

Visual design taken **unchanged** from `refference/Login Page.html`: split
layout, green gradient panel with orbs, wave strokes, glass card, "Bold.
Creative. Innovative.", hero figure and lightning badge; white form panel with
Z monogram, "Welcome Back!", pill fields and centred submit. Mobile stacks to
the rounded sheet as designed.

| Element | Decision |
|---|---|
| Identifier field | Label **"Email or User ID"** (design says "Username") |
| Password field | Retained, with eye toggle |
| Remember me | Shown |
| Forgot password | Shown → `/forgot-password` |
| Footer | Shown, reworded: *"Need access? Contact your administrator."* |
| Hero | `heroStyle = abstract` |
| Theme | dark default, light toggle |

**Stripped before production:**

- The React / ReactDOM / Babel `unpkg.com` script tags and the tweaks panel.
  Runtime third-party JavaScript on the page where passwords are typed is a
  supply-chain exposure; the tweaks panel is a design-time tool and its chosen
  values are baked in as the decisions recorded above.
- The simulated submit handler (`preventDefault` + timer), replaced by a real
  `POST /login`.

**States to build**, in the existing visual language: inline field validation,
generic failure banner, lockout notice with countdown, session-expired notice,
disabled-account notice, OTP entry screen, and OTP resend cooldown.

---

## 10. Stack and deployment

**Database: MySQL / MariaDB.** Ships with cPanel, co-located with the
application, no network hop per query. Supabase was considered and rejected:
on shared hosting it places an internet round-trip in front of every query on
a CRUD-heavy dashboard, many shared hosts block outbound database
connections, and its row-level security would duplicate and conflict with the
RBAC engine in §5, splitting the security model across two systems.

**Framework: pending one check.** Preference is **Laravel on PHP 8.2+** —
sessions, CSRF, middleware guards, authorisation policies, migrations and an
ORM arrive tested, and those are exactly the components that leak when
hand-written. Requires Composer, CLI access, and a document root pointed at
`/public`.

If MilesWeb's Business plan provides neither SSH/Terminal nor Composer, the
fallback is **CodeIgniter 4** — built for shared hosting, deploys by upload,
still providing routing, CSRF, filters and a query builder. **Vanilla PHP is
rejected** given the security requirements in §6.

This choice affects project layout and the implementation plan. It does not
affect anything specified above.

**Mail:** PHPMailer over the MilesWeb SMTP account. SPF and DKIM must be
configured or OTP messages will be filtered as spam — since OTP gates all
sign-in, this is a launch blocker, not a nicety.

**Cron:** cPanel cron jobs, needed later for attendance auto-close, payroll
runs and invoice reminders.

---

## 11. Operational notes

### 11.1 Environment

Secrets (database credentials, SMTP credentials, application key) live outside
the web root and are never committed.

### 11.2 Backups

Database backups scheduled via cPanel. Payroll and invoice data make this
non-optional.

### 11.3 Owner account recovery

Because there is exactly one admin account, no role above it, and no backup
codes, a documented recovery procedure is required: direct database access via
phpMyAdmin to clear the trusted-device requirement or reset the password hash.
This procedure is written up during Phase 0 implementation and stored outside
the application.

---

## 12. Module roadmap

**Phase 0 — Foundation** (this spec): auth and realms, RBAC engine, app shell,
master data, audit log, notifications, theming, Landing, Login.

**Then, in dependency order:**

Employees → Clients → Teams → Projects → Tasks → Attendance → Leave → Salary →
Tickets → Invoices → Meetings → Announcements → My Profile → Reports →
**Dashboard**

Rationale: Employees is the spine every other module references. Teams require
employees; Projects require teams and clients; Tasks require projects. Salary
requires Attendance and Leave to be complete or payroll cannot calculate.
Invoices require Clients and Projects. Reports requires nearly everything.
Dashboard is last because it is assembled from other modules — building it
earlier means guessing at data that does not yet exist.

**Deferred to v2:** Leads and Calendar ship as navigation entries returning
404, so the navigation shape stays stable and adding them later reshuffles
nothing users have learned.

**Client Portal** (`/client`) follows the internal side.

**Settings** lives inside the Admin Panel only, and contains Access Control,
Master Data, company configuration and the audit log.

### 12.1 Per-module UI pattern

Most modules have two faces — a personal one and a managing one (My Attendance
vs Attendance Management). Because roles stack additively, a person holding
Employee + HR needs both. The presentation pattern is supplied per module by
the designer's existing base design rather than being decided globally here.

---

## 13. Open items

### Resolved (2026-08-26)

1. **cPanel capability check** — confirmed: Terminal/SSH and Composer are
   available. **Laravel** is the framework; the application is scaffolded on
   Laravel 13 / PHP 8.3.
2. **Canonical Z SVG** — supplied as two authored marks, `z-black.svg` for
   light surfaces and `z-white.svg` for dark. Cropped to their path bounding
   box and installed at `public/assets/brand/`; swapped by `[data-theme]`.
3. **Support destination** — a **`mailto:` link**, not a form. A public
   unauthenticated write endpoint on the landing page was not worth the spam
   and abuse surface for a six-person company. The address is
   `ZEPHRYX_SUPPORT_EMAIL` in the environment (`config/zephryx.php`).

### Still open

4. **Support mailbox address** — `support@zephryxlabs.com` is a placeholder in
   `.env.example`. Confirm the real mailbox before deploying.
5. **Idle timeouts** — 12 hours staff/client, 30 minutes admin, stated as an
   assumption in §4.4 and awaiting confirmation. `SESSION_LIFETIME=720`
   encodes the staff/client value; the admin window needs its own middleware
   when that realm is built.

### Deviations from the handover, recorded

These are changes to the landing page beyond the copy and CTA edits in §9.1,
made during implementation:

- **The `@fonts` Blade directive is not used.** It emits an inline `<style>`
  block for the `@font-face` rules, which would force `'unsafe-inline'` into
  the `style-src` CSP. Plus Jakarta Sans is imported from `@fontsource` in
  `resources/css/app.css` instead, so the faces are bundled into the same
  linked stylesheet. The page renders zero inline styles and zero inline
  scripts, and a test enforces that.
- **The hero's floating cards were relabelled.** "Sales Overview +25.4%",
  "New Lead Added" and "Deal Closed" became "Project Activity / This quarter",
  "Invoice sent" and "Task completed" — this is not a sales CRM, Leads is
  deferred to v2 (§12), and an invented growth figure is the same problem
  §9.1 removed from the stat strip.
- **The feature strip was resized.** The handover sized it around the `stats`
  values ("50+", "30%"); the words §9.1 chose instead are longer and
  overflowed their dividers. Icon 48→44px, value 22→18px, and the text cell
  given `min-width: 0` so it wraps rather than spilling. Below 1100px each
  cell stacks its icon above the text.
- **A theme toggle was added to the topbar.** §7 requires one; the handover's
  landing nav carries the brand only.

On the login page (§9.2):

- **The Z monogram is the authored asset**, not the handover's inline
  `currentColor` path — the two marks are the resolved canonical asset above.
- **The form panel gained a floating theme toggle.** §9.2 requires a light
  toggle and the auth pages have no topbar to hang one from.
- **Feedback tokens were added.** The handover defines no error, warning or
  success colour, and §9.2 requires a failure banner, a lockout notice, a
  session-expired notice and a disabled-account notice. `--danger`,
  `--warning` and `--info` (plus soft/line variants for both themes) now sit
  in `tokens.css` beside the brand green, and drive a reusable `.notice`
  component rather than login-only styling.
- **The OTP step is six single-character boxes** in a `fieldset`, not one
  input. Focus advance, backspace, arrow keys and paste-distribution are
  progressive enhancement; the boxes work as plain inputs without JavaScript.
- **The submit button is marked busy, not disabled.** A disabled submit is not
  submitted with the form, and disabling it on click can drop the request.
- **Resend posts from a separate form** outside the verify form, so the
  entered code is never carried along with a resend request.
- **`?preview=lockout`** renders the lockout banner for design review. It is
  gated to local + debug and returns nothing anywhere else; a test pins that.

On the password reset pages (§4.6, §4.7):

- **The common-password blocklist is local**, not HaveIBeenPwned. Laravel's
  `uncompromised()` rule calls that API and treats an unreachable API as a
  pass, so on shared hosting with blocked outbound traffic the check would
  silently do nothing. `App\Rules\NotACommonPassword` reads
  `resources/security/common-passwords.txt` and always runs. It normalises the
  candidate — case, leet substitutions, trailing digits and punctuation — so
  one entry catches `Password123!`, `p@ssw0rd` and `PASSWORD`. **The file must
  be deployed with the application**; the rule reports an error rather than
  passing silently if it is missing. Add company-specific guesses to it as
  they come up.
- **The reset token route is constrained** to `[A-Za-z0-9._-]{1,128}`, so
  anything unexpected 404s at the router instead of reaching a view that
  echoes it into a hidden input.
