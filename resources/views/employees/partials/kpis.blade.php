{{--
    Every figure comes from `$stats`. The handover hardcoded 58 / 48 / 4 / 6
    and "20% vs last month"; the month-on-month comparison is dropped entirely
    rather than invented, since nothing records last month's headcount yet.
--}}
<section class="kpi-row" aria-label="Team summary">

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Total Employees</div>
            <div class="kpi-val">{{ number_format($stats['total']) }}</div>
            <span class="kpi-sub">All team members</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                <polyline points="17 11 19 13 23 9"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Active Employees</div>
            <div class="kpi-val">{{ number_format($stats['active']) }}</div>
            <span class="kpi-sub">Currently active</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-warn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <path d="M16 2v4M8 2v4M3 10h18"/><path d="M9 16h6"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">On Leave</div>
            <div class="kpi-val">{{ number_format($stats['on_leave']) }}</div>
            <span class="kpi-sub">On leave today</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-accent" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/>
                <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">New This Month</div>
            <div class="kpi-val">{{ number_format($stats['new_this_month']) }}</div>
            <span class="kpi-sub">Joined in {{ now()->format('F') }}</span>
        </div>
    </div>

</section>
