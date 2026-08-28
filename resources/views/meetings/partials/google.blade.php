@php use App\Support\MeetingPresenter as P; @endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>On Google</strong>
    </div>

    <div>
        <div class="stat-row">
            <span class="stat-label">Calendar event</span>
            <span class="stat-value {{ $meeting['event_id'] ? 'stat-value-mono' : '' }}">
                {{ $meeting['event_id'] ?? 'Not created' }}
            </span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Join link</span>
            <span class="stat-value">
                @if ($meeting['join_url'])
                    Ready
                @elseif ($meeting['event_id'])
                    {{-- An event with no conference is the failure worth naming:
                         it looks fine in a list and fails at the meeting. --}}
                    <span class="mt-warn">Event exists, no conference</span>
                @else
                    Not created
                @endif
            </span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Reference</span>
            <span class="stat-value stat-value-mono">{{ $meeting['id'] }}</span>
        </div>
        <div class="stat-row stat-row-block">
            <span class="stat-label">What it means</span>
            <span class="stat-value stat-value-quiet">{{ P::status($meeting['status'])['meaning'] }}</span>
        </div>
    </div>

    @if ($meeting['status'] === P::REQUESTED)
        {{--
            The one action on this page that talks to Google. Only a project
            manager or the system admin may take it (decided 2026-08-28);
            clients can ask for a meeting but not create one.

            TODO (backend phase): §2.6 for the permission, §6 for the audit
            entry, and it must be idempotent — pressing twice must not produce
            two events and two sets of invites.
        --}}
        <form class="mt-create" method="POST" action="{{ route('meetings.create.event', ['meeting' => $meeting['id']]) }}">
            @csrf
            <button class="btn btn-primary" type="submit" disabled title="Creating the Google event is not built yet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    <line x1="12" y1="14" x2="12" y2="18"/><line x1="10" y1="16" x2="14" y2="16"/>
                </svg>
                Create on Google
            </button>
            <span class="pay-hint">Creates the calendar event, generates the Meet link and sends the invites.</span>
        </form>
    @endif
</section>
