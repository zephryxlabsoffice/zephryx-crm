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
                    <th role="columnheader" scope="col" class="col-money">Gross</th>
                    <th role="columnheader" scope="col" class="col-money">Net pay</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Paid on</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Payslip</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($history as $run)
                    @php $pill = P::status($run['status']); @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Month">
                            <strong>{{ P::period($run['period']) }}</strong>
                        </td>
                        <td role="cell" class="cell-money" data-label="Gross">
                            <span class="money money-quiet">{{ P::totalEarnings($run)->format() }}</span>
                        </td>
                        <td role="cell" class="cell-money" data-label="Net pay">
                            <span class="money">{{ P::net($run)->format() }}</span>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Paid on">
                            <span class="{{ $run['status'] === P::PAID ? '' : 'sl-unpaid' }}">{{ P::paidOn($run) }}</span>
                        </td>
                        <td role="cell" class="cell-actions">
                            {{-- A real link to a real page. The handover's
                                 payslip control was an <a href="#"> that opened
                                 nothing. --}}
                            <a class="row-menu" href="{{ route('salary.payslip', ['period' => $run['period']]) }}">
                                <span class="sr-only">Payslip for {{ P::period($run['period']) }}</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h4"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
