<?php

namespace App\Http\Controllers\Client;

use App\Support\Demo\DemoClientPortal;
use App\Support\SupportContact;
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
     * TODO (backend phase): validate the posted payload against exactly this
     * list and discard the rest, before anything reaches a model.
     *
     * @var list<string>
     */
    public const EDITABLE = ['contact_name', 'contact_email', 'contact_phone', 'address'];

    public function show(Request $request): Response
    {
        $client = $this->client($request);

        $record = DemoClientPortal::profile($client);

        abort_if($record === null, 404);

        return response()->view('client.profile.show', $this->shell($request, 'profile') + [
            'profile' => $record,
            'editable' => self::EDITABLE,
            'stats' => DemoClientPortal::stats($client),
            'support' => [
                'email' => SupportContact::address(),
                'mailto' => SupportContact::mailto('Client portal — '.$client),
            ],
        ]);
    }
}
