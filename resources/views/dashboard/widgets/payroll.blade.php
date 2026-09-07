@php use App\Support\SalaryPresenter as SP; @endphp

{{--
    This month's payroll run, as a state of play rather than a total.

    "Paid out" is only what has actually gone out. A figure that folds in rows
    nobody has paid yet describes an intention, not a payment — the distinction
    the Salary module was rebuilt around.

    No individual amounts and no names: this card says how many, never how much
    for whom. Somebody's net pay is not a thing to render on a page that might
    be open on a shared screen.
--}}
<section class="card">
    <div class="card-hd">
        <span class="card-title">Payroll · {{ SP::period($w['period']) }}</span>
        <a class="card-link" href="{{ route('salary.index') }}">Open</a>
    </div>

    <div class="card-body">
        <div class="stat-row">
            <span class="stat-label">Paid</span>
            <span class="stat-value">{{ $w['stats']['paid'] }} <span class="stat-value-quiet">of {{ $w['stats']['total'] }}</span></span>
        </div>

        <div class="stat-row">
            <span class="stat-label">Awaiting payment</span>
            <span class="stat-value">
                @if ($w['stats']['awaiting_payment'] > 0)
                    <span class="is-soon">{{ $w['stats']['awaiting_payment'] }}</span>
                @else
                    0
                @endif
            </span>
        </div>

        <div class="stat-row">
            <span class="stat-label">No payslip yet</span>
            <span class="stat-value">{{ $w['stats']['no_payslip'] }}</span>
        </div>

        <div class="stat-row">
            <span class="stat-label">Paid out</span>
            <span class="stat-value">{{ $w['stats']['paid_out']->format() }}</span>
        </div>

        @if ($w['unbanked'] > 0)
            {{-- The thing that stops a payroll run on the day, found the week
                 before instead. --}}
            <p class="dash-note dash-note-warn">
                {{ $w['unbanked'] === 1 ? 'One person has' : $w['unbanked'].' people have' }}
                no bank details on file and cannot be paid.
            </p>
        @endif
    </div>
</section>
