{{--
    Five tiles. Every figure comes from `$stats`; the handover hardcoded
    28 / 16 / 8 / 4 / ₹12,45,000 and an invented "18.6%" month-on-month delta.
    Its counts also did not add up — 16 paid + 8 pending + 4 overdue is the
    whole 28, leaving no room for the "Partial" row its own table showed.

    Money leads, counts follow. "28 invoices" is trivia; "₹4,05,000 outstanding"
    is the number somebody does something about.

    ─────────────────────────────────────────────────────────────────────────
    A MIXED-CURRENCY TOTAL IS NOT A NUMBER

    We invoice in INR and USD. ₹75,000 + $2,000 has no sum without an exchange
    rate, and this system has no rate source. So each money tile shows its
    dominant currency and names the rest underneath, rather than converting.
    See App\Support\MoneyBag.
    ─────────────────────────────────────────────────────────────────────────
--}}
@php
    $outstanding = $stats['outstanding']->headline();
    $overdue = $stats['overdue_value']->headline();
    $collected = $stats['collected']->headline();
    $awaiting = $stats['sent'] + $stats['partial'] + $stats['overdue'];
@endphp

<section class="kpi-row kpi-row-compact" aria-label="Invoice summary">

    <div class="kpi">
        <div class="kpi-ic tone-accent" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Outstanding</div>
            <div class="kpi-val kpi-val-money">{{ $outstanding['lead'] }}</div>
            <span class="kpi-sub">
                @if ($outstanding['note'])
                    <span class="kpi-plus">{{ $outstanding['note'] }}</span>
                @endif
                {{ $awaiting }} {{ \Illuminate\Support\Str::plural('invoice', $awaiting) }} unpaid
            </span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-danger" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Overdue</div>
            <div class="kpi-val kpi-val-money">{{ $overdue['lead'] }}</div>
            <span class="kpi-sub">
                @if ($overdue['note'])
                    <span class="kpi-plus">{{ $overdue['note'] }}</span>
                @endif
                {{ $stats['overdue'] }} past its due date
            </span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Collected</div>
            <div class="kpi-val kpi-val-money">{{ $collected['lead'] }}</div>
            <span class="kpi-sub">
                @if ($collected['note'])
                    <span class="kpi-plus">{{ $collected['note'] }}</span>
                @endif
                Received to date
            </span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                <path d="M8 13h8"/><path d="M8 17h5"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Total Invoices</div>
            <div class="kpi-val">{{ number_format($stats['total']) }}</div>
            <span class="kpi-sub">{{ $stats['paid'] }} settled in full</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-warn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Drafts</div>
            <div class="kpi-val">{{ number_format($stats['draft']) }}</div>
            <span class="kpi-sub">Written but not sent</span>
        </div>
    </div>

</section>
