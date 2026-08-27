@extends('layouts.app')

@section('title', 'My Project Tickets')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>My Project Tickets</h1>
            <p>Tickets raised against projects you work on, whoever is handling them.</p>
        </div>

        @include('tickets.partials.actions', ['current' => 'projects'])
    </div>

    @include('tickets.partials.kpis', ['totalLabel' => 'On My Projects', 'totalSub' => 'All time'])

    {{-- The project column earns its place here: this is the one list where
         the rows come from more than one project. --}}
    @include('tickets.partials.table', [
        'title' => 'Tickets on your projects',
        'action' => route('tickets.projects'),
        'columns' => ['type', 'project', 'raiser', 'assignee', 'status'],
        'emptyTitle' => 'No tickets on your projects.',
        'emptyBody' => 'Client tickets raised against your projects will appear here.',
    ])
@endsection
