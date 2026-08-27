{{--
    Four tiles, every figure from `$stats`. The handover hardcoded 28 paid and
    12 pending for a company of twelve, against a table headed
    "All Salary Records (40)".

    The two that mean somebody has to do something lead: payslips still to add,
    and transfers still to make.
--}}
@php
    $paidOut = $stats['paid_out']->headline();
    $onFile = $stats['on_file']->headline();
@endphp

<section class="kpi-row" aria-label="Payroll summary">

    <div class="kpi">
        <div class="kpi-ic {{ $stats['no_payslip'] > 0 ? 'tone-danger' : 'tone-soft' }}" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                <line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Payslip still to add</div>
            <div class="kpi-val">{{ number_format($stats['no_payslip']) }}</div>
            <span class="kpi-sub">Nothing recorded for them yet</span>
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
            <div class="kpi-val">{{ number_format($stats['awaiting_payment']) }}</div>
            <span class="kpi-sub">Payslip on file, transfer not made</span>
        </div>
    </div>

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
        <div class="kpi-ic" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Month’s payroll</div>
            <div class="kpi-val kpi-val-money">{{ $onFile['lead'] }}</div>
            {{-- Says what it counts, because a payroll total that quietly
                 excludes the people with no payslip yet is a total somebody
                 will plan against and be wrong. --}}
            <span class="kpi-sub">Across the payslips added so far</span>
        </div>
    </div>

</section>
