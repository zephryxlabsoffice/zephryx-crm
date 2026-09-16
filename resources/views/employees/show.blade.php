@extends('layouts.app')

@php
    use App\Support\Avatar;
    use App\Support\EmployeePresenter as P;

    $pill = P::status($employee['status']);
    $user = $record->user;
@endphp

@section('title', $employee['name'])

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('employees.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Employees
            </a>
            <h1>{{ $employee['name'] }}</h1>
            <p>{{ $employee['user_id'] }}{{ $employee['designation'] ? ' · '.$employee['designation'] : '' }}</p>
        </div>

        @if ($mayEdit)
            <a class="btn btn-outline" href="{{ route('employees.edit', ['employee' => $employee['user_id']]) }}">
                Edit record
            </a>
        @endif
    </div>

    @if ($convertedTo)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'This record was converted to full-time',
            'message' => 'It is kept because the attendance, leave and payslips recorded against it '
                .'belong to it. Their current record is '.$convertedTo->user->user_id.'.',
        ])
    @endif

    @if ($convertedFrom)
        @include('partials.notice', [
            'tone' => 'info',
            'title' => 'Converted from an internship',
            'message' => 'They were '.$convertedFrom->user->user_id.' until '
                .($record->joined_on?->format('d M Y') ?? 'the conversion')
                .'. That record is closed and holds everything from before.',
        ])
    @endif

    @if ($user->status !== 'active')
        @include('partials.notice', [
            'tone' => $user->status === 'suspended' ? 'danger' : 'warning',
            'title' => $user->status === 'suspended'
                ? 'This account is suspended'
                : 'This record is closed',
            'message' => $user->status === 'suspended'
                ? 'They cannot sign in, and hold no permissions while this lasts. Everything they recorded is untouched.'
                : 'They cannot sign in. Their attendance, leave and payslips stay exactly as they were — this is why records are closed rather than deleted.',
        ])
    @endif

    <section class="att-detail-grid">
        <div class="att-main">
            <div class="card">
                <div class="att-record-hd">
                    <div class="person-row">
                        <span class="avatar {{ Avatar::tint($employee['name']) }}" aria-hidden="true">{{ Avatar::initials($employee['name']) }}</span>
                        <span class="person-body">
                            <strong>{{ $employee['name'] }}</strong>
                            <span>{{ $employee['designation'] ?: 'No designation set' }}</span>
                        </span>
                    </div>
                    <span class="pill {{ $pill['tone'] }}">{{ $pill['label'] }}</span>
                </div>

                <dl class="field-grid att-record-grid">
                    <div class="lv-field">
                        <dt class="lv-field-lbl">Staff ID</dt>
                        <dd>{{ $employee['user_id'] }}</dd>
                    </div>

                    <div class="lv-field">
                        <dt class="lv-field-lbl">Email</dt>
                        <dd><a class="emp-email" href="mailto:{{ $employee['email'] }}">{{ $employee['email'] }}</a></dd>
                    </div>

                    <div class="lv-field">
                        <dt class="lv-field-lbl">Department</dt>
                        <dd>{{ $employee['department'] ?: 'Not set' }}</dd>
                    </div>

                    <div class="lv-field">
                        <dt class="lv-field-lbl">Joined</dt>
                        <dd>{{ $employee['joined'] ? P::joined($employee['joined']) : 'Not recorded' }}</dd>
                    </div>

                    <div class="lv-field">
                        <dt class="lv-field-lbl">Birthday</dt>
                        {{-- Day and month. The year is on the record and never
                             reaches a page — a colleague needs to know when to
                             say happy birthday, not how old somebody is. --}}
                        <dd>
                            {{ $record->date_of_birth
                                ? \App\Support\Milestones::dayAndMonth($record->date_of_birth->toDateString())
                                : 'Not recorded' }}
                        </dd>
                    </div>

                    <div class="lv-field">
                        <dt class="lv-field-lbl">Milestones announced</dt>
                        <dd>{{ $record->announce_milestones ? 'Yes' : 'They opted out' }}</dd>
                    </div>
                </dl>
            </div>

            @if ($addresses !== null)
                @include('employees.partials.addresses', ['addresses' => $addresses])
            @endif

            @if ($identity !== null)
                @include('employees.partials.identity', ['identity' => $identity])
            @endif

            @if ($salary !== null)
                @include('employees.partials.salary', ['salary' => $salary])
            @endif

            @if ($mayConvert)
                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 8h13l-3-3M20 16H7l3 3"/>
                        </svg>
                        Convert to full-time
                    </div>

                    <div class="prose">
                        <p>
                            {{-- Says exactly what will happen before the button
                                 is pressed. This issues an account and closes
                                 one, and neither is undoable from a screen. --}}
                            This issues a new staff ID and a new record, and closes this one. Their
                            work email, identity and bank details, profile, photo and documents all
                            move across without being retyped, and they are emailed a link to set a
                            password for the new account.
                        </p>
                        <p>
                            <strong>Their leave balance starts again</strong> and the attendance,
                            leave and payslips already recorded stay here, under
                            {{ $employee['user_id'] }}. Tasks and team membership do not move.
                            You will need to record their salary afterwards.
                        </p>
                    </div>

                    @error('convert')
                        <div class="prose"><span class="field-error">{{ $message }}</span></div>
                    @enderror

                    <form method="POST" action="{{ route('employees.convert', ['employee' => $employee['user_id']]) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">Convert to full-time</button>
                    </form>
                </div>
            @endif

            @if ($mayDeactivate)
                <div class="card att-reject">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                            <path d="M12 9v4M12 17h.01"/>
                        </svg>
                        {{ $user->status === 'active' ? 'Close this record' : 'Reopen this record' }}
                    </div>

                    <div class="prose">
                        <p>
                            @if ($user->status === 'active')
                                Closing it stops them signing in. Nothing they recorded is
                                removed — attendance, leave and payslips all still point at
                                this person, which is why there is no delete here at all.
                            @else
                                Reopening lets them sign in again with the password they
                                already had. Everything is exactly where they left it.
                            @endif
                        </p>
                    </div>

                    <form method="POST" action="{{ route('employees.status', ['employee' => $employee['user_id']]) }}">
                        @csrf
                        <input type="hidden" name="status" value="{{ $user->status === 'active' ? 'inactive' : 'active' }}">
                        <button class="btn {{ $user->status === 'active' ? 'btn-outline' : 'btn-primary' }}" type="submit">
                            {{ $user->status === 'active' ? 'Close the record' : 'Reopen the record' }}
                        </button>
                    </form>
                </div>
            @endif
        </div>

        <aside class="rail">
            <section class="rail-card">
                <div class="rail-hd">
                    <strong>History</strong>
                </div>

                {{--
                    Everything that has happened to this record, from the audit
                    log (§6). Empty for somebody added before the log existed,
                    which is honest: it says nothing has been recorded, not that
                    nothing happened.
                --}}
                @forelse ($history as $entry)
                    <div class="stat-row">
                        <span class="stat-label">{{ $entry->at->format('d M Y') }}</span>
                        <span class="stat-value">{{ $entry->action }}</span>
                    </div>
                    @if ($entry->after_summary)
                        <p class="att-rail-note">
                            {{ $entry->after_summary }}
                            <span class="an-optional">— {{ $entry->actor_label }}</span>
                        </p>
                    @endif
                @empty
                    <p class="att-rail-note">Nothing has been recorded against this person yet.</p>
                @endforelse
            </section>
        </aside>
    </section>
@endsection
