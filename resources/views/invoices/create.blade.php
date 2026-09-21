@extends('layouts.app')

@section('title', 'Create invoice')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ route('invoices.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                All invoices
            </a>
            <h1>Create Invoice</h1>
            <p>Draft it here; nothing reaches the client until you send it.</p>
        </div>
    </div>

    <form class="inv-form" method="POST" action="{{ route('invoices.store') }}" enctype="multipart/form-data">
        @csrf

        <section class="inv-form-grid">
            <div class="inv-form-main">

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                        Who it is for
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="inv-client">Client</label>
                            <select id="inv-client" name="client_id" required>
                                <option value="">Select a client</option>
                                @foreach ($clientOptions as $name)
                                    <option value="{{ $name->id }}" @selected((int) old('client_id') === $name->id)>{{ $name->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="inv-project">Project</label>
                            {{-- Optional on purpose: not everything billed is
                                 against a project. A retainer or an ad-hoc
                                 piece of work still needs an invoice. --}}
                            <select id="inv-project" name="project_id">
                                <option value="">No project — bill directly</option>
                                @foreach ($projectOptions as $project)
                                    <option value="{{ $project['id'] }}" @selected((int) old('project_id') === $project['id'])>{{ $project['name'] }} · {{ $project['client'] }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="inv-currency">Currency</label>
                            {{--
                                Chosen once, at the top, and fixed for the whole
                                invoice. Per-line currency is not a feature —
                                it is a bug waiting to be discovered by a total
                                that cannot be computed.
                            --}}
                            <select id="inv-currency" name="currency" required>
                                @foreach ($currencies as $code => $label)
                                    <option value="{{ $code }}" @selected(old('currency', \App\Support\Money::DEFAULT_CURRENCY) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="pay-hint">The amount below is in the currency you pick here.</span>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="inv-number">Invoice number</label>
                            {{--
                                Shown, never typed. The number is issued by the
                                database inside the transaction that writes the
                                invoice — if the form chose it, two people
                                clicking Create at the same moment would get the
                                same one, and the sequence has to be gapless.
                            --}}
                            <input id="inv-number" type="text" value="{{ $nextNumber }}" readonly disabled>
                            <span class="pay-hint">Issued automatically. Numbers are sequential and never reused.</span>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="inv-date">Invoice date</label>
                            <input id="inv-date" name="invoice_date" type="date" required value="{{ old('invoice_date', now()->toDateString()) }}">
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="inv-due">Due date</label>
                            <input id="inv-due" name="due_date" type="date" required value="{{ old('due_date', $defaultDue) }}">
                            <span class="pay-hint">Terms are read from these two dates, so they can never disagree.</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        What is being billed
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="inv-amount">Amount ({{ old('currency', \App\Support\Money::DEFAULT_CURRENCY) }})</label>
                            {{-- inputmode="decimal" rather than type="number":
                                 a spinner on a money field invites somebody to
                                 nudge an amount with an arrow key. The server
                                 parses this into integer minor units — see
                                 App\Support\Money. Typed once, the way a
                                 payslip's net figure is; there is no GST here
                                 to compute from a rate and a quantity. --}}
                            <input id="inv-amount" name="amount" type="text" inputmode="decimal" placeholder="0.00" required value="{{ old('amount') }}">
                            @error('amount')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="inv-document">Invoice document</label>
                            {{-- `accept` narrows the file picker and protects
                                 nothing. The write checks the CONTENT through
                                 finfo, because an extension is whatever
                                 somebody typed. --}}
                            <input id="inv-document" name="document" type="file" accept="application/pdf,image/png,image/jpeg" required>
                            @error('document')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                            <span class="pay-hint">This is the document the client sees and downloads. It can be replaced later, from the invoice page.</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                        </svg>
                        Notes
                    </div>
                    <div class="card-body">
                        <label class="sr-only" for="inv-notes">Notes for the client</label>
                        <textarea id="inv-notes" name="notes" rows="3" maxlength="2000" placeholder="Anything the client should read alongside the amounts…">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <aside class="rail">
                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>About the amount</strong>
                    </div>

                    <p class="pay-hint pay-hint-block">
                        Typed once, in the field on the left — not itemised, and
                        not taxed. ZephryxLabs is not GST-registered, and this
                        module records what was agreed rather than generating an
                        invoice from a rate card.
                    </p>
                </section>

                <section class="rail-card">
                    <div class="rail-hd">
                        <strong>Then what</strong>
                    </div>

                    <div class="inv-next">
                        {{-- Saving and sending are two steps, not one button.
                             An invoice sent by accident has to be chased,
                             apologised for, and cancelled — and the cancellation
                             stays in the sequence forever. --}}
                        <button class="btn btn-primary" type="submit">
                            Save as draft
                        </button>
                        <p class="pay-hint pay-hint-block">
                            Drafts are private. You send it from the invoice
                            itself, once you have read it back.
                        </p>
                    </div>
                </section>
            </aside>
        </section>
    </form>
@endsection
