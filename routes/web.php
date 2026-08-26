<?php

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
