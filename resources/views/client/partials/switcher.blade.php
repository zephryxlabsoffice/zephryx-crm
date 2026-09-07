{{--
    The client switcher.

    Development only — DemoClientPortal::switchable() is empty outside local +
    debug, so this block does not exist in a deployed portal.

    ─────────────────────────────────────────────────────────────────────────────
    WHY A PORTAL LIKE THIS NEEDS ONE

    Ownership rules are invisible when there is only ever one client on screen.
    Every page looks correct, because everything on it does belong to the one
    client there is — including, if a query is wrong, the parts that do not.

    Switching between two clients is how you see it: open an invoice as DGL,
    switch to GreenLeaf, and the same URL must 404. That is the check §6 asks
    for, and it cannot be performed at all against a single fixed client.

    Links, not a form: a GET that changes nothing, bookmarkable, no CSRF token
    needed because it writes nothing.
    ─────────────────────────────────────────────────────────────────────────────
--}}
@if ($switchable !== [])
    <section class="cl-switch" aria-label="Client preview">
        <div class="cl-switch-hd">
            <span class="cl-switch-tag">Development preview</span>
            <p>
                Viewing the portal as <strong>{{ $client }}</strong>. Switch clients to
                check that a page shows one client's records and nobody else's — a
                URL that works here must return "not found" for everyone else.
            </p>
        </div>

        <div class="cl-switch-list">
            @foreach ($switchable as $option)
                <a class="chip-btn {{ $option === $client ? 'chip-btn-accent' : '' }}"
                   href="{{ request()->fullUrlWithQuery(['as' => $option]) }}">{{ $option }}</a>
            @endforeach
        </div>
    </section>
@endif
