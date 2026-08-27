@extends('layouts.app')

@section('title', 'My Salary')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            {{--
                This page is reached by a button on Salary Management, so it
                needs a way back — unlike My Teams and My Projects, which are
                navigation destinations and correctly have none.

                TODO (backend phase): render only for a viewer holding
                `salary.view.all`. Somebody who can only see their own pay never
                came from payroll and must not be shown a door into it.
            --}}
            @if ($canViewPayroll)
                <a class="back-link" href="{{ route('salary.index') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    Payroll
                </a>
            @endif
            <h1>My Salary</h1>
            <p>Your payslips, and what is on record for you.</p>
        </div>
    </div>

    @if ($latest === null)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'No salary record yet',
            'message' => 'Nothing has been recorded for you. If that seems wrong, speak to HR — this page shows what is on record, not what should be.',
        ])
    @else
        @include('salary.partials.mine-kpis')

        {{--
            No "pay for this month" card.

            The month's figure is already the first row of the history below,
            and the breakdown behind it lives in the payslip. Restating either
            here would be a second place to keep correct, and payroll is not run
            from this page — it is run in Excel, and this is where the result is
            read.
        --}}
        <section class="ms-grid">
            <div class="ms-main">
                @include('salary.partials.history')
            </div>

            <aside class="rail">
                @include('salary.partials.identity')
            </aside>
        </section>
    @endif
@endsection
