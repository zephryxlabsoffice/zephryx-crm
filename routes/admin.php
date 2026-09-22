<?php

use App\Http\Controllers\Admin\AccessController as AdminAccessController;
use App\Http\Controllers\Admin\AccountController as AdminAccountController;
use App\Http\Controllers\Admin\AuditController as AdminAuditController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\IntegrationsController as AdminIntegrationsController;
use App\Http\Controllers\Admin\MasterDataController as AdminMasterDataController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Panel
|--------------------------------------------------------------------------
|
| GUARDED BY THE FILE IT IS IN (§3.1). routes/web.php mounts this behind
| `realm:admin` with the `/admin` prefix and the `admin.` name prefix.
|
| A staff session is refused here — including the owner's, who signs in to this
| realm as a separate account with its own session cookie. That is why Settings
| is not in the staff sidebar.
|
| ─────────────────────────────────────────────────────────────────────────────
| READ WHAT IS ABSENT
|
| §2.1: the Admin Panel sets the rules and does not participate in them. No
| personal records, and NO OPERATIONAL AUTHORITY — it cannot approve leave, run
| payroll, mark attendance, raise an invoice or assign a task.
|
| That is enforced by absence. There is no route here that approves anything,
| and adding one would be the mistake this comment exists to stop. The panel
| configures who may approve leave; it can never approve any. A test walks the
| router to keep it that way.
|
| The audit log has no write route in either direction — nothing posts to it and
| nothing deletes from it. An audit log with a delete button is not an audit
| log, and this is the account whose actions most need the record.
| ─────────────────────────────────────────────────────────────────────────────
|
| §4.4 gives this realm a stricter session than the others: a 30-minute idle
| timeout with re-authentication, and never remember-me (§4.5). That lands with
| the authentication phase.
|
*/

Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');

Route::get('/accounts', [AdminAccountController::class, 'index'])->name('accounts.index');
Route::get('/accounts/{account}', [AdminAccountController::class, 'show'])
    ->where('account', '[A-Za-z0-9-]{1,32}')
    ->name('accounts.show');

/*
 * Suspending an account is not deleting one: the person's attendance,
 * leave and payslips stay exactly where they are, and their records keep
 * naming them. Nothing here deletes a user, and there is no route that could.
 *
 * THE OWNER ACCOUNT CANNOT BE SUSPENDED OR STRIPPED OF ITS ROLES BY THIS
 * PANEL. It is the only account that can reach these routes, so allowing it
 * would mean one click locks the company out of its own configuration with
 * no way back in that does not involve the database. It is excluded from
 * App\Support\Admin\AccountDirectory's query, so these routes 404 on it — a
 * row nobody may act on is a row that invites the attempt.
 */
Route::post('/accounts/{account}/status', [AdminAccountController::class, 'status'])
    ->where('account', '[A-Za-z0-9-]{1,32}')
    ->name('accounts.status');
Route::post('/accounts/{account}/roles', [AdminAccountController::class, 'roles'])
    ->where('account', '[A-Za-z0-9-]{1,32}')
    ->name('accounts.roles');

/*
 * Three distinct account-security acts, decided 2026-09-21 (review round
 * Q11) — a forgotten password, a stolen session and a compromised device are
 * three different problems, and none of the three routes below substitutes
 * for another. See AdminAccountController's own header on each method.
 */
Route::post('/accounts/{account}/force-password-reset', [AdminAccountController::class, 'forcePasswordReset'])
    ->where('account', '[A-Za-z0-9-]{1,32}')
    ->name('accounts.force-password-reset');
Route::post('/accounts/{account}/sign-out', [AdminAccountController::class, 'signOutEverywhere'])
    ->where('account', '[A-Za-z0-9-]{1,32}')
    ->name('accounts.sign-out');
Route::post('/accounts/{account}/untrust-devices', [AdminAccountController::class, 'untrustDevices'])
    ->where('account', '[A-Za-z0-9-]{1,32}')
    ->name('accounts.untrust-devices');

