{{--
    Four tiles, every figure from `$stats`. The handover hardcoded 28 paid and
    12 pending for a company with twelve people, against a table headed
    "All Salary Records (40)".

    "Paid out" counts only what has actually left the account. A total that
    folds in pending and held rows describes an intention, not a payment.
--}}
@php
    $paidOut = $stats['paid_out']->headline();
    $committed = $stats['committed']->headline();
@endphp

<section class="kpi-row" aria-label="Payroll summary">

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Paid out</div>
            <div class="kpi-val kpi-val-money">{{ $paidOut['lead'] }}</div>
            <span class="kpi-sub">{{ $stats['paid'] }} of {{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('person', $stats['total']) }}</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-warn" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Awaiting payment</div>
            <div class="kpi-val">{{ number_format($stats['pending']) }}</div>
            <span class="kpi-sub">Generated, not yet released</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-danger" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="10" y1="15" x2="10" y2="9"/><line x1="14" y1="15" x2="14" y2="9"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">On hold</div>
            <div class="kpi-val">{{ number_format($stats['on_hold']) }}</div>
            {{-- Never a silent state. Somebody chose this, and it should read
                 that way rather than as a system condition. --}}
            <span class="kpi-sub">Held back deliberately</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Month’s payroll</div>
            <div class="kpi-val kpi-val-money">{{ $committed['lead'] }}</div>
            <span class="kpi-sub">Paid and pending together</span>
        </div>
    </div>

</section>
