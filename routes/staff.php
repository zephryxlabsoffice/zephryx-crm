<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\ModulePlaceholderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\ShellPreferenceController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staff realm
|--------------------------------------------------------------------------
|
| GUARDED BY THE FILE IT IS IN (foundation spec §3.1).
|
| routes/web.php mounts this file behind `realm:staff`, so every route below
| inherits the check without naming it. That is the point of the split: a staff
| route cannot be added outside the guard, because being in this file IS being
| inside the group. The previous arrangement — one flat file, with a comment
| promising middleware later — depended on whoever added the next route
| remembering.
|
| The realm check runs before any data is read, and it is independent of
| permissions: a client session is refused here whatever keys it holds.
| Permissions are the second barrier, applied per page by the RBAC engine (§5).
|
| Every navigation entry resolves to a real route from the start so the shell's
| shape never shifts as modules land (§12). Each module replaces its own
| placeholder when it is built.
|
*/

Route::post('/shell', [ShellPreferenceController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('shell.store');

/*
 * The dashboard. One route, one page — and no `/dashboard/hr` beside it.
 *
 * The page is composed from config/dashboard.php against the viewer's
 * permissions, because §2.4 makes roles additive and Manager + HR is a person
 * who exists. A route per role would need a route for every combination, which
 * is the argument the whole module is built on; see the head of
 * config/dashboard.php.
 *
 * `?as=` previews one role's composition and exists only in local + debug. It
 * is intersected with the real gate, never substituted for it, so it can only
 * hide widgets — see DashboardController::gate.
 */
Route::get('/dashboard', DashboardController::class)->name('dashboard');

/*
 * Clients (2026-09-09).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * `clients.view` IS ON THE LIST NOW, AND IT WAS NOT BEFORE
 *
 * The page had no permission on it at all while it was reading demo rows, which
 * was survivable when there was nothing behind it and is not now. Three roles
 * hold the key — Manager, Support and the CEO — and the sidebar entry has always
 * been gated on it, so nothing about who sees the module changes; what changes
 * is that typing the URL is no longer a way around the sidebar.
 *
 * Four write permissions, because they are four different sizes of act:
 * recording that a company exists, correcting its details, moving it between
 * engagement states, and handing somebody a login that can read its invoices.
 * The last is sensitive — see RbacSeeder::SENSITIVE.
 *
 * There is no delete at any permission. Projects, invoices, tickets and
 * meetings all point back at a client.
 *
 * `create` is declared BEFORE `{client}`, or the word "create" is read as a
 * client reference and 404s.
 * ─────────────────────────────────────────────────────────────────────────────
 */
Route::get('/clients', [ClientController::class, 'index'])
    ->middleware('permission:clients.view')
    ->name('clients.index');

Route::get('/clients/create', [ClientController::class, 'create'])
    ->middleware('permission:clients.create')
    ->name('clients.create');
Route::post('/clients', [ClientController::class, 'store'])
    ->middleware('permission:clients.create')
    ->name('clients.store');

Route::get('/clients/{client}', [ClientController::class, 'show'])
    ->where('client', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:clients.view')
    ->name('clients.show');

Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])
    ->where('client', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:clients.edit')
    ->name('clients.edit');
Route::post('/clients/{client}', [ClientController::class, 'update'])
    ->where('client', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:clients.edit')
    ->name('clients.update');

Route::post('/clients/{client}/status', [ClientController::class, 'status'])
    ->where('client', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:clients.status')
    ->name('clients.status');

Route::post('/clients/{client}/invite', [ClientController::class, 'invite'])
    ->where('client', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:clients.invite')
    ->name('clients.invite');

/*
 * Employees — the first module with real writes (2026-09-08).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE GUARD IS ON THE ROUTE
 *
 * `permission:` is the second barrier (§5); the realm has already been checked
 * by the file this is written in. Declared here rather than inside the
 * controller because a check in a method is one somebody can forget to write,
 * and nothing fails when they do — the route just works for everybody. On the
 * route it is visible next to the thing it guards, and a route added without
 * one is conspicuous.
 *
 * Three write permissions and not one: correcting a designation, hiring
 * somebody and closing their record are different sizes of act, and a role that
 * should do the first is not automatically one that should do the third.
 *
 * There is no delete, at any permission. Attendance, payroll and the audit log
 * all point back at an employee.
 *
 * `create` is declared BEFORE `{employee}`, or the word "create" would be read
 * as a staff ID and 404.
 * ─────────────────────────────────────────────────────────────────────────────
 */
Route::get('/employees', [EmployeeController::class, 'index'])
    ->middleware('permission:employees.view')
    ->name('employees.index');

Route::get('/employees/create', [EmployeeController::class, 'create'])
    ->middleware('permission:employees.create')
    ->name('employees.create');
Route::post('/employees', [EmployeeController::class, 'store'])
    ->middleware('permission:employees.create')
    ->name('employees.store');

Route::get('/employees/{employee}', [EmployeeController::class, 'show'])
    ->where('employee', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:employees.view')
    ->name('employees.show');

Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])
    ->where('employee', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:employees.edit')
    ->name('employees.edit');
Route::post('/employees/{employee}', [EmployeeController::class, 'update'])
    ->where('employee', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:employees.edit')
    ->name('employees.update');

Route::post('/employees/{employee}/status', [EmployeeController::class, 'status'])
    ->where('employee', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:employees.deactivate')
    ->name('employees.status');

/*
 * Teams (2026-09-09).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * `/teams/mine` DELIBERATELY CARRIES NO PERMISSION
 *
 * §12.1 keeps the personal and managing faces as separate pages, and the
 * personal one is the viewer's own membership — reachable by anybody signed in,
 * for the same reason their own payslip is. It takes no parameter either: the
 * person comes from the session, so there is nothing to change to somebody
 * else's.
 *
 * The managing face keeps `teams.view`, which every staff role holds.
 *
 * Membership is its own permission because it is the routine act — a Team Lead
 * does it within their own team, which the route cannot express and the
 * controller checks (§2.6). `teams.edit` is the wider one: renaming a team,
 * moving it between states, naming its lead.
 *
 * There is no delete. Tasks and projects will point at teams.
 * ─────────────────────────────────────────────────────────────────────────────
 */
Route::get('/teams', [TeamController::class, 'index'])
    ->middleware('permission:teams.view')
    ->name('teams.index');

// Before the {team} route, or these are read as team references.
Route::get('/teams/mine', [TeamController::class, 'mine'])->name('teams.mine');

Route::get('/teams/create', [TeamController::class, 'create'])
    ->middleware('permission:teams.create')
    ->name('teams.create');
Route::post('/teams', [TeamController::class, 'store'])
    ->middleware('permission:teams.create')
    ->name('teams.store');

Route::get('/teams/{team}', [TeamController::class, 'show'])
    ->where('team', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:teams.view')
    ->name('teams.show');

Route::get('/teams/{team}/edit', [TeamController::class, 'edit'])
    ->where('team', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:teams.edit')
    ->name('teams.edit');
Route::post('/teams/{team}', [TeamController::class, 'update'])
    ->where('team', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:teams.edit')
    ->name('teams.update');

/*
 * The route guard is the floor here, not the whole rule: a Team Lead reaching
 * these holds `teams.members`, and the controller then refuses a team they do
 * not lead.
 */
Route::post('/teams/{team}/members', [TeamController::class, 'addMember'])
    ->where('team', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:teams.members')
    ->name('teams.members.store');
Route::post('/teams/{team}/members/remove', [TeamController::class, 'removeMember'])
    ->where('team', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:teams.members')
    ->name('teams.members.remove');

/*
 * Projects (2026-09-09).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE EOD ROUTES CARRY NO PERMISSION, AND THAT IS NOT AN OVERSIGHT
 *
 * Writing your own end-of-day update is not an authority — it is reporting your
 * own day — so it is guarded by an ownership check instead: the controller
 * refuses anybody who is not on the project. A permission would be the wrong
 * shape entirely, because it would either be held by everybody (and guard
 * nothing) or would stop people reporting the work they are doing.
 *
 * `projects.publish` IS a permission, and separate from `projects.edit`. It
 * puts a sentence written at six in the evening in front of the company it is
 * about, which is a disclosure and not an edit.
 *
 * `/projects/mine` and `/projects/updates` are the personal face (§12.1) and
 * take no parameter: the person comes from the session.
 * ─────────────────────────────────────────────────────────────────────────────
 */
Route::get('/projects', [ProjectController::class, 'index'])
    ->middleware('permission:projects.view')
    ->name('projects.index');

// These sit before /projects/{project} or they are read as project references.
Route::get('/projects/mine', [ProjectController::class, 'mine'])->name('projects.mine');
Route::get('/projects/updates', [ProjectController::class, 'updates'])->name('projects.updates');

Route::get('/projects/create', [ProjectController::class, 'create'])
    ->middleware('permission:projects.create')
    ->name('projects.create');
Route::post('/projects', [ProjectController::class, 'store'])
    ->middleware('permission:projects.create')
    ->name('projects.store');

Route::get('/projects/{project}', [ProjectController::class, 'show'])
    ->where('project', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:projects.view')
    ->name('projects.show');

Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])
    ->where('project', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:projects.edit')
    ->name('projects.edit');
Route::post('/projects/{project}', [ProjectController::class, 'update'])
    ->where('project', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:projects.edit')
    ->name('projects.update');

Route::get('/projects/{project}/eod', [ProjectController::class, 'createUpdate'])
    ->where('project', '[A-Za-z0-9-]{1,32}')
    ->name('projects.updates.create');
Route::post('/projects/{project}/eod', [ProjectController::class, 'storeUpdate'])
    ->where('project', '[A-Za-z0-9-]{1,32}')
    ->name('projects.updates.store');

Route::post('/projects/{project}/updates/{update}/visibility', [ProjectController::class, 'publishUpdate'])
    ->where('project', '[A-Za-z0-9-]{1,32}')
    ->where('update', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:projects.publish')
    ->name('projects.updates.visibility');

/*
 * Tasks (2026-09-09).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * COMPLETING A TASK CARRIES NO PERMISSION, AND THAT IS THE POINT
 *
 * Saying you have finished your own work is not an authority. The route is open
 * to any signed-in staff member and the controller refuses anybody who is not
 * the assignee, the lead of the team holding it, or somebody who may edit tasks
 * outright (§2.6).
 *
 * `tasks.assign` is a permission, and the route guard is only its floor: a Team
 * Lead holding it may assign within a team they actually lead and no other.
 * ─────────────────────────────────────────────────────────────────────────────
 */
Route::get('/tasks', [TaskController::class, 'index'])
    ->middleware('permission:tasks.view')
    ->name('tasks.index');

// Before /tasks/{task}, or these are read as task references.
Route::get('/tasks/mine', [TaskController::class, 'mine'])->name('tasks.mine');
Route::get('/tasks/team', [TaskController::class, 'team'])
    ->middleware('permission:tasks.view')
    ->name('tasks.team');

Route::get('/tasks/create', [TaskController::class, 'create'])
    ->middleware('permission:tasks.create')
    ->name('tasks.create');
Route::post('/tasks', [TaskController::class, 'store'])
    ->middleware('permission:tasks.create')
    ->name('tasks.store');

Route::get('/tasks/{task}', [TaskController::class, 'show'])
    ->where('task', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:tasks.view')
    ->name('tasks.show');

Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])
    ->where('task', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:tasks.edit')
    ->name('tasks.edit');
Route::post('/tasks/{task}', [TaskController::class, 'update'])
    ->where('task', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:tasks.edit')
    ->name('tasks.update');

Route::post('/tasks/{task}/assign', [TaskController::class, 'assign'])
    ->where('task', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:tasks.assign')
    ->name('tasks.assign');

// No permission: the controller's ownership check is the whole rule.
Route::post('/tasks/{task}/complete', [TaskController::class, 'complete'])
    ->where('task', '[A-Za-z0-9-]{1,32}')
    ->name('tasks.complete');

Route::get('/tickets', [TicketController::class, 'index'])
    ->middleware('permission:tickets.view')
    ->name('tickets.index');

// Before /tickets/{ticket}, or these are read as ticket references. The three
// personal queues carry no permission: they are the viewer's own work.
Route::get('/tickets/mine', [TicketController::class, 'mine'])->name('tickets.mine');
Route::get('/tickets/assigned', [TicketController::class, 'assigned'])->name('tickets.assigned');
Route::get('/tickets/projects', [TicketController::class, 'projects'])->name('tickets.projects');

// The review queue is the triage team's, not everybody's.
Route::get('/tickets/escalated', [TicketController::class, 'escalated'])
    ->middleware('permission:tickets.triage')
    ->name('tickets.escalated');

/*
 * Raising one needs nothing beyond being staff. A ticket is how somebody asks
 * for help, and a permission on that is a permission to ask.
 */
Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');

Route::get('/tickets/{ticket}', [TicketController::class, 'show'])
    ->where('ticket', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:tickets.view')
    ->name('tickets.show');

/*
 * Replying is not an authority either — anybody who can see the ticket can
 * answer it. Triage IS: routing, prioritising and escalating are the support
 * team's judgement, and `tickets.triage` is the key for it.
 */
Route::post('/tickets/{ticket}/comment', [TicketController::class, 'comment'])
    ->where('ticket', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:tickets.view')
    ->name('tickets.comment');
Route::post('/tickets/{ticket}/triage', [TicketController::class, 'triage'])
    ->where('ticket', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:tickets.triage')
    ->name('tickets.triage');

Route::get('/invoices', [InvoiceController::class, 'index'])
    ->middleware('permission:invoices.view')
    ->name('invoices.index');

// Before /invoices/{invoice}, or "create" is read as an invoice number.
Route::get('/invoices/create', [InvoiceController::class, 'create'])
    ->middleware('permission:invoices.manage')
    ->name('invoices.create');

Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
    ->where('invoice', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:invoices.view')
    ->name('invoices.show');

/*
 * Writes the backend phase implements. Named now so the forms are real forms
 * carrying CSRF tokens rather than dead markup.
 *
 * Note what is *not* here: there is no DELETE. An invoice number must never
 * leave the sequence — withdrawal is a cancellation that keeps the record and
 * its number. Adding a destroy route later would be a mistake, not a feature.
 */
Route::post('/invoices', [InvoiceController::class, 'store'])
    ->middleware('permission:invoices.manage')
    ->name('invoices.store');
Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'storePayment'])
    ->where('invoice', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:invoices.manage')
    ->name('invoices.payments.store');
Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])
    ->where('invoice', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:invoices.manage')
    ->name('invoices.send');
Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])
    ->where('invoice', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:invoices.manage')
    ->name('invoices.cancel');

/*
 * Salary. The most sensitive routes in the application — see
 * App\Http\Controllers\SalaryController for what the backend owes them.
 *
 * Note the shape of the payslip route: it takes a period and no employee, so
 * the person is resolved from the session and there is no identifier to tamper
 * with. The management view of someone else's run is separate and separately
 * guarded. A safe common path beats an ownership check somebody has to remember
 * to write.
 */
Route::get('/salary', [SalaryController::class, 'index'])
    ->middleware('permission:salary.view')
    ->name('salary.index');
// Before /salary/{employee}/{period}, or these are read as employee references.
Route::get('/salary/mine', [SalaryController::class, 'mine'])->name('salary.mine');
Route::get('/salary/payslip/{period}', [SalaryController::class, 'payslip'])
    ->where('period', '[0-9]{4}-[0-9]{2}')
    ->name('salary.payslip');
Route::get('/salary/{employee}/{period}', [SalaryController::class, 'show'])
    ->where('employee', '[A-Za-z0-9-]{1,32}')
    ->where('period', '[0-9]{4}-[0-9]{2}')
    ->middleware('permission:salary.view')
    ->name('salary.show');

/*
 * Marking people paid is a two-step flow on purpose. The first step is a read:
 * it names who is about to be marked paid and asks. Marking twelve people paid
 * by mis-click is hard to notice and awkward to undo, so the destructive step
 * is always the second one.
 *
 * `salary.pay.confirm` is a POST that renders — a dozen employee ids should not
 * end up in a URL that gets bookmarked, shared or written to an access log.
 */
Route::post('/salary/pay/confirm', [SalaryController::class, 'confirmPayment'])
    ->middleware('permission:salary.manage')
    ->name('salary.pay.confirm');

/*
 * The writes the backend phase implements. Both need an audit entry (§6), both
 * are restricted to whoever holds the finance permission (§2.6), and marking
 * paid must be IDEMPOTENT — running it against an already-paid record must not
 * move its payment date.
 *
 * Note what is not here: no route deletes a salary record, and none marks
 * somebody paid who has no payslip on file.
 */
Route::post('/salary/pay', [SalaryController::class, 'pay'])
    ->middleware('permission:salary.manage')
    ->name('salary.pay');
Route::post('/salary/{employee}/{period}/payslip', [SalaryController::class, 'storePayslip'])
    ->where('employee', '[A-Za-z0-9-]{1,32}')
    ->where('period', '[0-9]{4}-[0-9]{2}')
    ->middleware('permission:salary.manage')
    ->name('salary.payslip.store');

/*
 * The download carries NO permission on the route, and that is deliberate: your
 * own payslip is yours, and the controller is where the two answers — your own,
 * or `salary.view` — are decided together. A route-level key would have locked
 * people out of their own payslip.
 *
 * It is also the only way a stored file leaves this application: never a static
 * path, always after a check and an audit entry (§6).
 */
Route::get('/salary/{employee}/{period}/payslip/download', [SalaryController::class, 'downloadPayslip'])
    ->where('employee', '[A-Za-z0-9-]{1,32}')
    ->where('period', '[0-9]{4}-[0-9]{2}')
    ->name('salary.payslip.download');

Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
// Before /leave/{leaveRequest}, or these are read as request references.
Route::get('/leave/mine', [LeaveController::class, 'mine'])->name('leave.mine');
Route::get('/leave/request', [LeaveController::class, 'create'])->name('leave.create');
Route::get('/leave/{leaveRequest}', [LeaveController::class, 'show'])
    ->where('leaveRequest', '[A-Za-z0-9-]{1,32}')
    ->name('leave.show');

/*
 * The writes the backend phase implements.
 *
 * Approving and rejecting are separate routes rather than one endpoint taking a
 * decision parameter: they are opposite acts, and a single handler is a single
 * place for a default to be wrong. Rejecting requires a reason — the handover's
 * reject button captured nothing, which leaves the person guessing why.
 *
 * Three rules the writes must honour (§2.6, §6): `leave.approve` is its own
 * permission; NOBODY decides their own request, owner included; and a decision
 * is only valid on a still-pending request, checked inside the transaction so
 * two approvers cannot both decide it.
 */
Route::post('/leave', [LeaveController::class, 'store'])->name('leave.store');

Route::post('/leave/{leaveRequest}/approve', [LeaveController::class, 'approve'])
    ->where('leaveRequest', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:leave.approve')
    ->name('leave.approve');
Route::post('/leave/{leaveRequest}/reject', [LeaveController::class, 'reject'])
    ->where('leaveRequest', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:leave.approve')
    ->name('leave.reject');

/*
 * Withdrawing carries no permission: it is the requester's own act on their own
 * request, and the controller refuses anybody else. An approver refusing leave
 * is a rejection, with a reason — calling it a withdrawal would put words in
 * somebody's mouth.
 */
Route::post('/leave/{leaveRequest}/cancel', [LeaveController::class, 'cancel'])
    ->where('leaveRequest', '[A-Za-z0-9-]{1,32}')
    ->name('leave.cancel');

Route::get('/meetings', [MeetingController::class, 'index'])
    ->middleware('permission:meetings.view')
    ->name('meetings.index');

// Before /meetings/{meeting}, or "schedule" is read as a meeting reference.
Route::get('/meetings/schedule', [MeetingController::class, 'create'])
    ->middleware('permission:meetings.schedule')
    ->name('meetings.create');

Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])
    ->where('meeting', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:meetings.view')
    ->name('meetings.show');

/*
 * The writes the backend phase implements, all of which are Google Calendar API
 * calls — see App\Support\Meetings\GoogleMeetProvider for what each has to get
 * right.
 *
 * `store` creates the event and the conference: only a project manager or the
 * system admin may (decided 2026-08-28). `request` is the client's act, which
 * produces a record with no Google event until somebody creates it.
 *
 * There is no route that records an RSVP. Responses belong to Google Calendar —
 * people accept or decline in their own calendar and this application reads
 * that back.
 */
Route::post('/meetings', [MeetingController::class, 'store'])
    ->middleware('permission:meetings.schedule')
    ->name('meetings.store');
/*
 * Requesting a meeting moved to the client realm on 2026-09-07, as
 * `client.meetings.request`. It was always described as the client's act, and
 * once /client existed, a client's POST sitting at a /meetings URL was a route
 * that realm middleware would refuse to the only people meant to use it.
 */
Route::post('/meetings/{meeting}/create', [MeetingController::class, 'createEvent'])
    ->where('meeting', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:meetings.schedule')
    ->name('meetings.create.event');
Route::post('/meetings/{meeting}/cancel', [MeetingController::class, 'cancel'])
    ->where('meeting', '[A-Za-z0-9-]{1,32}')
    ->middleware('permission:meetings.schedule')
    ->name('meetings.cancel');

/*
 * Announcements and notifications are two surfaces, not one (decided
 * 2026-08-28). The board is written by a person and broadcast; the bell is
 * generated by an event and addressed to one reader. Task and ticket events go
 * to the bell — a board they flood is a board nobody reads.
 */
Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
// Before /announcements/{announcement}, or these are read as references.
Route::get('/announcements/manage', [AnnouncementController::class, 'manage'])
    ->middleware('permission:announcements.post')
    ->name('announcements.manage');
Route::get('/announcements/compose', [AnnouncementController::class, 'create'])
    ->middleware('permission:announcements.post')
    ->name('announcements.create');
Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])
    ->where('announcement', '[A-Za-z0-9-]{1,40}')
    ->name('announcements.show');

/*
 * Writes the backend phase implements. Posting needs `announcements.post` —
 * HR, project managers and the owner.
 *
 * Note what is absent: nothing creates a milestone. Birthdays and work
 * anniversaries are computed from employee records on every request, because a
 * stored one would be wrong the following year and would survive somebody
 * opting out of their own being announced.
 */
Route::post('/announcements', [AnnouncementController::class, 'store'])
    ->middleware('permission:announcements.post')
    ->name('announcements.store');
Route::post('/announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])
    ->where('announcement', '[A-Za-z0-9-]{1,40}')
    ->middleware('permission:announcements.post')
    ->name('announcements.publish');
Route::post('/announcements/{announcement}/expire', [AnnouncementController::class, 'expire'])
    ->where('announcement', '[A-Za-z0-9-]{1,40}')
    ->middleware('permission:announcements.post')
    ->name('announcements.expire');

Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
/*
 * Marking read is the reader's own act; nobody clears anybody else's.
 *
 * No permission on either route, and no `{notification}` on this one. Both are
 * decisions rather than omissions — see the head of NotificationController.
 */
Route::post('/notifications/read', [NotificationController::class, 'read'])->name('notifications.read');

/*
 * Attendance. Read the shape of this group carefully, because it is the
 * decision the module is built on (2026-09-03).
 *
 * There is NO approve route, and no queue for one to feed. A check-in is a
 * fact, not a request: the record counts from the moment it is made. Rejecting
 * is a correction applied afterwards by HR, with a reason.
 *
 * Note also what check-in and check-out do NOT take: no employee, no date, no
 * time. The person comes from the session and the clock comes from the server,
 * so there is no parameter for anyone to tamper with — the same reason the
 * payslip route takes a period and no employee.
 */
Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
// Before /attendance/{record}, or "mine" is read as a record reference.
Route::get('/attendance/mine', [AttendanceController::class, 'mine'])->name('attendance.mine');
Route::get('/attendance/{record}', [AttendanceController::class, 'show'])
    // The staff id half is no longer pinned to EMP000: test and future accounts
    // carry other shapes, and a route pattern that quietly 404s a real record is
    // worse than one that lets the lookup answer.
    ->where('record', 'ATT-[0-9]{4}-[0-9]{2}-[0-9]{2}-[A-Za-z0-9-]{1,16}')
    ->name('attendance.show');

/*
 * The writes the backend phase implements.
 *
 * Both clock routes must be idempotent per person per day, enforced by a unique
 * key on (employee, date) rather than by the button being hidden — a double
 * submit must produce one record, and a second check-out must not move the
 * first one's time.
 *
 * Rejecting requires a reason. "Rejected" with no explanation is the version
 * somebody has to come and ask about, and this is their attendance record.
 * `attendance.reject` is its own permission (§2.6), every rejection is audited
 * (§6), and nobody rejects their own record.
 *
 * Restoring exists because a rejection made in error must be reversible —
 * otherwise the correction mechanism needs a correction mechanism.
 *
 * Note what is NOT here: nothing approves, nothing edits a recorded time, and
 * nothing deletes a record. A wrong record is rejected and stays legible.
 */
/*
 * The clock. No permission and no parameters: the person is the session and the
 * time is the server's, so there is nothing to guard beyond being staff with an
 * employment record — which the controller checks, because a Mentor has none.
 */
Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
Route::post('/attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');

/*
 * Correcting a record IS a permission, and its own one (§2.6). The controller
 * adds the second half of the rule: nobody rejects their own record.
 */
Route::post('/attendance/{record}/reject', [AttendanceController::class, 'reject'])
    ->where('record', 'ATT-[0-9]{4}-[0-9]{2}-[0-9]{2}-[A-Za-z0-9-]{1,16}')
    ->middleware('permission:attendance.reject')
    ->name('attendance.reject');
Route::post('/attendance/{record}/restore', [AttendanceController::class, 'restore'])
    ->where('record', 'ATT-[0-9]{4}-[0-9]{2}-[0-9]{2}-[A-Za-z0-9-]{1,16}')
    ->middleware('permission:attendance.reject')
    ->name('attendance.restore');

/*
 * My Profile. Four pages, because the four "tabs" are four different things —
 * a form, a set of preferences, a security action and a log — and each is a
 * URL so it is bookmarkable and separately guarded.
 *
 * Note what the routes do NOT take: no employee parameter, anywhere. This is
 * the signed-in person's own profile, resolved from the session, so there is no
 * identifier for anyone to change to somebody else's. Viewing a colleague's
 * record is `employees.show`, which is a different page with different rules.
 * The same reason the payslip route takes a period and no employee.
 */
Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
Route::get('/profile/preferences', [ProfileController::class, 'preferences'])->name('profile.preferences');
Route::get('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
Route::get('/profile/activity', [ProfileController::class, 'activity'])->name('profile.activity');

/*
 * The writes the backend phase implements — see App\Http\Controllers\
 * ProfileController for what each owes.
 *
 * `profile.update` validates against ProfilePolicy::selfEditable() and DROPS
 * every other key. A field rendered disabled is not protected; the browser is
 * not where that rule lives.
 *
 * Email and password are separate routes from the details form on purpose.
 * They are the login credentials (§4.1, §4.7): changing an email is confirmed
 * from both addresses and changing a password needs the current one, neither of
 * which is something to bury in a Save button under eleven other fields.
 *
 * Note what is absent: nothing here writes name, department, designation,
 * reporting line, date of birth or role. Those are HR's, and a route that let
 * somebody set their own designation would make the record meaningless.
 */
Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
Route::post('/profile/preferences', [ProfileController::class, 'updatePreferences'])
    ->name('profile.preferences.update');
Route::post('/profile/password', [ProfileController::class, 'updatePassword'])
    ->name('profile.password.update');
Route::post('/profile/email', [ProfileController::class, 'changeEmail'])->name('profile.email.change');

/*
 * The photo. Stored on the private disk with the documents, so it needs a route
 * to be seen at all — and that route takes no identifier either: it serves the
 * signed-in person's own. Who may see whose photograph is a decision about the
 * Employees module, not this one.
 */
Route::post('/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
Route::get('/profile/photo', [ProfileController::class, 'showPhoto'])->name('profile.photo.show');

/*
 * Documents are downloaded through a route that checks who is asking and writes
 * an audit entry, never served as a static file. A PAN or Aadhaar scan under a
 * guessable path in the webroot is a link somebody can forward.
 */
Route::get('/profile/documents/{document}', [ProfileController::class, 'downloadDocument'])
    ->where('document', 'DOC-[0-9]{4}')
    ->name('profile.documents.download');
Route::post('/profile/documents', [ProfileController::class, 'storeDocument'])
    ->name('profile.documents.store');

// Deferred to v2. §12 keeps the navigation entries so adding the modules later
// reshuffles nothing users have learned, but the pages 404 until then — the
// 404 view recognises them and says "not built yet" rather than "not found".
//
// Reports joined them on 2026-09-03. It had been showing the generic "coming
// soon" placeholder, which is the weaker of the two answers: a placeholder page
// invites somebody to check back, while a deferred 404 says plainly that the
// module is planned and its place is already reserved. Reports also has nothing
// to report on until the modules it would summarise have real data behind them.
Route::get('/leads', [ModulePlaceholderController::class, 'missing'])->name('leads.index');
Route::get('/calendar', [ModulePlaceholderController::class, 'missing'])->name('calendar.index');
Route::get('/reports', [ModulePlaceholderController::class, 'missing'])->name('reports.index');
