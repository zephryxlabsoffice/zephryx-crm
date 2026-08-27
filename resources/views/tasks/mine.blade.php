@extends('layouts.app')

@section('title', 'My Tasks')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>My Tasks</h1>
            <p>Everything assigned to you.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-outline" href="{{ route('tasks.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 12l2 2 4-4"/>
                </svg>
                All Tasks
            </a>
        </div>
    </div>

    @include('tasks.partials.kpis', ['totalLabel' => 'My Tasks', 'totalSub' => 'Assigned to you'])

    @include('tasks.partials.table', [
        'title' => 'Tasks assigned to you',
        'action' => route('tasks.mine'),
        'emptyTitle' => 'Nothing assigned to you.',
        'emptyBody' => 'Tasks a manager or team lead gives you will appear here.',
    ])
@endsection
