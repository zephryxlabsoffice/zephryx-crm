@php
    use App\Support\Avatar;
    use App\Support\TaskPresenter as P;
    use App\Support\TeamPresenter as T;
    $priorityChip = P::priority($task['priority']);
    $due = $task['due_meta'];
@endphp

<div class="field-grid">

    <div>
        <div class="field-lbl">Project</div>
        <div class="field-val">
            <span class="field-ic tone-accent" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                </svg>
            </span>
            <span class="field-stack">
                @if ($task['project_record'])
                    <a href="{{ route('projects.show', ['project' => $task['project_record']['id']]) }}">{{ $task['project_record']['name'] }}</a>
                    <span class="sub">{{ $task['project_record']['client'] }}</span>
                @else
                    Not linked to a project
                @endif
            </span>
        </div>
    </div>

    <div>
        <div class="field-lbl">Team</div>
        <div class="field-val">
            @if ($task['team_record'])
                <span class="chip {{ Avatar::tint($task['team_record']['name']) }}" aria-hidden="true">{{ T::chip($task['team_record']['name']) }}</span>
                <span class="field-stack">
                    <a href="{{ route('teams.show', ['team' => $task['team_record']['id']]) }}">{{ $task['team_record']['name'] }}</a>
                    <span class="sub">{{ $task['team_record']['purpose'] }}</span>
                </span>
            @else
                <span class="assignee-empty">No team</span>
            @endif
        </div>
    </div>

    <div>
        <div class="field-lbl">Assigned to</div>
        <div class="field-val">
            @if ($task['assignee_record'])
                <span class="avatar {{ Avatar::tint($task['assignee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($task['assignee_record']['name']) }}</span>
                <span class="field-stack">
                    <a href="{{ route('employees.show', ['employee' => $task['assignee_record']['user_id']]) }}">{{ $task['assignee_record']['name'] }}</a>
                    <span class="sub">{{ $task['assignee_record']['designation'] }}</span>
                </span>
            @else
                <span class="field-ic tone-warn" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    </svg>
                </span>
                <span class="field-stack">
                    Whole team
                    <span class="sub">No individual assigned</span>
                </span>
            @endif
        </div>
    </div>

    <div>
        <div class="field-lbl">Priority</div>
        <div class="field-val">
            <span class="priority {{ $priorityChip['tone'] }}">{{ $priorityChip['label'] }}</span>
        </div>
    </div>

    <div>
        <div class="field-lbl">Created</div>
        <div class="field-val">
            <span class="field-ic tone-alt" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                </svg>
            </span>
            {{ P::date($task['created_at']) }}
        </div>
    </div>

    <div>
        <div class="field-lbl">Due</div>
        <div class="field-val">
            <span class="field-ic {{ $due['state'] === 'is-overdue' ? 'tone-warn' : 'tone-accent' }}" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </span>
            <span class="field-stack">
                {{ P::date($task['due']) }}
                <span class="sub">{{ $due['label'] }}</span>
            </span>
        </div>
    </div>

</div>
