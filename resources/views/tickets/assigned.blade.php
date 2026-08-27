@extends('layouts.app')

@section('title', 'Assigned to Me')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Assigned to Me</h1>
            <p>Tickets you are responsible for resolving.</p>
        </div>

        @include('tickets.partials.actions', ['current' => 'assigned'])
    </div>

    @include('tickets.partials.kpis', ['totalLabel' => 'Assigned to Me', 'totalSub' => 'All time'])

    {{-- "Assigned to" is dropped: on this page it is always you. --}}
    @include('tickets.partials.table', [
        'title' => 'Your queue',
        'action' => route('tickets.assigned'),
        'columns' => ['type', 'raiser', 'status', 'updated'],
        'emptyTitle' => 'Nothing assigned to you.',
        'emptyBody' => 'Tickets routed to you will appear here.',
    ])
@endsection
