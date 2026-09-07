<?php

use App\Http\Controllers\Auth\AccountStatusController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ThemeController;
use App\Http\Middleware\EnsureRealm;
use App\Support\Realm;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
|
| Everything reachable without a session.
|
| The three realms live in their own files, mounted at the foot of this one
| behind their own middleware (§3.1). Anything declared HERE is public, which
| makes the boundary a property of where a route is written rather than
| something to check on review.
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
 * Where an ex-employee or a former client lands.
 *
 * Read App\Http\Controllers\Auth\AccountStatusController before touching this:
 * the page is NOT reachable by URL, and that is the point. It renders on a
 * one-shot session value the login flow sets AFTER the password has been
 * checked and found correct, and redirects to the login form otherwise.
 *
 * §4.2 still stands at the form itself — an inactive account gets the generic
 * "Invalid credentials" there, because saying more before a password is checked
 * tells anybody who types an address whether it belongs to a real account. This
 * route is what happens one step later, to somebody who has already proved they
 * are the account holder.
 */
Route::get('/account/inactive', AccountStatusController::class)->name('account.inactive');

/*
|--------------------------------------------------------------------------
| The three realms
|--------------------------------------------------------------------------
|
| ─────────────────────────────────────────────────────────────────────────────
| THE GUARD IS THE FILE (foundation spec §3.1, 2026-09-07)
|
| Each realm's routes live in their own file, mounted here behind their own
| middleware. A route is inside the guard because of the file it is written in —
| not because somebody remembered to add middleware to it.
|
| This replaced one flat 750-line file whose staff section carried a comment
| reading "NOT YET GUARDED … it is added with authentication in the backend
| phase". That comment was correct for as long as it lasted, and it was also the
| whole risk: the next person to add a staff route would have added it outside a
| guard that did not exist yet, and nothing would have told them.
|
| Realm enforcement is independent of permissions and runs first, before any
| data is read. A client session on /employees is refused whatever keys it
| holds; a staff session on /admin likewise, including the owner's, who signs
| into that realm as a separate account. Permissions are the second barrier,
| applied per page by the RBAC engine (§5).
| ─────────────────────────────────────────────────────────────────────────────
|
*/

Route::middleware(EnsureRealm::for(Realm::STAFF))
    ->group(base_path('routes/staff.php'));

Route::prefix('client')
    ->name('client.')
    ->middleware(EnsureRealm::for(Realm::CLIENT))
    ->group(base_path('routes/client.php'));

Route::prefix('admin')
    ->name('admin.')
    ->middleware(EnsureRealm::for(Realm::ADMIN))
    ->group(base_path('routes/admin.php'));

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

    /*
     * The closed-account page, which is otherwise reachable only by having a
     * correct password on a closed account — so in practice never, during
     * development. A page nobody can look at is a page that ships with a broken
     * layout and a sentence nobody read.
     *
     * Local + debug for the same reason as the error previews above: anywhere
     * else, this would let somebody show a convincing "your account is closed"
     * at a URL of their choosing.
     */
    Route::get('/dev/account/{kind}', [AccountStatusController::class, 'preview'])
        ->where('kind', 'staff|client')
        ->name('dev.account');

    /*
     * ─────────────────────────────────────────────────────────────────────────
     * SIGN IN AS A SEEDED ACCOUNT, WITHOUT A PASSWORD. LOCAL + DEBUG ONLY.
     *
     * Read this before reaching for it anywhere else.
     *
     * Realm middleware and the RBAC engine landed before §4's credential check
     * did, which left every page in the application correctly guarded and
     * completely unreachable. This is the stopgap: it authenticates as a seeded
     * account so all three realms can be opened and reviewed.
     *
     * It is an authentication bypass. It is registered inside the same local +
     * debug block as the error previews, so it does not exist in a deployed
     * application at all — and unlike a flag somebody could flip, there is no
     * configuration that turns it on elsewhere.
     *
     * DELETE THIS ROUTE when LoginController::attempt is real. Not "leave it,
     * it's only local": a bypass that outlives its reason is one `APP_DEBUG=true`
     * on the wrong server away from being the whole security model.
     * ─────────────────────────────────────────────────────────────────────────
     */
    Route::get('/dev/sign-in/{account}', function (string $account) {
        $user = \App\Models\User::where('user_id', $account)->first();

        abort_if($user === null, 404, 'No seeded account with that id. Run: php artisan db:seed');

        auth()->login($user);
        request()->session()->regenerate();

        return redirect(\App\Support\Realm::dashboardFor($user));
    })->where('account', '[A-Za-z0-9-]{1,32}')->name('dev.sign-in');
}
