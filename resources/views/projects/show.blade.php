@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\ProjectPresenter as P;
    $statusPill = P::status($project['status']);
    $priorityChip = P::priority($project['priority']);
    $due = $project['deadline_meta'];
@endphp

@section('title', $project['name'])

@section('content')
    <div class="page-hd-row">
        <div class="detail-hd">
            {{-- A real link, not history.back(): from a fresh tab there is no
                 history to go back to. --}}
            <a class="hd-back" href="{{ route('projects.index') }}">
                <span class="sr-only">Back to projects</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>

            <div class="page-hd">
                <div class="proj-hd-title">
                    <h1>{{ $project['name'] }}</h1>
                    <span class="proj-ref" data-copy-source>{{ $project['id'] }}</span>
                    <button class="copy-btn" type="button" data-copy>
                        <span class="sr-only">Copy project reference</span>
                        <svg class="icon-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="9" y="9" width="13" height="13" rx="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                        <svg class="icon-done" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </button>
                </div>
                <p>{{ $project['client'] }}</p>
            </div>
        </div>

        <div class="hd-actions">
            {{-- Anybody on the project may write today's update; it is not an
                 authority, it is reporting your own day. Somebody who is not on
                 it does not see the button and would be refused the route. --}}
            @if ($mayPost)
                <a class="btn btn-primary" href="{{ route('projects.updates.create', ['project' => $project['id']]) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>
                    </svg>
                    Submit EOD
                </a>
            @endif

            @if ($mayEdit)
                <a class="btn btn-outline" href="{{ route('projects.edit', ['project' => $project['id']]) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>
                    </svg>
                    Edit Project
                </a>
            @endif
        </div>
    </div>

    @include('projects.partials.overview-kpis')

    <section class="projects-grid">
        <div>
            @include('projects.partials.teams')
            @include('projects.partials.updates')
        </div>

        <aside class="rail">
            @include('projects.partials.summary')
            @include('projects.partials.quick-actions')
        </aside>
    </section>
@endsection
