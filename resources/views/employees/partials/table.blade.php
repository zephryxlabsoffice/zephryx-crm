@php
    use App\Support\Avatar;
    use App\Support\EmployeePresenter as P;
@endphp

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title">All Employees</span>

        <form class="table-tools" method="GET" action="{{ route('employees.index') }}">
            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="employee-search">Search employees</label>
                <input id="employee-search" type="search" name="q" value="{{ $search }}" placeholder="Search name, ID or role…">
            </div>

            <label class="sr-only" for="employee-department">Filter by department</label>
            <select class="chip-btn" id="employee-department" name="department" data-auto-submit>
                <option value="">All departments</option>
                @foreach ($departments as $option)
                    <option value="{{ $option }}" @selected($department === $option)>{{ $option }}</option>
                @endforeach
            </select>

            <label class="sr-only" for="employee-status">Filter by status</label>
            <select class="chip-btn" id="employee-status" name="status" data-auto-submit>
                <option value="">All statuses</option>
                @foreach (P::statusOptions() as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ P::status($option)['label'] }}</option>
                @endforeach
            </select>

            <button class="chip-btn" type="submit">Search</button>

            @if ($filtered)
                <a class="chip-btn chip-btn-accent" href="{{ route('employees.index') }}">Clear filters</a>
            @endif
        </form>
    </div>

    <div class="card-body-table">
        {{-- Roles stated explicitly: below 760px the CSS sets `display: block`
             on these elements to stack them as cards, which drops a table's
             implicit ARIA semantics. --}}
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Employee</th>
                    <th role="columnheader" scope="col">Department</th>
                    <th role="columnheader" scope="col">Designation</th>
                    <th role="columnheader" scope="col">Email</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col">Joined</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($employees as $employee)
                    @php $pill = P::status($employee['status']); @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Employee">
                            <a class="row-link emp-name" href="{{ route('employees.show', ['employee' => $employee['user_id']]) }}">
                                <span class="avatar {{ Avatar::tint($employee['name']) }}" aria-hidden="true">{{ Avatar::initials($employee['name']) }}</span>
                                <span class="emp-name-text">
                                    <strong>{{ $employee['name'] }}</strong>
                                    <span>{{ $employee['user_id'] }}</span>
                                </span>
                            </a>
                        </td>
                        <td role="cell" data-label="Department">{{ $employee['department'] }}</td>
                        <td role="cell" data-label="Designation">{{ $employee['designation'] }}</td>
                        <td role="cell" data-label="Email">
                            {{-- The full address is in the title and the href, so
                                 truncating the visible text loses nothing. --}}
                            <a class="emp-email" href="mailto:{{ $employee['email'] }}" title="{{ $employee['email'] }}">{{ $employee['email'] }}</a>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                        </td>
                        <td role="cell" class="cell-tight" data-label="Joined">{{ P::joined($employee['joined']) }}</td>
                        <td role="cell" class="cell-actions">
                            <button class="row-menu" type="button" disabled title="Row actions are not built yet">
                                <span class="sr-only">Actions for {{ $employee['name'] }}</span>
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
                                    <strong>No employees match that search.</strong>
                                    Try a different term, or <a class="card-link" href="{{ route('employees.index') }}">clear the filters</a>.
                                @else
                                    <strong>No employees yet.</strong>
                                    Accounts created by an administrator will appear here.
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($employees->total() > 0)
        @include('partials.pagination', ['paginator' => $employees, 'unit' => 'employees'])
    @endif
</div>
