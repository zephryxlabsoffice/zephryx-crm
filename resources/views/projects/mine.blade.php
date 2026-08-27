@extends('layouts.app')

@section('title', 'My Projects')

@section('content')
    {{--
        The personal face of Projects (§12.1). No `projects.view` needed — it
        shows only what the viewer is on.

        The handover's back button (to Project Management) is not carried over:
        this is a destination reached from the navigation, and the managing face
        is not somewhere everyone can go. The link to it sits in the actions row
        instead, where it is permission-gated like any other.
    --}}
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>My Projects</h1>
            <p>Every project assigned to you.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-primary" href="{{ route('projects.updates') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/>
                </svg>
                Submit EOD
            </a>

            <a class="btn btn-outline" href="{{ route('projects.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                </svg>
                All Projects
            </a>
        </div>
    </div>

    @include('projects.partials.kpis', ['scope' => 'mine'])

    @include('projects.partials.table', [
        'title' => 'Projects you are on',
        'tools' => false,
        'progress' => false,
    ])
@endsection
