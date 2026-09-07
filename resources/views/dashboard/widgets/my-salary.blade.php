@php use App\Support\SalaryPresenter as SP; @endphp

{{--
    Your last payslip. Not "your salary" — this module records what was paid, it
    does not calculate what is owed (see the Salary module's own rebuild).

    Nothing here shows anybody else's figures: the widget takes the viewer and
    asks only for their own records, the same reason /salary/payslip/{period}
    takes a period and no employee.
--}}
<section class="rail-card">
    <div class="rail-hd">
        <strong>Your pay</strong>
        <a class="dash-link" href="{{ route('salary.mine') }}">All</a>
    </div>

    @if ($w['latest'] === null)
        <p class="rail-empty">No payslip on file yet.</p>
    @else
        <div class="dash-next">
            <span class="dash-quiet-meta">{{ SP::period($w['latest']['period']) }}</span>
            <strong class="dash-amount">{{ SP::net($w['latest']) }}</strong>
            <span class="dash-quiet-meta">{{ SP::paidOn($w['latest']) }}</span>
        </div>
    @endif

    @if ($w['current'] && SP::statusOf($w['current']) === SP::NO_PAYSLIP)
        {{-- Mid-run: the current month exists as a record with nothing added to
             it. Said plainly, because the alternative is somebody assuming the
             figure above is this month's. --}}
        <p class="dash-note">{{ SP::period($w['current']['period']) }} has not been added yet.</p>
    @endif

    @if ($w['banked'] === null)
        {{-- The one thing on this card somebody can act on, and the reason
             payroll day goes wrong when it is missing. --}}
        <p class="dash-note dash-note-warn">
            No bank details on file — payroll cannot pay an account it does not have.
        </p>
    @endif
</section>
