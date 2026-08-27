@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\MoneyBag;
    use App\Support\SalaryPresenter as P;
    $total = MoneyBag::of($records->map(fn (array $r) => $r['net']));
@endphp

@section('title', 'Confirm payment')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('salary.index', ['period' => $period]) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Payroll
            </a>
            <h1>Confirm payment</h1>
            <p>{{ P::period($period) }}</p>
        </div>
    </div>

    @if ($records->isEmpty())
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'Nothing here can be marked paid',
            'message' => 'Everyone selected either has no payslip on file or has already been paid. Nothing has been changed.',
        ])
    @else
        {{--
            The whole point of this page: say plainly what is about to happen,
            to how many people, for how much, before it happens.
        --}}
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'This records that the money has gone',
            'message' => 'It does not transfer anything — the bank does that. Confirming marks these '.$records->count().' '.\Illuminate\Support\Str::plural('record', $records->count()).' as paid, and that is written to the audit log against your name.',
        ])

        @if ($skipped > 0)
            {{-- Stated rather than swallowed: somebody ticked rows that cannot
                 be paid, and they should know which count was dropped. --}}
            @include('partials.notice', [
                'tone' => 'info',
                'title' => $skipped.' '.\Illuminate\Support\Str::plural('row', $skipped).' left out',
                'message' => 'They have no payslip on file, or were already paid. Only the rows below will be changed.',
            ])
        @endif

        <div class="card">
            <div class="section-hd">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                </svg>
                About to be marked paid
                <span class="tab-count">{{ $records->count() }}</span>
            </div>

            <div class="card-body-table">
                <table class="data-table data-table-stack" role="table">
                    <thead>
                        <tr role="row">
                            <th role="columnheader" scope="col">Employee</th>
                            <th role="columnheader" scope="col">Payslip</th>
                            <th role="columnheader" scope="col" class="col-money">Net pay</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr role="row">
                                <td role="cell" class="cell-lead" data-label="Employee">
                                    <span class="name-cell">
                                        <span class="avatar {{ Avatar::tint($record['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($record['employee_record']['name']) }}</span>
                                        <span class="name-cell-text">
                                            <strong>{{ $record['employee_record']['name'] }}</strong>
                                            <span>{{ $record['employee'] }} · {{ $record['employee_record']['department'] }}</span>
                                        </span>
                                    </span>
                                </td>
                                <td role="cell" data-label="Payslip">{{ $record['payslip']['name'] }}</td>
                                <td role="cell" class="cell-money" data-label="Net pay">
                                    <span class="money">{{ P::net($record) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr role="row">
                            <td role="cell" colspan="2">Total</td>
                            <td role="cell" class="cell-money money">{{ $total->format() }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="sl-confirm-actions">
                <form method="POST" action="{{ route('salary.pay') }}">
                    @csrf
                    <input type="hidden" name="period" value="{{ $period }}">
                    @foreach ($records as $record)
                        <input type="hidden" name="employees[]" value="{{ $record['employee'] }}">
                    @endforeach

                    {{-- TODO (backend phase): the write. §6 wants an audit entry
                         naming who confirmed it and for which period, and §2.6
                         restricts it to the finance role. It must be
                         idempotent — running it against an already-paid record
                         must not move that record's payment date. --}}
                    <button class="btn btn-primary" type="submit" disabled title="Recording payment is not built yet">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Yes, mark {{ $records->count() }} {{ \Illuminate\Support\Str::plural('person', $records->count()) }} paid
                    </button>
                </form>

                <a class="btn btn-ghost" href="{{ route('salary.index', ['period' => $period]) }}">Cancel</a>
            </div>
        </div>
    @endif
@endsection
