@php
    use App\Support\Avatar;
    use App\Support\TeamPresenter as T;
@endphp

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title">Assigned Teams</span>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Team</th>
                    <th role="columnheader" scope="col">Team Lead</th>
                    <th role="columnheader" scope="col">Members</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($teams as $team)
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Team">
                            <a class="row-link team-cell" href="{{ route('teams.show', ['team' => $team['id']]) }}">
                                <span class="chip {{ Avatar::tint($team['name']) }}" aria-hidden="true">{{ T::chip($team['name']) }}</span>
                                <span class="team-cell-text">
                                    <strong>{{ $team['name'] }}</strong>
                                    <span>{{ $team['purpose'] }}</span>
                                </span>
                            </a>
                        </td>

                        <td role="cell" data-label="Team lead">
                            @if ($team['lead_record'])
                                <span class="lead-cell">
                                    <span class="avatar {{ Avatar::tint($team['lead_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($team['lead_record']['name']) }}</span>
                                    <span class="lead-cell-text">
                                        <strong>{{ $team['lead_record']['name'] }}</strong>
                                        <span>{{ $team['lead_record']['designation'] }}</span>
                                    </span>
                                </span>
                            @else
                                <span class="lead-empty">No lead assigned</span>
                            @endif
                        </td>

                        <td role="cell" class="cell-tight" data-label="Members">
                            <span class="count-inline">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                </svg>
                                {{ $team['member_count'] }}
                                <span class="unit">{{ \Illuminate\Support\Str::plural('member', $team['member_count']) }}</span>
                            </span>
                        </td>

                        <td role="cell" class="cell-actions">
                            <a class="row-menu" href="{{ route('teams.show', ['team' => $team['id']]) }}">
                                <span class="sr-only">Open {{ $team['name'] }}</span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td role="cell" colspan="4">
                            <div class="table-empty">
                                <strong>No teams on this project yet.</strong>
                                Assign one to get started.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
