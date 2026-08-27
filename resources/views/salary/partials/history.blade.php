@php use App\Support\SalaryPresenter as P; @endphp

<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        Salary History
        <span class="tab-count">{{ $history->count() }}</span>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Month</th>
                    {{-- Net only. What was paid is a fact; the figures behind it
                         are on the payslip, which is the document that states
                         them. --}}
                    <th role="columnheader" scope="col" class="col-money">Net pay</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Paid on</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Payslip</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($history as $record)
                    @php $pill = P::status(P::statusOf($record)); @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Month">
                            <strong>{{ P::period($record['period']) }}</strong>
                        </td>
                        <td role="cell" class="cell-money" data-label="Net pay">
                            <span class="money {{ $record['net'] === null ? 'money-quiet' : '' }}">{{ P::net($record) }}</span>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Paid on">
                            <span class="{{ $record['paid_on'] ? '' : 'sl-unpaid' }}">{{ P::paidOn($record) }}</span>
                        </td>
                        <td role="cell" class="cell-actions cell-actions-wide" data-label="Payslip">
                            @if ($record['payslip'])
                                {{-- A real link to a real route. The handover's
                                     payslip control was an <a href="#">. --}}
                                <a class="btn btn-outline btn-sm" href="{{ route('salary.payslip', ['period' => $record['period']]) }}">
                                    View payslip
                                </a>
                            @else
                                <span class="sl-slip-off">Not added</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
