@extends('profile.partials.layout')

@php use App\Support\ProfilePresenter as P; @endphp

@section('title', 'Activity')

@section('panel')
    <div class="card">
        <div class="section-hd">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
            What has happened to your account
        </div>

        <div class="prose prose-quiet pf-section-note">
            <p>
                {{--
                    Says what it covers, because the value of this page is
                    entirely in whether somebody can trust that a missing entry
                    means nothing happened.

                    And it does NOT say where anything was done from. The
                    handover's read "New login from Kolkata, IN", which means IP
                    geolocation — a lookup service, a stored location per
                    sign-in, none of which exists or was decided. This is the
                    screen somebody checks when they think their account has been
                    used by someone else, and a wrong city sends them chasing
                    nothing.
                --}}
                Your sign-ins, your changes, and anything HR changed on your record.
                Only yours — nobody else's activity appears here, and yours does not
                appear on anybody else's page.
            </p>
        </div>

        @if ($entries->isEmpty())
            <p class="rail-empty">Nothing recorded yet.</p>
        @else
            <ol class="pf-activity">
                @foreach ($entries as $entry)
                    @php $meta = P::activity($entry['kind']); @endphp
                    <li class="pf-activity-row @if (P::isSecurityEvent($entry['kind'])) is-security @endif">
                        <span class="rail-ic {{ $meta['tone'] }}" aria-hidden="true">
                            @if ($entry['kind'] === 'password_changed' || $entry['kind'] === 'email_changed')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            @elseif ($entry['kind'] === 'sign_in' || $entry['kind'] === 'sign_out')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
                                </svg>
                            @elseif ($entry['kind'] === 'document_uploaded')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                                </svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.1 2.1 0 0 1 3 3L12 15l-4 1 1-4z"/>
                                </svg>
                            @endif
                        </span>

                        <span class="pf-activity-body">
                            <strong>{{ $entry['detail'] }}</strong>
                            <span>
                                {{ $meta['label'] }}
                                @if ($entry['by'] !== 'self')
                                    · not by you
                                @endif
                            </span>
                        </span>

                        <span class="pf-activity-time">{{ P::dateTime($entry['at_time']) }}</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    @include('partials.notice', [
        'tone' => 'info',
        'title' => 'Something here you did not do?',
        'message' => 'Change your password first, then tell the owner. A sign-in you do not recognise is worth reporting even if nothing looks wrong afterwards.',
    ])
@endsection
