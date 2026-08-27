@php use App\Support\SalaryPresenter as P; @endphp

{{--
    This month's pay, on the portal: the net figure and nothing else.

    The full earnings-and-deductions breakdown lives on the payslip. Printing it
    in both places means two renderings of one calculation, which is two places
    to change and two places to disagree — and the payslip is the one that has
    to be right, because it is the document.
--}}
<div class="card sl-net-card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
        </svg>
        Pay for this month
        <span class="tab-count">{{ P::period($run['period']) }}</span>
    </div>

    <div class="sl-net sl-net-lone">
        <span class="sl-net-lbl">Net pay</span>
        <strong class="sl-net-val money">{{ P::net($run)->format() }}</strong>
        <span class="sl-net-note">{{ P::paidOn($run) }} · {{ $run['method'] }}</span>
    </div>

    <p class="sl-net-cta">
        {{-- Built in one expression: a Blade newline before the full stop
             renders as "… August 2026 ." --}}
        {!! 'For the detailed salary breakup, check the <a class="card-link" href="'
            .e(route('salary.payslip', ['period' => $run['period']]))
            .'">payslip for '.e(P::period($run['period'])).'</a>.' !!}
    </p>
</div>
