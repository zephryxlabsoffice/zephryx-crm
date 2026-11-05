@extends('layouts.app')

@section('title', 'My Tasks')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>My Tasks</h1>
            <p>Everything assigned to you.@if (\App\Support\EodPolicy::isLive()) Report your progress in End of Day.@endif</p>
        </div>

        <div class="hd-actions">
            @if (\App\Support\EodPolicy::isLive())
                <a class="btn btn-primary" href="{{ route('tasks.eod') }}">Fill End of Day</a>
            @endif
        </div>
    </div>

    @include('tasks.partials.section-tabs', ['sectionTab' => 'mine'])

    @include('tasks.partials.kpis', ['totalLabel' => 'My Tasks', 'totalSub' => 'Assigned to you'])

    @include('tasks.partials.table', [
        'title' => 'Tasks assigned to you',
        'action' => route('tasks.mine'),
        'emptyTitle' => 'Nothing assigned to you.',
        'emptyBody' => 'Tasks a manager or team lead gives you will appear here.',
    ])
@endsection
