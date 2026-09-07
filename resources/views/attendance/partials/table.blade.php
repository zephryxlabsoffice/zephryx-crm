@php
    use App\Support\Avatar;
    use App\Support\AttendancePresenter as P;
@endphp

<div class="card table-card">
    <div class="card-hd">
        <span class="card-title">
            {{ $isToday ? 'Today' : P::longDate($date) }}
        </span>

        {{-- Filters are a GET form with real URLs, so a filtered roll can be
             bookmarked and sent to somebody. The handover wired its dropdowns
             with an inline <script>, which our CSP blocks — none of them would
             have filtered anything. --}}
        <form class="table-tools" method="GET" action="{{ route('attendance.index') }}">
            <div class="search-input">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <label class="sr-only" for="att-search">Search employees</label>
                <input id="att-search" type="search" name="q" value="{{ $search }}" placeholder="Search name or staff ID…">
            </div>

            {{-- A real date input, not a button labelled "Select Date" that
                 opened nothing. Capped at today: there is no attendance to
                 show for a day that has not happened. --}}
            <label class="sr-only" for="att-date">Date</label>
            <input class="chip-btn att-date" id="att-date" type="date" name="date"
                   value="{{ $date }}" max="{{ \Illuminate\Support\Carbon::today()->toDateString() }}"
                   data-auto-submit>

            <label class="sr-only" for="att-department">Department</label>
            <select class="chip-btn" id="att-department" name="department" data-auto-submit>
                <option value="">All departments</option>
                @foreach ($departments as $option)
                    <option value="{{ $option }}" @selected($department === $option)>{{ $option }}</option>
                @endforeach
            </select>

            <label class="sr-only" for="att-state">Status</label>
            <select class="chip-btn" id="att-state" name="state" data-auto-submit>
                <option value="">All statuses</option>
                @foreach (P::filterableStates() as $option)
                    <option value="{{ $option }}" @selected($state === $option)>{{ P::state($option)['label'] }}</option>
                @endforeach
            </select>

            <button class="chip-btn" type="submit">Search</button>

            @if ($filtered)
                <a class="chip-btn chip-btn-accent" href="{{ route('attendance.index', ['date' => $date]) }}">Clear filters</a>
            @endif
        </form>
    </div>

    <div class="card-body-table">
        <table class="data-table data-table-stack" role="table">
            <thead>
                <tr role="row">
                    <th role="columnheader" scope="col">Employee</th>
                    <th role="columnheader" scope="col">Department</th>
                    <th role="columnheader" scope="col">Check-in</th>
                    <th role="columnheader" scope="col">Check-out</th>
                    <th role="columnheader" scope="col">Hours</th>
                    <th role="columnheader" scope="col">Status</th>
                    <th role="columnheader" scope="col"><span class="sr-only">Open</span></th>
                </tr>
            </thead>

            <tbody>
                @forelse ($rows as $row)
                    @php
                        $pill = P::state($row['state']);
                        $url = $row['id'] ? route('attendance.show', ['record' => $row['id']]) : null;
                    @endphp
                    <tr role="row">
                        <td role="cell" class="cell-lead" data-label="Employee">
                            <span class="name-cell">
                                <span class="avatar {{ Avatar::tint($row['employee_record']['name']) }}" aria-hidden="true">{{ Avatar::initials($row['employee_record']['name']) }}</span>
                                <span class="name-cell-text">
                                    <strong>{{ $row['employee_record']['name'] }}</strong>
                                    <span>{{ $row['employee'] }}</span>
                                </span>
                            </span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Department">{{ $row['employee_record']['department'] }}</td>

                        {{-- No "on time" or "22m late" under the check-in. There
                             is no late status: what the arrival time produced is
                             in the hours column, which is the thing the day is
                             actually judged on. --}}
                        <td role="cell" class="cell-tight" data-label="Check-in">
                            <strong class="att-clock">{{ P::time($row['date'], $row['check_in']) }}</strong>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Check-out">
                            <span class="att-time">
                                <strong>{{ P::time($row['date'], $row['check_out']) }}</strong>
                                @if ($row['open'])
                                    {{-- An unclosed day is not a blank cell. It
                                         is a problem with a name on it, and once
                                         the window passes it is why the day was
                                         rejected. --}}
                                    <span class="{{ $row['auto_rejected'] ? 'is-warn' : '' }}">
                                        {{ $row['auto_rejected'] ? 'Never checked out' : P::openFor($row['open_minutes']) }}
                                    </span>
                                @endif
                            </span>
                        </td>

                        <td role="cell" class="cell-tight" data-label="Hours">{{ P::hours($row['worked_minutes']) }}</td>

                        <td role="cell" class="cell-tight" data-label="Status">
                            <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                        </td>

                        <td role="cell" class="cell-actions" data-label="Open">
                            {{--
                                One link to the record. Not a row menu with
                                "Approve" and "Reject" in it.

                                The handover had a three-dot menu on every row of
                                a hundred-and-twenty-eight-person table. Rejecting
                                somebody's attendance is a thing you do after
                                reading the times, the lateness and the reason —
                                all of which are on the record, and none of which
                                fit in a row.
                            --}}
                            @if ($url)
                                <a class="btn btn-outline btn-sm" href="{{ $url }}">Open</a>
                            @else
                                <span class="att-nothing" aria-hidden="true">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td role="cell" colspan="7">
                            <div class="table-empty">
                                @if ($filtered)
                                    <strong>Nobody matches that.</strong>
                                    Try a different term, or <a class="card-link" href="{{ route('attendance.index', ['date' => $date]) }}">clear the filters</a>.
                                @elseif (! $workingDay)
                                    <strong>Not a working day.</strong>
                                    Nobody was expected in, and nobody checked in.
                                @else
                                    <strong>Nothing recorded for this day.</strong>
                                    Records appear here as people check in.
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($rows->total() > 0)
        @include('partials.pagination', ['paginator' => $rows, 'unit' => 'employees'])
    @endif
</div>
