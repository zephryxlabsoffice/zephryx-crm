@extends('layouts.auth')

@section('title', 'Reset your password')

@section('form')
    <form class="auth-form" id="auth-form" method="POST" action="{{ route('password.request') }}" novalidate data-auth-form>
        @csrf

        <div class="form-brand">
            @include('partials.brand-mark', ['alt' => config('zephryx.brand.name')])
        </div>

        <h1 class="auth-heading">Forgot your password?</h1>

        <p class="auth-subheading">
            Enter your email address or user ID and we'll send you a link to set a new one.
        </p>

        @if (session('status'))
            @include('partials.notice', [
                'tone' => session('status_tone', 'success'),
                'message' => session('status'),
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

        <button type="submit" class="btn-auth" data-auth-submit>
            <span class="btn-label">Send reset link</span>
        </button>

        <p class="form-foot">
            Remembered it? <a href="{{ route('login') }}">Back to sign in</a>.
        </p>
    </form>
@endsection
