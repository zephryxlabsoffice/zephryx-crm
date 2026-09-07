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

Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
Route::get('/clients/create', fn () => app(ModulePlaceholderController::class)('clients'))->name('clients.create');
Route::get('/clients/{client}', fn () => app(ModulePlaceholderController::class)('clients'))->name('clients.show');

Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
Route::get('/employees/create', fn () => app(ModulePlaceholderController::class)('employees'))->name('employees.create');
Route::get('/employees/{employee}', fn () => app(ModulePlaceholderController::class)('employees'))->name('employees.show');

Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
// Before the {team} route, or "mine" is read as a team ID.
Route::get('/teams/mine', [TeamController::class, 'mine'])->name('teams.mine');
Route::get('/teams/create', fn () => app(ModulePlaceholderController::class)('teams'))->name('teams.create');
Route::get('/teams/{team}', [TeamController::class, 'show'])
    ->where('team', '[A-Za-z0-9-]{1,32}')
    ->name('teams.show');
Route::get('/teams/{team}/edit', fn () => app(ModulePlaceholderController::class)('teams'))
    ->where('team', '[A-Za-z0-9-]{1,32}')
    ->name('teams.edit');

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
// These sit before /projects/{project} or they are read as project references.
Route::get('/projects/mine', [ProjectController::class, 'mine'])->name('projects.mine');
Route::get('/projects/updates', [ProjectController::class, 'updates'])->name('projects.updates');
Route::get('/projects/create', fn () => app(ModulePlaceholderController::class)('projects'))->name('projects.create');
Route::get('/projects/{project}', [ProjectController::class, 'show'])
    ->where('project', '[A-Za-z0-9-]{1,32}')
    ->name('projects.show');
Route::get('/projects/{project}/edit', fn () => app(ModulePlaceholderController::class)('projects'))
    ->where('project', '[A-Za-z0-9-]{1,32}')
    ->name('projects.edit');
Route::get('/projects/{project}/eod', fn () => app(ModulePlaceholderController::class)('projects'))
    ->where('project', '[A-Za-z0-9-]{1,32}')
    ->name('projects.updates.create');

Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
// Before /tasks/{task}, or these are read as task references.
Route::get('/tasks/mine', [TaskController::class, 'mine'])->name('tasks.mine');
Route::get('/tasks/team', [TaskController::class, 'team'])->name('tasks.team');
Route::get('/tasks/create', fn () => app(ModulePlaceholderController::class)('tasks'))->name('tasks.create');
Route::get('/tasks/{task}', [TaskController::class, 'show'])
    ->where('task', '[A-Za-z0-9-]{1,32}')
    ->name('tasks.show');
Route::get('/tasks/{task}/edit', fn () => app(ModulePlaceholderController::class)('tasks'))
    ->where('task', '[A-Za-z0-9-]{1,32}')
    ->name('tasks.edit');

Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
// Before /tickets/{ticket}, or these are read as ticket references.
Route::get('/tickets/mine', [TicketController::class, 'mine'])->name('tickets.mine');
Route::get('/tickets/assigned', [TicketController::class, 'assigned'])->name('tickets.assigned');
Route::get('/tickets/projects', [TicketController::class, 'projects'])->name('tickets.projects');
Route::get('/tickets/escalated', [TicketController::class, 'escalated'])->name('tickets.escalated');
Route::get('/tickets/create', fn () => app(ModulePlaceholderController::class)('tickets'))->name('tickets.create');
Route::get('/tickets/{ticket}', [TicketController::class, 'show'])
    ->where('ticket', '[A-Za-z0-9-]{1,32}')
    ->name('tickets.show');

/*
 * Both are writes the backend phase implements. Named now so the forms they
 * belong to are real forms with CSRF tokens rather than dead markup — and so
 * the visibility choice on a comment is a submitted value from day one, not
 * something bolted on later.
 */
Route::post('/tickets/{ticket}/comment', fn () => abort(501))
    ->where('ticket', '[A-Za-z0-9-]{1,32}')
    ->name('tickets.comment');
Route::post('/tickets/{ticket}/triage', fn () => abort(501))
    ->where('ticket', '[A-Za-z0-9-]{1,32}')
    ->name('tickets.triage');

Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
// Before /invoices/{invoice}, or "create" is read as an invoice number.
Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])
    ->where('invoice', '[A-Za-z0-9-]{1,32}')
    ->name('invoices.show');

/*
 * Writes the backend phase implements. Named now so the forms are real forms
 * carrying CSRF tokens rather than dead markup.
 *
 * Note what is *not* here: there is no DELETE. An invoice number must never
 * leave the sequence — withdrawal is a cancellation that keeps the record and
 * its number. Adding a destroy route later would be a mistake, not a feature.
 */
Route::post('/invoices', fn () => abort(501))->name('invoices.store');
Route::post('/invoices/{invoice}/payments', fn () => abort(501))
    ->where('invoice', '[A-Za-z0-9-]{1,32}')
    ->name('invoices.payments.store');
Route::post('/invoices/{invoice}/send', fn () => abort(501))
    ->where('invoice', '[A-Za-z0-9-]{1,32}')
    ->name('invoices.send');
Route::post('/invoices/{invoice}/cancel', fn () => abort(501))
    ->where('invoice', '[A-Za-z0-9-]{1,32}')
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
Route::get('/salary', [SalaryController::class, 'index'])->name('salary.index');
// Before /salary/{employee}/{period}, or these are read as employee references.
Route::get('/salary/mine', [SalaryController::class, 'mine'])->name('salary.mine');
Route::get('/salary/payslip/{period}', [SalaryController::class, 'payslip'])
    ->where('period', '[0-9]{4}-[0-9]{2}')
    ->name('salary.payslip');
