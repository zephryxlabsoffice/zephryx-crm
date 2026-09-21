<?php

use App\Http\Controllers\Client\AnnouncementController as ClientAnnouncementController;
use App\Http\Controllers\Client\ContactController as ClientContactController;
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
| through App\Support\ClientPortal, which takes the client record as its first
| argument on every method and has no all(). A controller here cannot fetch a
| record without saying whose it is, because no such call exists to write. Read
| the head of that class before adding a page.
|
| Note the URL shape: no client identifier anywhere, not even on the detail
| routes. The client is the session; the id in the URL is only ever a project,
| invoice or ticket reference, and one belonging to somebody else 404s exactly
| as an imaginary one does.
|
| The `?as=` development switch that used to override the session is gone with
| the fixture it was written for. See App\Http\Controllers\Client\
| PortalController.
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
 * The document, through a route that checks ownership and audits the
 * access — never a static file under a guessable path, for the same reason
 * profile documents are not. An invoice names what a company pays and for
 * what.
 *
 * The real uploaded PDF, not a page built to look like one — invoices became
 * an upload rather than a generated document on 2026-09-21, which is what
 * makes this possible at all. `view` opens it in the browser; `download`
 * saves it. See Client\InvoiceController.
 */
Route::get('/invoices/{invoice}/document/view', [ClientInvoiceController::class, 'viewDocument'])
    ->where('invoice', '[A-Za-z0-9-]{1,32}')
    ->name('invoices.document.view');
Route::get('/invoices/{invoice}/document/download', [ClientInvoiceController::class, 'downloadDocument'])
    ->where('invoice', '[A-Za-z0-9-]{1,32}')
    ->name('invoices.document.download');

Route::get('/tickets', [ClientTicketController::class, 'index'])->name('tickets.index');
// Before /tickets/{ticket}, or "raise" is read as a ticket reference.
Route::get('/tickets/raise', [ClientTicketController::class, 'create'])->name('tickets.create');
Route::get('/tickets/{ticket}', [ClientTicketController::class, 'show'])
    ->where('ticket', '[A-Za-z0-9-]{1,32}')
    ->name('tickets.show');

/*
 * `tickets.store` sets the ticket's client from the SESSION and there is no
 * `client_id` in its validated set, so a posted one is dropped before it can
 * be read — not overwritten afterwards, which is the version that breaks the
 * day somebody reorders two lines. Same rule as the check-in routes taking
 * no employee.
 *
 * A comment posted here is always client-visible by construction: the
 * client wrote it. `visibility` is not read from the request at all, so
 * there is no branch that could be made to post an internal note.
 */
Route::post('/tickets', [ClientTicketController::class, 'store'])->name('tickets.store');
Route::post('/tickets/{ticket}/comment', [ClientTicketController::class, 'comment'])
    ->where('ticket', '[A-Za-z0-9-]{1,32}')
    ->name('tickets.comment');

Route::get('/meetings', [ClientMeetingController::class, 'index'])->name('meetings.index');
// Before /meetings/{meeting}, or "request" is read as a meeting reference.
Route::get('/meetings/request', [ClientMeetingController::class, 'create'])->name('meetings.create');

/*
 * A request is not a meeting. It produces a record with no Google Calendar
 * event behind it, which a project manager or the system admin then creates
 * — the client cannot put anything in anybody's calendar directly, and
 * there is deliberately no route here that would let them.
 */
Route::post('/meetings/request', [ClientMeetingController::class, 'store'])->name('meetings.request');

Route::get('/meetings/{meeting}', [ClientMeetingController::class, 'show'])
    ->where('meeting', '[A-Za-z0-9-]{1,32}')
    ->name('meetings.show');

/*
 * The one write a client may make against a meeting: calling it off. Google
 * is told first — see Client\MeetingController::cancel — and ownership runs
 * through ClientPortal::meetingModel, the same filter every other read in
 * this realm uses.
 */
Route::post('/meetings/{meeting}/cancel', [ClientMeetingController::class, 'cancel'])
    ->where('meeting', '[A-Za-z0-9-]{1,32}')
    ->name('meetings.cancel');

/*
 * The client board — see Client\AnnouncementController. Read-only: nothing
 * here writes anything, so there is no `store` alongside these two.
 */
Route::get('/announcements', [ClientAnnouncementController::class, 'index'])->name('announcements.index');
Route::get('/announcements/{announcement}', [ClientAnnouncementController::class, 'show'])
    ->where('announcement', '[A-Za-z0-9-]{1,32}')
    ->name('announcements.show');

Route::get('/profile', [ClientProfileController::class, 'show'])->name('profile.show');

/*
 * What a client may change about their own record is a short list: how to
 * reach them. Not their company name, not their industry, and nothing
 * about their projects or what they owe — those are ours, and a form
 * letting a client edit them would make the record meaningless.
 */
Route::post('/profile', [ClientProfileController::class, 'update'])->name('profile.update');

/*
 * Refused as a 501 until 2026-09-17, on the grounds that a client account is an
 * ORGANISATION and what it uploads is a company LOGO — which appears on
 * invoices, and whether a client may set the image on their own invoice was a
 * question nobody had answered.
 *
 * The invoices reversal removed the question: an invoice is an uploaded PDF
 * now, so nothing this application generates carries a client logo anywhere.
 * What is left is an avatar on their own portal, which the owner has said they
 * may change like anybody else.
 *
 * Neither route takes an identifier. The record is the session's client, so
 * there is nothing in a URL to point at somebody else's.
 */
Route::post('/profile/photo', [ClientProfileController::class, 'photo'])->name('profile.photo');
Route::get('/profile/photo', [ClientProfileController::class, 'showPhoto'])->name('profile.photo.show');

/*
 * The additional contacts beyond the one main contact on the profile above —
 * several people, one login. No edit route: a wrong entry is cheaper to
 * delete and re-add than a second edit form is worth building. Ownership on
 * the delete runs through the query itself, not a check afterwards — see
 * Client\ContactController::destroy.
 */
Route::post('/contacts', [ClientContactController::class, 'store'])->name('contacts.store');
Route::delete('/contacts/{contact}', [ClientContactController::class, 'destroy'])
    ->where('contact', '[0-9]+')
    ->name('contacts.destroy');
