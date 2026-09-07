@php use App\Support\MeetingPresenter as MP; @endphp

{{--
    Meetings a client has asked for and nobody has created yet.

    A requested meeting has no Google Calendar event behind it — that is what
    makes it a request rather than a meeting. It sits here until a project
    manager or the system admin creates one, which is the only act that puts it
    in anybody's calendar.
--}}
<section class="card table-card">
    <div class="card-hd">
        <span class="card-title">Meeting requests</span>
        <a class="card-link" href="{{ route('meetings.index') }}">View all</a>
    </div>

    @if ($w['items']->isEmpty())
        <div class="card-body">
            <p class="rail-empty">No open requests.</p>
        </div>
    @else
        <div class="rail-list">
            @foreach ($w['items'] as $meeting)
                <a class="rail-row" href="{{ route('meetings.show', $meeting['id']) }}">
                    <div class="rail-body">
                        <strong>{{ $meeting['title'] }}</strong>
                        <span>{{ MP::when($meeting) }} · {{ MP::duration($meeting) }}</span>
                    </div>
                    <span class="rail-time">Requested</span>
                </a>
            @endforeach
        </div>

        @if ($w['total'] > $w['items']->count())
            <div class="card-body dash-more">
                <span>{{ $w['total'] - $w['items']->count() }} more</span>
            </div>
        @endif
    @endif
</section>
