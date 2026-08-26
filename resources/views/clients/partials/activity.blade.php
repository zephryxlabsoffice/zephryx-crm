<section class="rail-card">
    <div class="rail-hd">
        <strong>Recent Client Activity</strong>
        <a class="card-link" href="{{ route('notifications.index') }}">View all</a>
    </div>

    <div class="rail-list">
        @forelse ($activity as $entry)
            <div class="rail-row">
                <div class="rail-ic {{ $entry['tone'] }}" aria-hidden="true">
                    @include('partials.nav-icon', ['icon' => $entry['icon']])
                </div>
                <div class="rail-body">
                    <strong>{{ $entry['client'] }}</strong>
                    <span>{{ $entry['what'] }}</span>
                </div>
                <span class="rail-time">{{ $entry['when'] }}</span>
            </div>
        @empty
            <p class="rail-empty">No client activity recorded yet.</p>
        @endforelse
    </div>
</section>
