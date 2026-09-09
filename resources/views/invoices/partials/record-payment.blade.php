@php use App\Support\InvoicePresenter as P; @endphp

@if (($mayManage ?? false) && $invoice['status'] !== P::CANCELLED && ! $invoice['balance']->isZero() && $invoice['status'] !== P::DRAFT)
    {{--
        Recording a payment is the ONLY way an invoice becomes paid. There is no
        "mark as paid" control anywhere in this module, deliberately — status is
        derived from this ledger, so marking without recording would be marking
        without evidence.

        The amount field defaults to the outstanding balance because that is
        what is usually received, but stays editable because part-payments are
        the whole reason this form exists.
    --}}
    <section class="rail-card">
        <div class="rail-hd">
            <strong>Record a payment</strong>
        </div>

        <form class="pay-form" method="POST" action="{{ route('invoices.payments.store', ['invoice' => $invoice['id']]) }}">
            @csrf

            <div class="pay-field">
                <label class="form-field-lbl" for="pay-amount">Amount received ({{ $invoice['currency'] }})</label>
                {{-- inputmode="decimal" rather than type="number": a number
                     spinner on a money field invites somebody to nudge an
                     amount with an arrow key. The server parses this into
                     integer minor units — see App\Support\Money. --}}
                <input id="pay-amount" name="amount" type="text" inputmode="decimal"
                       value="{{ old('amount', $invoice['balance']->plain()) }}" required>
                @error('amount')
                    <span class="field-error">{{ $message }}</span>
                @enderror
                <span class="pay-hint">{{ $invoice['balance']->format() }} outstanding</span>
            </div>

            <div class="pay-field">
                <label class="form-field-lbl" for="pay-date">Received on</label>
                <input id="pay-date" name="received_on" type="date" required max="{{ now()->toDateString() }}" value="{{ old('received_on', now()->toDateString()) }}">
            </div>

            <div class="pay-field">
                <label class="form-field-lbl" for="pay-method">Method</label>
                <select id="pay-method" name="method" required>
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method }}">{{ $method }}</option>
                    @endforeach
                </select>
            </div>

            <div class="pay-field">
                <label class="form-field-lbl" for="pay-reference">Reference</label>
                <input id="pay-reference" name="reference" type="text" maxlength="120" placeholder="Transaction or cheque number" value="{{ old('reference') }}">
            </div>

            {{-- A financial write: audited with who recorded it (§6), behind
                 `invoices.manage`, and a payment larger than the balance is
                 REFUSED rather than absorbed — an overpayment is a conversation
                 with the client, and an invoice showing a negative balance is a
                 page nobody trusts. --}}
            <button class="btn btn-primary" type="submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Record payment
            </button>
        </form>
    </section>
@endif
