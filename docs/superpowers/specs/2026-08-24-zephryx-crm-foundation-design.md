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

4. ~~**Support mailbox address**~~ — **resolved 2026-08-27**: the mailbox is
   `admin@zephryxlabs.in`. `MAIL_FROM_ADDRESS` was moved to the same domain
   (`no-reply@zephryxlabs.in`) on the assumption that `.in` is the company's
   mail domain — **worth confirming**, because SPF and DKIM must be configured
   for whichever domain sends, and OTP delivery gates every sign-in (§10).
   All four surfaces that link to support now go through
   `App\Support\SupportContact`, so the address is one edit.
5. **Currency conversion** — deliberately absent. Money totals across
   currencies are shown as separate subtotals (see Invoices below). If the owner
   ever wants a single consolidated figure, the exchange rate must be recorded
   on each invoice at its issue date; there is no rate feed and inventing one at
   display time would be worse than not having the figure.
6. **Idle timeouts** — 12 hours staff/client, 30 minutes admin, stated as an
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
- **The hero composition is hidden on phones.** Decided 2026-08-26: the public
  pages must fit the viewport without scrolling. The floating-card visual costs
  ~360px — more than everything the page actually says — and is already
  `aria-hidden` ornament, so below 760px it gives way to the copy, the calls to
  action and the feature strip. The tagline card (the handover's own
  mobile-specific element) stays, and is itself dropped below 720px of height.

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
- **`?preview=…`** renders the states only the backend can produce, for design
  review: `lockout`, `expired`, `disabled` on `/login`, and `cooldown` on
  `/login/verify`. Gated to local + debug and inert everywhere else — a fake
  "Session expired" or "Cannot sign in" on a live sign-in page is a phishing
  aid, so tests pin that each one does nothing outside local.
- **One banner, decided in the controller.** `LoginController::notice()` is the
  single place that chooses which form-level state is shown. A failed attempt
  outranks everything else: it describes what the user just did, so showing
  "session expired" over it would be actively misleading.

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

### App shell (decided 2026-08-26)

The handover (`refference/shell.css`, `refference/shell.js`) is drawn for
**Superadmin — every entry visible**. That is a *superset*, not a role: what
any one person sees is decided in PHP at render time. The staff realm uses this
shell; `/client` and `/admin` get their own.

- **Navigation is data, not markup.** `config/navigation.php` lists the whole
  roadmap in order, each entry carrying a `permission` key.
  `App\Support\Navigation` drops entries the viewer lacks, and entries whose
  route does not exist yet — so the file can name modules that have not shipped
  without breaking the shell.
- **Hiding an entry is a courtesy, not the access control.** Every route is
  guarded too (§3.1). A filtered nav without guarded routes is security
  theatre; guarded routes without a filtered nav teach people to expect 403s.
- **`PermissiveGate` throws in production.** The RBAC engine (§5) does not
  exist yet, so the placeholder gate allows everything — and the failure mode of
  forgetting to swap it is "every user sees every module". It fails loudly at
  boot instead. Swap the binding in `AppServiceProvider` when §5 lands.
- **Settings stays in the navigation**, gated on `settings.view` and pointing
  at `admin.settings`. §12 is unchanged — the *pages* still live in the Admin
  Panel; only the entry is in the staff nav, and it renders for nobody until
  that route exists.
- **Leads and Calendar keep their entries and 404**, exactly as §12 describes.
- **Every module has a placeholder route from the start**, so the navigation's
  shape never shifts as modules land one at a time. Each module replaces its
  own.
- **Sign-out is `POST /logout`.** The handover linked to a page; as a GET, any
  `<img src="/logout">` on any page would sign people out.
- **Notifications are server-rendered** from `notifications` (§8). The handover
  populated the panel by cloning the host page's "Recent Activity" list in
  JavaScript, which made it a mirror of whatever was on screen rather than of
  the viewer's own unread items, and left it empty on pages with no such list.
- **Hover-to-open is mouse-only**, behind `(hover: hover) and (pointer: fine)`.
  Bound unconditionally it makes the first tap on a phone open-and-close.
- **Global search renders disabled.** Cross-module search must run every result
  through the permission and ownership checks in §6 or it becomes the easiest
  data-leak surface in the application, so it waits for the backend.
- **Sidebar collapse and density persist** in cookies, resolved server-side onto
  `<html>` for the same reason the theme is — read in JavaScript, every
  navigation would show the default for a frame and then jump.

**Token and class reconciliation.** `refference/shell.css` carried its own
`:root` block with values slightly different from the ones already shipped
(`--page`, `--line`, radii). Adopting it wholesale would have silently shifted
the landing and auth pages, so it is folded into `tokens.css` instead (§7). Two
radius scales are kept deliberately: the public pages are spacious
(`--radius-card: 22px`), the app is dense (`--r-card: 14px`). The handover's
`--red/--amber/--blue` map onto the existing feedback tokens; `--accent-*` are
added for categorical pills, which must never reuse feedback colours — a blue
"In progress" pill beside a blue "session expired" banner teaches people that
colour means nothing.

The shell also collided with existing class names. `.card` and `.topbar` are
now the app shell's; the landing page's became `.hero-card*` and
`.public-topbar`.

### Clients (decided 2026-08-26)

**Build order follows the sidebar, not §12's dependency order** — the owner
asked for it so progress is easy to track against the navigation. Clients is
safe to take first: it depends on nothing that must exist. Dashboard stays last
regardless, since it is assembled from other modules.

- **Every figure comes from data.** The handover hardcoded `58+`, `42`,
  `₹1,85,000` and `12` tickets. Three of the four KPIs are sourced from modules
  that do not exist (Invoices, Tickets) and read zero until they land.
  `App\Support\Demo\DemoClients` supplies sample rows for design review and is
  inert outside local + debug, like the login `?preview=` states.
- **Search, status filter and pagination are real** and operate on whatever
  collection they are handed, so swapping the source for an Eloquent query is a
  one-method change. They live in the URL as a GET form, so a filtered list can
  be bookmarked and shared, and paging preserves the filter.
