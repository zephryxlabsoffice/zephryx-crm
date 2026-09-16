# Backend completion — from demo sources to a real database

**Decided 2026-09-08.** The development phase ends when this plan is finished.
Deployment is a separate exercise afterwards (GitHub + SSH, step by step), and
nothing in it may require application work that could have been done here.

## Where the build actually was

Every screen in the application was complete and every one of them read from an
in-memory `Demo*` source — 20 of them. The database held six migrations and four
models, all of which arrived with authentication and RBAC. There was no
`employees` table, no `projects`, no `invoices`.

Two gaps beyond the obvious one:

- **43 write routes were `abort(501)`** — 31 staff, 6 client, 6 admin.
- **Five modules had no create or edit path at all.** Employees, Clients, Teams,
  Projects and Tasks were read-only directories: not placeholders, but no route,
  form or controller method anywhere. As built, the company could not hire
  anybody. Confirmed in scope 2026-09-08.

## The two decisions this plan rests on

**Every module gets full CRUD**, gated and audited like everything else.

**The seed splits in two.** A production seeder creates only what the
application needs in order to run: roles, permissions, the master data lists,
company settings and the owner account. The demo dataset moves to a local-only
seeder, so development keeps its full screens and no fictional person ever
appears in a live payroll or audit log.

## Order

Dependency order, because the foreign keys run this way:

**Master data → Employees → Clients → Teams → Projects → Tasks → Attendance →
Leave → Salary → Tickets → Invoices → Meetings → Announcements → Notifications
→ Profile → Admin (accounts, access, settings, audit)**

Master data comes first because departments, designations, leave types and
document types are referenced by the modules above them, and Employees is the
spine every other table has a foreign key into.

## What "done" means for one module

A module is not finished until all seven hold:

1. **Migration** — the tables, with foreign keys and the indexes the pages
   actually query on.
2. **Models** — relationships, casts, and the scopes the controllers need.
3. **Seeders** — production rows where the module needs them to function;
   the demo dataset reproduced in the local seeder so no screen loses content.
4. **Reads swapped** — the `Demo*` call replaced by real queries. The presenters
   and policies already hold the logic and are tested; this is a data-source
   change, not a rewrite.
5. **Writes** — every `abort(501)` replaced by a real controller action with
   validation, plus the create/edit surfaces the module never had.
6. **Permission gates** — each write behind its `§5` permission. The 11
   `TODO (backend phase)` markers name the checks that are already known to be
   missing; they are a floor, not the list.
7. **Audit entries** — anything that changes a record somebody could later
   argue about writes to `audit_log` (§6).

Tests stay green at every commit, and each module lands as its own commit.

## Open questions collected for the review round

Recorded here as they surface rather than guessed at silently, for the
end-to-end review before deployment.

1. **Directory ordering.** The employee list is ordered by staff ID, which is
   what it has always been. Ordering by name would arguably read better — the
   name is the column people scan. Left alone deliberately: changing it during a
   data-source swap would have been a UI decision smuggled into a refactor.

2. **`on_leave` as a status.** The demo rows carried it as a stored value and
   the directory offered it as a filter. It is not a stored state — it is a
   question about today that an approved leave request answers — so it is gone
   until the Leave module lands, at which point it returns as a derived filter.
   Until then `stats()['on_leave']` reports 0 rather than a guess, and the
   status filter offers only what the database can answer.

