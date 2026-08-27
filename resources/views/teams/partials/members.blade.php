@php
    use App\Support\Avatar;
    use App\Support\EmployeePresenter as E;
@endphp

@php
    $tabs = [
        'all' => ['All members', $tabCounts['all']],
        'by_department' => ['By department', null],
        'on_leave' => ['On leave', $tabCounts['on_leave']],
        'inactive' => ['Inactive', $tabCounts['inactive']],
    ];
    $lastDepartment = null;
@endphp

<div class="card table-card">
    {{-- Tabs are links with their own URL, so a filtered view can be
         bookmarked and the back button works. The handover used buttons. --}}
    <nav class="tabs" aria-label="Member views">
        @foreach ($tabs as $key => [$label, $count])
            <a class="tab @if ($tab === $key) active @endif"
               href="{{ route('teams.show', ['team' => $team['id'], 'tab' => $key]) }}"
               @if ($tab === $key) aria-current="page" @endif>
                {{ $label }}
                @if ($count !== null)
                    <span class="tab-count">{{ $count }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="card-hd">
        <form class="table-tools" method="GET" action="{{ route('teams.show', ['team' => $team['id']]) }}">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="member-search">Search members</label>
                <input id="member-search" type="search" name="q" value="{{ $search }}" placeholder="Search members…">
            </div>

            <label class="sr-only" for="member-department">Filter by department</label>
            <select class="chip-btn" id="member-department" name="department" data-auto-submit>
                <option value="">All departments</option>
                @foreach ($departments as $option)
                    <option value="{{ $option }}" @selected($department === $option)>{{ $option }}</option>
                @endforeach
            </select>

            <button class="chip-btn" type="submit">Search</button>

            @if ($filtered)
                <a class="chip-btn chip-btn-accent" href="{{ route('teams.show', ['team' => $team['id'], 'tab' => $tab]) }}">Clear filters</a>
            @endif
        </form>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Member</th>
                    <th role="columnheader" scope="col">Department</th>
                    <th role="columnheader" scope="col">Role</th>
                    <th role="columnheader" scope="col">Email</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Joined</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($members as $member)
                    @php $pill = E::status($member['status']); @endphp

                    @if ($grouped && $member['department'] !== $lastDepartment)
                        @php $lastDepartment = $member['department']; @endphp
                        <tr class="group-row" role="row">
                            <td role="cell" colspan="7">{{ $member['department'] }}</td>
                        </tr>
                    @endif

                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Member">
                            <a class="row-link emp-name" href="{{ route('employees.show', ['employee' => $member['user_id']]) }}">
                                <span class="avatar {{ Avatar::tint($member['name']) }}" aria-hidden="true">{{ Avatar::initials($member['name']) }}</span>
                                <span class="emp-name-text">
                                    <strong>{{ $member['name'] }}</strong>
                                    <span>{{ $member['user_id'] }}</span>
                                </span>
                            </a>
                        </td>
                        <td role="cell" data-label="Department"><span class="tag">{{ $member['department'] }}</span></td>
                        <td role="cell" data-label="Role"><span class="tag">{{ $member['designation'] }}</span></td>
                        <td role="cell" data-label="Email">
                            <a class="emp-email" href="mailto:{{ $member['email'] }}" title="{{ $member['email'] }}">{{ $member['email'] }}</a>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Joined">{{ E::joined($member['joined']) }}</td>
                        <td role="cell" class="cell-actions">
                            <button class="row-menu" type="button" disabled title="Row actions are not built yet">
                                <span class="sr-only">Actions for {{ $member['name'] }}</span>
                                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td role="cell" colspan="7">
                            <div class="table-empty">
                                @if ($filtered)
                                    <strong>No members match that search.</strong>
                                    Try a different term, or <a class="card-link" href="{{ route('teams.show', ['team' => $team['id'], 'tab' => $tab]) }}">clear the filters</a>.
                                @elseif ($tab === 'on_leave')
                                    <strong>Nobody in this team is on leave.</strong>
                                @elseif ($tab === 'inactive')
                                    <strong>Every member of this team is active.</strong>
                                @else
                                    <strong>This team has no members yet.</strong>
                                    Add someone to get started.
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($members->total() > 0)
        @include('partials.pagination', ['paginator' => $members, 'unit' => 'members'])
    @endif
</div>
