@extends('profile.partials.layout')

@section('title', 'Preferences')

@section('panel')
    {{--
        ─────────────────────────────────────────────────────────────────────────
        THE ONLY SURFACE FOR THE MILESTONE OPT-OUT

        Announcements decided (2026-08-28) that birthdays and work anniversaries
        post themselves and that anyone may opt out of their own. The flag has
        existed on the employee record since — `announce_milestones` — and until
        now there was nowhere to set it. A per-person opt-out nobody can reach is
        not an opt-out; it is a column.

        It is first on this page for that reason. It is also the only preference
        here that concerns other people rather than the person's own screen.
        ─────────────────────────────────────────────────────────────────────────
    --}}
    <form class="pf-form" method="POST" action="{{ route('profile.preferences.update') }}">
        @csrf

        <div class="card">
            <div class="section-hd">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 11l18-8v18l-18-8z"/>
                </svg>
                What the company sees
            </div>

            <div class="pf-toggle-row">
                <label class="pf-toggle" for="pf-milestones">
                    <input id="pf-milestones" name="announce_milestones" type="checkbox" value="1"
                           @checked($profile['announce_milestones'])>
                    <span class="pf-toggle-body">
                        <strong>Announce my birthday and work anniversary</strong>
                        <span>
                            Posted to the board on the day, with your name and the date —
                            never the year you were born. Turn this off and neither is
                            ever announced; nobody is told you opted out.
                        </span>
                    </span>
                </label>
            </div>
        </div>

        <div class="card">
            <div class="section-hd">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                </svg>
                How this looks on your screen
            </div>

            <div class="prose prose-quiet pf-section-note">
                <p>
                    {{-- These three genuinely work already — they are cookie
                         backed and shipped with the app shell — so the page
                         shows their real current values rather than defaults it
                         cannot read. --}}
                    These are yours alone and take effect on this browser. They are
                    already live: the theme switch in the top bar writes the same
                    setting this does.
                </p>
            </div>

            <div class="form-grid pf-form-grid">
                <div class="form-field">
                    <label class="form-field-lbl" for="pf-theme">Appearance</label>
                    <select id="pf-theme" name="theme">
                        @foreach ($themes as $option)
                            <option value="{{ $option }}" @selected($theme === $option)>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                    <span class="pay-hint">Currently {{ $theme }}.</span>
                </div>

                <div class="form-field">
                    <label class="form-field-lbl" for="pf-density">Density</label>
                    <select id="pf-density" name="density">
                        <option value="comfortable" @selected($density === 'comfortable')>Comfortable</option>
                        <option value="compact" @selected($density === 'compact')>Compact</option>
                    </select>
                    <span class="pay-hint">Compact fits more rows on a screen.</span>
                </div>

                <div class="form-field">
                    <label class="form-field-lbl" for="pf-sidebar">Sidebar</label>
                    <select id="pf-sidebar" name="sidebar">
                        <option value="expanded" @selected($sidebar === 'expanded')>Expanded</option>
                        <option value="collapsed" @selected($sidebar === 'collapsed')>Collapsed</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="section-hd">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>
                </svg>
                Notifications
            </div>

            <div class="prose prose-quiet pf-section-note">
                <p>
                    {{-- Honest about the ones that cannot be switched off, rather
                         than offering a control that quietly ignores them. --}}
                    What reaches the bell. Announcements to everyone, decisions on
                    your leave and meeting invites always arrive — those are how
                    work reaches you, and a decision nobody receives is not a
                    decision.
                </p>
            </div>

            <div class="pf-toggle-row">
                <label class="pf-toggle" for="pf-notify-tasks">
                    <input id="pf-notify-tasks" name="notify_tasks" type="checkbox" value="1"
                           @checked($profile['notify_tasks'])>
                    <span class="pf-toggle-body">
                        <strong>Tasks assigned to me</strong>
                        <span>When somebody puts you on a task.</span>
                    </span>
                </label>

                <label class="pf-toggle" for="pf-notify-tickets">
                    <input id="pf-notify-tickets" name="notify_tickets" type="checkbox" value="1"
                           @checked($profile['notify_tickets'])>
                    <span class="pf-toggle-body">
                        <strong>Tickets I raised or was assigned</strong>
                        <span>Replies, and tickets routed to you.</span>
                    </span>
                </label>
            </div>

            {{--
                The handover's third toggle — "also send these by email" — is
                gone rather than disabled.

                Nothing in this application emails a notification. A switch that
                turns on a thing that does not exist is a promise, and the
                person who sets it stops watching the bell. It comes back the
                day there is something behind it.
            --}}
        </div>

        <div class="form-actions form-actions-padded">
            <button class="btn btn-primary" type="submit">Save preferences</button>
        </div>
    </form>
@endsection
