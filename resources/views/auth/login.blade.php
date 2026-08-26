@extends('layouts.auth')

@section('title', 'Login')

@section('form')
    <form class="auth-form" id="auth-form" method="POST" action="{{ route('login.attempt') }}" novalidate data-auth-form>
        @csrf

        <div class="form-brand">
            @include('partials.brand-mark', ['alt' => config('zephryx.brand.name')])
        </div>

        <h1 class="auth-heading">Welcome Back !</h1>

        {{-- Form-level state. Only one banner shows at a time; they are mutually
             exclusive outcomes of a single sign-in attempt (spec §9.2). --}}
        @if ($lockedUntil ?? null)
            {{-- Lockout: too many attempts. The countdown is filled in by
                 resources/js/auth.js and degrades to the static minute figure. --}}
            @include('partials.notice', [
                'tone' => 'warning',
                'title' => 'Too many attempts',
                'message' => 'Sign-in is paused for this account. Try again in about '
                    .max(1, (int) ceil($lockedUntil / 60)).' minute'
                    .(ceil($lockedUntil / 60) === 1.0 ? '' : 's').'.',
            ])
            <p class="sr-only" data-lockout-seconds="{{ (int) $lockedUntil }}"></p>
        @elseif ($errors->has('auth'))
            {{-- Deliberately generic: never distinguishes an unknown identifier
                 from a wrong password (spec §4.2). --}}
            @include('partials.notice', [
                'tone' => 'danger',
                'message' => $errors->first('auth'),
            ])
        @elseif (session('status'))
            @include('partials.notice', [
                'tone' => session('status_tone', 'info'),
                'message' => session('status'),
            ])
        @endif

        {{-- A single field accepting either an email address or a user ID
             (spec §4.1); the design said "Username". --}}
        <label class="field {{ $errors->has('identifier') ? 'has-error' : '' }}">
            <span class="sr-only">Email or User ID</span>
            <span class="field-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
            </span>
            <input type="text"
                   name="identifier"
                   value="{{ old('identifier') }}"
                   placeholder="Email or User ID"
                   autocomplete="username"
                   autocapitalize="none"
                   spellcheck="false"
                   required
                   @if ($errors->has('identifier')) aria-invalid="true" aria-describedby="identifier-error" @endif>
        </label>
        @error('identifier')
            <span class="field-error" id="identifier-error">{{ $message }}</span>
        @enderror

        <label class="field {{ $errors->has('password') ? 'has-error' : '' }}">
            <span class="sr-only">Password</span>
            <span class="field-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="11" width="16" height="10" rx="2"/>
                    <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
                </svg>
            </span>
            <input type="password"
                   name="password"
                   placeholder="Password"
                   autocomplete="current-password"
                   required
                   @if ($errors->has('password')) aria-invalid="true" aria-describedby="password-error" @endif>

            <button type="button" class="eye-toggle" data-eye-toggle aria-pressed="false" aria-label="Show password">
                <svg class="icon-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <svg class="icon-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                    <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
                    <path d="M1 1l22 22"/>
                </svg>
            </button>
        </label>
        @error('password')
            <span class="field-error" id="password-error">{{ $message }}</span>
        @enderror

        <div class="form-row">
            <label class="check">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                <span class="box" aria-hidden="true"></span>
                <span>Remember me</span>
            </label>

            <a class="link-quiet" href="{{ url('/forgot-password') }}">Forgot password?</a>
        </div>

        <button type="submit" class="btn-auth" data-auth-submit>
            <span class="btn-label">Login Now</span>
        </button>

        <p class="form-foot">
            Need access? Contact your <a href="{{ $supportMailto }}">administrator</a>.
        </p>
    </form>
@endsection
