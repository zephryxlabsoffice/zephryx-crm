@extends('layouts.app')

@section('title', 'Sunday roster')

{{--
    Rostering Sunday and holiday work (client portal decisions, review round,
    2026-09-11).

    No approval step — a row existing IS the roster. A Manager rosters
    anyone; a Team Lead rosters only their own team's members
    (AttendanceController::canRoster, §2.6), which is why the employee
    dropdown here is already scoped server-side rather than filtered in the
    browser.
--}}
@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('attendance.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Attendance
            </a>
            <h1>Sunday roster</h1>
            <p>A rostered Sunday or holiday counts as attendance and earns a comp-off for a full day worked.</p>
        </div>
    </div>

    <section class="att-detail-grid">
        <div class="att-main">
            <div class="card">
                <div class="section-hd">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    Upcoming and current roster
                </div>

                @if ($roster->isEmpty())
                    <div class="card-body">
                        <p class="rail-empty">Nobody is rostered for an upcoming Sunday or holiday.</p>
                    </div>
                @else
                    <div class="card-body-table">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col">Person</th>
                                    <th scope="col">Date</th>
                                    <th scope="col">Rostered by</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($roster as $entry)
                                    <tr>
                                        <td>
                                            <strong>{{ $entry->employee?->user?->name }}</strong>
                                            <span class="dash-sub">{{ $entry->employee?->user?->user_id }}</span>
                                        </td>
                                        <td class="cell-tight">{{ $entry->date->format('D, d M Y') }}</td>
                                        <td class="cell-tight">{{ $entry->rosterer?->user?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>Roster somebody</strong>
                </div>

                <form method="POST" action="{{ route('attendance.roster.store') }}">
                    @csrf

                    <div class="form-field">
                        <label class="form-field-lbl" for="roster-employee">Who</label>
                        <select id="roster-employee" name="employee_id" required>
                            <option value="">Choose a person</option>
                            @foreach ($employeeChoices as $employee)
                                <option value="{{ $employee->id }}">
                                    {{ $employee->user?->name }} ({{ $employee->user?->user_id }})
                                </option>
                            @endforeach
                        </select>
                        @error('employee_id')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-field">
                        <label class="form-field-lbl" for="roster-date">Which Sunday or holiday</label>
                        <select id="roster-date" name="date" required>
                            <option value="">Choose a date</option>
                            @foreach ($upcoming as $day)
                                <option value="{{ $day['date'] }}">{{ $day['label'] }}</option>
                            @endforeach
                        </select>
                        <span class="pay-hint">Only Sundays and announced holidays are offered — a working day needs no roster.</span>
                        @error('date')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </div>

                    <button class="btn btn-primary" type="submit">Roster them</button>
                </form>
            </section>

            <section class="rail-card">
                <div class="rail-hd">
                    <strong>What this does</strong>
                </div>
                <div class="prose prose-quiet">
                    <p>
                        Nothing is asked of the person — a row existing is the whole
                        roster. If they work the full day, one comp-off is added
                        automatically when they check out. Rostered and absent still
                        counts as absent, not as a week off.
                    </p>
                </div>
            </section>
        </aside>
    </section>
@endsection
