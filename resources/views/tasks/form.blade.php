@extends('layouts.app')

@php
    use App\Models\Task as TaskModel;
    use App\Support\TaskPresenter as P;

    $editing = $task !== null;
@endphp

@section('title', $editing ? 'Edit '.$task->name : 'Create a task')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ $editing ? route('tasks.show', ['task' => $task->reference]) : route('tasks.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                {{ $editing ? $task->name : 'Tasks' }}
            </a>
            <h1>{{ $editing ? 'Edit this task' : 'Create a task' }}</h1>
            <p>
                @if ($editing)
                    Changes are recorded against this task, with who made them.
                @else
                    A task can go to a whole team and be picked up later, or straight to a person.
                @endif
            </p>
        </div>
    </div>

    <form class="an-form" method="POST"
          action="{{ $editing ? route('tasks.update', ['task' => $task->reference]) : route('tasks.store') }}">
        @csrf

        <section class="an-form-grid">
            <div class="an-form-main">
                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                        </svg>
                        The work
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="task-name">Task</label>
                            <input id="task-name" name="name" type="text" required
                                   value="{{ old('name', $task?->name) }}">
                            @error('name')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="task-reference">Reference</label>
                            <input id="task-reference" type="text" value="{{ $reference }}" disabled>
                            <span class="pay-hint">Assigned automatically and never reused.</span>
                        </div>

                        <div class="form-field an-form-wide">
                            <label class="form-field-lbl" for="task-description">Description <span class="an-optional">(optional)</span></label>
                            <textarea id="task-description" name="description" rows="6" maxlength="5000">{{ old('description', $task?->description) }}</textarea>
                            <span class="pay-hint">What actually has to be done. Blank is better than filler.</span>
                            @error('description')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                        </svg>
                        Where it belongs
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="task-project">Project <span class="an-optional">(optional)</span></label>
                            {{-- Finished projects are not offered: attaching new
                                 work to one is a mistake the dropdown should not
                                 invite. --}}
                            <select id="task-project" name="project_id">
                                <option value="">Not linked to a project</option>
                                @foreach ($projectChoices as $project)
                                    <option value="{{ $project->id }}"
                                        @selected((int) old('project_id', $task?->project_id) === $project->id)>
                                        {{ $project->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('project_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="task-team">Team <span class="an-optional">(optional)</span></label>
                            <select id="task-team" name="team_id">
                                <option value="">No team</option>
                                @foreach ($teamChoices as $team)
                                    <option value="{{ $team->id }}"
                                        @selected((int) old('team_id', $task?->team_id) === $team->id)>
                                        {{ $team->name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="pay-hint">A task with a team and nobody on it sits in that team's queue.</span>
                            @error('team_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="task-assignee">Assigned to <span class="an-optional">(optional)</span></label>
                            <select id="task-assignee" name="assignee_id">
                                <option value="">Nobody yet</option>
                                @foreach ($employeeChoices as $employee)
                                    <option value="{{ $employee->id }}"
                                        @selected((int) old('assignee_id', $task?->assignee_id) === $employee->id)>
                                        {{ $employee->user?->name }} ({{ $employee->user?->user_id }})
                                    </option>
                                @endforeach
                            </select>
                            @error('assignee_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                        </svg>
                        State and date
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="task-status">Status</label>
                            <select id="task-status" name="status" required>
                                @foreach (TaskModel::STATUSES as $value)
                                    <option value="{{ $value }}" @selected(old('status', $task?->status ?? 'pending') === $value)>
                                        {{ P::status($value)['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="task-priority">Priority</label>
                            <select id="task-priority" name="priority" required>
                                @foreach (TaskModel::PRIORITIES as $value)
                                    <option value="{{ $value }}" @selected(old('priority', $task?->priority ?? 'medium') === $value)>
                                        {{ P::priority($value)['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('priority')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="task-due">Due</label>
                            <input id="task-due" name="due_on" type="date" required
                                   value="{{ old('due_on', $task?->due_on?->toDateString()) }}">
                            <span class="pay-hint">Required. Every list here is built around what is late.</span>
                            @error('due_on')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rail">
                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>{{ $editing ? 'What changes' : 'What happens next' }}</strong>
                    </div>

                    <div class="prose">
                        @if ($editing)
                            <p>
                                The task is updated and the change is written to the audit log
                                with your name against it. Putting somebody different on it is
                                recorded separately, so the timeline can answer who was on this
                                and when.
                            </p>
                        @else
                            <p>
                                Left with a team and nobody on it, the task waits in that team's
                                queue for its lead to pick somebody. Given straight to a person,
                                it appears on their own list immediately.
                            </p>
                        @endif
                    </div>

                    <button class="btn btn-primary" type="submit">
                        {{ $editing ? 'Save changes' : 'Create task' }}
                    </button>

                    <a class="btn btn-outline btn-sm"
                       href="{{ $editing ? route('tasks.show', ['task' => $task->reference]) : route('tasks.index') }}">
                        Cancel
                    </a>
                </section>
            </aside>
        </section>
    </form>
@endsection
