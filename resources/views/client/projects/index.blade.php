@extends('layouts.app')

@php use App\Support\ProjectPresenter as PP; @endphp

@section('title', 'Projects')

@section('content')
    <div class="page-hd">
        <h1>Projects</h1>
        <p>Everything we are building for you, and where each piece has reached.</p>
    </div>

    @include('client.partials.switcher')

    <section class="kpi-row" aria-label="Project summary">
        @include('client.partials.stat', [
            'label' => 'Active',
            'value' => $stats['active'],
            'sub' => 'Under way now',
            'icon' => 'projects',
            'tone' => 'tone-soft',
        ])

        @include('client.partials.stat', [
            'label' => 'In review',
            'value' => $stats['review'],
            'sub' => $stats['review'] > 0 ? 'Waiting on a final look' : 'Nothing waiting',
            'icon' => 'tasks',
            'tone' => $stats['review'] > 0 ? 'tone-warn' : 'tone-soft',
        ])

        @include('client.partials.stat', [
            'label' => 'Delivered',
            'value' => $stats['completed'],
            'sub' => 'Completed projects',
            'icon' => 'reports',
            'tone' => 'tone-accent',
        ])
    </section>

    <section class="card table-card">
        <div class="card-hd">
            <span class="card-title">Your projects</span>
        </div>

        @if ($projects->isEmpty())
            <div class="card-body">
                <p class="rail-empty">Nothing here yet. A project appears once it starts.</p>
            </div>
        @else
            <div class="card-body-table">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th scope="col">Project</th>
                            <th scope="col">Progress</th>
                            <th scope="col">Status</th>
                            <th scope="col">Your contact</th>
                            <th scope="col">Deadline</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                            @php
                                $deadline = PP::deadline($project['deadline'], $project['status']);
                                $status = PP::status($project['status']);
                            @endphp
                            <tr>
                                <td>
                                    <a class="row-link" href="{{ route('client.projects.show', $project['id']) }}">
                                        <strong>{{ $project['name'] }}</strong>
                                    </a>
                                </td>
                                <td>
                                    <div class="progress-cell {{ PP::progressState($project['progress']) }}">
                                        <progress class="progress" value="{{ $project['progress'] }}" max="100"></progress>
                                        <span class="progress-pct">{{ $project['progress'] }}%</span>
                                    </div>
                                </td>
                                <td class="cell-tight"><span class="pill {{ $status['tone'] }}">{{ $status['label'] }}</span></td>
                                {{-- The one person they deal with. Not the teams
                                     on the project, which would hand over the
                                     org chart a row at a time. --}}
                                <td class="cell-tight">{{ $project['manager_record']['name'] ?? 'To be assigned' }}</td>
                                <td class="cell-tight">
                                    <div class="due-cell">
                                        <strong>{{ PP::date($project['deadline']) }}</strong>
                                        <span class="{{ $deadline['state'] }}">{{ $deadline['label'] }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
