<?php

use App\Http\Controllers\Client\DashboardController as ClientDashboardController;
use App\Http\Controllers\Client\InvoiceController as ClientInvoiceController;
use App\Http\Controllers\Client\MeetingController as ClientMeetingController;
use App\Http\Controllers\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Client\ProjectController as ClientProjectController;
use App\Http\Controllers\Client\TicketController as ClientTicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Client realm
|--------------------------------------------------------------------------
|
| GUARDED BY THE FILE IT IS IN (§3.1). routes/web.php mounts this behind
| `realm:client` with the `/client` prefix and the `client.` name prefix, so
| every route below inherits all three.
|
| TWO BARRIERS, AND THIS FILE IS INSIDE THE FIRST.
|
| The realm check refuses a staff session before any data is read. The second
| is ownership (§6) — "a client requesting invoice 47 must be verified as the
| owner of invoice 47", named there as the most common real-world leak in
| applications of this shape.
|
| Ownership is structural rather than remembered: every read in this group goes
| through App\Support\Demo\DemoClientPortal, which takes the client as its first
| argument on every method and has no all(). A controller here cannot fetch a
| record without saying whose it is, because no such call exists to write. Read
| the head of that class before adding a page.
|
| Note the URL shape: no client identifier anywhere, not even on the detail
| routes. The client is the session; the id in the URL is only ever a project,
| invoice or ticket reference, and one belonging to somebody else 404s exactly
| as an imaginary one does.
|
*/

Route::get('/dashboard', ClientDashboardController::class)->name('dashboard');

Route::get('/projects', [ClientProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{project}', [ClientProjectController::class, 'show'])
    ->where('project', '[A-Za-z0-9-]{1,32}')
    ->name('projects.show');

Route::get('/invoices', [ClientInvoiceController::class, 'index'])->name('invoices.index');
Route::get('/invoices/{invoice}', [ClientInvoiceController::class, 'show'])
    ->where('invoice', '[A-Za-z0-9-]{1,32}')
    ->name('invoices.show');

/*
 * A PDF is generated and streamed through a route that checks ownership and
 * audits the download — never a static file under a guessable path, for the
 * same reason profile documents are not. An invoice names what a company
 * pays and for what.
 */
Route::get('/invoices/{invoice}/download', fn () => abort(501))
    ->where('invoice', '[A-Za-z0-9-]{1,32}')
    ->name('invoices.download');

Route::get('/tickets', [ClientTicketController::class, 'index'])->name('tickets.index');
// Before /tickets/{ticket}, or "raise" is read as a ticket reference.
Route::get('/tickets/raise', [ClientTicketController::class, 'create'])->name('tickets.create');
Route::get('/tickets/{ticket}', [ClientTicketController::class, 'show'])
    ->where('ticket', '[A-Za-z0-9-]{1,32}')
    ->name('tickets.show');

/*
 * The writes the backend phase implements.
 *
 * `tickets.store` must set the ticket's client from the SESSION and ignore
 * any client the form sends — otherwise raising a ticket becomes a way to
 * file one against somebody else. Same rule as the check-in routes taking
 * no employee.
 *
 * A comment posted here is always client-visible by construction: the
 * client wrote it. It must never be possible to post an internal note
 * through this route, whatever the payload says.
 */
Route::post('/tickets', fn () => abort(501))->name('tickets.store');
Route::post('/tickets/{ticket}/comment', fn () => abort(501))
    ->where('ticket', '[A-Za-z0-9-]{1,32}')
    ->name('tickets.comment');

Route::get('/meetings', [ClientMeetingController::class, 'index'])->name('meetings.index');
Route::get('/meetings/request', [ClientMeetingController::class, 'create'])->name('meetings.create');

/*
 * A request is not a meeting. It produces a record with no Google Calendar
 * event behind it, which a project manager or the system admin then creates
 * — the client cannot put anything in anybody's calendar directly, and
 * there is deliberately no route here that would let them.
 */
Route::post('/meetings/request', fn () => abort(501))->name('meetings.request');

Route::get('/profile', [ClientProfileController::class, 'show'])->name('profile.show');

/*
 * What a client may change about their own record is a short list: how to
 * reach them. Not their company name, not their industry, and nothing
 * about their projects or what they owe — those are ours, and a form
 * letting a client edit them would make the record meaningless.
 */
Route::post('/profile', fn () => abort(501))->name('profile.update');
Route::post('/profile/photo', fn () => abort(501))->name('profile.photo');
