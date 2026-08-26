{{--
    KPI strip. Every figure comes from `$stats` — none is written into the
    markup. Three of the four are sourced from modules that do not exist yet
    (Invoices, Tickets), so they read zero until those land rather than
    carrying the handover's invented numbers into the application.
--}}
@php
    $currency = config('zephryx.currency.symbol');
@endphp

<section class="kpi-row" aria-label="Client summary">

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Total Clients</div>
            <div class="kpi-val">{{ number_format($stats['total']) }}</div>
            <span class="kpi-sub">On the books</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Active Clients</div>
            <div class="kpi-val">{{ number_format($stats['active']) }}</div>
            <span class="kpi-sub">Currently ongoing</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-warn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                <circle cx="12" cy="15" r="1.5"/><path d="M12 11.5v1"/><path d="M12 17v1"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Pending Payments</div>
            <div class="kpi-val">{{ $currency }}{{ number_format($stats['receivable']) }}</div>
            @if ($stats['overdue'] > 0)
                <span class="kpi-sub down">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                    {{ $stats['overdue'] }} overdue
                </span>
            @else
                <span class="kpi-sub">Nothing overdue</span>
            @endif
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-accent" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 14v-2a9 9 0 0 1 18 0v2"/>
                <rect x="2" y="14" width="5" height="7" rx="2"/><rect x="17" y="14" width="5" height="7" rx="2"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Support Tickets</div>
            <div class="kpi-val">{{ number_format($stats['tickets']) }}</div>
            <span class="kpi-sub">{{ $stats['unresolved'] }} unresolved</span>
        </div>
    </div>

</section>
