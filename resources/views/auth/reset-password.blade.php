@extends('layouts.auth')

@section('title', 'Choose a new password')

@section('form')
    <form class="auth-form" id="auth-form" method="POST" action="{{ route('password.reset') }}" novalidate data-auth-form>
        @csrf

        {{-- The token identifies the reset request; it is single-use and expires
             after 60 minutes (spec §4.6). --}}
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-brand">
            @include('partials.brand-mark', ['alt' => config('zephryx.brand.name')])
        </div>

        <h1 class="auth-heading">Choose a new password</h1>

        <p class="auth-subheading">
            Signing you out everywhere else — you'll need to sign in again on your other devices.
        </p>

        @if ($errors->has('token'))
            @include('partials.notice', [
                'tone' => 'danger',
                'message' => $errors->first('token'),
            ])
        @endif

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
                   value="{{ old('identifier', $identifier) }}"
                   placeholder="Email or User ID"
                   autocomplete="username"
                   autocapitalize="none"
                   spellcheck="false"
                   required>
        </label>
        @error('identifier')
            <span class="field-error">{{ $message }}</span>
        @enderror

        <label class="field {{ $errors->has('password') ? 'has-error' : '' }}">
            <span class="sr-only">New password</span>
            <span class="field-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="4" y="11" width="16" height="10" rx="2"/>
                    <path d="M8 11V7a4 4 0 0 1 8 0v4"/>
                </svg>
            </span>
            <input type="password"
                   name="password"
                   placeholder="New password"
                   autocomplete="new-password"
                   required
                   aria-describedby="password-hint">

            <button type="button" class="eye-toggle" data-eye-toggle aria-pressed="false" aria-label="Show password">
                @include('partials.eye-icons')
            </button>
        </label>
        @error('password')
            <span class="field-error">{{ $message }}</span>
        @enderror

        {{-- The only rule is length (spec §4.7): no composition requirements,
             because they push people towards predictable substitutions. --}}
        <p class="field-hint" id="password-hint">
            At least 12 characters. A short phrase you'll remember beats a
            scrambled word.
        </p>

        <label class="field {{ $errors->has('password') ? 'has-error' : '' }}">
            <span class="sr-only">Confirm new password</span>
            <span class="field-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
            </span>
            <input type="password"
                   name="password_confirmation"
                   {{-- Shorter than the label on purpose: the full wording runs
                        under the reveal button on a 320px screen. --}}
                   placeholder="Confirm password"
                   autocomplete="new-password"
                   required>

            <button type="button" class="eye-toggle" data-eye-toggle aria-pressed="false" aria-label="Show password">
                @include('partials.eye-icons')
            </button>
        </label>

        <button type="submit" class="btn-auth" data-auth-submit>
            <span class="btn-label">Update password</span>
        </button>

        <p class="form-foot">
            <a href="{{ route('login') }}">Back to sign in</a>
        </p>
    </form>
@endsection
