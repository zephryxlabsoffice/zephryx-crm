@php
    // Which field, if any, was just revealed — see EmployeeController::reveal.
    // It survives this render and no other.
    $revealedField = $identity['revealed_field'] ?? null;
@endphp

{{--
    Identity and payment, for whoever holds `employees.identifiers`.

    Every value here was masked in PHP before it reached this file — see
    EmployeeController::maskedIdentity. There is no full number in this markup,
    no data attribute holding one, and nothing for a stylesheet to un-hide. The
    single exception is a value just revealed on purpose, which is rendered once
    and is gone on the next load.

    `$identity` is null when the viewer may not see this at all, and the
    controller never loaded the row in that case. This partial is only included
    when it is non-null, so the check below is about what is ON FILE, not about
    who is looking.
--}}
<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="5" width="18" height="14" rx="2"/>
            <path d="M3 10h18M7 15h4"/>
        </svg>
        Identity and payment
    </div>

    @if (! $identity['on_file'])
        {{-- The state that matters operationally: nobody has set this person
             up, so they cannot be paid. An absence, stated. --}}
        <div class="prose">
            <p>Nothing on file yet — no ID proof and no bank details, so this person cannot be paid.</p>
        </div>
    @else
        @if ($revealedField)
            @include('partials.notice', [
                'tone' => 'warning',
                'title' => $identity['revealed_label'].' is shown in full below',
                'message' => 'This has been written to the audit log with your name, the reason you gave and the time. It disappears when you leave this page.',
            ])
        @endif

        <dl class="field-grid att-record-grid">
            @php
                // Label, masked value, and the key a reveal asks for. IFSC and
                // the bank name carry no key: neither is masked, so neither has
                // anything to reveal.
                $rows = [
                    ['label' => $identity['id_proof_label'], 'value' => $identity['id_proof'], 'field' => 'id_proof_number'],
                    ['label' => 'PAN', 'value' => $identity['pan'], 'field' => 'pan'],
                    ['label' => 'Bank', 'value' => $identity['bank'], 'field' => null],
                    ['label' => 'Account', 'value' => $identity['account'], 'field' => 'account_number'],
                    ['label' => 'IFSC', 'value' => $identity['ifsc'], 'field' => null],
                ];
            @endphp

            @foreach ($rows as $row)
                <div class="lv-field">
                    <dt class="lv-field-lbl">{{ $row['label'] }}</dt>
                    <dd class="stat-value-mono">
                        @if ($row['field'] !== null && $revealedField === $row['field'])
                            {{ $identity['revealed_value'] }}
                        @else
                            {{ $row['value'] }}
                        @endif
                    </dd>
                </div>
            @endforeach

            <div class="lv-field">
                <dt class="lv-field-lbl">Photocopy received</dt>
                <dd>
                    {{ $identity['copy_received_on']
                        ? $identity['copy_received_on']->format('d M Y')
                        : 'Not yet' }}
                </dd>
            </div>
        </dl>

        {{--
            One form per field, each carrying its own reason.

            A single form with a dropdown would be fewer boxes and the wrong
            shape: the reason belongs to the field being asked for, and sharing
            one would invite the same sentence to be reused for whatever is
            looked at next.

            No JavaScript. The CSP forbids inline script, and a privileged read
            that writes an audit entry must not depend on script running at all.
        --}}
        <div class="prose">
            <p>
                Paying somebody does not require reading their account number: payroll uses
                the bank transfer file. If one has to be checked, say which and why —
                it is shown once, and the log keeps a record of your having looked.
            </p>
        </div>

        @foreach (['id_proof_number' => $identity['id_proof_label'], 'pan' => 'PAN', 'account_number' => 'Account number'] as $field => $label)
            <form class="reveal-row" method="POST"
                  action="{{ route('employees.reveal', ['employee' => $employee['user_id']]) }}">
                @csrf
                <input type="hidden" name="field" value="{{ $field }}">

                <label class="sr-only" for="reveal-reason-{{ $field }}">
                    Why do you need to see the {{ $label }}?
                </label>
                <input id="reveal-reason-{{ $field }}" name="reason" type="text"
                       placeholder="Why do you need the {{ $label }}?"
                       maxlength="300" autocomplete="off">

                <button class="btn btn-outline btn-sm" type="submit">Reveal {{ $label }}</button>
            </form>
        @endforeach

        @error('reason')
            <span class="field-error">{{ $message }}</span>
        @enderror
        @error('field')
            <span class="field-error">{{ $message }}</span>
        @enderror
    @endif
</div>
