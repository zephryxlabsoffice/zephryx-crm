@extends('layouts.app')

@section('title', 'Tasks')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Task Overview</h1>
            <p>Track and manage tasks across projects and teams.</p>
        </div>

        <div class="hd-actions">
            {{-- Hidden rather than disabled for somebody without the
                 permission: a button that leads straight to a 403 is worse than
                 no button. The route is guarded either way. --}}
            @if ($mayCreate)
            <a class="btn btn-primary" href="{{ route('tasks.create') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Assign Task
            </a>
            @endif

            <a class="btn btn-outline" href="{{ route('tasks.mine') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                My Tasks
            </a>

            <a class="btn btn-outline" href="{{ route('tasks.team') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                </svg>
                Team Tasks
            </a>
        </div>
    </div>

    @include('tasks.partials.kpis')

    <section class="tasks-grid">
        @include('tasks.partials.table')

        <aside class="rail">
            @include('tasks.partials.deadlines')
            @include('tasks.partials.activity')
        </aside>
    </section>
@endsection
