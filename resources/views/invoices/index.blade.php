@extends('layouts.app')

@section('title', 'Invoices')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Invoice Management</h1>
            <p>What clients owe us, and what has been received.</p>
        </div>

        <div class="hd-actions">
            {{-- Hidden rather than disabled without the permission: a button
                 that leads straight to a 403 is worse than no button. --}}
            @if ($mayManage)
            <a class="btn btn-primary" href="{{ route('invoices.create') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Create Invoice
            </a>
            @endif

            {{-- Export streams every amount we have ever billed out of the
                 building, so §6 gives it its own permission and an audit entry
                 before it does anything. Import is worse: it creates financial
                 records, and needs per-row validation and a dry run first. --}}
            <button class="btn btn-outline" type="button" disabled title="Export needs its own permission and an audit entry">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Export
            </button>
        </div>
    </div>

    @include('invoices.partials.kpis')

    <section class="inv-grid">
        @include('invoices.partials.table')

        <aside class="rail">
            @include('invoices.partials.mix')
            @include('invoices.partials.payments')
        </aside>
    </section>
@endsection
