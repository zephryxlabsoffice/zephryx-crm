{{--
    Where meetings actually live.

    Replaces the handover's "Need Immediate Help?" card, which carried a
    hardcoded phone number and two obfuscated email addresses — a client-support
    panel that had wandered onto an internal staff page.

    This says the thing a person on this page might actually be unsure about:
    the CRM organises, Google hosts, and nothing is recorded here.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>How meetings run</strong>
    </div>

    <ul class="mt-facts">
        <li class="mt-fact">
            <span class="mt-fact-ic tone-accent" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/>
                </svg>
            </span>
            <span class="mt-fact-body">
                <strong>Google Meet hosts every call</strong>
                <span>Scheduling here creates the calendar event and the link. Nothing runs on our server, and nothing is recorded by it.</span>
            </span>
        </li>

        <li class="mt-fact">
            <span class="mt-fact-ic tone-soft" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                </svg>
            </span>
            <span class="mt-fact-body">
                <strong>Replies come from Google</strong>
                <span>People accept or decline in their own calendar. This page shows what they did; it cannot answer for them.</span>
            </span>
        </li>

        <li class="mt-fact">
            <span class="mt-fact-ic tone-warn" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </span>
            <span class="mt-fact-body">
                <strong>The link is for people on the invite</strong>
                <span>A Meet link is effectively a password, so it is shown to attendees only — not to everyone who can see the meeting exists.</span>
            </span>
        </li>

        <li class="mt-fact">
            <span class="mt-fact-ic" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </span>
            <span class="mt-fact-body">
                <strong>Times are {{ \App\Support\MeetingPresenter::zone() }}</strong>
                <span>Stored in UTC and shown here in Indian time, so a meeting with an overseas client cannot drift when their clocks change.</span>
            </span>
        </li>
    </ul>
</section>
