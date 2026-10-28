@extends('layouts.standalone')

@section('title', 'This wasn\'t me')

@section('content')
    {{-- Reached from the red button in an address-change email. A page with
         a second press, not a lock on the first GET, because mail scanners
         open links in emails on their own. See App\Support\Auth\SecurityLock. --}}
    <section class="standalone-card">
        <div class="standalone-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="11" width="16" height="10" rx="2"/>
                <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
            </svg>
        </div>

        @if ($locked)
            <h1 class="standalone-title">The account is locked</h1>

            <p class="standalone-message">
                Nobody can sign in to it, and it has been signed out everywhere. The
                administrators have been told and will contact the account holder
                before unlocking it.
            </p>
        @else
            <h1 class="standalone-title">Did not ask for this change?</h1>

            <p class="standalone-message">
                An email address on {{ $name }}'s {{ config('zephryx.brand.name') }}
                account was changed, and this email was sent to {{ $address }}.
            </p>

            <p class="standalone-message">
                If you did not ask for it, lock the account. It will be signed out
                everywhere, nobody will be able to sign in, and the administrators will
                be told straight away.
            </p>

            <div class="standalone-actions">
                <form method="POST" action="{{ $action }}">
                    @csrf
                    <button class="btn btn-alarm" type="submit">This wasn't me, lock the account</button>
                </form>
            </div>
        @endif
    </section>
@endsection
