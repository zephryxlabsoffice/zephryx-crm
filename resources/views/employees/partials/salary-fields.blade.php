@php
    use App\Models\Employee;
    use App\Support\SalaryStructure;

    /*
        Which boxes to draw.

        On an EDIT the engagement is settled — it cannot be changed by this form
        at all — so exactly one group is rendered and the person sees only the
        components that apply to them.

        On a CREATE the engagement is a dropdown three cards above this one, and
        its value is not known until the form is posted. So all three groups are
        drawn, each labelled with the engagement it belongs to, and the validator
        accepts only the group matching what was chosen. The alternative is
        JavaScript that shows and hides them — and this application's forms work
        with JavaScript off, which is the same reason the languages field on My
        Profile is a comma-separated text box rather than chips.
    */
    $kinds = $editing
        ? [$employee->employment_type]
        : Employee::TYPES;
@endphp

<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v10M9.5 9.5h4a1.8 1.8 0 0 1 0 3.6h-3a1.8 1.8 0 0 0 0 3.6h4"/>
        </svg>
        What they are paid
    </div>

    <div class="prose">
        <p>
            {{-- Says what this is FOR before anybody types into it. Without the
                 sentence the obvious reading is that the CRM will calculate a
                 payslip from these, and it never will. --}}
            The standing agreement, kept so whoever prepares the payslip has the figures to
            start from. <strong>Nothing here is calculated or shown on the Salary page</strong> —
            the payslip HR uploads is what somebody was actually paid, and a breakdown printed
            beside it would argue with it in any month carrying a deduction.
        </p>
        @if ($editing)
            <p>Leave a box empty to keep what is on file.</p>
        @endif
    </div>

    @foreach ($kinds as $type)
        @php
            $kind = SalaryStructure::kindFor($type);
            $earnings = SalaryStructure::earnings($kind);
            $deductions = SalaryStructure::deductions($kind);
        @endphp

        @unless ($editing)
            <div class="section-hd section-hd-sub">
                If {{ mb_strtolower(\App\Support\EmployeePresenter::employmentType($type)['label']) }}
            </div>
        @endunless

        <div class="form-grid">
            @foreach ($earnings + $deductions as $column => $label)
                @php $field = str_replace('_minor', '', $column); @endphp

                <div class="form-field">
                    <label class="form-field-lbl" for="emp-pay-{{ $field }}">
                        {{ $label }}
                        @if (isset($deductions[$column]))
                            {{-- Marked, because six figures in one grid otherwise
                                 read as six things being added together. --}}
                            <span class="an-optional">(deducted)</span>
                        @endif
                    </label>
                    <input id="emp-pay-{{ $field }}" name="{{ $field }}" type="text"
                           inputmode="decimal" autocomplete="off" spellcheck="false"
                           placeholder="0.00"
                           value="{{ old($field, $salaryFields[$field] ?? '') }}"
                           @if ($errors->has($field)) aria-invalid="true" aria-describedby="emp-pay-{{ $field }}-error" @endif>
                    @error($field)
                        <span class="field-error" id="emp-pay-{{ $field }}-error">{{ $message }}</span>
                    @enderror
                </div>
            @endforeach

            @if ($kind === \App\Models\EmployeeSalaryStructure::RATE)
                <div class="form-field">
                    <label class="form-field-lbl" for="emp-pay-basis">Charged</label>
                    {{-- A rate with no basis is a number nobody can act on, so
                         the two are asked for together. --}}
                    <select id="emp-pay-basis" name="rate_basis"
                            @if ($errors->has('rate_basis')) aria-invalid="true" @endif>
                        <option value="">Not set</option>
                        @foreach (SalaryStructure::basisOptions() as $value => $label)
                            <option value="{{ $value }}" @selected(old('rate_basis', $salaryFields['rate_basis'] ?? '') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('rate_basis')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>
            @endif
        </div>
    @endforeach
</div>
