@php use App\Support\SupportContact; @endphp

{{--
    How to reach a person.

    The designer put a version of this on three of the client screens, and the
    instinct was right — the moment something is wrong, "who do I tell" is the
    only question on the page that matters.

    A mailto:, not a contact form. §6 and §13.3: a form here would be an
    unauthenticated-adjacent write endpoint reachable from every page of the
    portal, and the address is one the client already has.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Need a hand?</strong>
    </div>

    <p class="dash-note">
        For anything urgent, a support ticket reaches the team working on your
        project and stays attached to it — which an email thread does not.
    </p>

    <div class="dash-punch-action">
        <a class="btn btn-outline" href="{{ route('client.tickets.create') }}">Raise a ticket</a>
        <a class="dash-link" href="{{ SupportContact::mailto('Client portal') }}">{{ SupportContact::address() }}</a>
    </div>
</section>
