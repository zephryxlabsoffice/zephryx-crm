<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

/*
 * Production front controller for shared hosting (docs/DEPLOY.md).
 *
 * The web root is public_html and the application lives BESIDE it, in
 * ../zephryx-crm, where no URL can reach .env, vendor or storage. This file
 * replaces public/index.php only in the upload built by deploy/build.php; the
 * repository's own public/index.php stays the stock one for local development.
 */
$appDir = __DIR__.'/../zephryx-crm';

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $appDir.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $appDir.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $appDir.'/bootstrap/app.php';

// public_path() must name public_html, not zephryx-crm/public — the Vite
// manifest (build/manifest.json) is read through it on every page.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
