@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\ProjectPresenter as PP;

    $deadline = PP::deadline($project['deadline'], $project['status']);
    $status = PP::status($project['status']);
@endphp

@section('title', $project['name'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('client.projects.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Projects
            </a>
            <h1>{{ $project['name'] }}</h1>
            <p>{{ $project['id'] }}</p>
        </div>
    </div>


    <section class="dash-grid">
        <div class="dash-main">

            <section class="card">
                <div class="card-body cl-hero">
                    <div class="cl-hero-top">
                        <span class="pill {{ $status['tone'] }}">{{ $status['label'] }}</span>
                        <span class="{{ $deadline['state'] }}">{{ $deadline['label'] }}</span>
                    </div>

                    <div class="progress-cell {{ PP::progressState($project['progress']) }}">
                        <progress class="progress" value="{{ $project['progress'] }}" max="100"></progress>
                        <span class="progress-pct">{{ $project['progress'] }}%</span>
                    </div>
                </div>
            </section>

            {{--
                The daily updates.

                Every one of these was published deliberately. Staff write EOD
                notes against a project and each carries a visibility that
                defaults to internal — see the head of
                App\Support\Demo\DemoProjectUpdates. Nothing on this page has
                been filtered by the template: the internal ones never reach it.

                There is also no count of what is being withheld. A line reading
                "4 internal updates hidden" would be a disclosure in itself.
            --}}
            <section class="card">
                <div class="card-hd">
                    <span class="card-title">Daily updates</span>
                </div>

                <div class="card-body">
                    @if ($updates->isEmpty())
                        <p class="rail-empty">
                            No updates on this project yet. They appear here as the team posts them.
                        </p>
                    @else
                        <ol class="cl-updates">
                            @foreach ($updates as $update)
                                <li class="cl-update">
                                    <div class="cl-update-hd">
                                        <span class="cl-update-who">
                                            <span class="avatar {{ Avatar::tint($update['author_record']['name'] ?? 'Z') }}" aria-hidden="true">
                                                {{ Avatar::initials($update['author_record']['name'] ?? 'Z') }}
                                            </span>
                                            {{ $update['author_record']['name'] ?? 'ZephryxLabs' }}
                                        </span>
                                        <span>{{ $update['posted_at']->format('d M Y, g:i A') }}</span>
                                    </div>

                                    <strong>{{ $update['title'] }}</strong>
                                    <p>{{ $update['body'] }}</p>

                                    {{--
                                        The attachment grid that was here is
                                        gone with the fixture that fed it.

                                        `project_updates` has no attachment
                                        table behind it and never had one — the
                                        files were invented for the mockup. The
                                        markup rendered nothing against real
                                        rows and would have gone on rendering
                                        nothing forever, which is worse than its
                                        absence: it reads as a feature that is
                                        broken rather than one that was never
                                        built. It comes back with the table.
                                    --}}
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </section>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Project information</strong>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Reference</span>
                    <span class="stat-value">{{ $project['id'] }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Started</span>
                    <span class="stat-value">{{ PP::date($project['start_date']) }}</span>
                </div>

                <div class="stat-row">
                    <span class="stat-label">Deadline</span>
                    <span class="stat-value">{{ PP::date($project['deadline']) }}</span>
                </div>

                @if ($project['manager_record'])
                    <div class="stat-row">
                        <span class="stat-label">Your contact</span>
                        <span class="stat-value">{{ $project['manager_record']['name'] }}</span>
                    </div>
                @endif
            </section>

            @include('client.partials.help')
        </aside>
    </section>
@endsection
