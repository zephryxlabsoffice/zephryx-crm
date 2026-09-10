@extends('layouts.app')

@php
    use App\Support\InvoicePresenter as P;

    $pill = P::status($invoice['status']);
    $due = P::dueState($invoice);
@endphp

@section('title', $invoice['id'])

@section('content')
    {{--
        The same document the staff side renders, from the same partial.

        §9 makes this the one screen where our view and the client's must show
        the same thing, because it is the screen that gets argued about on a
        call. Rebuilding the layout here — even faithfully — would guarantee
        that the two drift apart the first time somebody edits one of them.

        What is NOT reused is the rail. The staff summary links into
        projects.show, a staff route, and sits beside Send, Cancel and Record
        Payment. Those are ours.
    --}}
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('client.invoices.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Invoices
            </a>
            <h1>{{ $invoice['id'] }}</h1>
            <p>@if ($invoice['project_record']){{ $invoice['project_record']['name'] }}@else Invoice @endif</p>
        </div>

        <div class="hd-actions">
            {{-- The printable copy. Not a PDF file — this host has no PDF
                 library, so the route returns a page built for printing and
                 says so, rather than shipping a file that claims to be a PDF
                 and is not. Ownership is re-checked and the download is
                 audited. See Client\InvoiceController::download. --}}
            <a class="btn btn-outline" href="{{ route('client.invoices.download', $invoice['id']) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Printable copy
            </a>
        </div>
    </div>

    @if ($invoice['status'] === P::CANCELLED)
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'This invoice was cancelled',
            'message' => 'It keeps its number so the sequence has no gap. Nothing is owed against it.',
        ])
    @elseif ($invoice['status'] === P::OVERDUE)
        @include('partials.notice', [
            'tone' => 'danger',
            'title' => $due['label'],
            'message' => $invoice['balance']->format().' is still outstanding. It was due on '.P::date($invoice['due_date']).'.',
        ])
    @endif

    <section class="inv-doc-grid">
        <div class="inv-doc-main">
            @include('invoices.partials.document')
            @include('invoices.partials.payment-history')
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Status</strong>
                    <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Invoice total</span>
                    <span class="stat-value">{{ $invoice['total']->format() }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Paid</span>
                    <span class="stat-value">{{ $invoice['paid']->format() }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Balance</span>
                    <span class="stat-value">{{ $invoice['balance']->format() }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Due</span>
                    <span class="stat-value"><span class="{{ $due['tone'] }}">{{ P::date($invoice['due_date']) }}</span></span>
                </div>

                @if ($invoice['project_record'])
                    <div class="stat-row">
                        <span class="stat-label">Project</span>
                        <span class="stat-value">
                            <a class="dash-link" href="{{ route('client.projects.show', $invoice['project_record']['id']) }}">
                                {{ $invoice['project_record']['name'] }}
                            </a>
                        </span>
                    </div>
                @endif
            </section>

            @include('client.partials.help')
        </aside>
    </section>
@endsection
