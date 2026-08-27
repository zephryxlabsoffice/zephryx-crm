@php
    use App\Support\Avatar;
    use App\Support\InvoicePresenter as P;
@endphp

<div class="card table-card">
    {{-- Tabs are links with their own URL, so a queue can be bookmarked and the
         back button works. "Outstanding" leads rather than "All", because it is
         the one people open this page to see. --}}
    <nav class="tabs" aria-label="Invoice queues">
        @foreach (['all' => 'All', 'outstanding' => 'Outstanding', 'overdue' => 'Overdue', 'paid' => 'Paid', 'draft' => 'Drafts'] as $key => $label)
            <a class="tab @if ($tab === $key) active @endif"
               href="{{ route('invoices.index', ['tab' => $key]) }}"
               @if ($tab === $key) aria-current="page" @endif>
                {{ $label }}
                <span class="tab-count">{{ $tabCounts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="card-hd">
        <span class="card-title">Invoices</span>

        <form class="table-tools" method="GET" action="{{ route('invoices.index') }}">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="invoice-search">Search invoices</label>
                <input id="invoice-search" type="search" name="q" value="{{ $search }}" placeholder="Search number, client or project…">
            </div>

            <label class="sr-only" for="invoice-status">Filter by status</label>
            <select class="chip-btn" id="invoice-status" name="status" data-auto-submit>
                <option value="">All statuses</option>
                @foreach (P::statusOptions() as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ P::status($option)['label'] }}</option>
                @endforeach
            </select>

            {{-- Worth having as its own filter: with two currencies in the list,
                 "show me only the dollar invoices" is how somebody makes the
                 amount column comparable down the page. --}}
            <label class="sr-only" for="invoice-currency">Filter by currency</label>
            <select class="chip-btn" id="invoice-currency" name="currency" data-auto-submit>
                <option value="">All currencies</option>
                @foreach ($currencies as $code => $label)
                    <option value="{{ $code }}" @selected($currency === $code)>{{ $code }}</option>
                @endforeach
            </select>

            <button class="chip-btn" type="submit">Search</button>

            @if ($filtered)
                <a class="chip-btn chip-btn-accent" href="{{ route('invoices.index', ['tab' => $tab]) }}">Clear filters</a>
            @endif
        </form>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Invoice</th>
                    <th role="columnheader" scope="col">Client</th>
                    <th role="columnheader" scope="col">Project</th>
                    <th role="columnheader" scope="col">Due</th>
                    <th role="columnheader" scope="col" class="col-money">Amount</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($invoices as $item)
                    @php
                        $pill = P::status($item['status']);
                        $due = P::dueState($item);
                    @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Invoice">
                            <a class="row-link" href="{{ route('invoices.show', ['invoice' => $item['id']]) }}">
                                <strong class="inv-ref">{{ $item['id'] }}</strong>
                                <span class="inv-issued">Issued {{ P::date($item['invoice_date']) }}</span>
                            </a>
                        </td>

                        <td role="cell" data-label="Client">
                            <span class="name-cell">
                                <span class="avatar {{ Avatar::tint($item['client']) }}" aria-hidden="true">{{ Avatar::initials($item['client']) }}</span>
                                <span class="name-cell-text"><strong>{{ $item['client'] }}</strong></span>
                            </span>
                        </td>

                        <td role="cell" data-label="Project">
                            {{ $item['project_record']['name'] ?? '—' }}
                        </td>

                        <td role="cell" class="cell-tight" data-label="Due">
                            <span class="due-cell">
                                <strong>{{ P::date($item['due_date']) }}</strong>
                                <span class="{{ $due['tone'] }}">{{ $due['label'] }}</span>
                            </span>
                        </td>

                        {{--
                            The amount cell carries its own currency symbol on
                            every row. With a mixed list, a bare "2,000" in a
                            column headed "Amount" is genuinely ambiguous, and
                            the ambiguity is worth about eighty times the
                            difference.

                            When some of it has been paid, the balance is what
                            matters and the total is the context — so the
                            balance leads and the total is stated beneath it.
                        --}}
                        <td role="cell" class="cell-money" data-label="Amount">
                            @if ($item['paid']->isPositive() && ! $item['balance']->isZero())
                                <span class="money-cell">
                                    <strong class="money">{{ $item['balance']->format() }}</strong>
                                    <span class="money-note">of {{ $item['total']->format() }}</span>
                                </span>
                            @else
                                <span class="money-cell">
                                    <strong class="money">{{ $item['total']->format() }}</strong>
                                    <span class="money-note">{{ $item['currency'] }}</span>
                                </span>
                            @endif
                        </td>

                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                        </td>

                        <td role="cell" class="cell-actions">
                            <a class="row-menu" href="{{ route('invoices.show', ['invoice' => $item['id']]) }}">
                                <span class="sr-only">Open {{ $item['id'] }}</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td role="cell" colspan="7">
                            <div class="table-empty">
                                @if ($filtered)
                                    <strong>No invoices match that search.</strong>
                                    Try a different term, or <a class="card-link" href="{{ route('invoices.index', ['tab' => $tab]) }}">clear the filters</a>.
                                @else
                                    <strong>No invoices here yet.</strong>
                                    Invoices raised against clients and projects will appear in this list.
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($invoices->total() > 0)
        @include('partials.pagination', ['paginator' => $invoices, 'unit' => 'invoices'])
    @endif
</div>
