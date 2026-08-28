<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\ModulePlaceholderController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\ShellPreferenceController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Everything reachable without a session. Realm-guarded route groups (staff,
| client, admin — foundation spec §3) are added as those surfaces are built,
| each behind its own middleware so new pages inherit the guard automatically.
|
*/

Route::get('/', LandingController::class)->name('landing');

Route::post('/theme', [ThemeController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('theme.store');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| Front end only for now — see App\Http\Controllers\Auth\LoginController. The
| throttles below are the shape §4.2 calls for and stay in place when the real
| credential check lands; they are not a substitute for it.
|
*/

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'attempt'])
    ->middleware('throttle:10,1')
    ->name('login.attempt');

Route::get('/login/verify', [LoginController::class, 'showVerify'])->name('login.verify');
Route::post('/login/verify', [LoginController::class, 'verify'])
    ->middleware('throttle:10,1')
    ->name('login.verify.attempt');

Route::post('/login/resend', [LoginController::class, 'resend'])
    ->middleware('throttle:5,1')
    ->name('login.resend');

Route::get('/forgot-password', [PasswordResetController::class, 'showRequest'])->name('password.forgot');
Route::post('/forgot-password', [PasswordResetController::class, 'request'])
    ->middleware('throttle:5,1')
    ->name('password.request');

Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])
    ->where('token', '[A-Za-z0-9._-]{1,128}')
    ->name('password.reset.form');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:5,1')
    ->name('password.reset');

Route::post('/logout', LogoutController::class)->name('logout');

/*
|--------------------------------------------------------------------------
| Staff realm
|--------------------------------------------------------------------------
|
| NOT YET GUARDED. Spec §3.1 requires a realm check in middleware on this
| whole group before any data is read; it is added with authentication in the
| backend phase. Nothing here reads data yet.
|
| Every navigation entry resolves to a real route from the start so the shell's
| shape never shifts as modules land (§12). Each module replaces its own
| placeholder when it is built.
|
*/

Route::post('/shell', [ShellPreferenceController::class, 'store'])
    ->middleware('throttle:60,1')
    ->name('shell.store');

Route::get('/dashboard', fn () => app(ModulePlaceholderController::class)('dashboard'))->name('dashboard');

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
Route::post('/meetings/request', fn () => abort(501))->name('meetings.request');
Route::post('/meetings/{meeting}/create', fn () => abort(501))
    ->where('meeting', '[A-Za-z0-9-]{1,32}')
    ->name('meetings.create.event');
Route::post('/meetings/{meeting}/cancel', fn () => abort(501))
    ->where('meeting', '[A-Za-z0-9-]{1,32}')
    ->name('meetings.cancel');

foreach ([
    'attendance' => 'attendance.index',
    'reports' => 'reports.index',
    'announcements' => 'announcements.index',
] as $segment => $name) {
    Route::get('/'.$segment, fn () => app(ModulePlaceholderController::class)($segment))->name($name);
}

Route::get('/profile', fn () => app(ModulePlaceholderController::class)('profile'))->name('profile.show');
Route::get('/notifications', fn () => app(ModulePlaceholderController::class)('notifications'))->name('notifications.index');

// Deferred to v2. §12 keeps the navigation entries so adding the modules later
// reshuffles nothing users have learned, but the pages 404 until then — the
// 404 view recognises them and says "not built yet" rather than "not found".
Route::get('/leads', [ModulePlaceholderController::class, 'missing'])->name('leads.index');
Route::get('/calendar', [ModulePlaceholderController::class, 'missing'])->name('calendar.index');

/*
|--------------------------------------------------------------------------
| Error page previews
|--------------------------------------------------------------------------
|
| Error pages are hard to see on purpose, which is how they end up shipping
| broken. These render them on demand. Local + debug only: registering them
| anywhere else would let anyone show staff a convincing "session expired" or
| "maintenance" page at a URL of their choosing.
|
*/

if (app()->environment('local') && config('app.debug')) {
    Route::get('/dev/errors/{code}', fn (string $code) => response()->view("errors.{$code}", [], (int) $code))
        ->where('code', '403|404|419|429|500|503')
        ->name('dev.errors');
}
