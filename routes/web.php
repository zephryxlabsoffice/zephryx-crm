<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\LandingController;
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
