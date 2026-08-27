@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\TaskPresenter as P;
    use App\Support\TeamPresenter as T;
    $statusPill = P::status($task['status']);
    $priorityChip = P::priority($task['priority']);
    $due = $task['due_meta'];
@endphp

@section('title', $task['name'])

@section('content')
    <div class="page-hd-row">
        <div class="detail-hd">
            <a class="hd-back" href="{{ route('tasks.index') }}">
                <span class="sr-only">Back to tasks</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>

            <div class="page-hd">
                <div class="task-hd-title">
                    <h1>{{ $task['name'] }}</h1>
                    <span class="pill {{ $statusPill['tone'] }}">{{ $statusPill['label'] }}</span>
                </div>
                <p class="hd-id">
                    <span data-copy-source>{{ $task['id'] }}</span>
                    <button class="copy-btn" type="button" data-copy>
                        <span class="sr-only">Copy task reference</span>
                        <svg class="icon-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="9" y="9" width="13" height="13" rx="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                        <svg class="icon-done" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                    </button>
                    · Created {{ P::date($task['created_at']) }}
                </p>
            </div>
        </div>

        <div class="hd-actions">
            {{-- TODO (backend phase): completing a task is a write, and §2.6
                 applies — only the assignee, their lead or a manager may do it. --}}
            <button class="btn btn-primary" type="button" disabled title="Completing a task is not built yet">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Mark Completed
            </button>

            <a class="btn btn-outline" href="{{ route('tasks.edit', ['task' => $task['id']]) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4z"/>
                </svg>
                Edit Task
            </a>
        </div>
    </div>

    <section class="task-detail-grid">
        <div class="task-detail-main">
            {{-- A team task with nobody on it is the state this page exists to
                 resolve, so it leads. --}}
            @if (! $task['assignee_record'] && $task['team_record'])
                <div class="notice notice-info" role="status">
                    <span class="notice-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><path d="M12 16v-5M12 8h.01"/>
                        </svg>
                    </span>
                    <div class="notice-body">
                        <strong>Nobody assigned yet</strong>
                        <p>This task belongs to {{ $task['team_record']['name'] }}. Put someone on it to get it moving.</p>
                    </div>
                    <span class="notice-action">
                        <a class="btn btn-primary" href="{{ route('tasks.edit', ['task' => $task['id']]) }}">Assign Employee</a>
                    </span>
                </div>
            @endif

            <div class="card">
                @include('tasks.partials.fields')
            </div>

            <div class="card">
                <div class="section-hd">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                        <path d="M8 13h8M8 17h5"/>
                    </svg>
                    Description
                </div>
                <div class="prose">
                    <p>
                        Create a modern, responsive layout for this task's deliverable, in line
                        with the brand guidelines and the wireframes agreed with the client.
                    </p>
                    <h4>Key requirements</h4>
                    <ul>
                        <li>Follow the agreed wireframes</li>
                        <li>Responsive on mobile, tablet and desktop</li>
                        <li>Use the brand colours and typography</li>
                        <li>Hand over source files on completion</li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="section-hd">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                    </svg>
                    Attachments
                    @if ($attachments !== [])
                        <span class="tab-count">{{ count($attachments) }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if ($attachments === [])
                        <p class="rail-empty">No files on this task.</p>
                    @else
                        <div class="attachment-grid">
                            @foreach ($attachments as $file)
                                @php $type = P::fileType($file['name']); @endphp
                                {{-- TODO (backend phase): §6 requires uploads to be
                                     served through an authorising controller, never
                                     from a public path. --}}
                                <span class="attachment">
                                    <span class="attachment-ic {{ $type['class'] }}" aria-hidden="true">{{ $type['label'] }}</span>
                                    <span class="attachment-body">
                                        <strong>{{ $file['name'] }}</strong>
                                        <span>{{ $file['kind'] }} · {{ $file['size'] }}</span>
                                    </span>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                                    </svg>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <aside class="rail">
            @include('tasks.partials.timeline')

            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Task Information</strong>
                </div>
                <div>
                    <div class="stat-row">
                        <span class="stat-label">Visibility</span>
                        <span class="stat-value">{{ $task['assignee_record'] ? 'Personal task' : 'Team task' }}</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Created</span>
                        <span class="stat-value">{{ P::date($task['created_at']) }}</span>
                    </div>
                    <div class="stat-row">
                        <span class="stat-label">Due</span>
                        <span class="stat-value">{{ P::date($task['due']) }}</span>
                    </div>
                </div>
            </section>

            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Actions</strong>
                </div>
                <div class="rail-actions">
                    <a class="btn btn-primary" href="{{ route('tasks.edit', ['task' => $task['id']]) }}">Assign Employee</a>
                    <a class="btn btn-outline" href="{{ route('tasks.edit', ['task' => $task['id']]) }}">Edit Task</a>
                </div>
            </section>
        </aside>
    </section>
@endsection
