@extends('layouts.standalone')

@php use App\Support\Auth\SecurityLock; @endphp

@section('title', 'This wasn\'t me')

@section('content')
    {{-- Reached from the red button in an account email. A page with a
         second press, not a lock on the first GET, because mail scanners
         open links in emails on their own. See App\Support\Auth\SecurityLock. --}}
    <section class="standalone-card">
        <div class="standalone-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="11" width="16" height="10" rx="2"/>
                <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
            </svg>
        </div>

        @if ($locked)
            <h1 class="standalone-title">The account is on hold</h1>

            <p class="standalone-message">
                Nobody can sign in to it, and it has been signed out everywhere. The
                administrators have been told. Once they have checked with you, they will
                send you a link to choose a new password.
            </p>
        @else
            <h1 class="standalone-title">Did not ask for this?</h1>

            <p class="standalone-message">
                {{ ucfirst(SecurityLock::describe($reason)) }} for {{ $name }}'s
                {{ config('zephryx.brand.name') }} account was sent to {{ $address }}.
            </p>

            <p class="standalone-message">
                If you did not ask for it, put the account on hold. It will be signed out
                everywhere, nobody will be able to sign in, and the administrators will be
                told straight away.
            </p>

            <div class="standalone-actions">
                <form method="POST" action="{{ $action }}">
                    @csrf
                    <button class="btn btn-alarm" type="submit">This wasn't me, put the account on hold</button>
                </form>
            </div>
        @endif
    </section>
@endsection
