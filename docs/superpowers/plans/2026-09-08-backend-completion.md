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
- **Sensitive fields** (ID proof, PAN, bank): full value to HR and CEO only.
  The employee sees their own masked, to check it was entered correctly.
- **Salary details**: Basic, HRA, other allowances, PF, PT, TDS. Stored for
  payslip preparation, NOT shown on the Salary page. Visible to HR, CEO and
  the employee — who sees them only on their payslip. Payslips stay an HR
  upload for now (CRM-generated payslips: owner to decide later). Interns: monthly stipend. Freelancers: a per-project
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
- **No GST billing fields.** Invoices will be uploaded like payslips, not
  generated. *(open: this reverses the built Invoices module.)*
- **Country and currency** per client.
- **Several contacts** per client, but **one login** per client, which sees
  everything for that client (projects, invoices, tickets, meetings).
- **No delete**, only Inactive. **One account manager.**
- **Signed date only.** Agreements and other documents are kept as hardcopy.

## PENDING — resume the review round here

Asked 2026-09-11, not yet answered. Re-ask exactly these, in this format
(short, point-wise, ⭐ = recommendation), then continue module by module:
Tasks → Attendance → Leave → Salary → Tickets → Invoices → Meetings →
Announcements → Notifications → Profile → Admin.

**Clients: checks**

1. ⚠️ Invoices are currently BUILT by the CRM (line items, totals, payments,
   send). Owner said "upload like payslips".
   a) Switch to upload: PDF + amount + due date, payments still recorded so
      overdue/balance keeps working ⭐
   b) Upload the PDF only, with no amounts or payment tracking
2. Several contacts but one login — whose email is the login?
   a) A separate company login email, and contacts are just for reference ⭐
   b) One chosen contact's email

**Teams** (today: name, purpose, lead, status Active/Inactive/Archived,
formed date, members)

3. Statuses: a) keep all 3  b) Active / Inactive only ⭐
4. Can one employee be in more than one team? a) Yes ⭐  b) No
5. Team lead: a) one lead per team ⭐  b) several leads
6. Can interns and freelancers be team members? a) Both  b) Interns only

**Projects** (today: client, manager, teams, progress %, status, priority,
start date, deadline, updates internal/client-visible)

7. Progress %: a) typed in by the manager (current)  b) worked out from
   completed tasks ⭐
8. Statuses Planning / In progress / Review / On hold / Completed / Cancelled:
   a) fine as is ⭐  b) change them
9. Project ID (now `WD-2024-001`): a) keep it  b) use a ZEPH style (owner
   gives the format)
10. Who works on a project: a) whole teams  b) individual people  c) both ⭐
11. Project value or budget: a) not in the CRM ⭐  b) add a project value
12. What the client sees: a) status, progress, deadline and client-visible
    updates ⭐  b) also their tasks
13. Deleting a project: a) no delete, only Cancelled ⭐  b) allow delete
