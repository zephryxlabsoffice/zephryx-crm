<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ModulePlaceholderController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ShellPreferenceController;
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

foreach ([
    'tasks' => 'tasks.index',
    'tickets' => 'tickets.index',
    'invoices' => 'invoices.index',
    'salary' => 'salary.index',
    'attendance' => 'attendance.index',
    'leave' => 'leave.index',
    'meetings' => 'meetings.index',
    'reports' => 'reports.index',
    'announcements' => 'announcements.index',
] as $segment => $name) {
    Route::get('/'.$segment, fn () => app(ModulePlaceholderController::class)($segment))->name($name);
}

Route::get('/profile', fn () => app(ModulePlaceholderController::class)('profile'))->name('profile.show');
Route::get('/notifications', fn () => app(ModulePlaceholderController::class)('notifications'))->name('notifications.index');

// Deferred to v2. §12 keeps the navigation entries so adding the modules later
// reshuffles nothing users have learned, but the pages 404 until then.
Route::get('/leads', [ModulePlaceholderController::class, 'missing'])->name('leads.index');
Route::get('/calendar', [ModulePlaceholderController::class, 'missing'])->name('calendar.index');