Route::get('/salary/{employee}/{period}', [SalaryController::class, 'show'])
    ->where('employee', '[A-Za-z0-9-]{1,32}')
    ->where('period', '[0-9]{4}-[0-9]{2}')
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
Route::post('/salary/pay/confirm', [SalaryController::class, 'confirmPayment'])->name('salary.pay.confirm');

/*
 * The writes the backend phase implements. Both need an audit entry (§6), both
 * are restricted to whoever holds the finance permission (§2.6), and marking
 * paid must be IDEMPOTENT — running it against an already-paid record must not
 * move its payment date.
 *
 * Note what is not here: no route deletes a salary record, and none marks
 * somebody paid who has no payslip on file.
 */
Route::post('/salary/pay', fn () => abort(501))->name('salary.pay');
Route::post('/salary/{employee}/{period}/payslip', fn () => abort(501))
    ->where('employee', '[A-Za-z0-9-]{1,32}')
    ->where('period', '[0-9]{4}-[0-9]{2}')
    ->name('salary.payslip.store');
Route::get('/salary/{employee}/{period}/payslip/download', fn () => abort(501))
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
Route::post('/leave', fn () => abort(501))->name('leave.store');
Route::post('/leave/{leaveRequest}/approve', fn () => abort(501))
    ->where('leaveRequest', '[A-Za-z0-9-]{1,32}')
    ->name('leave.approve');
Route::post('/leave/{leaveRequest}/reject', fn () => abort(501))
    ->where('leaveRequest', '[A-Za-z0-9-]{1,32}')
    ->name('leave.reject');
Route::post('/leave/{leaveRequest}/cancel', fn () => abort(501))
    ->where('leaveRequest', '[A-Za-z0-9-]{1,32}')
    ->name('leave.cancel');

Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
// Before /meetings/{meeting}, or "schedule" is read as a meeting reference.
Route::get('/meetings/schedule', [MeetingController::class, 'create'])->name('meetings.create');
Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])
    ->where('meeting', '[A-Za-z0-9-]{1,32}')
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
Route::post('/meetings', fn () => abort(501))->name('meetings.store');
/*
 * Requesting a meeting moved to the client realm on 2026-09-07, as
 * `client.meetings.request`. It was always described as the client's act, and
 * once /client existed, a client's POST sitting at a /meetings URL was a route
 * that realm middleware would refuse to the only people meant to use it.
 */
Route::post('/meetings/{meeting}/create', fn () => abort(501))
    ->where('meeting', '[A-Za-z0-9-]{1,32}')
    ->name('meetings.create.event');
Route::post('/meetings/{meeting}/cancel', fn () => abort(501))
    ->where('meeting', '[A-Za-z0-9-]{1,32}')
    ->name('meetings.cancel');

/*
 * Announcements and notifications are two surfaces, not one (decided
 * 2026-08-28). The board is written by a person and broadcast; the bell is
 * generated by an event and addressed to one reader. Task and ticket events go
 * to the bell — a board they flood is a board nobody reads.
 */
Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
// Before /announcements/{announcement}, or these are read as references.
Route::get('/announcements/manage', [AnnouncementController::class, 'manage'])->name('announcements.manage');
Route::get('/announcements/compose', [AnnouncementController::class, 'create'])->name('announcements.create');
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
Route::post('/announcements', fn () => abort(501))->name('announcements.store');
Route::post('/announcements/{announcement}/publish', fn () => abort(501))
    ->where('announcement', '[A-Za-z0-9-]{1,40}')
    ->name('announcements.publish');
Route::post('/announcements/{announcement}/expire', fn () => abort(501))
    ->where('announcement', '[A-Za-z0-9-]{1,40}')
    ->name('announcements.expire');

Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
// Marking read is the reader's own act; nobody clears anybody else's.
Route::post('/notifications/read', fn () => abort(501))->name('notifications.read');

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
    ->where('record', 'ATT-[0-9]{4}-[0-9]{2}-[0-9]{2}-EMP[0-9]{3}')
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
Route::post('/attendance/check-in', fn () => abort(501))->name('attendance.check-in');
Route::post('/attendance/check-out', fn () => abort(501))->name('attendance.check-out');
Route::post('/attendance/{record}/reject', fn () => abort(501))
    ->where('record', 'ATT-[0-9]{4}-[0-9]{2}-[0-9]{2}-EMP[0-9]{3}')
    ->name('attendance.reject');
Route::post('/attendance/{record}/restore', fn () => abort(501))
    ->where('record', 'ATT-[0-9]{4}-[0-9]{2}-[0-9]{2}-EMP[0-9]{3}')
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
Route::post('/profile', fn () => abort(501))->name('profile.update');
Route::post('/profile/preferences', fn () => abort(501))->name('profile.preferences.update');
Route::post('/profile/password', fn () => abort(501))->name('profile.password.update');
Route::post('/profile/email', fn () => abort(501))->name('profile.email.change');
Route::post('/profile/photo', fn () => abort(501))->name('profile.photo');

/*
 * Documents are downloaded through a route that checks who is asking and writes
 * an audit entry, never served as a static file. A PAN or Aadhaar scan under a
 * guessable path in the webroot is a link somebody can forward.
 */
Route::get('/profile/documents/{document}', fn () => abort(501))
    ->where('document', 'DOC-[0-9]{4}')
    ->name('profile.documents.download');
Route::post('/profile/documents', fn () => abort(501))->name('profile.documents.store');

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
