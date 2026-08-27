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
            <a class="btn btn-primary" href="{{ route('projects.edit', ['project' => $project['id']]) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/>
                    <line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>
                </svg>
                Assign Team
            </a>

            <a class="btn btn-outline" href="{{ route('projects.edit', ['project' => $project['id']]) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>
                </svg>
                Edit Project
            </a>
        </div>
    </div>

    @include('projects.partials.overview-kpis')

    <section class="projects-grid">
        @include('projects.partials.teams')

        <aside class="rail">
            @include('projects.partials.summary')
            @include('projects.partials.quick-actions')
        </aside>
    </section>
@endsection
