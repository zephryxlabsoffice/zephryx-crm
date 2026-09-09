@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\TeamPresenter as P;
    $pill = P::status($team['status']);
@endphp

@section('title', $team['name'])

@section('content')
    <div class="page-hd-row">
        <div class="detail-hd">
            {{-- A real link, not history.back(): opened in a new tab there is
                 no history to go back to. --}}
            <a class="hd-back" href="{{ route('teams.index') }}">
                <span class="sr-only">Back to teams</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>

            <div class="page-hd">
                <div class="hd-title-row">
                    <h1>{{ $team['name'] }}</h1>
                    <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                </div>
                <p class="hd-id">
                    Team ID: <strong data-copy-source>{{ $team['id'] }}</strong>
                    <button class="copy-btn" type="button" data-copy>
                        <span class="sr-only">Copy team ID</span>
                        <svg class="icon-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="9" y="9" width="13" height="13" rx="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                        <svg class="icon-done" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </button>
                </p>
            </div>
        </div>

        <div class="hd-actions">
            @if ($mayEdit)
                <a class="btn btn-outline" href="{{ route('teams.edit', ['team' => $team['id']]) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                        <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>
                    </svg>
                    Edit Team
                </a>
            @endif
        </div>
    </div>

    @include('teams.partials.overview-kpis')

    <section class="teams-grid">
        @include('teams.partials.members')

        <aside class="rail">
            @if ($mayManageMembers)
                @include('teams.partials.add-member')
            @endif

            @include('teams.partials.composition')
            @include('teams.partials.summary')
        </aside>
    </section>
@endsection
