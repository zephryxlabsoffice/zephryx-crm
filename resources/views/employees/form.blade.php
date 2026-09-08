@extends('layouts.app')

@php
    // One template for adding and for editing. The fields, their validation and
    // their explanations are identical, and two files would mean every future
    // change made twice — with the second one eventually forgotten.
    $editing = $employee !== null;
    $user = $editing ? $employee->user : null;
@endphp

@section('title', $editing ? 'Edit '.$user->name : 'Add an employee')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ $editing ? route('employees.show', ['employee' => $user->user_id]) : route('employees.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                {{ $editing ? $user->name : 'Employees' }}
            </a>
            <h1>{{ $editing ? 'Edit this record' : 'Add an employee' }}</h1>
            <p>
                @if ($editing)
                    Changes are recorded against this person, with who made them.
                @else
                    This creates an account. They will be emailed a link to set their own password.
                @endif
            </p>
        </div>
    </div>

    <form class="an-form" method="POST"
          action="{{ $editing ? route('employees.update', ['employee' => $user->user_id]) : route('employees.store') }}">
        @csrf

        <section class="an-form-grid">
            <div class="an-form-main">
                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                        Who they are
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-name">Full name</label>
                            <input id="emp-name" name="name" type="text" required
                                   value="{{ old('name', $user?->name) }}"
                                   @if ($errors->has('name')) aria-invalid="true" aria-describedby="emp-name-error" @endif>
                            @error('name')
                                <span class="field-error" id="emp-name-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-email">Email</label>
                            <input id="emp-email" name="email" type="email" required
                                   autocapitalize="none" spellcheck="false"
                                   value="{{ old('email', $user?->email) }}"
                                   @if ($errors->has('email')) aria-invalid="true" aria-describedby="emp-email-error" @endif>
                            <span class="pay-hint">This is what they sign in with, so it has to be theirs alone.</span>
                            @error('email')
                                <span class="field-error" id="emp-email-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-staff-id">Staff ID</label>
                            {{-- Shown, never typed. It is derived from the highest
                                 existing number so it can never be reissued to a
                                 second person, which is what would make an audit
                                 trail ambiguous. --}}
                            <input id="emp-staff-id" type="text" value="{{ $staffId }}" disabled>
                            <span class="pay-hint">Assigned automatically and never reused.</span>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-joined">Joining date</label>
                            <input id="emp-joined" name="joined_on" type="date" required
                                   max="{{ now()->toDateString() }}"
                                   value="{{ old('joined_on', $employee?->joined_on?->toDateString()) }}"
                                   @if ($errors->has('joined_on')) aria-invalid="true" aria-describedby="emp-joined-error" @endif>
                            @error('joined_on')
                                <span class="field-error" id="emp-joined-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        </svg>
                        Where they sit
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-department">Department</label>
                            {{-- Only active master data is offered. A deactivated
                                 department keeps the people already in it and stops
                                 being offered for new ones — that is the whole
                                 difference between deactivating and deleting. --}}
                            <select id="emp-department" name="department_id">
                                <option value="">Not set</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}"
                                        @selected((int) old('department_id', $employee?->department_id) === $department->id)>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('department_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-designation">Designation</label>
                            <select id="emp-designation" name="designation_id">
                                <option value="">Not set</option>
                                @foreach ($designations as $designation)
                                    <option value="{{ $designation->id }}"
                                        @selected((int) old('designation_id', $employee?->designation_id) === $designation->id)>
                                        {{ $designation->name }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="pay-hint">A label. Designations carry no rank and grant nothing (§2.3).</span>
                            @error('designation_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                        </svg>
                        Milestones
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-dob">Date of birth <span class="an-optional">(optional)</span></label>
                            <input id="emp-dob" name="date_of_birth" type="date"
                                   value="{{ old('date_of_birth', $employee?->date_of_birth?->toDateString()) }}"
                                   @if ($errors->has('date_of_birth')) aria-invalid="true" aria-describedby="emp-dob-error" @endif>
                            <span class="pay-hint">Only ever shown as a day and a month — never the year, and never an age.</span>
                            @error('date_of_birth')
                                <span class="field-error" id="emp-dob-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field an-form-wide">
                            <label class="check-row">
                                {{-- Hidden field first so clearing the box sends a
                                     value. Without it an unchecked box sends
                                     nothing, and "leave it as it was" would be
                                     indistinguishable from "turn it off". --}}
                                <input type="hidden" name="announce_milestones" value="0">
                                <input type="checkbox" name="announce_milestones" value="1"
                                       @checked(old('announce_milestones', $employee?->announce_milestones ?? true))>
                                <span>Announce their birthday and work anniversary on the board</span>
                            </label>
                            <span class="pay-hint">Their choice to make. Ask before turning this on for somebody.</span>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="rail">
                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>{{ $editing ? 'What changes' : 'What happens next' }}</strong>
                    </div>

                    <div class="prose">
                        @if ($editing)
                            <p>
                                The record is updated and the change is written to the audit log
                                with your name against it. Their staff ID does not change, and
                                nothing they have already recorded — attendance, leave, payslips
                                — is touched.
                            </p>
                        @else
                            <p>
                                An account is created and they are emailed a single-use link
                                to choose their own password. Nobody, including you, ever
                                sees or sets it.
                            </p>
                            <p>
                                They start with the Employee role, which is their own attendance,
                                leave, payslips and profile. Anything beyond that is granted in
                                the Admin Panel.
                            </p>
                        @endif
                    </div>

                    <button class="btn btn-primary" type="submit">
                        {{ $editing ? 'Save changes' : 'Add employee' }}
                    </button>

                    <a class="btn btn-outline btn-sm"
                       href="{{ $editing ? route('employees.show', ['employee' => $user->user_id]) : route('employees.index') }}">
                        Cancel
                    </a>
                </section>
            </aside>
        </section>
    </form>
@endsection
