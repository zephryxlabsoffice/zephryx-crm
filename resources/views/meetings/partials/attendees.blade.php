@php
    use App\Support\Avatar;
    use App\Support\MeetingPresenter as P;
    $accepted = collect($meeting['attendees'])->where('response', P::ACCEPTED)->count();
@endphp

{{--
    Who is invited and what they said.

    Every response here came from Google Calendar. This application has no way
    to accept on somebody's behalf and no route that tries — a person responds
    in their own calendar, and there is deliberately no `setAttendance()` on
    App\Support\Meetings\MeetingProvider.
--}}
<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Attendees
        <span class="tab-count">{{ count($meeting['attendees']) }}</span>
    </div>

    <ul class="mt-attendees">
        @foreach ($meeting['attendees'] as $attendee)
            @php $rsvp = P::response($attendee['response']); @endphp
            <li class="mt-attendee">
                <span class="{{ $attendee['kind'] === 'client' ? 'mt-face mt-face-client' : 'avatar' }} {{ Avatar::tint($attendee['name']) }}" aria-hidden="true">{{ Avatar::initials($attendee['name']) }}</span>

                <span class="mt-attendee-body">
                    <strong>
                        {{ $attendee['name'] }}
                        @if ($attendee['organiser'])
                            <span class="mt-organiser">Organiser</span>
                        @endif
                    </strong>
                    <span>{{ $attendee['detail'] }}</span>
                </span>

                {{-- The word, not only a colour. Whether somebody is coming is
                     not a distinction to leave to a dot. --}}
                <span class="mt-rsvp {{ $rsvp['tone'] }}">{{ $rsvp['label'] }}</span>
            </li>
        @endforeach
    </ul>

    @if ($meeting['status'] === P::SCHEDULED)
        <p class="mt-attendee-note">
            {{ $accepted }} of {{ count($meeting['attendees']) }} have accepted.
            Replies come from Google Calendar — nobody can answer here on somebody else’s behalf.
        </p>
    @endif
</div>
