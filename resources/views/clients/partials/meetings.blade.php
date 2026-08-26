<section class="rail-card">
    <div class="rail-hd">
        <strong>Upcoming Meetings</strong>
        <a class="card-link" href="{{ route('meetings.index') }}">View all</a>
    </div>

    <div class="rail-list">
        @forelse ($meetings as $meeting)
            <div class="mt-row">
                <div class="mt-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </div>
                <div class="mt-body">
                    <strong>{{ $meeting['client'] }}</strong>
                    <span>{{ $meeting['when'] }}</span>
                </div>
                <span class="mt-platform">
                    @include('clients.partials.platform-icon', ['platform' => $meeting['platform']])
                    {{ $meeting['platform'] }}
                </span>
            </div>
        @empty
            <p class="rail-empty">No meetings scheduled.</p>
        @endforelse
    </div>
</section>
