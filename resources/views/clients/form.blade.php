@extends('layouts.app')

@php
    use App\Models\Client as ClientModel;
    use App\Support\ClientPresenter as P;

    // One template for adding and for editing. The fields and their validation
    // are identical, and two files would mean every future change made twice —
    // with the second one eventually forgotten.
    $editing = $client !== null;
@endphp

@section('title', $editing ? 'Edit '.$client->name : 'Add a client')

@section('content')
    <div class="page-hd-row">
        <div class="page-hd">
            <a class="back-link" href="{{ $editing ? route('clients.show', ['client' => $client->reference]) : route('clients.index') }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                {{ $editing ? $client->name : 'Clients' }}
            </a>
            <h1>{{ $editing ? 'Edit this client' : 'Add a client' }}</h1>
            <p>
                @if ($editing)
                    Changes are recorded against this client, with who made them.
                @else
                    This records the organisation. It does not create a login — portal access is given separately.
                @endif
            </p>
        </div>
    </div>

    <form class="an-form" method="POST"
          action="{{ $editing ? route('clients.update', ['client' => $client->reference]) : route('clients.store') }}">
        @csrf

        <section class="an-form-grid">
            <div class="an-form-main">
                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/>
                        </svg>
                        The organisation
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="cl-name">Client name</label>
                            <input id="cl-name" name="name" type="text" required
                                   value="{{ old('name', $client?->name) }}"
                                   @if ($errors->has('name')) aria-invalid="true" aria-describedby="cl-name-error" @endif>
                            <span class="pay-hint">Has to be unique — two identical rows on a list cannot be told apart.</span>
                            @error('name')
                                <span class="field-error" id="cl-name-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="cl-reference">Reference</label>
                            {{-- Shown, never typed. Derived from the highest
                                 existing one so it can never be reissued to a
                                 second client, which is what would make an
                                 invoice trail ambiguous. --}}
                            <input id="cl-reference" type="text" value="{{ $reference }}" disabled>
                            <span class="pay-hint">Assigned automatically and never reused.</span>
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="cl-industry">Industry <span class="an-optional">(optional)</span></label>
                            <input id="cl-industry" name="industry" type="text"
                                   value="{{ old('industry', $client?->industry) }}">
                            @error('industry')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="cl-country">Country <span class="an-optional">(optional)</span></label>
                            <select id="cl-country" name="country">
                                <option value="">Not set</option>
                                @foreach ($countries as $code => $name)
                                    <option value="{{ $code }}" @selected(old('country', $client?->country) === $code)>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('country')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="cl-currency">Currency</label>
                            <select id="cl-currency" name="currency" required>
                                @foreach ($currencies as $code)
                                    <option value="{{ $code }}" @selected(old('currency', $client?->currency ?? 'INR') === $code)>
                                        {{ $code }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="pay-hint">What this client is invoiced in. Figures across clients in different currencies are never summed into one total.</span>
                            @error('currency')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="cl-status">Status</label>
                            <select id="cl-status" name="status" required>
                                @foreach (ClientModel::STATUSES as $value)
                                    <option value="{{ $value }}" @selected(old('status', $client?->status ?? 'active') === $value)>
                                        {{ P::status($value)['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="pay-hint">The engagement, not anybody's login. An inactive client's own portal switches to a read-only invoices page — they never lose access outright.</span>
                            @error('status')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="cl-signed">Signed on <span class="an-optional">(optional)</span></label>
                            <input id="cl-signed" name="signed_on" type="date"
                                   max="{{ now()->toDateString() }}"
                                   value="{{ old('signed_on', $client?->signed_on?->toDateString()) }}">
                            @error('signed_on')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="cl-manager">Account manager <span class="an-optional">(optional)</span></label>
                            {{-- Only active staff are offered. Somebody whose
                                 record was closed last month is not who a client
                                 should be told to contact — and the rule is on
                                 the validator too, because a dropdown is not
                                 where that is enforced. --}}
                            <select id="cl-manager" name="account_manager_id">
                                <option value="">Not assigned</option>
                                @foreach ($managers as $manager)
                                    <option value="{{ $manager->id }}"
                                        @selected((int) old('account_manager_id', $client?->account_manager_id) === $manager->id)>
                                        {{ $manager->name }} ({{ $manager->user_id }})
                                    </option>
                                @endforeach
                            </select>
                            @error('account_manager_id')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="section-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                        Who to talk to
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-field-lbl" for="cl-contact-name">Contact name <span class="an-optional">(optional)</span></label>
                            <input id="cl-contact-name" name="contact_name" type="text"
                                   value="{{ old('contact_name', $client?->contact_name) }}">
                            @error('contact_name')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="cl-contact-email">Contact email <span class="an-optional">(optional)</span></label>
                            <input id="cl-contact-email" name="contact_email" type="email"
                                   autocapitalize="none" spellcheck="false"
                                   value="{{ old('contact_email', $client?->contact_email) }}">
                            <span class="pay-hint">Recording it here does not give anybody a login.</span>
                            @error('contact_email')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field">
                            <label class="form-field-lbl" for="cl-contact-phone">Contact phone <span class="an-optional">(optional)</span></label>
                            <input id="cl-contact-phone" name="contact_phone" type="tel"
                                   value="{{ old('contact_phone', $client?->contact_phone) }}">
                            @error('contact_phone')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-field an-form-wide">
                            <label class="form-field-lbl" for="cl-notes">Notes <span class="an-optional">(optional)</span></label>
                            <textarea id="cl-notes" name="notes" rows="4">{{ old('notes', $client?->notes) }}</textarea>
                            <span class="pay-hint">Internal. The client never sees this.</span>
                            @error('notes')
                                <span class="field-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
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
                                with your name against it. The reference does not change, so
                                every invoice, ticket and project pointing at this client keeps
                                pointing at it.
                            </p>
                        @else
                            <p>
                                The client is recorded and given a reference. Nobody gains a
                                login: portal access is a separate act, on the client's own
                                page, because it hands somebody the ability to read this
                                company's invoices.
                            </p>
                        @endif
                    </div>

                    <button class="btn btn-primary" type="submit">
                        {{ $editing ? 'Save changes' : 'Add client' }}
                    </button>

                    <a class="btn btn-outline btn-sm"
                       href="{{ $editing ? route('clients.show', ['client' => $client->reference]) : route('clients.index') }}">
                        Cancel
                    </a>
                </section>
            </aside>
        </section>
    </form>
@endsection
