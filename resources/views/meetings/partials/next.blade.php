@php use App\Support\MeetingPresenter as P; @endphp

{{--
    The next meeting the VIEWER is on — not simply the company's next meeting.

    A card headed "Next meeting" showing one somebody is not invited to is worse
    than showing nothing: they will act on it. The handover's card hardcoded a
    name and a dead "Join Meeting" link.
--}}
<section class="rail-card mt-next">
    <div class="rail-hd">
        <strong>Your next meeting</strong>
    </div>

    @if ($next === null)
        <p class="rail-empty">Nothing coming up that you are on.</p>
    @else
        @php $timing = P::timing($next); @endphp

        <div class="mt-next-body">
            <a class="mt-next-title" href="{{ route('meetings.show', ['meeting' => $next['id']]) }}">{{ $next['title'] }}</a>

            <p class="mt-next-when">
                {{ P::when($next) }}
                @if ($timing['tone'])
                    <span class="{{ $timing['tone'] }}">{{ $timing['label'] }}</span>
                @endif
            </p>

            @if ($next['project_record'])
                <p class="mt-next-meta">{{ $next['project_record']['name'] }}</p>
            @endif

            @if ($next['join_url'])
                {{--
                    A real link to a real room, and only because the viewer is
                    on the invite — the controller strips it otherwise.

                    rel="noopener noreferrer" on a target="_blank": without
                    noopener the opened page can reach back through
                    window.opener, and noreferrer keeps our URL out of its
                    referrer header.
                --}}
                <a class="btn {{ P::isJoinable($next) ? 'btn-primary' : 'btn-outline' }} mt-join"
                   href="{{ $next['join_url'] }}" target="_blank" rel="noopener noreferrer">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M23 7l-7 5 7 5V7z"/><rect x="1" y="5" width="15" height="14" rx="2"/>
                    </svg>
                    Join on Google Meet
                </a>
            @else
                <p class="mt-next-note">No link yet — it appears once the meeting is created on Google.</p>
            @endif
        </div>
    @endif
</section>
