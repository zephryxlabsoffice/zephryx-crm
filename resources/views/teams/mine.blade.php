@extends('layouts.app')

@section('title', 'My Teams')

@section('content')
    {{--
        The personal face of Teams (§12.1). Reachable by anyone signed in —
        it needs no `teams.view`, because it shows only the viewer's own
        memberships.

        The handover put a back button here that called history.back(). This
        page is a destination in its own right, not a step in a flow, and
        history.back() from a fresh tab leaves the application entirely — so
        there is no back button, and the link to the managing face is where a
        person with the rights for it would expect: in the actions row.
    --}}
    <div class="page-hd-row">
        <div class="page-hd">
            <h1>My Teams</h1>
            <p>Every team you are a part of.</p>
        </div>

        <div class="hd-actions">
            <a class="btn btn-outline" href="{{ route('teams.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                All Teams
            </a>
        </div>
    </div>

    @include('teams.partials.kpis', ['scope' => 'mine'])

    @include('teams.partials.table', ['title' => 'Teams you belong to', 'tools' => false])
@endsection
