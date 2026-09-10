<?php

namespace App\Http\Controllers\Client;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Shared ground for the client portal's pages.
 *
 * Its whole job is answering "which client is this?" in exactly one place. Every
 * page in this namespace passes that answer to App\Support\ClientPortal, which
 * cannot be asked for anything without it — see the head of that class for why
 * the reads are shaped that way rather than checked in the controllers.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * THE `?as=` PREVIEW SWITCH IS GONE
 *
 * It existed while the portal read a fixture keyed on a company NAME, and it
 * existed for one honest reason: ownership rules are invisible when there is
 * only ever one client on screen, and switching between two is how you notice a
 * page showing somebody else's invoice.
 *
 * Its own docblock said it must not survive sessions. It has not. The portal
 * reads real tables scoped by a foreign key now, the seeded client accounts can
 * each be signed into, and a query parameter that changes whose data a page
 * shows is a thing nobody should have to reason about being safe.
 * ─────────────────────────────────────────────────────────────────────────────
 */
abstract class PortalController extends Controller
{
    /**
     * The client record behind the session.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE SESSION, AND ONE METHOD.
     *
     * `client_ref` on the account is the whole answer, and every page in the
     * portal is correctly scoped by this one method returning it — because none
     * of them resolves the client for itself, and ClientPortal will not answer
     * without being told whose data to read.
     *
     * It holds the client's REFERENCE rather than a name, because a name is not
     * an identifier: two companies can share one, and renaming a company must
     * not detach its invoices from its own portal.
     *
     * An account whose client does not exist is a session scoped to nothing.
     * That is a 403 rather than an empty portal: an empty portal says "you have
     * no projects", which is a different and untrue statement.
     * ─────────────────────────────────────────────────────────────────────────
     */
    protected function client(Request $request): Client
    {
        $reference = $request->user()?->client_ref;

        $client = $reference === null ? null : Client::where('reference', $reference)->first();

        abort_if($client === null, 403);

        return $client;
    }

    /**
     * View data every page in the portal needs.
     *
     * @return array<string, mixed>
     */
    protected function shell(Request $request, string $activeNav): array
    {
        return [
            'activeNav' => $activeNav,
            // The NAME, because this is the heading on the page. Everything
            // that reads data takes the record.
            'client' => $this->client($request)->name,
            'clientRecord' => $this->client($request),
        ];
    }
}
