@php use App\Support\InvoicePresenter as P; @endphp

<section class="rail-card">
    <div class="rail-hd">
        <strong>Recent Payments</strong>
    </div>

    @if ($recentPayments === [])
        <p class="rail-empty">No payments recorded yet.</p>
    @else
        <ul class="rail-list">
            @foreach ($recentPayments as $payment)
                <li class="rail-row">
                    <div class="rail-ic tone-soft" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/>
                        </svg>
                    </div>

                    <div class="rail-body">
                        <strong>{{ $payment['client'] }}</strong>
                        {{-- The invoice number is a link: a payment that looks
                             wrong is a reason to open the invoice, and making
                             somebody search for it by hand is how they stop
                             checking. --}}
                        <span>
                            <a class="card-link" href="{{ route('invoices.show', ['invoice' => $payment['invoice']]) }}">{{ $payment['invoice'] }}</a>
                            · {{ $payment['method'] }}
                        </span>
                    </div>

                    <div class="rail-meta">
                        <strong class="money">{{ $payment['amount_money']->format() }}</strong>
                        <span>{{ P::date($payment['received_on']) }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</section>
