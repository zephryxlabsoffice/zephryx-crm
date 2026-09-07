@php use App\Support\MeetingPresenter as MP; @endphp

{{--
    The viewer's own meetings, never the company's.

    A card headed "Your meetings" listing one somebody is not invited to is
    worse than an empty card — the filtering happens in DashboardData, which
    made the same call DemoMeetings::nextFor did.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Your meetings</strong>
        <a class="dash-link" href="{{ route('meetings.index') }}">All</a>
    </div>

    @if ($w['next'] === null)
        <p class="rail-empty">Nothing in your calendar.</p>
    @else
        <div class="dash-next-meeting">
            <span class="dash-when">{{ MP::when($w['next']) }}</span>
            <strong>{{ $w['next']['title'] }}</strong>
            <span class="dash-quiet-meta">{{ MP::timeRange($w['next']) }} · {{ MP::duration($w['next']) }}</span>

            @if (MP::isJoinable($w['next']))
                {{-- The link is Google's, and it opens in a new tab because
                     leaving the CRM to join a call and losing the page you were
                     on is a small daily annoyance. --}}
                <a class="btn btn-primary" href="{{ route('meetings.show', $w['next']['id']) }}">Open</a>
            @endif
        </div>

        @if ($w['rest']->isNotEmpty())
            <div class="rail-list">
                @foreach ($w['rest'] as $meeting)
                    <a class="rail-row" href="{{ route('meetings.show', $meeting['id']) }}">
                        <div class="rail-body">
                            <strong>{{ $meeting['title'] }}</strong>
                            <span>{{ MP::when($meeting) }}</span>
                        </div>
                        <span class="rail-time">{{ MP::time($meeting['starts_at']) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    @endif
</section>
