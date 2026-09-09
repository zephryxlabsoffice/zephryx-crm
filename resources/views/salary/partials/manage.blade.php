@php use App\Support\SalaryPresenter as P; @endphp

{{--
    The two things whoever runs payroll does on this page: add the payslip, and
    mark the transfer done.

    They are separate on purpose and in that order. There is no way to mark
    somebody paid without a payslip on file — nothing to check the amount
    against, and nothing to give them if they ask what they were paid for.
--}}
<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
            <line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
        </svg>
        {{ $record['payslip'] ? 'Replace the payslip' : 'Add the payslip' }}
    </div>

    @if ($record['payslip'])
        <div class="prose">
            <p>
                <strong>{{ $record['payslip']['name'] }}</strong> was added on
                {{ P::date($record['payslip']['added_on']) }} by {{ $record['payslip']['added_by'] }}.
                Uploading another replaces it, and the replacement is recorded
                against whoever makes it — the audit log keeps what the figure
                was before, which is the part somebody would need to check.
            </p>
        </div>
    @else
        <div class="prose">
            <p>
                Upload the payslip your payroll produced and type the net figure
                it states. The figure is typed once, here, and every other screen
                reads it — so it can never disagree with the document beside it.
            </p>
        </div>
    @endif

    <form class="form-grid" method="POST" action="{{ route('salary.payslip.store', ['employee' => $record['employee'], 'period' => $record['period']]) }}" enctype="multipart/form-data">
        @csrf

        <div class="form-field">
            <label class="form-field-lbl" for="payslip-file">Payslip file</label>
            {{-- Validated by type and size, stored outside the web root, and served
                 only through a controller that checks the viewer may have it
                 and records that they took it (§6). --}}
            <input id="payslip-file" name="payslip" type="file" accept="application/pdf,image/png,image/jpeg" required>
            <span class="pay-hint">PDF or an image of one, up to 8 MB.</span>
            @error('payslip')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-field">
            <label class="form-field-lbl" for="payslip-net">Net pay ({{ $record['currency'] }})</label>
            {{-- inputmode="decimal", not type="number": a spinner on a money
                 field invites somebody to nudge an amount with an arrow key.
                 The server parses this into integer minor units — see
                 App\Support\Money. --}}
            <input id="payslip-net" name="net" type="text" inputmode="decimal"
                   value="{{ old('net', $record['net']?->plain()) }}" placeholder="0.00" required>
            <span class="pay-hint">Exactly as the payslip states it.</span>
            @error('net')
                <span class="field-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                {{ $record['payslip'] ? 'Replace payslip' : 'Add payslip' }}
            </button>
        </div>
    </form>
</div>

<div class="card">
    <div class="section-hd">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/>
        </svg>
        Payment
    </div>

    @if (P::statusOf($record) === P::PAID)
        <div class="prose">
            <p>
                Marked paid on <strong>{{ P::date($record['paid_on']) }}</strong>
                by {{ $record['method'] }}.
            </p>
            <p class="prose-quiet">
                {{-- Undoing a payment record is not offered casually. If the
                     transfer really did not happen, that is a correction
                     somebody makes deliberately and it is logged. --}}
                If this is wrong, a correction has to be made deliberately and is
                recorded against whoever makes it.
            </p>
        </div>
    @elseif (P::isPayable($record))
        <div class="prose">
            <p>
                This records that the transfer has been made. It does not move
                any money — the bank does that, from the transfer file.
            </p>
        </div>

        <form class="form-actions form-actions-padded" method="POST" action="{{ route('salary.pay') }}">
            @csrf
            <input type="hidden" name="period" value="{{ $record['period'] }}">
            <input type="hidden" name="employees[]" value="{{ $record['employee'] }}">

            {{-- One person, so no confirmation page: the button already names
                 who and for how much, which is the thing the bulk flow has to
                 stop and spell out. --}}
            <button class="btn btn-primary" type="submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Payment done · {{ P::net($record) }}
            </button>
        </form>
    @else
        <div class="prose">
            <p>
                Nobody can be marked paid without a payslip on file — there would
                be nothing to check the amount against, and nothing to give them
                if they ask what they were paid for.
            </p>
        </div>
    @endif
</div>
