{{--
    Three tiles, shared by the managing and personal faces — the numbers differ,
    the shape does not. `$scope` changes the sub-labels so "Total Teams" on
    /teams/mine does not read as the company total.
--}}
@php $scope = $scope ?? 'company'; @endphp

<section class="kpi-row" aria-label="Team summary">

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Total Teams</div>
            <div class="kpi-val">{{ number_format($stats['total']) }}</div>
            <span class="kpi-sub">{{ $scope === 'mine' ? 'You are a member of' : 'Across the company' }}</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Active Teams</div>
            <div class="kpi-val">{{ number_format($stats['active']) }}</div>
            <span class="kpi-sub">Currently active</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-warn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Inactive Teams</div>
            <div class="kpi-val">{{ number_format($stats['inactive']) }}</div>
            <span class="kpi-sub">Not currently active</span>
        </div>
    </div>

</section>
