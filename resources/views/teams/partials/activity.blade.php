{{--
    Recent changes to teams. Reads from the audit log (§8) once that exists —
    who did what, when — which is exactly the shape here.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Recent Activity</strong>
        <a class="card-link" href="{{ route('notifications.index') }}">View all</a>
    </div>

    <div class="rail-list">
        @forelse ($activity as $entry)
            <div class="rail-row">
                <div class="rail-ic {{ $entry['tone'] }}" aria-hidden="true">
                    @include('partials.nav-icon', ['icon' => 'teams'])
                </div>
                <div class="rail-body">
                    <strong>{{ $entry['what'] }}</strong>
                    <span>by {{ $entry['who'] }} · {{ $entry['when'] }}</span>
                </div>
            </div>
        @empty
            <p class="rail-empty">No team changes recorded yet.</p>
        @endforelse
    </div>
</section>
