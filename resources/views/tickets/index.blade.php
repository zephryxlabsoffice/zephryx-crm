@extends('layouts.app')

@section('title', 'Tickets')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Ticket Management</h1>
            <p>Internal operations and client support in one queue.</p>
        </div>

        @include('tickets.partials.actions', ['current' => 'index'])
    </div>

    @include('tickets.partials.kpis')

    @include('tickets.partials.table', [
        'title' => 'Tickets',
        'queues' => [
            'all' => 'All',
            'unassigned' => 'Unassigned',
            'escalated' => 'Escalated',
            'client' => 'Client',
            'in_progress' => 'In progress',
            'resolved' => 'Resolved',
        ],
    ])
@endsection
