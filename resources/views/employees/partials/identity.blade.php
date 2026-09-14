{{--
    Identity and payment, for whoever holds `employees.identifiers`.

    Every value here was masked in PHP before it reached this file — see
    EmployeeController::maskedIdentity. There is no full number in this markup,
    no data attribute holding one, and nothing for a stylesheet to un-hide.

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
        <dl class="field-grid att-record-grid">
            <div class="lv-field">
                <dt class="lv-field-lbl">{{ $identity['id_proof_label'] }}</dt>
                <dd class="stat-value-mono">{{ $identity['id_proof'] }}</dd>
            </div>

            <div class="lv-field">
                <dt class="lv-field-lbl">Photocopy received</dt>
                <dd>
                    {{ $identity['copy_received_on']
                        ? $identity['copy_received_on']->format('d M Y')
                        : 'Not yet' }}
                </dd>
            </div>

            <div class="lv-field">
                <dt class="lv-field-lbl">PAN</dt>
                <dd class="stat-value-mono">{{ $identity['pan'] }}</dd>
            </div>

            <div class="lv-field">
                <dt class="lv-field-lbl">Bank</dt>
                <dd>{{ $identity['bank'] }}</dd>
            </div>

            <div class="lv-field">
                <dt class="lv-field-lbl">Account</dt>
                <dd class="stat-value-mono">{{ $identity['account'] }}</dd>
            </div>

            <div class="lv-field">
                <dt class="lv-field-lbl">IFSC</dt>
                <dd class="stat-value-mono">{{ $identity['ifsc'] }}</dd>
            </div>
        </dl>

        <p class="sl-privacy">
            Masked for everybody, including you. Paying somebody does not require
            reading their account number off a screen — payroll uses the bank
            transfer file — and each full value will be a deliberate, audited
            reveal rather than something a page shows because of who opened it.
        </p>
    @endif
</div>
