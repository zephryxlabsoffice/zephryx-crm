@extends('layouts.app')

@section('title', 'Escalated Tickets')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Escalated Tickets</h1>
            <p>Tickets someone could not resolve and passed up for review.</p>
        </div>

        @include('tickets.partials.actions', ['current' => 'escalated'])
    </div>

    {{--
        The queue depth leads, because it is the number this page exists for.
        Escalation is flat — one shared queue rather than an L1→L2→L3 ladder
        (decided 2026-08-27): with six staff, most rungs of a ladder would be
        empty and a ticket would sit waiting for a level nobody holds.
    --}}
    @if ($stats['total'] > 0)
        @include('partials.notice', [
            'tone' => 'warning',
            'title' => 'Waiting for review',
            'message' => $stats['total'].' '.\Illuminate\Support\Str::plural('ticket', $stats['total'])
                .' '.($stats['total'] === 1 ? 'has' : 'have').' been escalated. Each one is already with someone who could not resolve it.',
        ])
    @endif

    @include('tickets.partials.kpis', ['totalLabel' => 'Escalated', 'totalSub' => 'Awaiting review'])

    @include('tickets.partials.table', [
        'title' => 'Review queue',
        'action' => route('tickets.escalated'),
        'columns' => ['type', 'project', 'raiser', 'escalator', 'assignee', 'status'],
        'emptyTitle' => 'Nothing is escalated.',
        'emptyBody' => 'Tickets passed up for review will appear here.',
    ])
@endsection
