@extends('layouts.app')

@php use App\Support\InvoicePresenter as IP; @endphp

@section('title', 'Invoices')

@section('content')
    <div class="page-hd">
        <h1>Invoices</h1>
        <p>What has been billed, what has been paid, and what is outstanding.</p>
    </div>


    {{--
        Four tiles, computed from the same collection the table below lists.

        The handover had five. The fifth read "Total Paid ₹18,75,000" and was a
        word-for-word repeat of the Paid tile's own subtitle; its Pending tile
        claimed four invoices totalling ₹4,20,000 above a table showing three
        totalling ₹6,70,000. Both problems have the same cause — figures typed
        in rather than derived — and the same fix.

        Money is a MoneyBag, never a number: a mixed-currency set has no single
        total without an exchange rate this system does not have.
    --}}
    <section class="kpi-row" aria-label="Invoice summary">
        @include('client.partials.stat', [
            'label' => 'Outstanding',
            'value' => $stats['outstanding']->headline()['lead'],
            'sub' => $stats['outstanding']->headline()['note'] ?: 'Across unpaid invoices',
            'icon' => 'invoices',
            'tone' => 'tone-warn',
        ])

        @include('client.partials.stat', [
            'label' => 'Paid to date',
            'value' => $stats['collected']->headline()['lead'],
            'sub' => $stats['paid'].' settled',
            'icon' => 'salary',
            'tone' => 'tone-soft',
        ])

        @include('client.partials.stat', [
            'label' => 'Awaiting payment',
            'value' => $stats['sent'] + $stats['partial'],
            'sub' => 'Inside their terms',
            'icon' => 'reports',
            'tone' => 'tone-accent',
        ])

        @include('client.partials.stat', [
            'label' => 'Overdue',
            'value' => $stats['overdue'],
            'sub' => $stats['overdue'] > 0 ? 'Past the due date' : 'Nothing past its terms',
            'icon' => 'attendance',
            'tone' => $stats['overdue'] > 0 ? 'tone-warn' : 'tone-soft',
        ])
    </section>

    <section class="dash-grid">
        <div class="dash-main">
            <section class="card table-card">
                <div class="card-hd">
                    <span class="card-title">All invoices</span>

                    {{-- A GET form, so a filtered list is a URL somebody can
                         bookmark and the back button behaves. --}}
                    <form class="table-tools" method="GET" action="{{ route('client.invoices.index') }}">
                        <label class="sr-only" for="inv-status">Status</label>
                        <select class="chip-btn" id="inv-status" name="status" data-auto-submit>
                            <option value="">All statuses</option>
                            @foreach (IP::statusOptions() as $option)
                                {{-- Drafts are not a client-facing state and
                                     never appear in this list. --}}
                                @continue($option === IP::DRAFT)
                                <option value="{{ $option }}" @selected($status === $option)>{{ IP::status($option)['label'] }}</option>
                            @endforeach
                        </select>

                        <button class="chip-btn" type="submit">Filter</button>
                    </form>
                </div>

                @if ($invoices->isEmpty())
                    <div class="card-body">
                        <p class="rail-empty">No invoices to show.</p>
                    </div>
                @else
                    <div class="card-body-table">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Invoice</th>
                                    <th scope="col">Issued</th>
                                    <th scope="col">Due</th>
                                    <th scope="col">Amount</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoices as $invoice)
                                    @php
                                        $state = IP::statusOf($invoice);
                                        $due = IP::dueState($invoice);
                                    @endphp
                                    <tr>
                                        <td>
                                            <a class="row-link" href="{{ route('client.invoices.show', $invoice['id']) }}">
                                                <strong>{{ $invoice['id'] }}</strong>
                                                <span class="dash-sub">{{ $invoice['project'] }}</span>
                                            </a>
                                        </td>
                                        <td class="cell-tight">{{ IP::date($invoice['invoice_date']) }}</td>
                                        <td class="cell-tight">
                                            <div class="due-cell">
                                                <strong>{{ IP::date($invoice['due_date']) }}</strong>
                                                <span class="{{ $due['tone'] }}">{{ $due['label'] }}</span>
                                            </div>
                                        </td>
                                        <td class="cell-tight">{{ $invoice['total']->short() }}</td>
                                        <td class="cell-tight">
                                            <span class="pill {{ IP::status($state)['tone'] }}">{{ IP::status($state)['label'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @include('partials.pagination', ['paginator' => $invoices])
                @endif
            </section>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Summary</strong>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Invoices</span>
                    <span class="stat-value">{{ $stats['total'] }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Outstanding</span>
                    <span class="stat-value">{{ $stats['outstanding']->format() }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Paid to date</span>
                    <span class="stat-value">{{ $stats['collected']->format() }}</span>
                </div>
            </section>

            @if ($recentlyPaid->isNotEmpty())
                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>Recently settled</strong>
                    </div>

                    <div class="rail-list">
                        @foreach ($recentlyPaid as $invoice)
                            <a class="rail-row" href="{{ route('client.invoices.show', $invoice['id']) }}">
                                <div class="rail-body">
                                    <strong>{{ $invoice['id'] }}</strong>
                                    <span>{{ $invoice['project'] }}</span>
                                </div>
                                <span class="rail-time">{{ $invoice['total']->short() }}</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @include('client.partials.help')
        </aside>
    </section>
@endsection
