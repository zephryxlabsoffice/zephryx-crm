<?php

namespace App\Http\Controllers\Client;

use App\Support\Audit\AuditLog;
use App\Support\ClientPortal;
use App\Support\Documents\DocumentStore;
use App\Support\Images\PhotoIntake;
use App\Support\SupportContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The client's own record.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * A VERY SHORT LIST OF WHAT THEY MAY CHANGE
 *
 * How to reach them. That is the list.
 *
 * Not the company name — invoices, projects and tickets reference a client by
 * it, and letting the far side rewrite it would rename their own invoice
 * history. Not the industry, which is ours to record. Not the account status,
 * obviously, and nothing at all about projects, deadlines or what is owed.
 *
 * `client.profile.update` therefore validates against a list of editable keys
 * and drops every other one, exactly as the staff ProfileController does with
 * ProfilePolicy::selfEditable. A field rendered read-only is not protected; the
 * browser is not where that rule lives.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * The page also carries the support contact, because "how do I reach a human"
 * is the actual reason a client opens this page — the reference put a Support
 * Contact card here and that instinct was right.
 */
class ProfileController extends PortalController
{
    /**
     * The keys `client.profile.update` may write, and the whole of it.
     *
     * The validated set below is built from this list, so a field that stops
     * being the client's stops being accepted without anybody remembering to
     * change two places.
     *
     * @var list<string>
     */
    public const EDITABLE = ['contact_name', 'contact_email', 'contact_phone', 'billing_address'];

    public function __construct(
        protected AuditLog $audit,
        protected DocumentStore $documents,
        protected PhotoIntake $photos,
    ) {
    }

    public function show(Request $request): Response
    {
        $client = $this->requireActiveClient($request);

        return response()->view('client.profile.show', $this->shell($request, 'profile') + [
            'profile' => $client,
            'editable' => self::EDITABLE,
            // Additional contacts beyond the one main contact above — several
            // people, one login. See App\Models\ClientContact and
            // Client\ContactController for the add/remove routes.
            'contacts' => $client->contacts,
            'stats' => ClientPortal::stats($client),
            'support' => [
                'email' => SupportContact::address(),
                'mailto' => SupportContact::mailto('Client portal — '.$client->name),
            ],
        ]);
    }

    /**
     * POST /client/profile
     *
     * ─────────────────────────────────────────────────────────────────────────
     * FOUR KEYS, AND EVERY OTHER ONE IS DROPPED
     *
     * Not refused — dropped. A request arriving with `name`, `status` or
     * `industry` in it saves the four it is allowed to and ignores the rest
     * without saying so, which is the behaviour that does not teach anybody
     * which fields exist.
     *
     * The record is the SESSION'S client, resolved by PortalController::client
     * from `client_ref`. No route in this realm takes a client identifier, so
     * there is nothing to change to somebody else's.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function update(Request $request): RedirectResponse
    {
        $client = $this->requireActiveClient($request);

        $data = $request->validate([
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'string', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'billing_address' => ['nullable', 'string', 'max:500'],
        ]);

        $before = $client->only(self::EDITABLE);

        $client->update(collect($data)->only(self::EDITABLE)->all());

        $this->audit->record(
            action: AuditLog::CLIENT_UPDATED,
            actor: $request->user(),
            entityType: 'client',
            entityId: $client->reference,
            /*
             * The values, not just the field names — unlike the staff profile,
             * where the audit entry names the fields and withholds the contents
             * because they are somebody's home address and emergency contact.
             * These are a company's business contact details, which the client
             * publishes anyway and which somebody here has to be able to check
             * against an invoice that bounced.
             */
            before: $this->describe($before),
            after: $this->describe($client->only(self::EDITABLE)).' — changed by the client',
            request: $request,
        );

        return redirect()
            ->route('client.profile.show')
            ->with('status', 'Saved. We will use these from now on.')
            ->with('status_tone', 'success');
    }

    /**
     * POST /client/profile/photo
     *
     * ─────────────────────────────────────────────────────────────────────────
     * REFUSED UNTIL 2026-09-17, AND THE REASON IT STOPPED BEING REFUSED
     *
     * This route threw a 501 and said why: a client account is an ORGANISATION,
     * so what it uploads is a company logo — and a logo appears on invoices,
     * which made "may a client set the logo on their own invoice" a question
     * nobody had answered.
     *
     * The invoices reversal removed the question rather than answering it.
     * Invoices are an uploaded PDF now, so nothing this application generates
     * carries a client logo anywhere. What is left is an avatar on their own
     * portal, and the owner has said they may change it like anybody else.
     *
     * IT SAVES IMMEDIATELY, UNLIKE THE STAFF PHOTO
     *
     * A staff photograph became a request to HR on 2026-09-14 because the
     * profile is the company's record of a person, checked against documents.
     * A client's avatar is a record of nothing: there is no document to check it
     * against and no HR relationship to check it. Queueing it would put a
     * picture in front of somebody whose only possible answer is "fine".
     *
     * The cleaning is identical either way — parsed and rebuilt from its picture
     * segments, so nothing the camera recorded alongside it survives. See
     * App\Support\Images\PhotoIntake.
     * ─────────────────────────────────────────────────────────────────────────
     */
    public function photo(Request $request): RedirectResponse
    {
        $client = $this->requireActiveClient($request);

        $request->validate([
            'photo' => [
                'required', 'file',
                'max:'.(int) (PhotoIntake::MAX_BYTES / 1024),
                // Checked by content through finfo, then again by the parser.
                // The extension is whatever somebody typed.
                'mimes:'.implode(',', PhotoIntake::ALLOWED),
            ],
        ]);

        try {
            $clean = $this->photos->clean($request->file('photo'));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['photo' => $e->getMessage()]);
        }

        $was = $client->photo_path;

        $stored = $this->documents->putBytes(
            'clients/'.$client->id.'/photo',
            $clean['extension'],
            $clean['contents'],
        );

        $client->update(['photo_path' => $stored['path']]);

        // Only after the new path is saved. The other order leaves them with no
        // picture at all if the write fails.
        if ($was !== null && $was !== $stored['path']) {
            $this->documents->forget($was);
        }

        $this->audit->record(
            action: AuditLog::CLIENT_UPDATED,
            actor: $request->user(),
            entityType: 'client',
            entityId: $client->reference,
            before: $was === null ? 'no picture' : 'picture on record',
            after: 'Picture replaced ('.$clean['width'].'×'.$clean['height'].', metadata stripped) — changed by the client',
            request: $request,
        );

        return redirect()
            ->route('client.profile.show')
            ->with('status', 'Your picture is saved. Anything the camera recorded with it was not.')
            ->with('status_tone', 'success');
    }

    /**
     * GET /client/profile/photo — the signed-in client's own.
     *
     * On the private disk with everything else, so it needs a route to be seen
     * at all — and this one takes no identifier. The record is the SESSION'S
     * client, so there is nothing in the URL to change to somebody else's.
     */
    public function showPhoto(Request $request): StreamedResponse
    {
        $path = $this->requireActiveClient($request)->photo_path;

        abort_if($path === null || ! $this->documents->exists($path), 404);

        return $this->documents->stream($path);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function describe(array $fields): string
    {
        $set = collect($fields)->filter(fn ($v) => $v !== null && $v !== '');

        return $set->isEmpty()
            ? 'nothing on record'
            : $set->map(fn ($v, $k) => str_replace('_', ' ', $k).': '.$v)->join(' · ');
    }
}
