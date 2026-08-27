@php use App\Support\ProjectPresenter as P; @endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>Upcoming Deadlines</strong>
        <a class="card-link" href="{{ route('projects.index') }}">View all</a>
    </div>

    <div class="rail-list">
        @forelse ($upcoming as $item)
            @php $due = $item['deadline_meta']; @endphp
            <div class="rail-row">
                <div class="rail-ic {{ P::tint($item['id']) }}" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    </svg>
                </div>
                <div class="rail-body">
                    <strong>{{ $item['name'] }}</strong>
                    <span>{{ $item['client'] }}</span>
                </div>
                <span class="rail-meta">
                    <strong>{{ P::date($item['deadline']) }}</strong>
                    {{-- Coloured only when it is actually a problem. The
                         handover made every countdown red. --}}
                    <em class="{{ $due['state'] }}">{{ $due['label'] }}</em>
                </span>
            </div>
        @empty
            <p class="rail-empty">No deadlines coming up.</p>
        @endforelse
    </div>
</section>
