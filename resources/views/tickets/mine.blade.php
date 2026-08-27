@extends('layouts.app')

@section('title', 'My Tickets')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>My Tickets</h1>
            <p>Everything you have raised, and where it has got to.</p>
        </div>

        @include('tickets.partials.actions', ['current' => 'mine'])
    </div>

    @include('tickets.partials.kpis', ['totalLabel' => 'Raised by Me', 'totalSub' => 'All time'])

    {{-- "Raised by" is dropped: on this page it is always you. --}}
    @include('tickets.partials.table', [
        'title' => 'Tickets you raised',
        'action' => route('tickets.mine'),
        'columns' => ['type', 'assignee', 'status', 'updated'],
        'emptyTitle' => 'You have not raised any tickets.',
        'emptyBody' => 'Raise one when something needs fixing.',
    ])
@endsection
