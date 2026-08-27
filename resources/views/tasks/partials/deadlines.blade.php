@php use App\Support\TaskPresenter as P; @endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>Upcoming Deadlines</strong>
        <a class="card-link" href="{{ route('tasks.index') }}">View all</a>
    </div>

    <div class="rail-list">
        @forelse ($upcoming as $item)
            @php $due = $item['due_meta']; @endphp
            <div class="rail-row">
                <div class="rail-ic {{ P::tint($item['id']) }}" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 12l2 2 4-4"/>
                    </svg>
                </div>
                <div class="rail-body">
                    <strong>{{ $item['name'] }}</strong>
                    <span>{{ $item['project_record']['name'] ?? 'No project' }}</span>
                </div>
                <span class="rail-meta">
                    <strong>{{ P::date($item['due']) }}</strong>
                    <em class="{{ $due['state'] }}">{{ $due['label'] }}</em>
                </span>
            </div>
        @empty
            <p class="rail-empty">Nothing due soon.</p>
        @endforelse
    </div>
</section>
