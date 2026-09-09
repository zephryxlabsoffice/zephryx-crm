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

    <form class="inv-form" method="POST" action="{{ route('invoices.store') }}">
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
                            <span class="pay-hint">Every line on this invoice is in the currency you pick here.</span>
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
                            <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
                            <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                        </svg>
                        What is being billed
                    </div>

                    <div class="card-body-table">
                        <table class="data-table inv-line-form" role="table">
                            <thead>
                                <tr role="row">
                                    <th role="columnheader" scope="col">Description</th>
                                    <th role="columnheader" scope="col" class="col-num">Qty</th>
                                    <th role="columnheader" scope="col" class="col-money">Unit price</th>
                                    <th role="columnheader" scope="col" class="col-money">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{--
                                    Three empty rows rather than a JavaScript
                                    "add row" button. The row count is a server
                                    concern once this saves — the backend
                                    validates an array of lines and adds rows on
                                    submit, so the form works with JavaScript
                                    off, which is also how it stays testable.

                                    The Amount column is deliberately not an
                                    input. It is qty × unit price, computed
                                    server-side on save; letting somebody type
                                    an amount that disagrees with the two
                                    figures beside it is how an invoice ends up
                                    self-contradicting.
                                --}}
                                @for ($i = 0; $i < 3; $i++)
                                    <tr role="row">
                                        <td role="cell">
                                            <label class="sr-only" for="line-{{ $i }}-desc">Line {{ $i + 1 }} description</label>
                                            <input id="line-{{ $i }}-desc" name="lines[{{ $i }}][description]" type="text" placeholder="What was delivered" value="{{ old('lines.'.$i.'.description') }}">
                                        </td>
                                        <td role="cell" class="col-num">
                                            <label class="sr-only" for="line-{{ $i }}-qty">Line {{ $i + 1 }} quantity</label>
                                            <input id="line-{{ $i }}-qty" name="lines[{{ $i }}][qty]" type="text" inputmode="numeric" value="{{ old('lines.'.$i.'.qty', 1) }}">
                                        </td>
                                        <td role="cell" class="col-money">
                                            <label class="sr-only" for="line-{{ $i }}-unit">Line {{ $i + 1 }} unit price</label>
                                            <input id="line-{{ $i }}-unit" name="lines[{{ $i }}][unit]" type="text" inputmode="decimal" placeholder="0.00" value="{{ old('lines.'.$i.'.unit') }}">
                                        </td>
                                        <td role="cell" class="col-money money money-quiet">—</td>
                                    </tr>
                                @endfor
                            </tbody>
                        </table>
                    </div>

                    <div class="card-body">
                        {{-- Blank rows are dropped on save rather than saved as
                             empty lines, so three fields are up to three lines
                             and one of them is enough. The "add row" button was
                             a script that had to load before the form worked;
                             a longer invoice is a line that says "and four
                             others, itemised in the attached". --}}
                        <p class="pay-hint pay-hint-block">
                            Rows left blank are ignored. At least one line is needed.
                        </p>
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
                        <strong>Totals</strong>
                    </div>

                    <dl class="inv-totals inv-totals-quiet">
                        <div class="inv-total-row">
                            <dt>Total</dt>
                            <dd class="money money-quiet">—</dd>
                        </div>
                        <div class="inv-total-row inv-total-due">
                            <dt>Balance due</dt>
                            <dd class="money money-quiet">—</dd>
                        </div>
                    </dl>

                    <p class="pay-hint pay-hint-block">
                        Totals are computed from the lines when the invoice is
                        saved, never typed. No tax is applied — ZephryxLabs is
                        not GST-registered.
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
