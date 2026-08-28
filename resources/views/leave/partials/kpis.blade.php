{{--
    Four tiles. Every figure from `$stats`; the handover hardcoded 8 / 25 / 6 /
    3 / 42.

    "Pending" leads and is the only one that ever gets an urgent tone, because
    it is the only figure on this page that means somebody has to do something.
    A count of last year's approvals is history.
--}}
<section class="kpi-row" aria-label="Leave summary">

    <div class="kpi">
        <div class="kpi-ic {{ $stats['pending'] > 0 ? 'tone-warn' : 'tone-soft' }}" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Waiting on you</div>
            <div class="kpi-val">{{ number_format($stats['pending']) }}</div>
            <span class="kpi-sub">{{ $stats['pending'] === 0 ? 'Nothing to decide' : 'Undecided requests' }}</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Approved</div>
            <div class="kpi-val">{{ number_format($stats['approved']) }}</div>
            <span class="kpi-sub">Granted this year</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-danger" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Rejected</div>
            <div class="kpi-val">{{ number_format($stats['rejected']) }}</div>
            <span class="kpi-sub">Each with a reason on it</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Withdrawn</div>
            <div class="kpi-val">{{ number_format($stats['cancelled']) }}</div>
            {{-- Not "cancelled" in the same breath as "rejected": one the person
                 did themselves, the other was done to them. --}}
            <span class="kpi-sub">Pulled by the person who asked</span>
        </div>
    </div>

</section>
