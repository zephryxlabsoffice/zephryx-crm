<?php

namespace App\Http\Controllers\Client;

use App\Models\Client;
use App\Support\Demo\DemoClientPortal;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Shared ground for the client portal's pages.
 *
 * Its whole job is answering "which client is this?" in exactly one place. Every
 * page in this namespace passes that answer to DemoClientPortal, which cannot be
 * asked for anything without it — see the head of that class for why the reads
 * are shaped that way rather than checked in the controllers.
 */
abstract class PortalController extends Controller
{
    /**
     * The signed-in client.
     *
     * ─────────────────────────────────────────────────────────────────────────
     * THE SESSION, AND ONE METHOD.
     *
     * `client_ref` on the account is the whole answer, and every page in the
     * portal is correctly scoped by this one method returning it — because none
     * of them resolves the client for itself, and DemoClientPortal will not
     * answer without being told whose data to read.
     *
     * The development switch below still overrides it in local + debug, for the
     * reason in preview(). It is the last thing here that will go.
     * ─────────────────────────────────────────────────────────────────────────
     */
    protected function client(Request $request): string
    {
        return $this->preview($request)
            ?? $this->record($request)?->name
            ?? DemoClientPortal::viewer();
    }

    /**
     * The client record behind the session.
     *
     * `client_ref` holds the client's REFERENCE — an identifier, because a name
     * is not one: two companies can share a name, and renaming one must not
     * detach its invoices from its portal.
     *
     * The name is what `client()` returns because the reads above it are still
     * DemoClientPortal's, and those are keyed on the name. That is the last
     * thing keeping this method's return value a string; when the portal's data
     * comes from real tables, the ownership check becomes this record's id and
     * the name goes back to being only a heading.
     */
    protected function record(Request $request): ?Client
    {
        $reference = $request->user()?->client_ref;

        return $reference === null ? null : Client::where('reference', $reference)->first();
    }

    /**
     * The client being previewed, in development only.
     *
     * Ownership rules are invisible when there is only ever one client on
     * screen: every page looks correct, because everything on it does belong to
     * the one client there is. Switching between two is how you notice that a
     * page is showing somebody else's invoice, and it is the only way to review
     * this portal honestly before sessions exist.
     *
     * It is `?as=`, validated against the known clients so it cannot put
     * arbitrary text on the page, and DemoClientPortal::switchable() is empty
     * outside local + debug — so in a deployed application this method always
     * returns null and the parameter does nothing at all.
     *
     * Unlike the staff dashboard's role preview, this one cannot narrow or
     * widen anything: it changes WHOSE data is shown, not how much. Once
     * sessions exist it must not survive in any form.
     */
    protected function preview(Request $request): ?string
    {
        if (DemoClientPortal::switchable() === []) {
            return null;
        }

        $validated = $request->validate([
            'as' => ['nullable', 'string', Rule::in(DemoClientPortal::switchable())],
        ]);

        return $validated['as'] ?? null;
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
            'client' => $this->client($request),
            // Empty outside local + debug, which removes the switcher entirely.
            'switchable' => DemoClientPortal::switchable(),
            'previewing' => $this->preview($request),
        ];
    }
}
