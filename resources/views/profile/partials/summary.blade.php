{{--
    Five figures, every one of them read from the module that owns it and
    linking there.

    The handover hardcoded 15 projects, 128 tasks, 42 tickets, "22 / 22"
    attendance and 12 days of leave. A profile is a summary of other pages, and
    a summary that disagrees with the page it summarises is worse than no
    summary — the first time somebody notices, they stop trusting all five.

    So each row links. A number somebody disputes is one click from the page
    that produced it, which is also what stops this card quietly becoming a
    second source of truth.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>You, across the app</strong>
    </div>

    @if ($summary === [])
        <p class="rail-empty">Nothing to summarise yet.</p>
    @else
        <div class="rail-list">
            @foreach ($summary as $row)
                <a class="rail-row" href="{{ route($row['route']) }}">
                    <span class="rail-ic {{ $row['tone'] }}" aria-hidden="true">
                        @include('partials.nav-icon', ['icon' => $row['icon']])
                    </span>
                    <span class="rail-body">
                        <strong>{{ $row['label'] }}</strong>
                    </span>
                    <span class="rail-time">{{ $row['value'] }}</span>
                </a>
            @endforeach
        </div>
    @endif
</section>
