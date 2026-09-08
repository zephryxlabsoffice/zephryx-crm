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