Route::get('/access', [AdminAccessController::class, 'index'])->name('access.index');

/*
 * Before /access/{role}, or "create" is read as a role key. Answered
 * 2026-09-21 (review round Q12): an admin may create a role, not only
 * assign and edit the seeded ones — the role list is not fixed, and needing
 * a deploy to add one defeats the point of the Admin Panel.
 */
Route::get('/access/create', [AdminAccessController::class, 'create'])->name('access.create');
Route::post('/access', [AdminAccessController::class, 'store'])->name('access.store');

Route::get('/access/{role}', [AdminAccessController::class, 'show'])
    ->where('role', '[a-z_]{1,32}')
    ->name('access.show');

/*
 * Changing what a role may do. Audited with the people it lands on, not
 * just the key that moved (§6) — see App\Support\Admin\AccessDirectory::
 * whoWouldHold, computed before the write, because afterwards the answer to
 * "who would this land on" is "nobody, they hold it already".
 *
 * The Employee base is not a role and must not be editable here (§5): it is
 * granted implicitly to every staff account of kind `employee` precisely so
 * that it cannot be revoked by a role edit.
 */
Route::post('/access/{role}', [AdminAccessController::class, 'update'])
    ->where('role', '[a-z_]{1,32}')
    ->name('access.update');

Route::get('/master-data', [AdminMasterDataController::class, 'index'])->name('master.index');
Route::get('/master-data/{list}', [AdminMasterDataController::class, 'show'])
    ->where('list', '[a-z-]{1,32}')
    ->name('master.show');

/*
 * Note what is missing: DELETE. These lists are referenced by records that
 * already exist, so the destructive act available is deactivation — the row
 * stops being offered and keeps answering for history. See the head of
 * App\Support\Admin\MasterDataDirectory.
 */
Route::post('/master-data/{list}', [AdminMasterDataController::class, 'store'])
    ->where('list', '[a-z-]{1,32}')
    ->name('master.store');
Route::post('/master-data/{list}/deactivate', [AdminMasterDataController::class, 'deactivate'])
    ->where('list', '[a-z-]{1,32}')
    ->name('master.deactivate');

Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings');

/*
 * Changing a setting is TWO steps, and the first one is a read.
 *
 * Several of these values are used to derive attendance and leave on every
 * read rather than being stored, so changing one silently re-judges months
 * of records that already exist. `settings.preview` computes exactly what
 * would move and names it; only the second step writes.
 *
 * It is a POST that renders, like `salary.pay.confirm`, for the same
 * reason: the proposed values should not end up in a URL that gets
 * bookmarked or written to an access log.
 */
Route::post('/settings/preview', [AdminSettingsController::class, 'preview'])->name('settings.preview');
Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');

/*
 * The one Google connection. Its own key (`admin.integrations.view`, see
 * Rbac::ADMIN_BASE) rather than folded into Settings — a Drive credential
 * must not be reachable by the grant that changes the brand name.
 *
 * `test` and `disconnect` are POST for the reason every other admin write is:
 * an act, not a page. `connect` doubles as reconnect/rotate — there is one
 * connection, not a history of them.
 */
Route::get('/integrations', [AdminIntegrationsController::class, 'index'])->name('integrations');
Route::post('/integrations/connect', [AdminIntegrationsController::class, 'connect'])->name('integrations.connect');
Route::post('/integrations/test', [AdminIntegrationsController::class, 'test'])->name('integrations.test');
Route::post('/integrations/disconnect', [AdminIntegrationsController::class, 'disconnect'])->name('integrations.disconnect');

/*
 * The audit log. GET only, in both senses — nothing writes to it through
 * the panel and nothing removes from it. See DemoAudit.
 */
Route::get('/audit', [AdminAuditController::class, 'index'])->name('audit.index');
Route::get('/audit/{entry}', [AdminAuditController::class, 'show'])
    ->where('entry', 'AUD-[0-9]{1,8}')
    ->name('audit.show');
