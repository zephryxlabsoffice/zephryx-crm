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

            @if (auth()->user()->hasEmployeeBase() && \App\Support\EodPolicy::isLive())
                <a class="btn btn-outline" href="{{ route('tasks.eod') }}">Fill End of Day</a>
            @endif
        </div>
    </div>

    @include('tasks.partials.section-tabs', ['sectionTab' => 'all'])

    @include('tasks.partials.kpis')

    <section class="tasks-grid">
        @include('tasks.partials.table')

        <aside class="rail">
            @include('tasks.partials.deadlines')
            @include('tasks.partials.activity')
        </aside>
    </section>
@endsection