3. **Row actions are still disabled** in the directory markup ("Row actions are
   not built yet"). They light up with the CRUD work; noted so the disabled
   button is not mistaken for a decision.

## Review round decisions (2026-09-11)

Answered by the owner in a question round. Items marked *open* are awaiting a
follow-up answer. Nothing here is built yet.

### Employees

- **Directory order:** by staff ID (open questions 1 above: settled, stays).
- **Staff ID format** replaces `EMP001`:
  - Employees: `ZEPH` + `YY` + `D` + `NNN`, e.g. `ZEPH261001`.
    `YY` = year the record is created. `D`: 1 full-time, 2 intern,
    3 freelance. `NNN` restarts at 001 each year, per type.
  - Mentors: `ZEPH4NNN` (e.g. `ZEPH4001`). Clients: `ZEPH5NNN` (e.g. `ZEPH5001`).
- **Intern → full-time:** a "Convert to full-time" button issues a new ID with no
  re-entry of the form. Two separate records: history stays under the old ID,
  which is closed. Leave balance starts fresh. Interns only — freelancers are
  not converted. The work email moves to the new account, the old account is
  closed, and a fresh set-password link is sent. Tasks and team membership are
  not moved (conversion follows an accepted offer, so nothing is open).
- **Client ID** is per client company, one ID each, and one login per client.
- **"On leave" filter** returns, derived from approved leave for today.
- **Row actions** (view / edit / change status) are enabled.
- **Add form** picks roles, and also takes: personal email, work email (the
  sign-in and invite address), phone, PAN, Aadhaar, photo, bank details, salary
  details. Required fields vary by type: full-time all, interns and freelancers
  fewer *(open: exact list)*.
- **Aadhaar is not mandatory** (private employer). Replaced by an **ID proof**:
  type dropdown (Aadhaar / Voter ID / Passport / Driving Licence), all of which
  carry an address. The CRM stores type and number only; physical photocopies
  are submitted to the office. Current and permanent address are typed in.
- **PAN** is a separate field, required for full-time.
- **Required by type:** full-time — everything. Interns — ID proof, phone, bank.
  Freelancers — ID proof, phone, PAN, bank.
- **Sensitive fields** (ID proof, PAN, bank): masked for everybody by default.
  HR and CEO reveal **one field at a time**, and every reveal writes an audit
  entry naming who looked at whose record and why (revised 2026-09-14). The
  employee sees their own masked, to check it was entered correctly. Payroll
  still never reads them: it uses the bank transfer file.
- **ID proof and bank details share one encrypted record** — the existing
  `employee_banking` table, whose Aadhaar-only column becomes an ID-proof type
  and number.
- **Salary details**: Basic, HRA, other allowances, PF, PT, TDS. Stored for
  payslip preparation, NOT shown on the Salary page. Visible to HR, CEO and
  the employee — who sees them only on their payslip. **Payslips are an HR
  upload, full stop** (settled 2026-09-16): the CRM never generates one, and
  the uploaded file lives on Drive. Interns: monthly stipend. Freelancers: a per-project
  or hourly rate only; their payments are tracked outside the CRM.
- **No delete.** Records are closed, never removed.
- **Milestone announcements** on by default.
- **Status:** Active / Inactive. "Suspended" is removed. Closing a record asks
  for a reason (Resigned / Terminated / Contract ended) and the last working
  day. Sign-in is blocked after the last working day.

### Attendance: comp-off

- Weekly off is Sunday only, no per-person week-off.
- **Sunday work is rostered** by a Manager or Team Lead (not HR), not
  requested. No approval step: a rostered Sunday counts as attendance and earns
  a comp-off. Rostered and absent = marked absent (can count as unpaid).
- An unrostered Sunday clock-in is recorded but earns nothing.
- Public holidays use the same roster rule.
- A comp-off must be taken before the next Sunday or it lapses. Half a day
  worked earns half a comp-off. Taking one is a request the manager approves.
- **Working a Sunday against leave already taken** needs manager approval. The
  leave day returns to the balance. The leave must be in the same month.
- Full-time and interns only. Freelancers have no attendance or leave at all.
- Sunday-against-leave approval is asked BEFORE working the Sunday. A Team
  Lead rosters only their own team.

### Employees: documents

- ID proof photocopies: a "Photocopy received" field with a date, so HR can see
  what is pending.

### Clients

- **Status:** Active / Inactive (replaces the project-style statuses).
- **No Leads module.** The unused `leads.view` permission goes.
- **No GST billing fields.** Invoices are uploaded like payslips, not
  generated. Amount, due date and bank payments are still recorded by hand, so
  overdue and balance keep working. **No payment gateway** — everything is
  bank to bank. *(This reverses the built line-item invoice builder.)*
- **Country and currency** per client.
- **Several contacts** per client, but **one login** per client, which sees
  everything for that client (projects, invoices, tickets, meetings). The
  login uses whichever email the client gives us; who at their end uses it is
  their business. A client is one entity.
  (Employees differ: work email is the sign-in, personal email is record only.)
- **No delete**, only Inactive. **One account manager.**
- **Signed date only.** Agreements and other documents are kept as hardcopy.

### Teams

- **Status:** Active / Inactive only — "Archived" goes.
- An employee may be in several teams. **One lead** per team.
- **Interns can be members; freelancers cannot.**

### Projects

- **Progress % is derived from completed tasks**, not typed in.
- Statuses stay: Planning / In progress / Review / On hold / Completed /
  Cancelled.
- **Reference:** `PRJ-YYYY-NNN` (the type code becomes the fixed `PRJ`).
- Work is assigned to **whole teams and to individuals**.
- **No project value or budget** in the CRM.
- The client sees status, progress, deadline and client-visible updates only.
- **No delete anywhere** — everything is documentation for later.

### Tasks

- **Several assignees** per task — a task can go to a team and flow to its
  members.
- **Comments and file attachments** on a task. **No time tracking.**
- Created by Manager and Team Lead only.
- A ticket can be turned into a task.

### File storage — applies to every module

- **All uploaded files live on Google Drive, reached through an index**, not on
  the server disk. Website assets (logos and the like) are the exception.

**Answered 2026-09-16.**

- **Shared web hosting only.** No VPS, no background worker, no object store to
  pay for. Everything runs inside ordinary PHP request handling — which is
  workable because the files are small: a payslip is tens of kilobytes and an
  invoice PDF not much more.
- **Photos stay on the local disk.** They are small and there are few of them.
  Cloudflare R2 was considered and set aside to keep the bill at zero. *Files*
  — payslips, invoices, ticket and task attachments — always go to Drive.
- **PDFs open IN THE BROWSER**, not as a download. Clicking a payslip lands on
  the document itself, the way every site backed by an object store behaves.
  We have to produce that without the object store.
- **Payslips are an HR upload and the file lives on Drive.** The CRM does not
  generate them (this closes the "owner to decide later" line under Employees).

**How, concretely.** The previous index (BhadooIndex) reads only; this has to
write as well, so it is the Drive API directly:

1. **One Google account holds the files**, in a folder per module. The
   application authenticates as that account and nothing is ever shared
   publicly — no "anyone with the link", because a Drive link that works
   without a session is exactly the forwardable URL §6 exists to prevent.
2. **`DocumentStore` grows a second driver.** The class is already the only
   seam the application has for a stored file: it takes an upload, composes the
   path, and hands bytes back through a controller that has checked the person
   and written an audit entry. Drive changes what `path` means — a file ID
   rather than a disk key — and nothing above it moves.
3. **Nothing is migrated.** What is on the private disk today is demo content;
   the production seeder ships no files at all. So the driver lands and the
   first real payslip goes straight to Drive.
4. **In-browser viewing is ours, not Drive's.** The existing route already
   streams a file after the permission check. It gains a viewer sibling that
   sends `Content-Type: application/pdf` with `Content-Disposition: inline`,
   `X-Content-Type-Options: nosniff` and a CSP that allows the document
   nothing — no scripts, no network — so an uploaded PDF renders in the
   browser's own viewer without being able to act inside our origin. That last
   part is why `DocumentStore::stream()` refuses uploaded files today, and it
   is the condition on which the refusal is lifted.

**Workspace Shared Drive, not the Gmail folder** (settled 2026-09-16 — the
owner offered either).

Three reasons, and the third is the one that decides it:

- **A service account can own files on a Shared Drive and cannot on a personal
  Drive.** A service account has no storage quota of its own, so an upload into
  a folder a Gmail account merely shared with it fails outright. The Gmail route
  would have to be a one-time OAuth consent whose refresh token we store — a
  credential that can be revoked by anybody clicking through their Google
  security page, and which then breaks uploads with nothing in the application
  to explain why.
- **The files belong to the company, not to a person.** A Shared Drive survives
  whoever set it up leaving. A folder in somebody's Gmail leaves with them.
- **The Calendar integration already assumes exactly this credential.**
  `App\Support\Meetings\GoogleMeetProvider` is written against a Workspace
  service account, and Meetings is still waiting on it. One key, connected once,
  serves Drive and Calendar both. Building the Gmail route would mean two
  credentials, two failure modes and two screens.

Note the asymmetry between the two Google jobs, because it is a security
property rather than a detail: **Drive needs no domain-wide delegation** — the
service account is added as a member of the Shared Drive like any other member
— while Calendar does, in order to impersonate the meeting host. So the key is
granted delegation scoped to Calendar only, and Drive access is a membership
that can be removed from the Drive's own sharing panel without touching
anything else.

### Connecting it: Admin Panel, and what a credential is NOT

Required by the owner (2026-09-16): every connection detail is entered in the
**Admin Panel**, not in a file on the server. Nothing about this arrangement
should need a deploy, an SSH session or a developer.

That much the settings machinery already does — `company_settings` overrides
`config/`, so a value entered in the panel survives the next deploy. **A
credential must not go through it**, and the reason is worth writing down
before the security audit finds it:

- `CompanySettings::apply()` pushes every stored row into the config repository
  at boot. A service-account private key in `config()` is a key in reach of any
  stack trace, any `config:show`, any debug page, and any future `dd(config())`
  in a hurry.
- `company_settings.value` is plain JSON in a column. A database dump, a
  backup on somebody's laptop, a read-only replica — none of them has a
  permission layer, which is the same argument that put the identity columns
  behind the `encrypted` cast.

So credentials get their own home, and it is shaped by the rules the rest of
this application already follows:

1. **Its own table, encrypted at rest.** The key material carries the
   `encrypted` cast, exactly as `employee_banking` does. Never pushed into
   config; read at the point of use by the Drive client and nowhere else.
2. **Write-only in the panel.** The key is pasted in and never rendered back —
   not masked, not partially, not in a `value` attribute. What the screen shows
   afterwards is the service account's **email address, the key fingerprint and
   the date it was connected**, which is everything needed to tell one key from
   another and nothing that could be stolen from the markup. This is the same
   rule as the identity card, and for the same reason: markup the browser was
   sent has already been read by whoever is sitting at it.
3. **CEO and System Administrator only**, behind its own sensitive permission
   — not folded into the existing settings permission. Master data may be
   edited by HR (see Admin panel above); a Drive key must not be reachable by
   the same grant that lets somebody add a department.
4. **Connecting, changing and disconnecting are audited**, by fingerprint and
   never by value. So is a failed connection test, which is the entry that
   explains an outage afterwards.
5. **A "Test connection" button that actually writes.** It creates and deletes
   a small file in the Shared Drive, because a credential that can list a folder
   and cannot write to it is the failure this whole exercise is about — and it
   would otherwise be discovered by the first person trying to upload a payslip.

**What exists today, honestly.** The settings half is built and is the right
shape: `company_settings` overrides `config/`, with the catalogue deciding what
may be a setting at all and retroactive changes shown before they are saved.
The credential half is not built, and what stands in for it is `config/meetings.php`
reading `GOOGLE_SERVICE_ACCOUNT_KEY` out of `.env` — a file on the server, which
is exactly what the owner has now said they do not want to depend on. So the
credential store above supersedes those three env values, Calendar included, and
the panel screen configures one Google connection rather than two.

### Attendance

- Clock in from anywhere. **The office-IP idea is dropped** (2026-09-16): it
  stays simple, and there is no location check of any kind.
- **No WFH marking. No "late" status.**
- A day left open stays **rejected**; there is no correction request.
- **No attendance export here** — it belongs to a Reports module shipped as
  v1.2 after go-live.

### Leave

- **The leave year runs from each employee's own joining month**, not a company
  year. Unused days lapse at the end of it.
- **Earned monthly**, not granted up front. Leave starts from day one — there
  is no probation rule, because everyone is hired as an intern first.
- **Interns get the same leave as full-time.**
- Beyond the balance, days become **Unpaid automatically**.
- **Full days only, no half-day leave.**
- Sick leave of 3 days or more needs a medical certificate — **emailed to the
  office, not uploaded**. The request carries a "certificate emailed" tick HR
  sets when it arrives.
- **Privilege leave is granted in full** at the start of each employee's own
  year (it can be taken in month one or month twelve). Casual and sick accrue
  monthly — and **sick leave is granted in full** as well.
- **Comp-off:** half a Sunday earns nothing — only a full day earns one
  (overrides the earlier half-comp-off answer), which keeps leave full-day only.
  "Full day" is the ordinary 4-hour present threshold.

### Salary

- LOP, bonus, incentive and arrears are all absorbed into the one monthly
  amount; HR works them out outside the CRM. A **rejected attendance day is
  fixed by HR in the payroll sheet outside the CRM**, not in it.
- HR and CEO mark a salary paid. The employee sees only their own payslips and
  paid status. Freelancer payments stay out of the CRM. **HR types the monthly
  amount**; the CRM does not compute it.

### Tickets

- Statuses stay as built. Priority is set by staff at triage, never by the
  client. Categories and departments come from Master Data.
- Attachments allowed (storage per the Drive decision above).
- **Closed is final.** A new ticket can reference the previous one, and closes
  it. **No SLA targets** for now.

### Meetings

- The Google Calendar / Meet link stays automatic. If Google fails the meeting
  is still saved and a link can be added later.
- **No CRM reminders** (Google's own are enough) and **no recurring meetings**.

### Announcements

- Categories move to Master Data.
- Audience stays everyone or one department, plus a **"clients" marking** —
  clients see an announcement only when it is marked for them.

### Notifications

- **In-app only**, no email.
- A new client ticket notifies the **Support role and the Project Manager**
  first; once they assign it, the assignee is notified too.

### My Profile

**Revised 2026-09-14, replacing "changes apply immediately".** Personal data on
My Profile is no longer self-service. An employee REQUESTS a change, submits
the physical documents to the office, and HR accepts it — at which point the
record moves. Nothing changes on the strength of the form alone.

- Applies to: address (current and permanent), phone, emergency contact,
  gender, marital status, nationality, languages, skills, photo.
- Does NOT apply to preferences — theme, density, sidebar, and the task and
  ticket notification toggles still save instantly. They are settings, not a
  record of anything, and an approval queue full of dark-mode requests would
  bury the ones that matter.
- The flow follows the shape `email_changes` already uses: a pending row with
  its own state, the live record untouched until it is applied, and nothing
  deleted afterwards.
- This reverses the 2026-09-03 profile decision ("nobody should raise a ticket
  to correct their own phone number") deliberately and at the owner's
  instruction: the profile is the company's record, and it is corrected against
  documents rather than on assertion.

### Security

- **Two-factor by email OTP for every account**, on a new device or browser
  only, then trusted for 30 days.
- **No idle sign-out.** These are office machines.
- **Audit entries are kept forever.** Backups are handled on cPanel, not by
  the CRM.

**The standing rule (stated 2026-09-16).** There will be a security audit before
go-live, and nothing is to be built that a reasonable auditor would flag. The
working test for any new surface is the one this application has used since §6:
*a person with no grant must not be able to reach it, and a person with a grant
must leave a trace.* Which in practice means, every time:

- **The query is the gate, not the template.** If somebody may not see a thing,
  the row is never loaded — an `@if` around data the controller fetched anyway
  is one refactor away from a leak.
- **Nothing sensitive is rendered to be hidden.** No masked value that carries
  the real one in a data attribute, no secret in a `value` attribute, no
  full identifier "hidden" by CSS.
- **No file is reachable by URL alone.** Every document goes through a route
  that checks who is asking and writes an entry, whether the bytes live on the
  local disk or on Drive. No public link, no signed link that outlives the
  session, no guessable path.
- **Secrets are encrypted in the column** and never in config, never in a log,
  never in the audit log. The log names the field and the actor; the value is
  what it exists to protect.
- **Rank as well as permission** on anything done TO another person, and nobody
  applies a control to themselves.

### Client portal

- Clients may raise tickets, reply to them and request meetings — no comments
  on project updates.
- Invoices: they see and download the uploaded PDF and its paid/unpaid state.
- They may edit their phone and contact person only.

### Admin panel and permissions

- Admin panel, role changes, master data and company settings: **CEO and
  System Administrator**. Master data may also be edited by HR.
- Company settings are **CEO only**.

### Everyone, including the top

- CEO and HR clock in like everyone else. The CEO's leave is recorded and
  auto-approved — nobody approves it.
- Closing an employee record warns about their open tasks first, so they can
  be reassigned.

### Support

- The sidebar "Support" link opens a page with two buttons: raise a ticket
  (goes to the ticket form) and email us (opens mail).

## Built so far (2026-09-16)

Step 1 is part done. What is committed, in order:

- **The ZEPH staff ID** (`9ed7641`) — `ZEPH261001`, mentors `ZEPH4NNN`, clients
  `ZEPH5NNN`, each series counted from its own highest so an identifier is
  never reissued. With it, `employment_type` on the employment record: set when
  somebody is added, refused by the edit form, because converting an intern is
  its own act.
- **ID proof replaces the Aadhaar column** (`3195e1a`) — renamed in place, since
  the values are ciphertext and a copy would mean decrypting every row inside a
  migration. Masking follows the document, not the column.
- **The masked identity card** (`1a1a0fc`) — behind `employees.identifiers`, a
  new sensitive READ. It needed a declared home (`MODULE_READS`): a key with no
  row cannot be granted, so the gate would have failed closed and refused HR
  silently.
- **Capture** (`28a18bb`) — ID proof, PAN, bank and the photocopy date on the
  form. The edit form renders empty inputs beside masked hints: blank keeps,
  typed replaces. A full-time hire now cannot be created without documents.
- **The audited reveal** (`c709002`) — POST only, one field, a required reason,
  flashed for a single render, logged by field and reason and never by value.

- **Two addresses** (2026-09-16) — the profile's single `address` column splits
  into `current_address` and `permanent_address`, typed on the add form and
  required of a full-time hire. Read behind `employees.identifiers` like the
  identity card, and shown in FULL: an address is not a credential, and half of
  one cannot be checked against the photocopy in the file. Prefilled on edit for
  that reason too — a correction is a line, not a re-entry. The audit entry
  names the field and never the address.

- **The salary structure** (2026-09-16) — `employee_salary_structures`, one row
  per person, shaped by the engagement: six components for full-time, a single
  stipend for an intern, a rate and a basis for a freelancer. Amounts are
  integers of paise. Required of a full-time hire, read behind `salary.view`
  and written behind `salary.manage` — so `employees.edit` alone cannot move a
  salary by posting extra fields at the same form. **No total is computed and
  the Salary page does not show it**: a breakdown printed beside an uploaded
  payslip would argue with it in any month carrying a deduction. The audit
  entry names the components that moved, never the figures.

- **The profile change-request flow** (2026-09-16) — My Profile stopped saving.
  `profile_change_requests` holds a pending row shaped like `email_changes`:
  only the fields that differ, the live record untouched, spent rows kept. A
  new owner in `ProfilePolicy` — `REQUESTED` — carries the reversal, and
  preferences deliberately stayed `SELF`. The photo is cleaned and stored on
  upload but lands on the request, not the record; a declined or withdrawn one
  deletes the candidate file. HR works a queue at `/employees/requests` behind
  `employees.edit`, which names fields and never values, and **nobody decides
  their own**. Declining requires a reason, and the person reads it on their
  own page.

- **Convert intern to full-time** (2026-09-16) — a button, not a second pass at
  the form. New ZEPH ID, new account, new employment record with today as
  `joined_on` so the leave year starts again; the old record closed and kept,
  threaded by `converted_from_id`. The work email moves and the closed account
  keeps a tombstone address built from it, since the sign-in identifier is
  unique and both rows cannot hold it. Identity, bank, profile, photo,
  documents and roles come across — files COPIED, never shared, or replacing
  one later deletes it from under the closed record. Attendance, leave,
  payslips, tasks and team membership stay put, and the salary is deliberately
  not carried: a stipend and a breakdown are not the same shape.
  `employees.create`, because it creates an account, plus the rank check that
  closing a record makes.

**Step 1 is finished.**
Also still missing from the add form, noticed while doing the addresses:
personal email alongside the work email, and a phone number — the latter is
required of interns and freelancers by the decisions above, so it cannot wait
for the profile page.

Also done, outside step 1: the donut restyle (`055cbb9`) across the five
modules that share it.

## RESUME HERE — the rework order (agreed 2026-09-12)

The review round is finished. Nothing below is built. The original module
order at the top of this plan is superseded by this one, because the decisions
change modules that are already committed.

1. **Employee record and the ZEPH ID scheme** — everything hangs off it: type
   (full-time / intern / freelance), ID proof, PAN, addresses, bank, salary
   breakdown, masking rules, convert-to-full-time.
2. **Clients → Teams → Projects → Tasks** — client statuses, country and
   currency, contacts; "Archived" off teams; derived project progress and
   `PRJ-YYYY-NNN`; task comments, attachments and several assignees.
3. **Attendance roster and comp-off → Leave year rules** — Sunday roster,
   comp-off earning and expiry; the per-employee leave year, monthly casual
   accrual, privilege and sick granted in full.
4. **Salary → the Invoices rewrite** — the line-item builder is replaced by an
   uploaded PDF with amount, due date and hand-recorded bank payments. This is
   the largest single change.
5. **Tickets, Meetings, Announcements, Notifications, Profile.**
6. **2FA on new devices, the Support page, Admin tidy-up.**

Storage moves to Google Drive as one job — decided in full above, and no longer
waiting on anything. It lands with the **Admin Panel connection screen** and the
**inline PDF viewer** in the same piece of work, because a Drive driver with no
way to connect it and no way to read a payslip back is three-quarters of a
feature. Sequenced before step 4, since the Invoices rewrite is an uploaded PDF
and has nowhere to put one until this exists.

The office-IP attendance logic is dropped, not deferred.
