@php use App\Support\InvoicePresenter as IP; @endphp

{{--
    Money owed, and only the invoices past their terms.

    An invoice inside its terms is not news. One past them is the entire reason
    to look at this card, so the list is overdue only and the totals below say
    what the whole book looks like.

    Totals are MoneyBags, not numbers: a mixed-currency set has no single total
    without an exchange rate this system does not have. See App\Support\MoneyBag.
--}}
<section class="card table-card">
    <div class="card-hd">
        <span class="card-title">Overdue invoices</span>
        <a class="card-link" href="{{ route('invoices.index') }}">View all</a>
    </div>

    @if ($w['items']->isEmpty())
        <div class="card-body">
            <p class="rail-empty">Nothing past its terms.</p>
        </div>
    @else
        <div class="card-body-table">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Invoice</th>
                        <th scope="col">Due</th>
                        <th scope="col">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($w['items'] as $invoice)
                        @php $due = IP::dueState($invoice); @endphp
                        <tr>
                            <td>
                                <a class="row-link" href="{{ route('invoices.show', $invoice['id']) }}">
                                    <strong>{{ $invoice['id'] }}</strong>
                                    <span class="dash-sub">{{ $invoice['client'] }}</span>
                                </a>
                            </td>
                            <td class="cell-tight">
                                <span class="{{ $due['tone'] }}">{{ $due['label'] }}</span>
                            </td>
                            <td class="cell-tight">{{ $invoice['balance']->short() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="card-body dash-more">
        <span>{{ $w['stats']['outstanding']->format() }} outstanding across {{ $w['stats']['total'] }} invoices</span>
    </div>
</section>
