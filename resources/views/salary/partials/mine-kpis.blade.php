@php
    use App\Support\SalaryPresenter as P;
    $pill = P::status($latest['status']);
@endphp

{{--
    Four tiles. The handover's CTC (₹12,60,000), its net (₹85,800) and the
    management table's figure for the same person (₹80,000) were three unrelated
    numbers; all of these come from one structure, so they reconcile by
    construction — CTC is twelve times the gross, and net is the gross less
    deductions.
--}}
<section class="kpi-row" aria-label="Your salary summary">

    <div class="kpi">
        <div class="kpi-ic {{ $latest['status'] === P::PAID ? 'tone-soft' : 'tone-warn' }}" aria-hidden="true">
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
            <div class="kpi-val kpi-val-money">{{ P::net($latest)->format() }}</div>
            {{-- Gross is deliberately not shown on the portal: net is what
                 lands in the account, and the figures behind it belong on the
                 payslip rather than being restated here. --}}
            <span class="kpi-sub">Breakup is on the payslip</span>
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
            <div class="kpi-lbl">Annual CTC</div>
            <div class="kpi-val kpi-val-money">{{ P::annualCtc($latest)->format() }}</div>
            {{-- Says where the number came from, because a CTC figure that
                 cannot be traced to a monthly one is a figure people
                 distrust. --}}
            <span class="kpi-sub">Twelve months at your current salary</span>
        </div>
    </div>

    <div class="kpi">
        <div class="kpi-ic tone-soft" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
            </svg>
        </div>
        <div class="kpi-body">
            <div class="kpi-lbl">Employment</div>
            <div class="kpi-val kpi-val-status">{{ $identity['type'] ?? '—' }}</div>
            <span class="kpi-sub">Joined {{ P::date($employee['joined']) }}</span>
        </div>
    </div>

</section>
