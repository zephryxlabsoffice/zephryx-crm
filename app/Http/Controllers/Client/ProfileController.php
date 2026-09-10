<?php

namespace App\Http\Controllers\Client;

use App\Support\Audit\AuditLog;
use App\Support\ClientPortal;
use App\Support\SupportContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function __construct(protected AuditLog $audit)
    {
    }

    public function show(Request $request): Response
    {
        $client = $this->client($request);

        return response()->view('client.profile.show', $this->shell($request, 'profile') + [
            'profile' => $client,
            'editable' => self::EDITABLE,
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
        $client = $this->client($request);

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
     * POST /client/profile/photo — not built, and not a stub.
     *
     * A client account is an ORGANISATION, not a person: several people at the
     * client share it, and the portal greets the company rather than a name.
     * What this route would upload is a company logo, which is a different
     * thing from a profile photo — it appears on invoices, it belongs in the
     * client record HR keeps, and whether a client may set the logo that
     * appears on their own invoice is a question nobody has answered.
     *
     * The staff photo landed because a person's own photograph is
     * unambiguously theirs. This one is left refusing until somebody decides
     * what it is for.
     */
    public function photo(Request $request): RedirectResponse
    {
        abort(501);
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
