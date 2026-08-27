@extends('layouts.app')

@section('title', 'Team Tasks')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Team Tasks</h1>
            <p>Tasks held by a team rather than a person. Put someone on them.</p>
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

    {{-- The waiting-to-be-assigned count is the whole point of this page, so it
         leads rather than sitting in the tile row. --}}
    @if ($stats['total'] > 0)
        @include('partials.notice', [
            'tone' => 'info',
            'message' => $stats['total'].' '.\Illuminate\Support\Str::plural('task', $stats['total'])
                .' '.($stats['total'] === 1 ? 'is' : 'are').' assigned to a team but not yet to a person.',
        ])
    @endif

    @include('tasks.partials.kpis', ['totalLabel' => 'Team Tasks', 'totalSub' => 'Awaiting an assignee'])

    @include('tasks.partials.table', [
        'title' => 'Waiting to be assigned',
        'action' => route('tasks.team'),
        'emptyTitle' => 'Every team task has someone on it.',
        'emptyBody' => 'Nothing is waiting to be assigned.',
    ])
@endsection
