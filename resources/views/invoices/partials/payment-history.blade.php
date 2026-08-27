@php use App\Support\InvoicePresenter as P; @endphp

{{--
    Every payment received against this invoice.

    This is the ledger the status is derived from, so it is shown in full rather
    than summarised. If somebody disputes that an invoice is settled, this is
    the screen that answers it — which is why each row carries its method and
    reference, not just an amount and a date.
--}}
<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
        </svg>
        Payments
        @if ($invoice['payments'] !== [])
            <span class="tab-count">{{ count($invoice['payments']) }}</span>
        @endif
    </div>

    @if ($invoice['payments'] === [])
        <div class="card-body">
            <p class="rail-empty">
                Nothing received against this invoice yet.
                @if ($invoice['status'] !== P::DRAFT && $invoice['status'] !== P::CANCELLED)
                    The full {{ $invoice['total']->format() }} is outstanding.
                @endif
            </p>
        </div>
    @else
        <div class="card-body-table">
            <table class="data-table data-table-stack" role="table">
                <thead>
                    <tr role="row">
                        <th role="columnheader" scope="col">Received</th>
                        <th role="columnheader" scope="col">Method</th>
                        <th role="columnheader" scope="col">Reference</th>
                        <th role="columnheader" scope="col" class="col-money">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoice['payments'] as $payment)
                        <tr role="row">
                            <td role="cell" data-label="Received">{{ P::date($payment['received_on']) }}</td>
                            <td role="cell" data-label="Method">{{ $payment['method'] }}</td>
                            <td role="cell" data-label="Reference"><span class="inv-ref-mono">{{ $payment['reference'] }}</span></td>
                            <td role="cell" class="col-money money" data-label="Amount">{{ $payment['amount_money']->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr role="row">
                        <td role="cell" colspan="3">Total received</td>
                        <td role="cell" class="col-money money">{{ $invoice['paid']->format() }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif
</div>
