@php
    use App\Support\SalaryPresenter as P;
    $status = P::statusOf($latest);
    $pill = P::status($status);
    $payslips = $history->reject(fn (array $r) => $r['payslip'] === null)->count();
@endphp

{{--
    Three tiles, and no CTC.

    The handover showed CTC ₹12,60,000, net ₹85,800 and a management figure of
    ₹80,000 for one person with nothing connecting them. The salary structure
    that would produce a CTC is gone (decided 2026-08-27) — pay is worked out in
    Excel, and holding our own version of somebody else's calculation is how two
    numbers for one salary come to exist.
--}}
<section class="kpi-row" aria-label="Your salary summary">

    <div class="kpi">
        <div class="kpi-ic {{ $status === P::PAID ? 'tone-soft' : 'tone-warn' }}" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">{{ P::periodShort($latest['period']) }}</div>
            <div class="kpi-val kpi-val-status">{{ $pill['label'] }}</div>
            <span class="kpi-sub">{{ P::paidOn($latest) }}</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-accent" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Net this month</div>
            <div class="kpi-val kpi-val-money">{{ P::net($latest) }}</div>
            <span class="kpi-sub">Breakup is on the payslip</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                <path d="M9 13h6"/><path d="M9 17h4"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Payslips</div>
            <div class="kpi-val">{{ number_format($payslips) }}</div>
            <span class="kpi-sub">Joined {{ P::date($employee['joined']) }}</span>
        </div>
    </div>

</section>
