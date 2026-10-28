<?php

use App\Http\Controllers\Verify\AdminController as VerifyAdminController;
use App\Http\Controllers\Verify\PublicController as VerifyPublicController;
use App\Http\Middleware\VerifyAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| verify.zephryxlabs.in (2026-10-29)
|--------------------------------------------------------------------------
|
| Mounted by routes/web.php on the verify domain only, and before everything
| else, so these win on that host and do not exist on the CRM's. See
| config/verify.php and App\Http\Middleware\VerifyHost.
|
| Public: the search page, a person's verify page (needs a signature - see
| VerifySignature), their photo under the same signature, and the company
| registration PDF. Rate limited per IP, because an Employee ID is short and
| a search box is an invitation to try them all.
|
| /admin: the CRM owner's Admin account only (VerifyAdmin).
*/

Route::get('/', [VerifyPublicController::class, 'landing'])->name('verify.landing');
Route::post('/search', [VerifyPublicController::class, 'search'])
    ->middleware('throttle:10,1')
    ->name('verify.search');

Route::get('/verify/{employee}', [VerifyPublicController::class, 'show'])
    ->where('employee', '[A-Za-z0-9-]{1,32}')
    ->middleware('throttle:60,1')
    ->name('verify.show');
Route::get('/verify/{employee}/photo', [VerifyPublicController::class, 'photo'])
    ->where('employee', '[A-Za-z0-9-]{1,32}')
    ->middleware('throttle:60,1')
    ->name('verify.photo');

Route::get('/company-registration', [VerifyPublicController::class, 'registration'])
    ->middleware('throttle:30,1')
    ->name('verify.registration');

Route::middleware(VerifyAdmin::class)->prefix('admin')->group(function () {
    Route::get('/', [VerifyAdminController::class, 'dashboard'])->name('verify.admin');
    Route::get('/qr/{employee}', [VerifyAdminController::class, 'qr'])
        ->where('employee', '[A-Za-z0-9-]{1,32}')
        ->name('verify.admin.qr');
    Route::post('/settings', [VerifyAdminController::class, 'settings'])->name('verify.admin.settings');
    Route::post('/registration', [VerifyAdminController::class, 'registration'])->name('verify.admin.registration');
});
