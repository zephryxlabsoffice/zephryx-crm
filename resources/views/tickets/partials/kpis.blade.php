{{--
    Five tiles. Every figure comes from `$stats`; the handover hardcoded
    152 / 12 / 8 / 54 / 31 and percentages that did not match them.

    "Unassigned" and "Escalated" lead because they are the two that mean
    somebody has to do something. The handover led with the all-time total,
    which is the one number nobody acts on.
--}}
@php
    $share = fn (int $n) => $stats['total'] > 0
        ? round($n / $stats['total'] * 100, 1).'% of all'
        : 'None yet';
@endphp

<section class="kpi-row" aria-label="Ticket summary">

    <div class="kpi">
        <div class="kpi-ic tone-warn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Unassigned</div>
            <div class="kpi-val">{{ number_format($stats['unassigned']) }}</div>
            <span class="kpi-sub">Waiting for triage</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-warn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 19V5"/><polyline points="5 12 12 5 19 12"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Escalated</div>
            <div class="kpi-val">{{ number_format($stats['escalated']) }}</div>
            <span class="kpi-sub">Awaiting review</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-accent" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">In Progress</div>
            <div class="kpi-val">{{ number_format($stats['in_progress']) }}</div>
            <span class="kpi-sub">{{ $share($stats['in_progress']) }}</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Resolved</div>
            <div class="kpi-val">{{ number_format($stats['resolved']) }}</div>
            <span class="kpi-sub">{{ $share($stats['resolved']) }}</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 10V8a2 2 0 0 0-2-2h-4l-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2"/>
                <path d="M21 10a2 2 0 0 0 0 4"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">{{ $totalLabel ?? 'Total Tickets' }}</div>
            <div class="kpi-val">{{ number_format($stats['total']) }}</div>
            <span class="kpi-sub">{{ $totalSub ?? 'All time' }}</span>
        </div>
    </div>

</section>
