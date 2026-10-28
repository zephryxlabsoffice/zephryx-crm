@extends('layouts.standalone')

@php use App\Support\SupportContact; @endphp

@section('title', 'Account on hold')

@section('content')
    {{-- Where an account put on hold from a "This wasn't me" button lands at
         sign-in. Held, not closed: see App\Support\Auth\SecurityLock. --}}
    <section class="standalone-card">
        <div class="standalone-mark" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
        </div>

        <h1 class="standalone-title">Your account is on hold for security</h1>

        <p class="standalone-message">
            Somebody said an email about this account was not their doing, so it has been
            put on hold to keep it safe. Nobody can sign in to it until an administrator
            has checked with you.
        </p>

        <p class="standalone-message">
            Once it is restored you will be emailed a link to choose a new password.
        </p>

        <div class="standalone-actions">
            <a class="btn btn-primary" href="{{ SupportContact::mailto(config('zephryx.brand.name').' - account on hold') }}">Contact Admin</a>

            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline" type="submit">Sign out</button>
                </form>
            @else
                <a class="btn btn-outline" href="{{ route('login') }}">Back to sign in</a>
            @endauth
        </div>
    </section>
@endsection
