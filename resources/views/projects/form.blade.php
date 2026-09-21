@extends('layouts.app')

@php
    use App\Models\Project as ProjectModel;
    use App\Support\ProjectDirectory;
    use App\Support\ProjectPresenter as P;

    // One template for creating and for editing.
    $editing = $project !== null;
@endphp

@section('title', $editing ? 'Edit '.$project->name : 'Add a project')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ $editing ? route('projects.show', ['project' => $project->reference]) : route('projects.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                {{ $editing ? $project->name : 'Projects' }}
            </a>
            <h1>{{ $editing ? 'Edit this project' : 'Add a project' }}</h1>
            <p>
                @if ($editing)
                    Changes are recorded against this project, with who made them.
                @else
                    Work is always for a client. Teams can be assigned now or later.
                @endif
            </p>
        </div>
    </div>

    <form class="an-form" method="POST"
          action="{{ $editing ? route('projects.update', ['project' => $project->reference]) : route('projects.store') }}">
        @csrf

        <section class="an-form-grid">
            <div class="an-form-main">
                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        </svg>
                        The work
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="proj-name">Project name</label>
                            <input id="proj-name" name="name" type="text" required
                                   value="{{ old('name', $project?->name) }}">
                            @error('name')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="proj-reference">Reference</label>
                            {{-- Shown, never typed. Derived from the highest
                                 existing one in the year, so it cannot be
                                 reissued to a second project. --}}
                            <input id="proj-reference" type="text" value="{{ $reference }}" disabled>
                            <span class="pay-hint">Assigned automatically and never reused.</span>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="proj-client">Client</label>
                            {{-- Completed engagements are not offered: starting
                                 new work against one is a mistake the dropdown
                                 should not invite. --}}
                            <select id="proj-client" name="client_id" required>
                                <option value="">Choose a client</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}"
                                        @selected((int) old('client_id', $project?->client_id) === $client->id)>
                                        {{ $client->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('client_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="proj-manager">Project manager <span class="an-optional">(optional)</span></label>
                            <select id="proj-manager" name="manager_id">
                                <option value="">Unassigned</option>
                                @foreach ($managers as $manager)
                                    <option value="{{ $manager->id }}"
                                        @selected((int) old('manager_id', $project?->manager_id) === $manager->id)>
                                        {{ $manager->user?->name }} ({{ $manager->user?->user_id }})
                                    </option>
                                @endforeach
                            </select>
                            @error('manager_id')
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
                        Dates and state
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="proj-started">Started on <span class="an-optional">(optional)</span></label>
                            <input id="proj-started" name="started_on" type="date"
                                   value="{{ old('started_on', $project?->started_on?->toDateString()) }}">
                            @error('started_on')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="proj-deadline">Deadline</label>
                            <input id="proj-deadline" name="deadline" type="date" required
                                   value="{{ old('deadline', $project?->deadline?->toDateString()) }}">
                            <span class="pay-hint">Required. Every list in this module is built around what is late.</span>
                            @error('deadline')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="proj-status">Status</label>
                            <select id="proj-status" name="status" required>
                                @foreach (ProjectModel::STATUSES as $value)
                                    <option value="{{ $value }}" @selected(old('status', $project?->status ?? 'planning') === $value)>
                                        {{ P::status($value)['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('status')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="proj-priority">Priority</label>
                            <select id="proj-priority" name="priority" required>
                                @foreach (ProjectModel::PRIORITIES as $value)
                                    <option value="{{ $value }}" @selected(old('priority', $project?->priority ?? 'medium') === $value)>
                                        {{ P::priority($value)['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('priority')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    @if ($editing)
                        <div class="prose prose-quiet">
                            <p>
                                Progress is {{ ProjectDirectory::progress($project) }}%,
                                computed from this project's tasks — completed over total. There is
                                nothing to type here; add, assign and complete tasks to move it.
                            </p>
                        </div>
                    @endif
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="8" r="3"/><circle cx="5" cy="17" r="2.5"/><circle cx="19" cy="17" r="2.5"/>
                        </svg>
                        Teams on this project
                    </div>

                    @if ($teams->isEmpty())
                        <p class="att-rail-note">There are no active teams to assign yet.</p>
                    @else
                        <div class="form-grid">
                            @foreach ($teams as $team)
                                <div class="form-field">
                                    <label class="check-row">
                                        <input type="checkbox" name="teams[]" value="{{ $team->id }}"
                                               @checked(in_array($team->id, old('teams', $assigned) ?: [], false))>
                                        <span>{{ $team->name }}</span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <span class="pay-hint">
                            Unticking a team takes it off the project. Nobody's team membership changes.
                        </span>
                    @endif
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
                                The project is updated and the change is written to the audit log
                                with your name against it. Its reference does not change, so
                                every update written against it stays attached.
                            </p>
                        @else
                            <p>
                                The project is created and appears on the list, and on the
                                personal page of everybody managing it or in a team assigned to
                                it. They can post an end-of-day update against it straight away.
                            </p>
                        @endif
                    </div>

                    <button class="btn btn-primary" type="submit">
                        {{ $editing ? 'Save changes' : 'Add project' }}
                    </button>

                    <a class="btn btn-outline btn-sm"
                       href="{{ $editing ? route('projects.show', ['project' => $project->reference]) : route('projects.index') }}">
                        Cancel
                    </a>
                </section>
            </aside>
        </section>
    </form>
@endsection
