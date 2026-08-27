@php use App\Support\ProjectPresenter as P; @endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>Project Summary</strong>
    </div>

    <div>
        <div class="stat-row">
            <span class="stat-label">
                <span class="stat-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    </svg>
                </span>
                Client
            </span>
            <span class="stat-value">{{ $project['client'] }}</span>
        </div>

        <div class="stat-row">
            <span class="stat-label">
                <span class="stat-ic tone-accent" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                    </svg>
                </span>
                Started
            </span>
            <span class="stat-value">{{ P::date($project['start_date']) }}</span>
        </div>

        <div class="stat-row">
            <span class="stat-label">
                <span class="stat-ic tone-accent" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                </span>
                Deadline
            </span>
            <span class="stat-value">{{ P::date($project['deadline']) }}</span>
        </div>

        <div class="stat-row">
            <span class="stat-label">
                <span class="stat-ic" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/>
                        <line x1="4" y1="22" x2="4" y2="15"/>
                    </svg>
                </span>
                Priority
            </span>
            @php $priorityChip = P::priority($project['priority']); @endphp
            <span class="priority {{ $priorityChip['tone'] }}">{{ $priorityChip['label'] }}</span>
        </div>
    </div>
</section>
