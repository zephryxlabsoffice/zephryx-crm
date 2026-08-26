@extends('layouts.auth')

@section('title', 'Verify your sign-in')

@section('form')
    <form class="auth-form" id="auth-form" method="POST" action="{{ route('login.verify.attempt') }}" novalidate data-auth-form>
        @csrf

        <div class="form-brand">
            @include('partials.brand-mark', ['alt' => config('zephryx.brand.name')])
        </div>

        <h1 class="auth-heading">Check your email</h1>

        <p class="auth-subheading">
            We sent a 6-digit code to <strong>{{ $maskedEmail }}</strong>.<br>
            It expires in {{ $expiresInMinutes }} minutes.
        </p>

        @if ($errors->has('code'))
            @include('partials.notice', [
                'tone' => 'danger',
                'message' => $errors->first('code'),
            ])
        @elseif (session('status'))
            @include('partials.notice', [
                'tone' => session('status_tone', 'success'),
                'message' => session('status'),
            ])
        @endif

        {{-- Six boxes, one logical field. resources/js/auth.js advances focus,
             handles backspace and distributes a pasted code; the boxes still
             work as plain inputs with JavaScript off. --}}
        <fieldset class="otp-row {{ $errors->has('code') ? 'has-error' : '' }}" data-otp>
            <legend class="sr-only">Enter the 6-digit code from your email</legend>

            @for ($i = 0; $i < 6; $i++)
                <input type="text"
                       inputmode="numeric"
                       pattern="[0-9]*"
                       maxlength="1"
                       name="code[]"
                       aria-label="Digit {{ $i + 1 }} of 6"
                       autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}"
                       @if ($i === 0) autofocus @endif
                       required>
            @endfor
        </fieldset>

        <button type="submit" class="btn-auth" data-auth-submit>
            <span class="btn-label">Verify</span>
        </button>

        {{-- Resend is rate limited and invalidates the previous code (spec §4.3).
             The cooldown is rendered server-side so it holds without JavaScript;
             auth.js counts it down in place. --}}
        <p class="resend-row">
            Didn't get it?
            <button type="submit"
                    form="resend-form"
                    class="resend-btn"
                    data-resend
                    @disabled($resendCooldown > 0)>
                <span data-resend-label>
                    @if ($resendCooldown > 0)
                        Resend in {{ $resendCooldown }}s
                    @else
                        Send a new code
                    @endif
                </span>
            </button>
        </p>

        <p class="form-foot">
            Wrong account? <a href="{{ route('login') }}">Start over</a>.
        </p>
    </form>

    {{-- Kept outside the verify form so submitting it never carries the code. --}}
    <form id="resend-form"
          method="POST"
          action="{{ route('login.resend') }}"
          hidden
          data-resend-seconds="{{ (int) $resendCooldown }}">
        @csrf
    </form>
@endsection
