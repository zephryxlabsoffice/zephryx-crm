@php use App\Support\InvoicePresenter as P; @endphp

{{--
    The document — what the client receives.

    Laid out as the printed invoice rather than as another dashboard panel,
    because this is the one screen where the staff view and the client view
    should show the same thing. If they diverge, the divergence is what gets
    argued about on a call.

    No tax block: the company is not GST-registered today (decided 2026-08-27).
    When that changes, the totals block below gains the tax rows and the line
    items gain a rate — the layout has room for both, which is why the totals
    are a definition list rather than three hard-coded rows.
--}}
<div class="card inv-doc">

    <header class="inv-doc-hd">
        <div class="inv-doc-brand">
            @include('partials.brand-mark')
            <div>
                <strong>ZephryxLabs</strong>
                <span>Kolkata, India</span>
                <span>{{ \App\Support\SupportContact::address() }}</span>
            </div>
        </div>

        <div class="inv-doc-meta">
            <span class="inv-doc-kind">Invoice</span>
            <strong class="inv-doc-no">{{ $invoice['id'] }}</strong>
            <span class="inv-doc-cur">All amounts in {{ $invoice['currency'] }}</span>
        </div>
    </header>

    <div class="inv-doc-parties">
        <div class="inv-doc-party">
            <span class="inv-doc-lbl">Billed to</span>
            <strong>{{ $invoice['client'] }}</strong>
            @if ($invoice['project_record'])
                <span>{{ $invoice['project_record']['name'] }}</span>
                <span class="inv-doc-ref">{{ $invoice['project_record']['id'] }}</span>
            @endif
        </div>

        <dl class="inv-doc-dates">
            <div>
                <dt>Invoice date</dt>
                <dd>{{ P::date($invoice['invoice_date']) }}</dd>
            </div>
            <div>
                <dt>Due date</dt>
                <dd>{{ P::date($invoice['due_date']) }}</dd>
            </div>
            <div>
                <dt>Terms</dt>
                {{-- Derived from the two dates above, never stored, so it can
                     never contradict them. --}}
                <dd>{{ P::terms($invoice) }}</dd>
            </div>
        </dl>
    </div>

    <div class="card-body-table">
        <table class="data-table inv-lines" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Description</th>
                    <th role="columnheader" scope="col" class="col-num">Qty</th>
                    <th role="columnheader" scope="col" class="col-money">Unit price</th>
                    <th role="columnheader" scope="col" class="col-money">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice['lines'] as $line)
                    <tr role="row">
                        <td role="cell">{{ $line['description'] }}</td>
                        <td role="cell" class="col-num">{{ $line['qty'] }}</td>
                        <td role="cell" class="col-money money">{{ $line['unit_price']->format() }}</td>
                        <td role="cell" class="col-money money">{{ $line['amount']->format() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{--
        The totals.

        Total is the sum of the lines above it — not a stored figure that could
        disagree with them. Balance is total minus everything received. Both are
        computed on every render, so there is no state to drift.
    --}}
    <dl class="inv-totals">
        <div class="inv-total-row">
            <dt>Total</dt>
            <dd class="money">{{ $invoice['total']->format() }}</dd>
        </div>

        @if ($invoice['paid']->isPositive())
            <div class="inv-total-row">
                <dt>Received</dt>
                <dd class="money money-in">− {{ $invoice['paid']->format() }}</dd>
            </div>
        @endif

        <div class="inv-total-row inv-total-due">
            <dt>{{ $invoice['balance']->isZero() ? 'Settled' : 'Balance due' }}</dt>
            <dd class="money">{{ $invoice['balance']->format() }}</dd>
        </div>
    </dl>

    @if ($invoice['notes'])
        <div class="inv-doc-notes">
            <span class="inv-doc-lbl">Notes</span>
            <p>{{ $invoice['notes'] }}</p>
        </div>
    @endif
</div>
