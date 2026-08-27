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
