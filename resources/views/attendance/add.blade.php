@extends('layouts.app')

@section('title', 'Add a missed day')

{{--
    Filling in a past day somebody could not check in for (decided 2026-10-27).

    Creates a record only where none exists - it never edits one. Who entered
    it and why goes to the audit log, and the record's own page says so. See
    AttendanceController::createEntry.
--}}
@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('attendance.index', $chosenDate !== '' ? ['date' => $chosenDate] : []) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Attendance
            </a>
            <h1>Add a missed day</h1>
            <p>For a past working day somebody worked but could not check in.</p>
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
                    The day
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('attendance.add.store') }}">
                        @csrf

                        <div class="form-field">
                            <label class="form-field-lbl" for="entry-employee">Who</label>
                            <select id="entry-employee" name="employee_id" required>
                                <option value="">Choose a person</option>
                                @foreach ($employeeChoices as $employee)
                                    <option value="{{ $employee->id }}"
                                            @selected((string) old('employee_id', $chosenEmployee) === (string) $employee->id)>
                                        {{ $employee->user?->name }} ({{ $employee->user?->user_id }})
                                    </option>
                                @endforeach
                            </select>
                            @error('employee_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="entry-date">Date</label>
                            <input id="entry-date" name="date" type="date" required
                                   max="{{ $latest }}" value="{{ old('date', $chosenDate) }}">
                            <span class="pay-hint">Past days only. Today is recorded by checking in.</span>
                            @error('date')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="entry-in">Check-in</label>
                            <input id="entry-in" name="check_in" type="time" required
                                   value="{{ old('check_in', $policy['start']) }}">
                            @error('check_in')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="entry-out">Check-out</label>
                            <input id="entry-out" name="check_out" type="time" required
                                   value="{{ old('check_out', $policy['end']) }}">
                            @error('check_out')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="entry-reason">Why is this being entered by hand?</label>
                            <textarea id="entry-reason" name="reason" rows="3" required minlength="10" maxlength="1000"
                                      placeholder="e.g. Worked on site at the client's office and could not check in.">{{ old('reason') }}</textarea>
                            <span class="prose-quiet">Required, and shown on the record.</span>
                            @error('reason')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <button class="btn btn-primary" type="submit">Add the day</button>
                    </form>
                </div>
            </div>
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>What this does</strong>
                </div>
                <div class="prose prose-quiet">
                    <p>
                        Creates the day's record with the times you enter. It is judged
                        like any other day: present, or half day if it is short.
                    </p>
                    <p>
                        It only fills a day with no record. A day that already has one
                        cannot be changed here. You cannot enter your own attendance.
                        Your name and reason are saved in the audit log.
                    </p>
                </div>
            </section>
        </aside>
    </section>
@endsection
