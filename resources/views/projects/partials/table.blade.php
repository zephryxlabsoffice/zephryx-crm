@php
    use App\Support\Avatar;
    use App\Support\ProjectPresenter as P;
@endphp

{{--
    The project list, shared by the managing face and the personal one.

    `$tools`    — search and filters. My Projects shows a handful of rows.
    `$progress` — the progress column. The personal list omits it, as the
                  handover does; it is the managing view's metric.
--}}
@php
    $tools = $tools ?? true;
    $progress = $progress ?? true;
    $columns = $progress ? 8 : 7;
@endphp

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title">{{ $title ?? 'All Projects' }}</span>

        @if ($tools)
            <form class="table-tools" method="GET" action="{{ route('projects.index') }}">
                <div class="search-input">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <label class="sr-only" for="project-search">Search projects</label>
                    <input id="project-search" type="search" name="q" value="{{ $search }}" placeholder="Search name, ref or client…">
                </div>

                <label class="sr-only" for="project-status">Filter by status</label>
                <select class="chip-btn" id="project-status" name="status" data-auto-submit>
                    <option value="">All statuses</option>
                    @foreach (P::statusOptions() as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ P::status($option)['label'] }}</option>
                    @endforeach
                </select>

                <label class="sr-only" for="project-priority">Filter by priority</label>
                <select class="chip-btn" id="project-priority" name="priority" data-auto-submit>
                    <option value="">All priorities</option>
                    @foreach (P::priorityOptions() as $option)
                        <option value="{{ $option }}" @selected($priority === $option)>{{ P::priority($option)['label'] }}</option>
                    @endforeach
                </select>

                <button class="chip-btn" type="submit">Search</button>

                @if ($filtered)
                    <a class="chip-btn chip-btn-accent" href="{{ route('projects.index') }}">Clear filters</a>
                @endif
            </form>
        @endif
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Project</th>
                    <th role="columnheader" scope="col">Client</th>
                    <th role="columnheader" scope="col">Manager</th>
                    @if ($progress)
                        <th role="columnheader" scope="col">Progress</th>
                    @endif
                    <th role="columnheader" scope="col">Deadline</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Priority</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($projects as $item)
                    @php
                        $statusPill = P::status($item['status']);
                        $priorityChip = P::priority($item['priority']);
                        $due = $item['deadline_meta'];
                    @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Project">
                            <a class="row-link proj-cell" href="{{ route('projects.show', ['project' => $item['id']]) }}">
                                <span class="chip {{ P::tint($item['id']) }}" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                                    </svg>
                                </span>
                                <span class="proj-cell-text">
                                    <strong>{{ $item['name'] }}</strong>
                                    <span>{{ $item['id'] }}</span>
                                </span>
                            </a>
                        </td>

                        <td role="cell" data-label="Client">{{ $item['client'] }}</td>

                        <td role="cell" data-label="Manager">
                            @if ($item['manager_record'])
                                <span class="pm-cell">
                                    <span class="avatar {{ Avatar::tint($item['manager_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($item['manager_record']['name']) }}</span>
                                    <strong>{{ $item['manager_record']['name'] }}</strong>
                                </span>
                            @else
                                <span class="pm-empty">Unassigned</span>
                            @endif
                        </td>

                        @if ($progress)
                            <td role="cell" data-label="Progress">
                                {{-- A native <progress>, not a div sized with an
                                     inline style — see components/ui.css. --}}
                                <span class="progress-cell {{ P::progressState($item['progress']) }}">
                                    <span class="progress-pct">{{ $item['progress'] }}%</span>
                                    <progress class="progress" max="100" value="{{ $item['progress'] }}">{{ $item['progress'] }}%</progress>
                                </span>
                            </td>
                        @endif

                        <td role="cell" class="cell-tight" data-label="Deadline">
                            <span class="date-cell {{ $due['state'] }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                                </svg>
                                {{ P::date($item['deadline']) }}
                            </span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $statusPill['tone'] }}">{{ $statusPill['label'] }}</span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Priority">
                            <span class="priority {{ $priorityChip['tone'] }}">{{ $priorityChip['label'] }}</span>
                        </td>

                        <td role="cell" class="cell-actions">
                            <a class="row-menu" href="{{ route('projects.show', ['project' => $item['id']]) }}">
                                <span class="sr-only">Open {{ $item['name'] }}</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td role="cell" colspan="{{ $columns }}">
                            <div class="table-empty">
                                @if ($tools && $filtered)
                                    <strong>No projects match that search.</strong>
                                    Try a different term, or <a class="card-link" href="{{ route('projects.index') }}">clear the filters</a>.
                                @elseif ($tools)
                                    <strong>No projects yet.</strong>
                                    Projects created by a manager will appear here.
                                @else
                                    <strong>No projects assigned to you.</strong>
                                    A manager can put you on one.
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($projects->total() > 0)
        @include('partials.pagination', ['paginator' => $projects, 'unit' => 'projects'])
    @endif
</div>
