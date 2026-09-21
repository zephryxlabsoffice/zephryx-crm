<?php

namespace App\Http\Controllers\Client;

use App\Models\ClientContact;
use App\Support\Audit\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The client's own list of contacts, beyond the one main contact on their
 * profile.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * SEVERAL PEOPLE, ONE LOGIN
 *
 * A client organisation is one account, not one person — the accounts payable
 * contact who wants invoice copies is rarely the person who raises tickets.
 * `contact_name`/`contact_email`/`contact_phone` on the client record stay the
 * MAIN contact, editable through Client\ProfileController exactly as before;
 * this controller adds a table of the rest, self-served.
 *
 * There is no edit here, only add and remove — a wrong entry is cheaper to
 * delete and re-add than to build a second edit form for.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ContactController extends PortalController
{
    public function __construct(protected AuditLog $audit) {}

    /**
     * POST /client/contacts
     */
    public function store(Request $request): RedirectResponse
    {
        $client = $this->requireActiveClient($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'string', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $contact = $client->contacts()->create($data);

        $this->audit->record(
            action: AuditLog::CLIENT_UPDATED,
            actor: $request->user(),
            entityType: 'client',
            entityId: $client->reference,
            after: 'Contact added: '.$contact->name.' — added by the client',
            request: $request,
        );

        return redirect()
            ->route('client.profile.show')
            ->with('status', 'Contact added.')
            ->with('status_tone', 'success');
    }

    /**
     * DELETE /client/contacts/{contact}
     *
     * `$contact` is looked up scoped to THIS client's own row, not just by id
     * — the same ownership-in-the-query rule ClientPortal enforces for reads,
     * applied here to the one write this controller makes. A contact id
     * belonging to another client's account 404s exactly as one that does not
     * exist does.
     */
    public function destroy(Request $request, int $contact): RedirectResponse
    {
        $client = $this->requireActiveClient($request);

        $record = ClientContact::where('client_id', $client->id)->where('id', $contact)->first();

        abort_if($record === null, 404);

        $name = $record->name;
        $record->delete();

        $this->audit->record(
            action: AuditLog::CLIENT_UPDATED,
            actor: $request->user(),
            entityType: 'client',
            entityId: $client->reference,
            after: 'Contact removed: '.$name.' — removed by the client',
            request: $request,
        );

        return redirect()
            ->route('client.profile.show')
            ->with('status', 'Contact removed.')
            ->with('status_tone', 'success');
    }
}
