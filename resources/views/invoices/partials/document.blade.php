@php use App\Support\InvoicePresenter as P; @endphp

{{--
    The invoice summary — what it is for, and where the actual document is.

    Decided 2026-09-11, built 2026-09-21: an invoice is an uploaded PDF, not
    something this application renders to look like one. So this partial does
    not reproduce a letterhead or a line-item table any more — that would be a
    second copy of a document that already exists, and the two would drift the
    first time somebody corrected one and not the other. It states the amount
    that was typed in, and links to the one real file.

    Shared by the staff invoice page, the client invoice page, and nothing
    else — no print view exists any more, because there is nothing left to
    render for printing that the PDF itself does not already do better.
--}}
<div class="card inv-doc">

    <header class="inv-doc-hd">
        <div class="inv-doc-brand">
            @include('partials.brand-mark')
            <div>
                <strong>ZephryxLabs</strong>
                <span>Kolkata, India</span>
                <span>{{ \App\Support\SupportContact::address() }}</span>
            </div>
        </div>

        <div class="inv-doc-meta">
            <span class="inv-doc-kind">Invoice</span>
            <strong class="inv-doc-no">{{ $invoice['id'] }}</strong>
            <span class="inv-doc-cur">All amounts in {{ $invoice['currency'] }}</span>
        </div>
    </header>

    <div class="inv-doc-parties">
        <div class="inv-doc-party">
            <span class="inv-doc-lbl">Billed to</span>
            <strong>{{ $invoice['client'] }}</strong>
            @if ($invoice['project_record'])
                <span>{{ $invoice['project_record']['name'] }}</span>
                <span class="inv-doc-ref">{{ $invoice['project_record']['id'] }}</span>
            @endif
        </div>

        <dl class="inv-doc-dates">
            <div>
                <dt>Invoice date</dt>
                <dd>{{ P::date($invoice['invoice_date']) }}</dd>
            </div>
            <div>
                <dt>Due date</dt>
                <dd>{{ P::date($invoice['due_date']) }}</dd>
            </div>
            <div>
                <dt>Terms</dt>
                {{-- Derived from the two dates above, never stored, so it can
                     never contradict them. --}}
                <dd>{{ P::terms($invoice) }}</dd>
            </div>
        </dl>
    </div>

    {{--
        The amount. Typed once, the same way a payslip's net figure is — not
        a sum of rows that no longer exist. No tax block: the company is not
        GST-registered (decided 2026-08-27), and there is nothing generated
        here that would need one.
    --}}
    <dl class="inv-totals">
        <div class="inv-total-row">
            <dt>Amount</dt>
            <dd class="money">{{ $invoice['total']->format() }}</dd>
        </div>

        @if ($invoice['paid']->isPositive())
            <div class="inv-total-row">
                <dt>Received</dt>
                <dd class="money money-in">− {{ $invoice['paid']->format() }}</dd>
            </div>
        @endif

        <div class="inv-total-row inv-total-due">
            <dt>{{ $invoice['balance']->isZero() ? 'Settled' : 'Balance due' }}</dt>
            <dd class="money">{{ $invoice['balance']->format() }}</dd>
        </div>
    </dl>

    {{--
        The document itself. `target="_blank"` opens it in the browser's own
        viewer rather than navigating away from this page — the same choice
        Salary and Profile documents made (see App\Support\Documents\
        DocumentStore::viewInline() for what makes that safe for an upload).
    --}}
    <div class="inv-doc-file">
        @if ($invoice['has_document'])
            <a class="btn btn-primary" href="{{ route(($isClient ?? false) ? 'client.invoices.document.view' : 'invoices.document.view', ['invoice' => $invoice['id']]) }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>
                </svg>
                Open invoice document
            </a>
            <span class="inv-doc-file-note">{{ $invoice['document_name'] }}</span>
        @else
            <p class="rail-empty">No document has been attached to this invoice yet.</p>
        @endif
    </div>

    @if ($invoice['notes'])
        <div class="inv-doc-notes">
            <span class="inv-doc-lbl">Notes</span>
            <p>{{ $invoice['notes'] }}</p>
        </div>
    @endif
</div>
