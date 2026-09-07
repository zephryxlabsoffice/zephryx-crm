@extends('layouts.standalone')

@php use App\Support\SupportContact; @endphp

@section('title', 'Account closed')

@section('content')
    {{--
        The end of somebody's access, and often the end of a job or a contract.
        The tone is the whole design brief: plain, short, and not cheerful.

        ─────────────────────────────────────────────────────────────────────────
        WHAT IS NOT SAID, AND WHY

        Nothing here names a date, a reason, a manager or a replacement contact
        by name. Somebody reading this may have just been let go, and a page
        that explains their departure back to them is the wrong place for that
        conversation — it belongs to a person, not a screen.

        Nor does it offer a way back in. There is no "try again", no password
        reset link, no "contact us to reactivate": those imply the state is a
        mistake this page can fix, and it is not.
        ─────────────────────────────────────────────────────────────────────────

        The handover's version used a raster illustration and a Google Fonts
        link. Both are gone — the CDN is refused by §6 and the CSP, and a
        green-tinted PNG cannot follow the theme. The mark below is inline SVG
        on tokens, so it is right in both palettes.
    --}}
    <section class="standalone-card">
        <div class="standalone-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="11" width="16" height="10" rx="2"/>
                <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
            </svg>
        </div>

        @if ($kind === 'client')
            <h1 class="standalone-title">This account is closed</h1>

            <p class="standalone-message">
                Your access to the {{ config('zephryx.brand.name') }} client portal has
                ended. Your projects, invoices and past correspondence are kept on
                our side and are unaffected.
            </p>

            <p class="standalone-message">
                If you were expecting this account to still work, the quickest route
                is to write to us — we can tell you what happened to it.
            </p>
        @else
            <h1 class="standalone-title">Your account is closed</h1>

            <p class="standalone-message">
                Your {{ config('zephryx.brand.name') }} account is no longer active, so
                there is nothing here for you to sign in to. Your records — payslips,
                attendance and leave — are retained by the company.
            </p>

            <p class="standalone-message">
                For anything you still need from them, including documents relating to
                your time here, HR is the right place to ask.
            </p>
        @endif

        <div class="standalone-actions">
            {{-- One action, and it is a mailto: — the same reason the landing
                 page uses one (§6, §13.3). A contact form here would be an
                 unauthenticated write endpoint reachable by anybody whose
                 account has just been closed. --}}
            <a class="btn btn-primary" href="{{ SupportContact::mailto($subject) }}">
                {{ $kind === 'client' ? 'Contact us' : 'Contact HR' }}
            </a>
        </div>

        {{-- No "return to sign in". They have just come from there, and sending
             them back is an invitation to try the same credentials again. --}}
    </section>
@endsection
