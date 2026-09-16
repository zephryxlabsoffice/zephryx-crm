{{--
    What this form actually does, said once and at the top.

    ─────────────────────────────────────────────────────────────────────────────
    THE PAGE HAS TO EXPLAIN THE REVERSAL, NOT JUST IMPLEMENT IT

    Until 2026-09-14 this form saved. It now asks. Somebody who has used the page
    before will press the button expecting the old behaviour, and a form that
    looks identical and behaves differently is how a person ends up believing
    their address has changed when it has not.

    So: one explanation above the fields, a button that says "Send to HR" rather
    than "Save", and — while something is pending — the request itself shown
    with what it will do, instead of a form that would be refused.
--}}
<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <path d="M14 2v6h6M9 15l2 2 4-4"/>
        </svg>
        How changes to this page work
    </div>

    <div class="prose">
        <p>
            These details are the company's record of you, so they are corrected against
            documents rather than on the form alone.
            <strong>Filling this in sends a request to HR</strong> — bring the supporting
            document to the office, and HR applies it. Nothing below changes until they do.
        </p>
        <p>
            Your <a class="dash-link" href="{{ route('profile.preferences') }}">preferences</a>
            are not affected: theme, density, the sidebar and the notification toggles still
            save the moment you set them. They are settings, not a record of anything.
        </p>
    </div>
</div>

@if ($pending !== null)
    <div class="card">
        <div class="section-hd">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            </svg>
            Waiting with HR
        </div>

        <div class="prose">
            <p>
                Sent {{ $pending->created_at?->diffForHumans() }}. Your record is unchanged
                until HR applies it.
            </p>
        </div>

        <dl class="field-grid att-record-grid">
            @foreach ($pendingRows as $row)
                <div class="lv-field">
                    <dt class="lv-field-lbl">{{ $row['label'] }}</dt>
                    <dd>
                        {{-- Both halves, because the useful question while
                             waiting is "is this what I actually asked for". --}}
                        <span class="an-optional">{{ $row['was'] }}</span>
                        →
                        {{ $row['now'] }}
                    </dd>
                </div>
            @endforeach

            @if ($pendingPhoto)
                <div class="lv-field">
                    <dt class="lv-field-lbl">{{ \App\Support\ProfilePolicy::labelOf('photo') }}</dt>
                    {{-- Not previewed. The candidate file is held on the private
                         disk and serving it would need a route of its own, for
                         a picture the person has on the device they uploaded it
                         from. --}}
                    <dd>A new photo, waiting</dd>
                </div>
            @endif
        </dl>

        <form class="pf-photo-form" method="POST" action="{{ route('profile.requests.withdraw') }}">
            @csrf
            <button class="btn btn-outline" type="submit">Withdraw this request</button>
        </form>
    </div>
@endif

@if ($decided->isNotEmpty())
    <div class="card">
        <div class="section-hd">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/>
            </svg>
            What you have asked for before
        </div>

        <div class="stat-list">
            @foreach ($decided as $past)
                <div class="stat-row">
                    <span class="stat-label">{{ $past->updated_at?->format('d M Y') }}</span>
                    <span class="stat-value">{{ ucfirst($past->outcome()) }}</span>
                </div>
                @if ($past->decision_note)
                    {{-- HR's reason, shown to the person it is about. A decline
                         with no reason on the screen is a person told no by a
                         computer, and the first thing they do is ask somebody. --}}
                    <p class="att-rail-note">{{ $past->decision_note }}</p>
                @endif
            @endforeach
        </div>
    </div>
@endif
