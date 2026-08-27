{{--
    Five tiles, shared by the managing and personal faces. Every figure comes
    from `$stats`; the handover hardcoded 32 / 18 / 12 / 2 / 4.

    "Overdue" counts projects past their deadline that are *not* finished — a
    completed project that landed late is history, not an outstanding problem.
--}}
@php $scope = $scope ?? 'company'; @endphp

<section class="kpi-row" aria-label="Project summary">

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">{{ $scope === 'mine' ? 'My Projects' : 'Total Projects' }}</div>
            <div class="kpi-val">{{ number_format($stats['total']) }}</div>
            <span class="kpi-sub">{{ $scope === 'mine' ? 'Assigned to you' : 'Across the company' }}</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Active</div>
            <div class="kpi-val">{{ number_format($stats['active']) }}</div>
            <span class="kpi-sub">Planning, in progress or in review</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-accent" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Completed</div>
            <div class="kpi-val">{{ number_format($stats['completed']) }}</div>
            <span class="kpi-sub">Delivered</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-warn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="10" y1="15" x2="10" y2="9"/><line x1="14" y1="15" x2="14" y2="9"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">On Hold</div>
            <div class="kpi-val">{{ number_format($stats['on_hold']) }}</div>
            <span class="kpi-sub">Paused</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-warn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                <path d="M12 9v4M12 17h.01"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Overdue</div>
            <div class="kpi-val">{{ number_format($stats['overdue']) }}</div>
            <span class="kpi-sub">Past deadline, not delivered</span>
        </div>
    </div>

</section>
