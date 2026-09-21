@extends('layouts.app')

@php use App\Support\InvoicePresenter as P; @endphp

@section('title', $invoice['id'])

@section('content')
    @php
        $pill = P::status($invoice['status']);
        $due = P::dueState($invoice);
    @endphp

    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('invoices.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                All invoices
            </a>
            <h1>{{ $invoice['id'] }}</h1>
            <p>{{ $invoice['client'] }}@if ($invoice['project_record']) · {{ $invoice['project_record']['name'] }}@endif</p>
        </div>

        @include('invoices.partials.actions')
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
    @elseif ($invoice['status'] === P::DRAFT)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'Not sent yet',
            'message' => 'This is a draft. '.$invoice['client'].' has not seen it, and nothing is owed until it is sent.',
        ])
    @endif

    <section class="inv-doc-grid">
        <div class="inv-doc-main">
            @include('invoices.partials.document', ['isClient' => false])
            @include('invoices.partials.payment-history')
        </div>

        <aside class="rail">
            @include('invoices.partials.summary', ['pill' => $pill, 'due' => $due])
            @include('invoices.partials.upload-document')
            @include('invoices.partials.record-payment')
        </aside>
    </section>
@endsection
