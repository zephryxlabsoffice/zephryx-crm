@php use App\Support\InvoicePresenter as P; @endphp

{{--
    What can be done to this invoice, which depends on where it stands.

    Note what is absent at every state: Delete. An invoice number must never
    leave the sequence — a gap is the first thing an auditor asks about, and
    "we deleted it" is the wrong answer everywhere. Withdrawal is Cancel, which
    keeps the record and its number.

    Everything here is behind `invoices.manage`. Reading what a client owes and
    changing it are different acts, and the second is money.
--}}
<div class="hd-actions">
    @if ($mayManage ?? false)
        @if ($invoice['status'] === P::DRAFT)
            <form method="POST" action="{{ route('invoices.send', ['invoice' => $invoice['id']]) }}">
                @csrf
                <button class="btn btn-primary" type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
                    </svg>
                    Send to client
                </button>
            </form>
        @endif

        @if ($invoice['status'] !== P::CANCELLED && ! $invoice['paid']->isPositive())
            {{-- Only offered while nothing has been received. Cancelling an
                 invoice somebody has already part-paid would leave that payment
                 attached to a withdrawn document; that case needs a credit
                 note, which is its own record and its own decision — and the
                 controller refuses it whatever this markup does. --}}
            <form class="inv-cancel" method="POST" action="{{ route('invoices.cancel', ['invoice' => $invoice['id']]) }}">
                @csrf

                <div class="form-field">
                    <label class="sr-only" for="inv-cancel-reason">Why is it being cancelled?</label>
                    <input id="inv-cancel-reason" name="reason" type="text" required minlength="5" maxlength="500"
                           placeholder="Why it is being cancelled" value="{{ old('reason') }}">
                    {{-- Required. A cancelled invoice with no explanation is one
                         somebody has to reconstruct from memory a year later. --}}
                    @error('reason')
                        <span class="field-error">{{ $message }}</span>
                    @enderror
                </div>

                <button class="btn btn-ghost" type="submit">
                    Cancel invoice
                </button>
            </form>
        @endif
    @endif

    {{--
        Real now: an invoice is an uploaded PDF (decided 2026-09-11), so this
        is the same file `invoices.partials.document` links to, offered again
        here beside Send and Cancel. Absent when nothing has been attached —
        a download button that 404s is worse than no button.
    --}}
    @if ($invoice['has_document'])
        <a class="btn btn-outline" href="{{ route('invoices.document.download', ['invoice' => $invoice['id']]) }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Download
        </a>
    @endif

    {{--
        A reminder is an email with a schedule behind it — not a data
        problem, and not one this commit takes on.
    --}}
    @if (P::isOutstanding($invoice))
        <button class="btn btn-outline" type="button" disabled title="Reminders are not built yet">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            Send reminder
        </button>
    @endif
</div>