- **The table becomes cards below 760px.** The handover kept seven columns and
  scrolled sideways at `min-width: 680px`, which on a phone hides four columns
  behind a swipe *per row*. Roles are stated explicitly in the markup because
  `display: block` drops a table's implicit ARIA semantics.
- **"View All" became "Clear filters"**, and only appears when a filter is on —
  beside a search box on the clients list it had nothing to view-all *to*.
- **Identity tints are derived from the name**, not hand-assigned. The handover
  numbered `av-1`..`av-7` against exactly seven sample rows; the eighth client
  would have had none.
- **Rows are not clickable; the name is a link.** A `<tr>` cannot be focused or
  opened from the keyboard, so `cursor: pointer` on it promised something it
  could not deliver.
- **Quick actions are links, not buttons** — each one navigates.
- **Export renders disabled.** It streams a whole table out of the building, so
  it needs its own permission and an audit entry (§6) before it does anything.

**Class reconciliation.** `refference/clients.css` redefined `.btn`,
`.btn-primary` and `.btn-outline`, which the landing page already used at a
larger size. The public pages' call-to-action buttons are now `.cta*`, and
`.btn*` belongs to the app, where every module will use it. The handover's
`clients.css` was mostly *not* Clients-specific — buttons, search field, table,
pagination, KPI tiles, rail cards and quick-action tiles are in
`components/ui.css`; only the grid, identity tints and meeting rows are in
`pages/clients.css`.

### Employees (decided 2026-08-26)

Structurally parallel to Clients, and mostly built from the shared components
that module produced. New pieces: the stacked name/staff-ID cell, the
department donut, and the person rows used by the rail lists.

- **The legend uses classes, not `style="--dot:#15A848"`.** The handover set
  each department's colour with an inline style attribute, which our
  Content-Security-Policy blocks outright (§6) — the legend would have rendered
  colourless. Departments are master data, so the palette cycles by position
  rather than being keyed to a fixed list of names; the ninth department still
  gets a colour.
- **The donut's arcs are computed from the data.** The handover's SVG had fixed
  segments that would not have matched real headcounts. A unit test asserts the
  segments tile the full circle and each starts where the last ended.
- **The donut is `aria-hidden` and the legend is a real list.** A chart that is
  the only source of its own numbers is unreadable to a screen reader and to
  anyone who cannot separate the colours.
- **Birthdays render empty.** Date of birth is a field this module does not
  have yet; a wrong birthday is worse than an absent one.
- **"20% vs last month" is dropped, not zeroed.** Nothing records last month's
  headcount, so the comparison cannot exist yet in any form.
- **`white-space: nowrap` is on the name column only.** The handover put it on
  every cell, which is what forced the horizontal scroll. The identity column
  is the one people scan, so it holds its line; everything else may wrap, and
  the email truncates with the full address in its `title` and `href`.
- **Import and Export render disabled.** Import creates accounts and needs
  per-row validation, a dry-run and an audit entry; export of the staff list
  carries personal data. Both need their own permission first.

Avatars, tints and person rows moved from `pages/clients.css` into
`components/ui.css` — they belong to every list that shows a name.

### Teams (decided 2026-08-27)

Three pages: `/teams` (managing), `/teams/mine` (personal) and `/teams/{team}`
(overview) — the two-faced pattern §12.1 describes, kept as separate routes
rather than one page with a filter, because the personal face needs no
`teams.view`.

**The handover's three stylesheets are not used.** `team.css`,
`team-overview.css` and `my-teams.css` came to ~37KB that between them
re-derived the table, KPI tiles, search field, filter buttons, pagination and
rail cards under new names — `.tm-table`, `.to-table`, `.tm-kpi`, `.to-kpi`,
`.tm-search`, `.tm-foot`, `.rr-card`. Adopting them would have left four
parallel table implementations. They map onto `components/ui.css`; only the
team cell, lead cell, detail header and grouping row are in `pages/teams.css`.

- **The donut moved to `components/chart.css`** when Teams needed the same one
  Employees has. One implementation, so the two cannot drift.
- **Row clicks, the back button and the hidden activity list are gone.** The
  handover used `onclick` on `<tr>`, `onclick="history.back()"`,
  `style="display:none"` and inline `<style>` blocks — all four are blocked by
  our CSP, and the row click had the same keyboard problem §Clients fixed.
  `history.back()` also leaves the application entirely when a page is opened
  in a fresh tab, so the detail page uses a real link.
- **My Teams has no back button.** It is a destination reached from the
  navigation, not a step in a flow.
- **Member tabs are links with their own URLs**, so a filtered view is
  bookmarkable and the back button works. "By department" groups rather than
  filters — every member is still listed, with a heading when the department
  changes.
- **"Average tenure" and "Active since" are computed** from the member and team
  records. The handover had "3.2 months" and "4 months" as fixed text beside a
  table that would have contradicted them.
- **A team with no lead says so** rather than showing an empty cell.
- **The team chip drops the word "Team"** — it is on every one of them and
  distinguishes nothing, so "Web Development Team" is WD, not WT.
- **The overview drops the handover's "Status" tile.** The status is already a
  pill beside the team's name; a tile repeating it is a tile wasted.

**Four layout bugs found and fixed here, three of them pre-existing.** All were
the same mistake: a track or flex item keeping its automatic minimum, so a wide
child pushed the whole page sideways instead of shrinking or scrolling.

- `.teams-grid`, `.emp-grid`, `.cl-grid` used `1fr` in their single-column
  media query; they now use `minmax(0, 1fr)`.
- `.topbar` used `auto 1fr auto`, so the search box pushed the navigation off
  screen below about 900px.
- `.card-body-table` gained `overflow-x: auto` **and** `min-width: 0` — without
  the second, the scroll container itself overflowed and the page scrolled
  anyway.
- `.hd-actions .btn` used `flex: 1`, which keeps a minimum of the button's own
  label, so a third action overflowed rather than wrapping.

