@php use App\Support\ProfilePolicy; @endphp

{{--
    What this page will not let you change, and what to do about it.

    ─────────────────────────────────────────────────────────────────────────────
    A LOCKED FIELD NEEDS A ROUTE OUT

    Showing somebody their own wrong designation with no way to correct it is
    worse than not showing it. The handover solved that by making the fields
    editable, which is the wrong fix — a person who can set their own department
    can grant themselves whatever that department can see.

    The right fix is to say who owns each one and where to go. This module does
    not own the correction, HR does, so it names the surface that does rather
    than growing a request form of its own that nobody would monitor.
    ─────────────────────────────────────────────────────────────────────────────
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Not yours to change</strong>
    </div>

    <ul class="pf-locked">
        @foreach (ProfilePolicy::fieldsOwnedBy(ProfilePolicy::HR) as $field)
            <li class="pf-locked-row">
                <span class="pf-locked-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </span>
                <span class="pf-locked-body">
                    <strong>{{ ProfilePolicy::labelOf($field) }}</strong>
                    <span>{{ ProfilePolicy::whyOf($field) }}</span>
                </span>
            </li>
        @endforeach
    </ul>

    <p class="pf-rail-note">
        If any of these is wrong, raise it with HR — corrections are recorded
        against your account, so a change to your role or department is never
        something that happens quietly.
    </p>

    <a class="btn btn-outline btn-sm pf-rail-btn" href="{{ route(ProfilePolicy::correctionRoute()) }}">
        Ask HR to correct something
    </a>
</section>
