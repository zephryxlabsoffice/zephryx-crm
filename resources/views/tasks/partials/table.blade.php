@php
    use App\Support\Avatar;
    use App\Support\TaskPresenter as P;
    use App\Support\TeamPresenter as T;
@endphp

{{--
    The task list, shared by all three list faces. `$action` names the route the
    filter form posts back to, so each page filters itself rather than bouncing
    to the managing view.
--}}
@php $action = $action ?? route('tasks.index'); @endphp

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title">{{ $title ?? 'All Tasks' }}</span>

        <form class="table-tools" method="GET" action="{{ $action }}">
            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="task-search">Search tasks</label>
                <input id="task-search" type="search" name="q" value="{{ $search }}" placeholder="Search tasks…">
            </div>

            <label class="sr-only" for="task-project">Filter by project</label>
            <select class="chip-btn" id="task-project" name="project" data-auto-submit>
                <option value="">All projects</option>
                @foreach ($projects as $option)
                    <option value="{{ $option['id'] }}" @selected($project === $option['id'])>{{ $option['name'] }}</option>
                @endforeach
            </select>

            <label class="sr-only" for="task-status">Filter by status</label>
            <select class="chip-btn" id="task-status" name="status" data-auto-submit>
                <option value="">All statuses</option>
                @foreach (P::statusOptions() as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ P::status($option)['label'] }}</option>
                @endforeach
            </select>

            <label class="sr-only" for="task-priority">Filter by priority</label>
            <select class="chip-btn" id="task-priority" name="priority" data-auto-submit>
                <option value="">All priorities</option>
                @foreach (P::priorityOptions() as $option)
                    <option value="{{ $option }}" @selected($priority === $option)>{{ P::priority($option)['label'] }}</option>
                @endforeach
            </select>

            <button class="chip-btn" type="submit">Search</button>

            @if ($filtered)
                <a class="chip-btn chip-btn-accent" href="{{ $action }}">Clear filters</a>
            @endif
        </form>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Task</th>
                    <th role="columnheader" scope="col">Project</th>
                    <th role="columnheader" scope="col">Assigned to</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Priority</th>
                    <th role="columnheader" scope="col">Due</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($tasks as $item)
                    @php
                        $statusPill = P::status($item['status']);
                        $priorityChip = P::priority($item['priority']);
                        $due = $item['due_meta'];
                    @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Task">
                            <a class="row-link task-cell" href="{{ route('tasks.show', ['task' => $item['id']]) }}">
                                <span class="chip {{ P::tint($item['id']) }}" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 12l2 2 4-4"/>
                                    </svg>
                                </span>
                                <span class="task-cell-text">
                                    <strong>{{ $item['name'] }}</strong>
                                    <span>{{ $item['id'] }}</span>
                                </span>
                            </a>
                        </td>

                        <td role="cell" data-label="Project">
                            <span class="stack-cell">
                                <strong>{{ $item['project_record']['name'] ?? '—' }}</strong>
                                <span>{{ $item['project_record']['client'] ?? '' }}</span>
                            </span>
                        </td>

                        <td role="cell" data-label="Assigned to">
                            {{-- A round avatar for a person, a square chip for a
                                 team: a glance down the column says which. A
                                 second (or third) assignee is named as "+N" —
                                 the full roster is on the task's own page. --}}
                            @if (! empty($item['assignee_records']))
                                @php
                                    $first = $item['assignee_records'][0];
                                    $extra = count($item['assignee_records']) - 1;
                                @endphp
                                <span class="assignee-cell">
                                    <span class="avatar {{ Avatar::tint($first['name']) }}" aria-hidden="true">{{ Avatar::initials($first['name']) }}</span>
                                    <span class="stack-cell">
                                        <strong>{{ $first['name'] }}{{ $extra > 0 ? ' +'.$extra : '' }}</strong>
                                        <span>{{ $item['team_record']['name'] ?? 'No team' }}</span>
                                    </span>
                                </span>
                            @elseif ($item['team_record'])
                                <span class="assignee-cell">
                                    <span class="chip {{ Avatar::tint($item['team_record']['name']) }}" aria-hidden="true">{{ T::chip($item['team_record']['name']) }}</span>
                                    <span class="stack-cell">
                                        <strong>{{ $item['team_record']['name'] }}</strong>
                                        <span>Whole team</span>
                                    </span>
                                </span>
                            @else
                                <span class="assignee-empty">Unassigned</span>
                            @endif
                        </td>

                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $statusPill['tone'] }}">{{ $statusPill['label'] }}</span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Priority">
                            <span class="priority {{ $priorityChip['tone'] }}">{{ $priorityChip['label'] }}</span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Due">
                            <span class="due-cell">
                                <strong>{{ P::date($item['due']) }}</strong>
                                <span class="{{ $due['state'] }}">{{ $due['label'] }}</span>
                            </span>
                        </td>

                        <td role="cell" class="cell-actions">
                            <a class="row-menu" href="{{ route('tasks.show', ['task' => $item['id']]) }}">
                                <span class="sr-only">Open {{ $item['name'] }}</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td role="cell" colspan="7">
                            <div class="table-empty">
                                @if ($filtered)
                                    <strong>No tasks match that search.</strong>
                                    Try a different term, or <a class="card-link" href="{{ $action }}">clear the filters</a>.
                                @else
                                    <strong>{{ $emptyTitle ?? 'No tasks yet.' }}</strong>
                                    {{ $emptyBody ?? 'Tasks assigned by a manager will appear here.' }}
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($tasks->total() > 0)
        @include('partials.pagination', ['paginator' => $tasks, 'unit' => 'tasks'])
    @endif
</div>