Tables now scroll inside their card in the band between the stacking
breakpoint and a comfortable desktop; the page itself never scrolls sideways.

### Projects (decided 2026-08-27)

Four pages: `/projects` (managing), `/projects/mine` (personal),
`/projects/updates` (end-of-day submissions) and `/projects/{project}`
(overview). As with Teams, the handover's four stylesheets largely re-derived
components that already exist; progress bars, priority chips and deadline cells
were genuinely new and went into `components/ui.css`, since Tasks will want all
three.

- **Progress bars use the native `<progress>` element.** The handover sized a
  `<div>` with `style="width:75%"`, which our Content-Security-Policy blocks
  outright (§6) — every bar would have rendered empty. `<progress>` takes an
  attribute rather than a style, and announces itself to a screen reader
  without any ARIA of our own.
- **Deadlines are stored as an offset from today** in the sample data, not as
  fixed dates. The handover's were all in 2024, which by now reads as
  universally overdue and makes the deadline states impossible to review.
- **A delivered project past its date is not overdue.** It landed late, which
  is history, not an outstanding risk; the handover coloured every past date
  red regardless of status. The Overdue tile counts only unfinished work.
- **Countdowns are coloured only when they are a problem** — within a week, or
  already passed.
- **Progress under 20% is tinted amber.** A bar that is a fifth full reads as
  an achievement; with a deadline running it is closer to a warning.
- **The status control is a disabled `<select>`.** The handover had a custom
  listbox that changed status in place — that is a write, and there is no
  backend for it, so the control keeps its shape and does not pretend.
- **My Projects has no back button**, for the same reason My Teams does not.

One shared fix: `.kpi-row`'s minimum track dropped from 230px to 200px, because
Projects has five tiles and the fifth wrapped to a row of its own on a normal
desktop. And `.cell-actions` gained a `cell-actions-wide` variant — the mobile
card layout positions row actions absolutely for an icon button, and a labelled
button ("Submit EOD") landed on top of the row's name.

### Tasks (decided 2026-08-27)

