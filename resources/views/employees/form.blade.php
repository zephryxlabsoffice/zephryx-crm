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
                                 existing number in its own series so it can never
                                 be reissued to a second person, which is what
                                 would make an audit trail ambiguous.

                                 Not previewed when adding: the identifier carries
                                 the engagement type as a digit, so it is not known
                                 until the type below is chosen, and a preview that
                                 went stale on a dropdown change would be worse
                                 than none. --}}
                            <input id="emp-staff-id" type="text"
                                   value="{{ $staffId ?? 'Assigned when you save' }}" disabled>
                            <span class="pay-hint">
                                {{ $editing ? 'Assigned when this record was created, and never reused.' : 'Built from the year and the engagement type, e.g. ZEPH261001.' }}
                            </span>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-type">Engagement</label>
                            @if ($editing)
                                {{-- Deliberately not editable. Converting an intern
                                     issues a NEW staff ID and closes the old record,
                                     which is its own act with its own audit entry —
                                     not a dropdown somebody can nudge while fixing a
                                     phone number. --}}
                                <input id="emp-type" type="text"
                                       value="{{ \App\Support\EmployeePresenter::employmentType($employee->employment_type)['label'] }}" disabled>
                                <span class="pay-hint">Changed by converting the record, not by editing it.</span>
                            @else
                                <select id="emp-type" name="employment_type" required
                                        @if ($errors->has('employment_type')) aria-invalid="true" aria-describedby="emp-type-error" @endif>
                                    @foreach ($employmentTypes as $key => $label)
                                        <option value="{{ $key }}" @selected(old('employment_type', \App\Models\Employee::FULL_TIME) === $key)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                <span class="pay-hint">Freelancers have no attendance, leave or payroll.</span>
                                @error('employment_type')
                                    <span class="field-error" id="emp-type-error">{{ $message }}</span>
                                @enderror
                            @endif
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

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 10.5 12 3l9 7.5V21H3z"/><path d="M9 21v-7h6v7"/>
                        </svg>
                        Where they live
                    </div>

                    @php
                        // Null for somebody without `employees.identifiers`, and
                        // the boxes are then empty rather than absent: they may
                        // still type a correction, they simply are not shown what
                        // is already on file.
                        $mayReadAddresses = $addresses !== null;
                    @endphp

                    <div class="prose">
                        @if ($editing && ! $mayReadAddresses)
                            <p>
                                What is on file is not shown to you.
                                <strong>Leave these empty to keep it</strong>, or type a new
                                address to replace it.
                            </p>
                        @else
                            <p>
                                Seen by HR and the owner, and on no list of people. The
                                permanent address is the one printed on the ID proof, so it is
                                what the photocopy in the file is checked against.
                            </p>
                        @endif
                    </div>

                    <div class="form-grid">
                        <div class="form-field an-form-wide">
                            <label class="form-field-lbl" for="emp-current-address">
                                Current address
                                @if (! $editing)
                                    <span class="an-optional">(required for a full-time hire)</span>
                                @endif
                            </label>
                            <textarea id="emp-current-address" name="current_address" rows="3" maxlength="500"
                                      placeholder="Where they actually live"
                                      @if ($errors->has('current_address')) aria-invalid="true" aria-describedby="emp-current-address-error" @endif>{{ old('current_address', $addresses['current'] ?? '') }}</textarea>
                            @error('current_address')
                                <span class="field-error" id="emp-current-address-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field an-form-wide">
                            <label class="form-field-lbl" for="emp-permanent-address">
                                Permanent address
                                @if (! $editing)
                                    <span class="an-optional">(required for a full-time hire)</span>
                                @endif
                            </label>
                            <textarea id="emp-permanent-address" name="permanent_address" rows="3" maxlength="500"
                                      placeholder="As printed on the ID proof"
                                      @if ($errors->has('permanent_address')) aria-invalid="true" aria-describedby="emp-permanent-address-error" @endif>{{ old('permanent_address', $addresses['permanent'] ?? '') }}</textarea>
                            @error('permanent_address')
                                <span class="field-error" id="emp-permanent-address-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>
                        </svg>
                        Identity and payment
                    </div>

                    @php $onFile = $identity['on_file'] ?? false; @endphp

                    <div class="prose">
                        @if ($editing)
                            <p>
                                {{-- Why the boxes are empty on an edit. Without
                                     this the natural reading is that the record
                                     is blank. --}}
                                These are never filled in for you: putting the real numbers in this
                                page would put them in front of anyone who can see the screen.
                                <strong>Leave a box empty to keep what is on file</strong>, or type a
                                new value to replace it.
                            </p>
                        @else
                            <p>
                                Held encrypted, and shown masked everywhere afterwards — including to
                                you. The photocopy itself is submitted to the office on paper.
                            </p>
                        @endif
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-id-proof-type">ID proof</label>
                            <select id="emp-id-proof-type" name="id_proof_type"
                                    @if ($errors->has('id_proof_type')) aria-invalid="true" @endif>
                                @foreach (\App\Support\IdProof::options() as $key => $label)
                                    <option value="{{ $key }}"
                                        @selected(old('id_proof_type', $identity['type'] ?? \App\Support\IdProof::AADHAAR) === $key)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="pay-hint">A document showing their address.</span>
                            @error('id_proof_type')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-id-proof-number">ID proof number</label>
                            <input id="emp-id-proof-number" name="id_proof_number" type="text"
                                   autocomplete="off" spellcheck="false"
                                   value="{{ old('id_proof_number') }}"
                                   @if ($errors->has('id_proof_number')) aria-invalid="true" aria-describedby="emp-id-proof-number-error" @endif>
                            @if ($onFile)
                                <span class="pay-hint">On file: {{ $identity['id_proof'] }}</span>
                            @endif
                            @error('id_proof_number')
                                <span class="field-error" id="emp-id-proof-number-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-copy-received">
                                Photocopy received <span class="an-optional">(optional)</span>
                            </label>
                            <input id="emp-copy-received" name="id_proof_copy_received_on" type="date"
                                   max="{{ now()->toDateString() }}"
                                   value="{{ old('id_proof_copy_received_on', $onFile && $identity['copy_received_on'] ? $identity['copy_received_on']->toDateString() : '') }}"
                                   @if ($errors->has('id_proof_copy_received_on')) aria-invalid="true" @endif>
                            <span class="pay-hint">Leave empty until the paper copy is actually in the office.</span>
                            @error('id_proof_copy_received_on')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-pan">
                                PAN
                                @if (! $editing)
                                    <span class="an-optional">(not required for an intern)</span>
                                @endif
                            </label>
                            <input id="emp-pan" name="pan" type="text"
                                   autocomplete="off" spellcheck="false" autocapitalize="characters"
                                   value="{{ old('pan') }}"
                                   @if ($errors->has('pan')) aria-invalid="true" aria-describedby="emp-pan-error" @endif>
                            @if ($onFile)
                                <span class="pay-hint">On file: {{ $identity['pan'] }}</span>
                            @endif
                            @error('pan')
                                <span class="field-error" id="emp-pan-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-bank">Bank</label>
                            <input id="emp-bank" name="bank_name" type="text"
                                   value="{{ old('bank_name', $onFile ? $identity['bank'] : '') }}"
                                   @if ($errors->has('bank_name')) aria-invalid="true" @endif>
                            {{-- Not masked anywhere: "HDFC Bank" identifies no
                                 one, so it is prefilled like an ordinary field. --}}
                            @error('bank_name')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-ifsc">IFSC</label>
                            <input id="emp-ifsc" name="ifsc" type="text"
                                   autocomplete="off" spellcheck="false" autocapitalize="characters"
                                   value="{{ old('ifsc', $onFile ? $identity['ifsc'] : '') }}"
                                   @if ($errors->has('ifsc')) aria-invalid="true" aria-describedby="emp-ifsc-error" @endif>
                            <span class="pay-hint">Identifies a branch, not a person — it is printed on every cheque.</span>
                            @error('ifsc')
                                <span class="field-error" id="emp-ifsc-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="emp-account">Account number</label>
                            <input id="emp-account" name="account_number" type="text"
                                   autocomplete="off" spellcheck="false" inputmode="numeric"
                                   value="{{ old('account_number') }}"
                                   @if ($errors->has('account_number')) aria-invalid="true" aria-describedby="emp-account-error" @endif>
                            @if ($onFile)
                                <span class="pay-hint">On file: {{ $identity['account'] }}</span>
                            @endif
                            @error('account_number')
                                <span class="field-error" id="emp-account-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
                @if ($maySetSalary)
                    @include('employees.partials.salary-fields')
                @endif
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
