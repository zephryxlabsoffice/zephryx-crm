@extends('profile.partials.layout')

@php use App\Support\ProfilePresenter as P; @endphp

@section('title', 'Password')

@section('panel')
    <div class="card">
        <div class="section-hd">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Change your password
        </div>

        <div class="prose pf-section-note">
            <p>{{ P::passwordAge($profile['password_changed_at']) }}</p>
        </div>

        <form class="pf-password" method="POST" action="{{ route('profile.password.update') }}">
            @csrf

            <div class="form-field">
                <label class="form-field-lbl" for="pf-current">Current password</label>
                {{--
                    Asked for even though the person is already signed in.

                    A borrowed unlocked laptop is the entire threat this field
                    exists for: without it, anyone who walks past a logged-in
                    screen owns the account permanently in four keystrokes. It is
                    the only thing standing in front of that, and it is why
                    changing a password is its own page rather than three fields
                    at the bottom of a profile form.
                --}}
                <input id="pf-current" name="current_password" type="password" autocomplete="current-password" disabled>
                <span class="pay-hint">Asked for even though you are signed in — it is what stops somebody using an unlocked screen.</span>
            </div>

            <div class="form-field">
                <label class="form-field-lbl" for="pf-new">New password</label>
                <input id="pf-new" name="password" type="password" autocomplete="new-password" minlength="12" disabled>
            </div>

            <div class="form-field">
                <label class="form-field-lbl" for="pf-confirm">New password again</label>
                <input id="pf-confirm" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" disabled>
            </div>

            <button class="btn btn-primary" type="submit" disabled title="Changing your password is not built yet">
                Change password
            </button>
        </form>
    </div>

    <div class="card">
        <div class="section-hd">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            What we ask for, and what we do not
        </div>

        {{--
            The policy, stated. §4.7.

            Written out because a rule people can read is a rule they can meet
            on the first attempt, and because two of these are absences that are
            worth being explicit about — a password field that does not demand a
            capital and a symbol looks broken to anybody used to ones that do.
        --}}
        <div class="prose pf-policy">
            <ul>
                <li><strong>At least twelve characters.</strong> Length is what makes a password hard to guess; a long ordinary phrase beats a short scrambled one.</li>
                <li><strong>Not a common password.</strong> Checked against a blocklist of the ones that get tried first.</li>
                <li><strong>No composition rules.</strong> No required capital, digit or symbol. They push people towards <code>Password1!</code>, which is on every blocklist there is.</li>
                <li><strong>No expiry.</strong> Nothing here will ever ask you to change a good password on a schedule — forced rotation produces weaker ones, because people add a digit rather than think.</li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="section-hd">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="22,6 12,13 2,6"/>
            </svg>
            Your sign-in address
        </div>

        <div class="prose pf-section-note">
            <p>
                You sign in with <strong>{{ $profile['email'] }}</strong>.
                {{--
                    Not a text box.

                    This is the login identifier (§4.1). A field that writes it
                    straight to the record is an account-takeover primitive:
                    point the account at another address and the real owner is
                    locked out of a system that no longer knows how to reach
                    them. Changing it is a flow that confirms from both
                    addresses, and it starts with a button, not a Save.
                --}}
                Changing it means confirming from both the old address and the new one,
                and you keep signing in with this one until the new address is confirmed.
            </p>
        </div>

        <form class="pf-email-change" method="POST" action="{{ route('profile.email.change') }}">
            @csrf
            <button class="btn btn-outline" type="submit" disabled title="Changing your email is not built yet">
                Start an email change
            </button>
        </form>
    </div>
@endsection
