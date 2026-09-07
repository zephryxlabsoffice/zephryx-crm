@php use App\Support\Avatar; @endphp

{{--
    The teams the viewer is on, and the colleagues in them.

    People are deduplicated across teams: somebody on three of the same teams as
    you is one colleague, not three. The viewer is not in their own list either.
--}}
<section class="card">
    <div class="card-hd">
        <span class="card-title">Your team</span>
        <a class="card-link" href="{{ route('teams.mine') }}">View all</a>
    </div>

    <div class="card-body">
        @if ($w['teams']->isEmpty())
            <p class="rail-empty">You are not on a team yet.</p>
        @else
            @foreach ($w['teams'] as $team)
                <a class="stat-row dash-team-row" href="{{ route('teams.show', $team['id']) }}">
                    <span class="stat-label">{{ $team['name'] }}</span>
                    <span class="stat-value">
                        {{ $team['member_count'] }}
                        <span class="stat-value-quiet">{{ $team['member_count'] === 1 ? 'member' : 'members' }}</span>
                    </span>
                </a>
            @endforeach

            @if ($w['people']->isNotEmpty())
                <div class="dash-people">
                    @foreach ($w['people'] as $person)
                        <span class="avatar {{ Avatar::tint($person['name']) }}" title="{{ $person['name'] }}">
                            {{ Avatar::initials($person['name']) }}
                        </span>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</section>
