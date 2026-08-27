@extends('layouts.app')

@section('title', 'Projects')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>Project Management</h1>
            <p>Plan, track and deliver every project.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-primary" href="{{ route('projects.create') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add Project
            </a>

            <a class="btn btn-outline" href="{{ route('projects.mine') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                </svg>
                My Projects
            </a>

            <a class="btn btn-outline" href="{{ route('projects.updates') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>
                </svg>
                Submit EOD
            </a>
        </div>
    </div>

    @include('projects.partials.kpis', ['scope' => 'company'])

    <section class="projects-grid">
        @include('projects.partials.table')

        <aside class="rail">
            @include('projects.partials.status-chart')
            @include('projects.partials.deadlines')
            @include('projects.partials.quick-actions')
        </aside>
    </section>
@endsection