Four pages: `/tasks` (managing), `/tasks/mine` (personal), `/tasks/team` (the
team lead's queue) and `/tasks/{task}`. The handover's four stylesheets —
`tasks.css` alone was 23KB — again re-derived the table, KPI tiles, filters,
pagination and rail cards under new prefixes (`.tk-*`, `.tt-*`, `.to-*`).

Four genuinely new pieces went into `components/`, because Attendance, Leave
and Tickets will all want them: the **due-date cell** (date above a countdown),
the **timeline**, the **field grid** and **attachment cards**.

- **`/tasks/team` holds only unassigned tasks.** The handover drew it as a
  second copy of the full list; the point of a lead's queue is deciding who
  picks each one up, so "nobody assigned yet" is the normal case here, and the
  count leads the page.
- **The assignee column distinguishes a team from a person by shape** — a
  square chip for a team, a round avatar for an individual — so scanning the
  column tells you which without reading it.
- **KPI percentages are computed.** The handover's sub-labels were written in
  and did not add up to the numbers above them.
- **A completed task past its date is not overdue**, matching the rule Projects
  uses. Priority is delegated to `ProjectPresenter` outright, so "High" cannot
  come to mean two different things in two modules.
- **The timeline reflects what actually happened** — a pending task shows only
  "created", a completed one carries its whole history. The handover showed the
  same four fixed events on every task.
- **Each list filters itself.** All three list faces post their filter form to
  their own route rather than bouncing to the managing view.
- **"Mark Completed" is disabled.** It is a write, and §2.6 applies — only the
  assignee, their lead or a manager may do it.

`.kpi-row-compact` was added for six tiles: at the default size they wrapped
five-plus-one, which reads as an afterthought rather than a set.

### Tickets (decided 2026-08-27)

The first module where **two audiences read the same record**. Internal staff
discussion and client-facing replies live on one thread, and the whole design
follows from making the difference between them impossible to miss.

Six pages: `/tickets` (managing, with a tab per queue), `/tickets/mine` (raised
by me), `/tickets/assigned` (assigned to me), `/tickets/projects` (raised
against projects I work on), `/tickets/escalated` (the review queue) and
`/tickets/{ticket}`.

**The visibility contract.** This is the part that must not be softened later:

- **There is no method that returns "the comments".** `DemoTickets::commentsFor()`
  takes the audience as a *required* argument, so forgetting to filter is a
  syntax error rather than a client reading an internal note. The staff realm
  passes `AUDIENCE_STAFF`; when `/client` is built it passes `AUDIENCE_CLIENT`
  and nothing else changes. Do not add an audience-free convenience method.
- **`TicketPresenter::isInternal()` fails towards secrecy.** Anything not
  explicitly `'public'` — missing key, null, `'publik'`, `'PUBLIC'`, `' public '`
  — is treated as internal. A unit test pins every one of those cases.
- **Every comment states its audience in words**, not only in colour. The amber
  fill and left rail catch the eye; "Internal only" / "Client can see" is there
  because colour alone fails for anyone who cannot separate the two, and this is
  not a distinction to get wrong.
- **The composer has two submit buttons, not one button and a toggle.** A toggle
  has a default, and a default is a thing to get wrong on the one occasion it
  matters. "Reply to client" and "Internal note" are two labelled verbs, and the
  internal button carries its own colour so the pair never reads as primary and
  secondary. The visibility travels as a submitted `name="visibility"` value
  from day one, so the backend cannot inherit an implicit default.
- **An internal ticket offers no client reply at all** — there is no client on
  it, so the button would be a lie about who is reading.
- **A client ticket carries a standing warning in the rail**, naming the client.
  Not a substitute for the server-side filter, but the person typing is the one
  choosing which button to press.

**Ownership is the other obligation.** Every client ticket carries a client, and
the client realm must scope every read to the signed-in client's own tickets and
verify ownership on the detail route (§6). A client opening another company's
ticket is the worst failure this module can have; it is recorded at the top of
both `DemoTickets` and `TicketController` so it is read before either is changed.

Other decisions:

- **Escalation is a flat queue**, not an L1→L2→L3 ladder. With six staff most
  rungs would be permanently empty, and a ladder mostly adds places for a ticket
  to sit. One shared review queue that whoever holds the triage permission works
  through. A test asserts no ticket carries an escalation level, so the ladder
  cannot creep back in unnoticed.
- **Triage lives on the ticket, not on a "reviewer" page.** The handover put it
  on a separate screen, which meant a reviewer had to know which of four URLs to
  open. The panel appears on the ticket when the ticket needs it — unassigned,
  or escalated back for someone to route.
- **Untriaged fields read "Not set", never blank.** A blank cell hides the fact
  that something still has to be done; priority, category and department are all
  set *by* triage, so on a fresh ticket they are legitimately empty.
- **All five lists were built**, rather than one list with a filter. Each one is
  a different question ("what did I raise" is not "what am I working on"), they
  will carry different permissions, and only the managing view needs
  `tickets.view`.
- **All six KPI figures come from data.** The handover hardcoded 152 / 12 / 8 /
  54 / 31.
- **Attachments are treated as untrusted input.** Files on a client ticket are
  uploaded from outside the company: §6 requires validation by type and size,
  storage outside the web root and service through an authorising controller —
  never a direct link, never rendered inline. Recorded in the partial itself.
- **Posting and assigning render disabled**, with their routes named so the
  forms are real forms with CSRF tokens. Assigning is also subject to §2.6 —
  nobody may route a ticket to someone who outranks them in `support`.

`components/thread.css` is new and shared: the comment thread and its composer
will be wanted by any module that grows a discussion.

One layout fix came out of this module: **`.hd-actions` was `flex-shrink: 0`**,
so a row of five actions kept its full max-content width and pushed the page
sideways between the stacking breakpoint and a comfortable desktop instead of
folding onto a second line. It now wraps *and* shrinks.

### Invoices (decided 2026-08-27)

The first module handling money, and the first where being wrong costs
something measurable. Three pages: the list (`/invoices`), the invoice document
(`/invoices/{invoice}`) and the create form (`/invoices/create`). Only the list
was handed over; the other two were designed here.

**Four owner decisions taken 2026-08-27:**

1. **No GST.** ZephryxLabs is not registered, so invoices carry no tax block.
   The totals block is a definition list rather than fixed rows precisely so
   the tax rows can drop in later without it being rebuilt.
2. **Multi-currency** — INR plus USD and others.
3. **Invoices can be part-paid**, with payments recorded against them.
4. **Full module** built from the one reference page.

**Money is an integer, never a float.** `0.1 + 0.2 === 0.30000000000000004` in
PHP as everywhere else; sum a few hundred lines that way and a paisa goes
missing, and somebody spends an afternoon reconciling it against a bank
statement. `App\Support\Money` holds an integer count of minor units — paise,
cents — beside a currency, and only becomes a decimal when printed. The column,
when it lands, is a BIGINT of minor units: never DECIMAL, never FLOAT.

**A mixed-currency total is not a number.** ₹75,000 + $2,000 has no sum without
an exchange rate, and this system has no rate source — no feed, no stored rate,
nobody entering one. `App\Support\MoneyBag` keeps one subtotal per currency and
the KPI tiles show "₹3,77,000 / plus $2,150" rather than converting. Two honest
lines beat one confident fiction; a converted figure would look authoritative,
be wrong by however much the rate has moved, and eventually be quoted in a
meeting. `MoneyBag` deliberately has no `total()` and no `convertTo()`, and a
test asserts both stay absent. Adding real conversion means storing the rate on
each invoice at its issue date — a data-model decision, not a display one.

**Rupees use Indian digit grouping** — 1,20,000 and 1,00,00,000, not 120,000.
Western grouping on an invoice sent to an Indian client reads as a mistake.

**Status is derived, never stored.** There is no status column and no "mark as
paid" control anywhere in the module; an invoice becomes paid by recording the
payment that makes it paid. The order resolves the awkward cases: cancelled
outranks everything (a cancelled invoice past its date is not overdue — nobody
owes it), paid outranks overdue (money that arrived late is history, matching
the rule Projects and Tasks use for late-but-finished work), and overdue
outranks partly-paid (a half-paid invoice three weeks late is a collection
problem, and "Partly paid" buries that). A draft is never overdue, because the
client has not seen it.

**Numbers are gapless and invoices are never deleted.** There is no DELETE route
and no `invoices.destroy`, and a test walks the route table to keep it that way.
A gap in the sequence is the first thing an auditor asks about and "we deleted
it" is the wrong answer in every jurisdiction. Withdrawal is a cancellation that
keeps the record and its number — INV-2026-004 in the sample set is cancelled
and still in the sequence. Cancelling is not offered once any money has been
received: that case needs a credit note, which is its own record. The number is
shown on the create form but has no `name` and cannot be typed — it is issued by
the database inside the transaction that writes the invoice, or two simultaneous
creates collide.

Other decisions:

- **The document is laid out as the printed invoice**, not as another dashboard
  panel, because it is the one screen where the staff view and the client view
  should show the same thing. Divergence there is what gets argued about on a
  call.
- **Every amount carries its currency symbol on every row.** In a mixed list a
  bare "2,000" under a header reading "Amount" is genuinely ambiguous, and the
  ambiguity is worth about eighty times the difference.
- **Currency is chosen once per invoice and fixed.** Per-line currency is not a
  feature; it is a total that cannot be computed.
- **The Amount column on the create form is not an input.** It is qty × unit
  price, computed on save — letting somebody type an amount that disagrees with
  the two figures beside it is how an invoice ends up self-contradicting.
- **Saving and sending are two steps.** An invoice sent by accident has to be
  chased, apologised for and cancelled, and the cancellation stays in the
  sequence forever.
- **The donut takes status tones, not the shared cycling palette.** Employees
  and Teams break down by department — master data with no inherent meaning, so
  any colour will do. Invoice statuses already mean something in the pills
  beside them; colouring "Overdue" indigo because it sorted third is exactly
  what §7 says categorical accents exist to avoid.
- **Export and import render disabled.** Export streams every amount ever billed
  out of the building; import creates financial records. Both need their own
  permission and an audit entry (§6).
- **Ownership** is §6's canonical case, quoted there against invoices
  specifically. The client realm scopes every read to the signed-in client and
  re-checks on the detail route — a client reading another company's invoice
  reveals what we charge them.

**Errors found in the handover, none copied:** its counts did not add up
(16 paid + 8 pending + 4 overdue is the whole 28, leaving no room for the
"Partial" row its own table showed); "Pending" and "Overdue" were both red on
the same screen; the revenue tile carried an invented "18.6%" month-on-month
delta with nothing recording last month; the donut and legend used inline
`style` attributes our CSP blocks outright, so they would have rendered
colourless; and `.inv-id` was an `<a>` with `cursor: pointer` and no `href`,
so it was not keyboard-reachable.

**Five components were promoted out of page stylesheets** rather than
re-derived: `.section-hd` and `.prose` (from tasks.css), `.field`→`.form-field`
and `.name-cell` (from tickets.css), plus new `.money`/`.money-cell`,
`.form-grid` and `.back-link`. Each keeps its original class as a co-selector so
the module that introduced it needed no edit. Money cells use tabular figures —
proportional digits make a column of amounts impossible to scan and can make two
different amounts look the same width.

One name collision found doing it: `.field` and `.field-lbl` were already taken
by the auth pages and by `.field-grid` respectively, so the promoted form
control is `.form-field` / `.form-field-lbl`.

### Salary (decided 2026-08-27)

The most sensitive module in the application. It holds what every person earns,
their bank account, their PAN and their Aadhaar. Four pages: payroll
(`/salary`), a person's own pay (`/salary/mine`), one month's record
(`/salary/{employee}/{period}`, and `/salary/payslip/{period}` for your own),
and the payment confirmation.

> **Rewritten the same day.** It was first built as a payroll engine — salary
> structures, an earnings/deductions breakdown, a derived CTC. The owner then
> established that **this application must not calculate pay at all**: it is
> worked out in Excel today and will come from payroll software over an API
> later. That is the better boundary, and the section below describes what
> replaced it. The abandoned design is recorded only because its absence is now
> load-bearing.

**THIS APPLICATION DOES NOT CALCULATE PAYROLL.** A salary record holds three
things and no more: the **payslip** produced by whatever worked the pay out, the
**net** figure that document states, and **when the transfer was made**. There
is no basic/HRA split, no deductions engine and no derived CTC, because holding
our own version of a calculation somebody else owns gives the company two
sources of truth for what it pays people — and they disagree eventually, in
front of the employee. Tests assert `totalEarnings`, `totalDeductions` and
`annualCtc` stay absent from the presenter; if they come back, the question is
which system owns the figures.

The net **is** stored, typed once alongside the payslip, because a payroll page
that cannot say what was paid out this month is not much of a payroll page. It
is one number, taken from the document beside it.

**Three owner decisions taken 2026-08-27:**

1. **Aadhaar is kept**, against the recommendation to drop it. The
   recommendation stood on the fact that payroll runs on PAN and bank details,
   and that holding Aadhaar brings the Aadhaar Act 2016 and the UIDAI
   regulations with it — consent and purpose limitation, encryption, breach
   reporting, and penalties that reach private companies. **The owner elected to
   keep it; it is recorded here as their decision.** Since it is held, it is
   held properly: masked to the last four digits per UIDAI's own rule, shown to
   nobody but the person themselves, and encrypted at rest when the column
   lands.
2. **Adding a payslip captures the file and the net figure**, not a breakdown.
3. **Marking people paid in bulk goes through a confirmation** that names them.

**The two states, and how they are reached.** A record moves *No payslip →
Awaiting payment → Paid*, and status is derived from those two facts rather than
being a column anybody sets. **Nobody can be marked paid without a payslip on
file** — there would be nothing to check the amount against and nothing to give
them if they ask what they were paid for. A row that cannot be paid gets **no
checkbox at all**, not a disabled one: a greyed box invites the click anyway.

**Marking paid is a two-step flow.** Ticking rows and pressing the button posts
to a confirmation page that names every person, states the total, and asks.
Marking twelve people paid by mis-click is hard to notice and awkward to undo,
so the destructive step is always the second one. The confirmation is a **POST
that renders** rather than a GET, so a dozen employee ids never reach a URL that
gets bookmarked, shared or written to an access log. Rows that cannot be paid
are dropped from it and the gap is stated — "1 row left out" — rather than the
list silently shrinking. A single record needs no confirmation page: its button
already names who and for how much, which is the thing the bulk flow has to stop
and spell out.

**Where the security actually lives.** `App\Support\Sensitive` masks in PHP,
before the value reaches a view. The rule it exists to enforce: the full number
must never be written into the HTML and hidden with CSS, never put in a data
attribute, never included in a payload the page filters client-side. Whatever
the browser was sent has already been read by whoever is sitting at the browser;
"hidden" markup is a decoration over a leak. There is deliberately no accessor a
Blade template could call to get an unmasked value.

Three tests assert this against rendered HTML rather than against the helper,
because the question is what reached the browser: every unmasked identifier in
the sample data is searched for on the payroll list, on somebody else's payslip
and on the viewer's own pages.

- **The payroll list carries no bank, PAN or Aadhaar at all** — absent, not
  masked. A masked value on a list of everybody still confirms an account exists
  and hands over twelve people's last four digits at once.
- **`salary.view.all` is its own permission.** Seeing what a colleague earns is
  itself the harm; there is no "read-only so it is fine" here. It must not be
  implied by `employees.view`.
- **The payslip route takes a period and no employee.** The person is resolved
  from the session, so there is no identifier to tamper with and no ownership
  check anybody can forget to write. The management view of someone else's run
  is a separate, separately guarded route. A safe common path beats a check that
  has to be remembered — and a test asserts the route's only parameter stays
  `period`.
- **The IFSC is deliberately unmasked.** It identifies a branch, not a person,
  is published by the RBI and is printed on every cheque; masking it would imply
  the fields beside it are protected by obscurity too.
- **The account mask does not reveal length.** Indian account numbers run nine
  to eighteen digits, and rendering the true length narrows a guess while
  helping the reader not at all.

**What the backend still owes** (recorded in `Sensitive`'s own header so it is
read before the class is changed): encrypted at rest via the `encrypted` cast,
never plaintext columns, because a cPanel database backup is a file somebody can
email; revealing a full value is a dedicated route that checks a permission and
writes an audit entry naming who looked at whose record, not something a page
renders; and these fields go in the framework's redaction list so an exception
report cannot spill them into a log that is easier to read than the database.

Other decisions:

- **The portal shows no pay-for-this-month card.** The month's figure is already
  the first row of the history, and the breakdown behind it lives in the
  payslip; restating either would be a second place to keep correct. My Salary
  shows a status tile, the net, the payslip count, the history, and the masked
  identifiers. No gross anywhere on it.
- **My Salary has a back link to payroll**, because unlike My Teams and My
  Projects it is reached by a button on Salary Management rather than from the
  navigation. It must render only for a viewer holding `salary.view.all`:
  showing an employee a link into everyone's pay is a door they should not be
  shown.
- **The page shows who is *missing* from payroll.** The handover had no
  equivalent, and it is the module's most dangerous gap: a list of everyone
  being paid says nothing about the person who is not on it, and that person
  simply does not get paid. Two states, kept apart — "not on this month's list
  at all" and "no bank details on file", the second being worse because they
  cannot be paid by any route.
- **Marking paid must be idempotent.** Running it against an already-paid record
  must not move that record's payment date.
- **A missing net reads "Not recorded", never ₹0.00.** A zero is a claim that
  somebody was paid nothing.
- **An unpaid row reads "Not paid yet"**, not the handover's `--`, which reads
  as missing data rather than as something that has not happened.
- **The select-all box is an enhancement, not the mechanism.** Every row box is
  a real form control and the form submits without JavaScript — which matters
  when the form marks people paid, because a bulk action that only works when a
  script loads is one that half-works. The header box also goes indeterminate
  when some rows are ticked rather than lying about the state.
- **No route deletes a salary record**, and a test walks the route table to keep
  it so.

**Errors found in the handover, none copied:** it showed forty salary records
and "28 paid / 12 pending" for a company of twelve; CTC ₹12,60,000
(= ₹1,05,000/month), net ₹85,800 and a table figure of ₹80,000 for the same
person, with nothing connecting the three; a "Salary" column that never said
gross or net; every employee's bank account and IFSC on a rail of the *payroll
list*; `style="clear:both"` and `style="border-top:…"` that our CSP blocks; and
a payslip control that was an `<a href="#">` opening nothing.

**How payroll gets paid without anybody browsing bank details.** The owner
asked the right question: if bank details are visible only to the person
themselves, how does whoever runs payroll pay people? The answer is that paying
somebody does not require reading their account number off a screen — it
requires the number reaching the bank, and conflating the two is how every
employee's account number ends up on a page left open on a shared desk. Three
routes, in the order they should be reached for:

1. **The bank file, the normal path.** The system generates the NEFT/RTGS
   bulk-transfer file for a period — account, IFSC, amount, one row per person —
   as a download produced under `salary.disburse`, written to an audit entry and
   never rendered to screen. Whoever runs payroll uploads it to the bank portal
   without having read it. This is the disabled "Bank transfer file" action now
   on the payroll page.
2. **A single audited reveal, the exception path.** A transfer bounces and one
   account has to be checked. That is one record, revealed deliberately, with an
   audit entry naming who looked at whose details.
   `Sensitive::revealFor()` is where that check lands; it currently **throws**
   rather than being stubbed permissive, because a reveal without a permission
   check and an audit entry is precisely what the class exists to prevent, and
   the version that "works for now" is the one that ships.
3. **Aadhaar is on neither path.** Banks settle on account number and IFSC; it
   would be needed for EPF or ESI filings, and ZephryxLabs makes neither. It
   stays visible to the person themselves alone, whatever anyone's role.

When the automated payroll portal lands and pays over an API, that is path 1
with the download removed — machine to machine, still never on a screen.
Building it this way now makes that a swap of one step rather than a rework of
who can see what.

**One layout bug fixed globally.** `.page` was a plain block, so the space
between one section and the next came from whatever bottom margin each component
happened to carry — `.kpi-row` had one, `.notice` and `.card` did not, which is
why banners sat flush against the tiles below them and stacked cards touched. It
is now a flex column with a single `--density-gap`, so spacing is a property of
the page rather than something each component has to remember, and compact mode
tightens the whole page together.

**One pre-existing bug found here and fixed:** `.kpi-ic.tone-danger` was never
defined although `.qa-tile.tone-danger` was, so the Invoices "Overdue" tile —
already shipped — and the Salary "On hold" tile both fell back to the default
green. The most alarming figure on each page was rendering as though it were
fine. Feedback tones are now defined for every element that takes one.

### Leave (decided 2026-08-28)

Built out of order: Attendance was skipped because its design is changing.

Four pages: the approval queue (`/leave`), a person's own leave
(`/leave/mine`), the request form (`/leave/request`) and one request
(`/leave/{request}`).

**Two owner decisions shaped the whole module:**

1. **The policy lives in the Admin Panel.** Leave types and their annual
   entitlements are company policy, not application logic. `config/leave.php`
   is an explicit placeholder for that surface; `App\Support\LeavePolicy` reads
   it and every page goes through the class rather than the config, so the swap
   to a `leave_types` table is one method.
2. **This module counts. It does not decide.** "Just a portal to count and
   request leaves — we will not automate for now." So there is deliberately no
   working-day calculator, no holiday calendar and no automatic deduction. The
   requester states how many days a request costs and the approver agrees it;
   whether a Saturday counts or a public holiday is skipped is their judgement.
   Encoding a guess at those rules would produce balances that quietly disagree
   with what people were actually granted. Tests assert `workingDays()`,
   `holidays()` and `deduct()` stay absent.

What *is* computed is the sum of what was recorded: entitlement, less the days
on approved requests. That is counting, and it cannot be wrong unless the
records are.

**Pending days are reported, never netted off.** A pending request has not been
granted, and a balance that already assumes approval is how somebody plans
around days they may not get. The tiles say "not deducted until approved" in as
many words.

**Rejected and withdrawn are not the same colour.** The handover drew both red.
They are opposite events — one was done to the person, the other they did
themselves — and a withdrawal should not look like a refusal. Withdrawn is
grey, and the word shown is "Withdrawn" everywhere including the pill, because
the tab and the pill saying different things is its own bug.

**Deciding happens on the request, not in a table row.** The handover put a tick
and a cross as unlabelled icon buttons thirty pixels apart, each of which is
somebody's holiday. Here the queue offers "Review", and the decision sits under
the dates, the reason, the requester's balance and the clash list. Approve and
reject are **separate routes and separate forms** — one endpoint taking a
decision parameter is one place for a default to be wrong — and **rejecting
requires a reason**, because "rejected" with no explanation is the version
people have to chase in person.

**The page shows who else is off across those dates.** Not in the handover at
all, and the information the decision actually turns on: approving leave blind
is how a team ends up with nobody in on a Friday. Pending requests are included
as well as approved ones, since two people asking for the same week is exactly
the clash worth catching before either is granted, and same-department overlap
is called out separately because two designers off together is a problem in a
way that a designer and an accountant is not.

**Three rules the backend must honour** (recorded at the top of the controller):
`leave.approve` is its own permission; **nobody decides their own request, owner
included** — an approver who can grant themselves leave makes the record
meaningless; and a decision is only valid on a still-pending request, checked
inside the transaction so two approvers cannot both decide it.

**The reason and contact number are personal data.** "Fever, seeing a doctor
tomorrow" is health information. Neither is a column on any list — a test walks
every list page asserting no request's reason or phone number appears there.
They are on the request, for the person deciding it.

Other decisions:

- **Balances are bars, not a donut.** Beyond the handover's donut being drawn
  with `style="transform: rotate(...)"` and `style="--dot:#3B82F6"` — both
  CSP-blocked, so it would have rendered as a grey ring beside a colourless
  legend — a donut is the wrong chart. These are four independent allowances
  each with its own maximum; a donut implies they add up to something.
- **Unpaid leave has no balance.** The handover gave it two days on the same
  screen where its own policy card said "As Per Policy". Unpaid days are
  counted and reported, never deducted from an allowance they do not belong to.
- **No "Expired" tile.** Whether unused days carry forward is undecided
  (2026-08-28), so the pages say the balance is for this year only rather than
  showing a figure whose rule does not exist. The handover showed one, reading
  zero.
- **The queue defaults to Pending**, not All: the page exists to clear a queue.
  Rows sort soonest-first and pending ones carry how far away the leave is, so
  "starts tomorrow" is visibly more urgent than "starts in 20 days".
- **Leave can be withdrawn until it starts**, including after approval — plans
  change, and the alternative is a balance spent on days nobody took. Leave
  already under way is a conversation, not a button.
- **No "All Locations" filter.** One office; a dropdown with a single entry
  teaches people the controls are decorative.
- **Tabs are links with URLs.** The handover wired them with an inline
  `<script>`, which our CSP blocks — they would not have switched at all.

**Errors found in the handover, none copied:** its policy granted 34 days a year
against 12 taken, and every balance tile read 18 rather than 22; unpaid leave
had a balance; rejected and cancelled shared a colour; a Saturday was billed as
a day of casual leave; "Reporting To: Santanu (HR)" was hardcoded; and
`style="clear:both"`, the inline tab script and the donut's inline styles are
all CSP-blocked.

### Meetings (decided 2026-08-28)

Three pages: the list (`/meetings`), one meeting (`/meetings/{meeting}`) and the
scheduling form (`/meetings/schedule`).

**This server hosts nothing.** Every meeting is a Google Meet, created through
the Google Calendar API on the company Workspace account. The CRM organises: it
holds who is meeting whom about what, and a reference to the Google event. It
does not host, record or proxy a call, and **it never constructs a Meet URL** —
the link comes back from Google or there is no link. Google Calendar is the
source of truth for the event and for every RSVP; we read those back and never
write one. There is deliberately no `setAttendance()` on the provider and no
`meetings.rsvp` route, and a test asserts both stay absent.

**Four owner decisions taken 2026-08-28:**

1. **One company account**, service-account access with domain-wide delegation
   — not each person connecting their own Google. Links survive somebody
   leaving, nobody re-consents, and the CRM stores no per-user refresh tokens it
   would then have to protect.
2. **Clients may request a meeting but not create one.** Only a project manager
   or the system admin creates, which is what produces the Google event and the
   link. That is why `requested` is a real state rather than a synonym for
   pending: nothing has been sent, so there is no invite and no link, and the
   page says exactly that. A client sitting in a room that does not exist is the
   failure this state prevents.
3. **The join link is for attendees only.** A Meet link is effectively a
   password. It is withheld in PHP — the controller nulls it before the view
   sees it — so a colleague browsing the list cannot walk into a call about
   somebody's salary or a client dispute. Tests assert the raw link appears
   nowhere in the HTML of a meeting the viewer is absent from, on both the list
   and the detail page.
4. **Times are stored UTC and shown in Asia/Kolkata.** Storing local time is
   what makes a meeting with an overseas client drift by an hour twice a year
   when their clocks change and ours do not. Nothing renders a raw stored value;
   everything goes through `MeetingPresenter`, so showing another zone later is
   a display change rather than a migration. The zone abbreviation is printed
   beside the time, because a client abroad needs to know which four o'clock is
   meant.

**There is no "Completed" status.** The handover had one. Nothing here can see
whether a meeting took place — Google knows a room existed, not whether anybody
joined it. A scheduled meeting whose end time has passed reads **Ended**, which
is a fact about the clock rather than a claim about the meeting.

**`GoogleMeetProvider` throws rather than being stubbed.** Returning a plausible
event id and a fabricated `meet.google.com/abc-defg-hij` would make the pages
look finished and put somebody in a room that does not exist, waiting for a
client. The interface exists because the seam is where the failures live, and
naming them makes them impossible to skip — its header records what the
implementation must get right: the conference is *requested*
(`conferenceDataVersion=1` plus a `createRequest`, or the event exists with no
way to join), external guests must be allowed, a failed create leaves the
meeting requested rather than silently retried, cancelling must propagate, and
creating must be idempotent.

Other decisions:

- **Attendees are shown, with their replies in words.** The handover showed
  none anywhere and hardcoded "With: Santanu Kumar" in its rail. A client is
  drawn as a square chip and a colleague as a round avatar, so the column says
  whether this is a client call without being read. An unrecognised or missing
  RSVP reads as "No reply yet", never as attendance — assuming somebody is
  coming is the error that costs a meeting.
- **One status column, not two.** The handover's Status and Action columns said
  the same thing in different words: Upcoming/Approved, Cancelled/Declined,
  Completed/Completed.
- **Cancelled is not "declined".** Calling a meeting off and turning down an
  invite are different acts by different people; the handover's tile conflated
  them.
- **"Your next meeting" is the viewer's own.** A card with that heading showing
  a meeting somebody is not invited to is worse than showing nothing — they will
  act on it.
- **Joining opens shortly before the start.** Not a lock (a Meet link works
  whenever) but a live "Join" beside a meeting three weeks out has no reason to
  be pressed except by accident. The link itself is still available to attendees
  on the detail page, so it can be copied ahead of time.
- **Every `target="_blank"` carries `rel="noopener noreferrer"`**, and a test
  walks the pages to keep it that way. Without `noopener` the opened page can
  reach back through `window.opener`.
- **No route deletes a meeting.** Cancelling withdraws the Google invite and
  keeps the record; deleting would leave the event live on everyone's calendar
  with nothing here to show it existed.
- **The "Need Immediate Help?" card is gone** — a hardcoded phone number and two
  obfuscated email addresses, a client-support panel that had wandered onto an
  internal staff page. It is replaced by four plain statements about how
  meetings actually run, which is the thing somebody on this page might be
  unsure about.
- **Attendee pickers are `<select multiple>`**, not a JavaScript token field:
  they work without a script, submit an array the backend can validate, and are
  keyboard-operable by default.

**One CSS bug worth recording:** `.mt-attendee-body span` as a descendant
selector also matched the "Organiser" badge nested inside the name, turning an
inline chip into a full-width green bar. Direct-child selectors where a block
rule is meant only for the immediate children.

### Error pages (decided 2026-08-27)

Branded pages for 403, 404, 419, 429, 500 and 503. The last four were not asked
for, but they share one template and without them those states fall back to
Laravel's unstyled defaults — and two of them are reachable today, given CSRF
tokens on every form and throttles on sign-in.

- **The error layout is deliberately standalone** — no sidebar, no topbar, no
  navigation query, no view composer. This is what renders when something has
  already gone wrong, including when the thing that went wrong is the shell, so
  it depends on as little as possible. The cost is losing the sidebar as a way
  out, which the page's own buttons replace.
- **A deferred module says so.** `/leads` and `/calendar` keep their navigation
  entries and 404 until v2 (§12). The 404 view recognises those paths and says
  "Leads is not built yet" rather than "we cannot find that page" — someone who
  clicked a link we chose to show them should not be told it does not exist.
- **The 403 names nothing.** Not the permission, not the role that would grant
  it, not whether the record exists. All three tell someone probing the
  application how it is put together.
- **419 explains the session.** Laravel's stock wording is "Page Expired",
  which tells someone who has just lost a half-written form nothing useful.
  This is the most likely error page in the application.
- **500 carries no exception, trace or path.** In production those leak
  internals to whoever tripped the error; in local, Laravel shows its own debug
  page instead, so nothing is lost.
- **The 404 escapes the path it echoes back** — a 404 is a classic place to
  reflect attacker-controlled input straight into the browser.
- **`/dev/errors/{code}` renders any of them on demand**, registered only in
  local + debug. In production it would let anyone show staff a convincing
  "session expired" or "maintenance" page at a URL of their choosing.
- **The second action is Contact Support, not "back to start".** Someone on an
  error page has already found the thing that did not work; sending them to the
  landing page just makes them find it again. The mailto subject carries the
  status code so a reply does not have to begin by asking what they saw. The
  403 keeps "Contact an administrator" — same address, but the person who can
  grant access holds the Admin Panel, and saying so points at the right door.

### Viewport fit on mobile (decided 2026-08-26)

Every public page fits the phone viewport without scrolling. Verified at
320×568, 360×640, 375×667, 390×844, 412×915, 430×932 and 768×1024 — zero
vertical or horizontal overflow on any of the five pages.

Three rules govern this and should be kept when new surfaces are added:

1. **`dvh`, never `vh`.** `100vh` on mobile is the *address-bar-hidden* height,
   so a `100vh` layout pushes its primary action under the bar.
2. **`min-height`, never a fixed height with hidden overflow.** In landscape,
   or with a large system font, the content must stay reachable. Below 560px of
   viewport height the auth pages deliberately give up and scroll rather than
   clip the submit button out of reach.
3. **Decoration yields first, content never.** The green panel shrinks to a
   band and drops its lightning badge; the landing hero composition disappears
   entirely. The OTP page's masked address and the reset page's password hint
   stay at every size — they are the only guidance those screens carry.

Height, not width, is what runs out on a phone, so the auth breakpoints are
tiered on `max-height` (740px, 620px, 560px) rather than width alone.
