@php
    use App\Support\Avatar;
    use App\Support\TeamPresenter as P;
@endphp

{{--
    The team list, shared by the managing face (/teams) and the personal one
    (/teams/mine). §12.1 keeps those as separate pages; the table itself is the
    same thing twice, so it lives here once.

    `$tools` — whether to show search and filters. My Teams shows a handful of
    rows and does not need them.
--}}
@php $tools = $tools ?? true; @endphp

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title">{{ $title ?? 'All Teams' }}</span>

        @if ($tools)
            <form class="table-tools" method="GET" action="{{ route('teams.index') }}">
                <div class="search-input">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <label class="sr-only" for="team-search">Search teams</label>
                    <input id="team-search" type="search" name="q" value="{{ $search }}" placeholder="Search teams…">
                </div>

                <label class="sr-only" for="team-lead">Filter by team lead</label>
                <select class="chip-btn" id="team-lead" name="lead" data-auto-submit>
                    <option value="">All team leads</option>
                    @foreach ($leads as $option)
                        <option value="{{ $option['id'] }}" @selected($lead === $option['id'])>{{ $option['name'] }}</option>
                    @endforeach
                </select>

                <label class="sr-only" for="team-status">Filter by status</label>
                <select class="chip-btn" id="team-status" name="status" data-auto-submit>
                    <option value="">All statuses</option>
                    @foreach (P::statusOptions() as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ P::status($option)['label'] }}</option>
                    @endforeach
                </select>

                <button class="chip-btn" type="submit">Search</button>

                @if ($filtered)
                    <a class="chip-btn chip-btn-accent" href="{{ route('teams.index') }}">Clear filters</a>
                @endif
            </form>
        @endif
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Team</th>
                    <th role="columnheader" scope="col">Team Lead</th>
                    <th role="columnheader" scope="col">Members</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Created</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($teams as $team)
                    @php $pill = P::status($team['status']); @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Team">
                            <a class="row-link team-cell" href="{{ route('teams.show', ['team' => $team['id']]) }}">
                                <span class="chip {{ Avatar::tint($team['name']) }}" aria-hidden="true">{{ P::chip($team['name']) }}</span>
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
                                {{-- A team with no lead is a real state and worth
                                     seeing, not an empty cell. --}}
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

                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Created">{{ P::created($team['created']) }}</td>

                        <td role="cell" class="cell-actions">
                            {{-- The handover made the whole <tr> clickable with an
                                 inline onclick. A row cannot be focused or opened
                                 from the keyboard, and inline handlers are blocked
                                 by our CSP — so this is a real link. --}}
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
                        <td role="cell" colspan="6">
                            <div class="table-empty">
                                @if ($tools && $filtered)
                                    <strong>No teams match that search.</strong>
                                    Try a different term, or <a class="card-link" href="{{ route('teams.index') }}">clear the filters</a>.
                                @elseif ($tools)
                                    <strong>No teams yet.</strong>
                                    Teams created by a manager will appear here.
                                @else
                                    <strong>You are not in any teams yet.</strong>
                                    A manager can add you to one.
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($teams->total() > 0)
        @include('partials.pagination', ['paginator' => $teams, 'unit' => 'teams'])
    @endif
</div>
